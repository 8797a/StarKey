<?php
function app_config()
{
    if (!isset($GLOBALS['__app_config_cache']) || !is_array($GLOBALS['__app_config_cache'])) {
        $GLOBALS['__app_config_cache'] = require __DIR__ . '/config.php';
    }
    return $GLOBALS['__app_config_cache'];
}

function save_app_config($config)
{
    $content = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents(__DIR__ . '/config.php', $content, LOCK_EX);
}

function refresh_app_config()
{
    $GLOBALS['__app_config_cache'] = require __DIR__ . '/config.php';
}

function storage_path($name)
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/' . ltrim($name, '/\\');
}

function is_https_request()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function client_ip()
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $value = trim((string)$_SERVER[$key]);
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim((string)($parts[0] ?? ''));
        }
        if ($value !== '') {
            return $value;
        }
    }
    return '0.0.0.0';
}

function send_admin_security_headers()
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function configure_session_security()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_client_fingerprint()
{
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown-agent');
    $ip = client_ip();
    return hash('sha256', $userAgent . '|' . $ip);
}

function admin_login_attempts_path()
{
    return storage_path('admin_login_attempts.json');
}

function read_admin_login_attempts()
{
    $path = admin_login_attempts_path();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function write_admin_login_attempts($items)
{
    file_put_contents(admin_login_attempts_path(), json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function login_attempt_key($username, $ip)
{
    return hash('sha256', strtolower(trim($username)) . '|' . $ip);
}

function admin_login_lock_seconds()
{
    return 900;
}

function admin_login_max_attempts()
{
    return 5;
}

function clear_expired_admin_login_attempts()
{
    $items = read_admin_login_attempts();
    $lockSeconds = admin_login_lock_seconds();
    $now = time();
    $changed = false;

    foreach ($items as $key => $item) {
        $lastAttemptAt = (int)($item['last_attempt_at'] ?? 0);
        if ($lastAttemptAt <= 0 || ($now - $lastAttemptAt) > $lockSeconds) {
            unset($items[$key]);
            $changed = true;
        }
    }

    if ($changed) {
        write_admin_login_attempts($items);
    }
}

function get_admin_login_lock_message($username)
{
    clear_expired_admin_login_attempts();
    $ip = client_ip();
    $key = login_attempt_key($username, $ip);
    $items = read_admin_login_attempts();
    $item = $items[$key] ?? null;
    if (!is_array($item)) {
        return null;
    }

    $attempts = (int)($item['attempts'] ?? 0);
    $lastAttemptAt = (int)($item['last_attempt_at'] ?? 0);
    if ($attempts < admin_login_max_attempts()) {
        return null;
    }

    $remaining = admin_login_lock_seconds() - (time() - $lastAttemptAt);
    if ($remaining <= 0) {
        unset($items[$key]);
        write_admin_login_attempts($items);
        return null;
    }

    return 'Too many login attempts, please try again in ' . ceil($remaining / 60) . ' minutes';
}

function record_admin_login_failure($username)
{
    clear_expired_admin_login_attempts();
    $ip = client_ip();
    $key = login_attempt_key($username, $ip);
    $items = read_admin_login_attempts();
    $item = $items[$key] ?? ['attempts' => 0, 'last_attempt_at' => 0];
    $item['attempts'] = (int)($item['attempts'] ?? 0) + 1;
    $item['last_attempt_at'] = time();
    $items[$key] = $item;
    write_admin_login_attempts($items);
}

function clear_admin_login_failures($username)
{
    $ip = client_ip();
    $key = login_attempt_key($username, $ip);
    $items = read_admin_login_attempts();
    if (isset($items[$key])) {
        unset($items[$key]);
        write_admin_login_attempts($items);
    }
}

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbConfig = app_config()['db'] ?? [];
    $host = $dbConfig['host'] ?? '127.0.0.1';
    $port = (int)($dbConfig['port'] ?? 3306);
    $database = $dbConfig['database'] ?? '';
    $username = $dbConfig['username'] ?? '';
    $password = $dbConfig['password'] ?? '';
    $charset = $dbConfig['charset'] ?? 'utf8mb4';

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

    return $pdo;
}

function system_setting($key, $default = '')
{
    $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1');
    $stmt->execute([':setting_key' => $key]);
    $value = $stmt->fetchColumn();
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function set_system_setting($key, $value)
{
    $stmt = db()->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, :updated_at) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)');
    $stmt->execute([
        ':setting_key' => $key,
        ':setting_value' => (string)$value,
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);
}


function all_orders()
{
    $stmt = db()->query('SELECT order_id, pay_id, product_id, product_name, price, type, status, card, created_at, paid_at FROM orders ORDER BY id ASC');
    return $stmt->fetchAll();
}

function save_orders($orders)
{
    return $orders;
}

function all_closed_orders()
{
    $stmt = db()->query('SELECT order_id, closed_at FROM closed_orders ORDER BY id ASC');
    return $stmt->fetchAll();
}

function save_closed_orders($items)
{
    return $items;
}

function get_products()
{
    try {
        db()->query("SELECT description FROM products LIMIT 1");
    } catch (PDOException $e) {
        try {
            db()->exec("ALTER TABLE `products` ADD COLUMN `description` TEXT NULL AFTER `price`");
        } catch (Exception $ex) {
        }
    }

    $stmt = db()->query('SELECT id, name, price, description, status FROM products ORDER BY id ASC');
    $rows = $stmt->fetchAll();
    $products = [];

    foreach ($rows as $row) {
        $products[$row['id']] = [
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'description' => (string)($row['description'] ?? ''),
            'status' => (int)$row['status'],
            'stock' => product_stock($row['id']),
        ];
    }

    return $products;
}

function product_stock($productId)
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM cards WHERE product_id = :product_id AND status = 'unused'");
    $stmt->execute([':product_id' => $productId]);
    return (int)$stmt->fetchColumn();
}

function find_order($orderId)
{
    $stmt = db()->prepare('SELECT order_id, pay_id, product_id, product_name, price, type, status, card, created_at, paid_at FROM orders WHERE order_id = :order_id OR pay_id = :pay_id LIMIT 1');
    $stmt->execute([
        ':order_id' => $orderId,
        ':pay_id' => $orderId,
    ]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function update_order($orderId, $callback)
{
    $order = find_order($orderId);
    if (!$order) {
        return null;
    }

    $newOrder = $callback($order);
    if (!is_array($newOrder)) {
        return null;
    }

    $stmt = db()->prepare('UPDATE orders SET product_id = :product_id, product_name = :product_name, price = :price, type = :type, status = :status, card = :card, created_at = :created_at, paid_at = :paid_at WHERE order_id = :order_id');
    $stmt->execute([
        ':product_id' => $newOrder['product_id'],
        ':product_name' => $newOrder['product_name'],
        ':price' => $newOrder['price'],
        ':type' => $newOrder['type'],
        ':status' => $newOrder['status'],
        ':card' => $newOrder['card'],
        ':created_at' => $newOrder['created_at'],
        ':paid_at' => $newOrder['paid_at'],
        ':order_id' => $newOrder['order_id'],
    ]);

    return find_order($newOrder['order_id']);
}

function create_order_record($productId, $payType)
{
    $products = get_products();
    if (!isset($products[$productId])) {
        return [null, 'Product not found'];
    }

    if ((int)($products[$productId]['status'] ?? 0) !== 1) {
        return [null, 'Product is disabled'];
    }

    if (($products[$productId]['stock'] ?? 0) <= 0) {
        return [null, 'Out of stock'];
    }

    $product = $products[$productId];
    $orderId = date('YmdHis') . mt_rand(1000, 9999);
    $order = [
        'order_id' => $orderId,
        'pay_id' => date('YmdHis') . mt_rand(10000, 99999),
        'product_id' => $productId,
        'product_name' => $product['name'],
        'price' => number_format((float)$product['price'], 2, '.', ''),
        'type' => $payType,
        'status' => 'created',
        'card' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'paid_at' => null,
    ];

    $stmt = db()->prepare('INSERT INTO orders (order_id, pay_id, product_id, product_name, price, type, status, card, created_at, paid_at) VALUES (:order_id, :pay_id, :product_id, :product_name, :price, :type, :status, :card, :created_at, :paid_at)');
    $stmt->execute([
        ':order_id' => $order['order_id'],
        ':pay_id' => $order['pay_id'],
        ':product_id' => $order['product_id'],
        ':product_name' => $order['product_name'],
        ':price' => $order['price'],
        ':type' => $order['type'],
        ':status' => $order['status'],
        ':card' => $order['card'],
        ':created_at' => $order['created_at'],
        ':paid_at' => $order['paid_at'],
    ]);

    return [$order, null];
}

function build_vpay_url($order, $notifyUrl, $returnUrl)
{
    $param = $order['order_id'];
    $price = $order['price'];
    $type = (string)$order['type'];
    $payId = $order['pay_id'];
    $vpayKey = system_setting('vpay_key', (string)(app_config()['vpay_key'] ?? ''));
    $vpayBaseUrl = system_setting('vpay_base_url', (string)(app_config()['vpay_base_url'] ?? ''));
    $sign = md5($payId . $param . $type . $price . $vpayKey);
    $query = http_build_query([
        'payId' => $payId,
        'type' => $type,
        'price' => $price,
        'sign' => $sign,
        'param' => $param,
        'notifyUrl' => $notifyUrl,
        'returnUrl' => $returnUrl,
        'isHtml' => 1
    ]);
    return rtrim($vpayBaseUrl, '/') . '/createOrder.php?' . $query;
}

function complete_order($orderId)
{
    $pdo = db();
    $order = find_order($orderId);
    if (!$order) {
        return null;
    }

    if (($order['status'] ?? '') === 'paid') {
        return $order;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = :order_id LIMIT 1 FOR UPDATE');
        $stmt->execute([':order_id' => $order['order_id']]);
        $lockedOrder = $stmt->fetch();

        if (!$lockedOrder) {
            $pdo->rollBack();
            return null;
        }

        if (($lockedOrder['status'] ?? '') === 'paid') {
            $pdo->commit();
            return $lockedOrder;
        }

        $stmt = $pdo->prepare("SELECT id, card_code FROM cards WHERE product_id = :product_id AND status = 'unused' ORDER BY id ASC LIMIT 1 FOR UPDATE");
        $stmt->execute([':product_id' => $lockedOrder['product_id']]);
        $cardRow = $stmt->fetch();

        $card = null;
        if ($cardRow) {
            $card = $cardRow['card_code'];
            $stmt = $pdo->prepare("UPDATE cards SET status = 'sold', order_id = :order_id, sold_at = :sold_at WHERE id = :id");
            $stmt->execute([
                ':order_id' => $lockedOrder['order_id'],
                ':sold_at' => date('Y-m-d H:i:s'),
                ':id' => $cardRow['id'],
            ]);
        }

        $paidAt = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = :paid_at, card = :card WHERE order_id = :order_id");
        $stmt->execute([
            ':paid_at' => $paidAt,
            ':card' => $card,
            ':order_id' => $lockedOrder['order_id'],
        ]);

        $pdo->commit();
        return find_order($lockedOrder['order_id']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function close_order_record($orderId)
{
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare('INSERT INTO closed_orders (order_id, closed_at) VALUES (:order_id, :closed_at) ON DUPLICATE KEY UPDATE closed_at = VALUES(closed_at)');
    $stmt->execute([
        ':order_id' => $orderId,
        ':closed_at' => $now,
    ]);

    return update_order($orderId, function ($order) {
        if (($order['status'] ?? '') !== 'paid') {
            $order['status'] = 'closed';
        }
        return $order;
    });
}

function order_closed($orderId)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM closed_orders WHERE order_id = :order_id');
    $stmt->execute([':order_id' => $orderId]);
    return (int)$stmt->fetchColumn() > 0;
}

function json_response($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function post_value($name, $default = '')
{
    return isset($_POST[$name]) ? trim((string)$_POST[$name]) : $default;
}

function get_value($name, $default = '')
{
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function base_url()
{
    return rtrim(system_setting('site_url', 'http://localhost'), '/');
}

function notify_url()
{
    return base_url() . '/api/notify.php';
}

function return_url($orderId)
{
    return base_url() . '/api/return.php?orderId=' . urlencode($orderId);
}

function payment_name($type)
{
    return (string)$type === '2' ? 'Alipay' : 'WeChat';
}

function ensure_session_started()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        configure_session_security();
        session_start();
    }
}

function admin_config()
{
    $stmt = db()->query('SELECT username, password_hash, status FROM admins WHERE status = 1 ORDER BY id ASC LIMIT 1');
    $admin = $stmt->fetch();
    if ($admin) {
        return $admin;
    }

    return ['username' => 'admin', 'password_hash' => ''];
}

function admin_password_hash()
{
    $admin = admin_config();
    $hash = (string)($admin['password_hash'] ?? '');
    if ($hash !== '') {
        return $hash;
    }

    return password_hash('admin123', PASSWORD_DEFAULT);
}

function site_name()
{
    return system_setting('site_name', '绠€鍗曞彂鍗＄綉');
}

function admin_is_logged_in()
{
    ensure_session_started();
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }

    $fingerprint = (string)($_SESSION['admin_fingerprint'] ?? '');
    if ($fingerprint === '' || !hash_equals($fingerprint, current_client_fingerprint())) {
        admin_logout();
        return false;
    }

    return true;
}

function admin_login($username, $password)
{
    $lockMessage = get_admin_login_lock_message($username);
    if ($lockMessage !== null) {
        return $lockMessage;
    }

    $admin = admin_config();
    if ($username !== (string)($admin['username'] ?? '')) {
        record_admin_login_failure($username);
        return false;
    }

    $hash = (string)($admin['password_hash'] ?? '');
    $ok = $hash !== '' ? password_verify($password, $hash) : false;

    if (!$ok) {
        record_admin_login_failure($username);
        return false;
    }

    clear_admin_login_failures($username);
    ensure_session_started();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = 1;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_fingerprint'] = current_client_fingerprint();
    $_SESSION['admin_logged_in_at'] = time();
    return true;
}

function admin_logout()
{
    ensure_session_started();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

function require_admin_login()
{
    send_admin_security_headers();
    if (!admin_is_logged_in()) {
        header('Location: admin_login.php');
        exit;
    }
}

function admin_order_list($page = 1, $pageSize = 20, $filters = [])
{
    $page = max(1, (int)$page);
    $pageSize = max(1, min(100, (int)$pageSize));
    $offset = ($page - 1) * $pageSize;

    $where = [];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['keyword'])) {
        $where[] = '(order_id LIKE :keyword OR pay_id LIKE :keyword OR product_name LIKE :keyword OR card LIKE :keyword)';
        $params[':keyword'] = '%' . $filters['keyword'] . '%';
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = db()->prepare('SELECT COUNT(*) FROM orders' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $stmt = db()->prepare('SELECT order_id, pay_id, product_id, product_name, price, type, status, card, created_at, paid_at FROM orders' . $whereSql . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'pages' => max(1, (int)ceil($total / $pageSize)),
    ];
}

function admin_product_stats()
{
    $stmt = db()->query("SELECT p.id, p.name, p.price, p.description, p.status,
        SUM(CASE WHEN c.status = 'unused' THEN 1 ELSE 0 END) AS stock,
        SUM(CASE WHEN c.status = 'sold' THEN 1 ELSE 0 END) AS sold_count,
        COUNT(c.id) AS total_cards
        FROM products p
        LEFT JOIN cards c ON c.product_id = p.id
        GROUP BY p.id, p.name, p.price, p.description, p.status
        ORDER BY p.id ASC");
    return $stmt->fetchAll();
}

function admin_dashboard_stats()
{
    return [
        'products' => (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'unused_cards' => (int)db()->query("SELECT COUNT(*) FROM cards WHERE status = 'unused'")->fetchColumn(),
        'sold_cards' => (int)db()->query("SELECT COUNT(*) FROM cards WHERE status = 'sold'")->fetchColumn(),
        'paid_orders' => (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'paid'")->fetchColumn(),
    ];
}

function import_cards($productId, $content)
{
    $productId = trim($productId);
    if ($productId === '') {
        return [0, 'Product id is required'];
    }

    $products = get_products();
    if (!isset($products[$productId])) {
        return [0, 'Product not found'];
    }

    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = array_filter(array_map('trim', explode("\n", $content)), function ($line) {
        return $line !== '';
    });

    if (!$lines) {
        return [0, 'Card content is required'];
    }

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO cards (product_id, card_code, status, created_at) VALUES (:product_id, :card_code, :status, :created_at)');
    $count = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($lines as $line) {
        $stmt->execute([
            ':product_id' => $productId,
            ':card_code' => $line,
            ':status' => 'unused',
            ':created_at' => $now,
        ]);
        $count++;
    }

    return [$count, null];
}

function csrf_token()
{
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_or_fail()
{
    ensure_session_started();
    $token = post_value('csrf_token');
    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        exit('CSRF 楠岃瘉澶辫触');
    }
}

function admin_create_product($productId, $name, $price, $description = '')
{
    $productId = trim($productId);
    $name = trim($name);
    $price = trim($price);
    $description = trim($description);

    if ($productId === '' || $name === '' || $price === '') {
        return 'Product id, name and price are required';
    }

    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $productId)) {
        return 'Product id can only contain letters, numbers, underscores and hyphens';
    }

    if (!is_numeric($price) || (float)$price < 0) {
        return 'Invalid price format';
    }

    $existsStmt = db()->prepare('SELECT COUNT(*) FROM products WHERE id = :id');
    $existsStmt->execute([':id' => $productId]);
    if ((int)$existsStmt->fetchColumn() > 0) {
        return 'Product id already exists';
    }

    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare('INSERT INTO products (id, name, price, description, status, created_at, updated_at) VALUES (:id, :name, :price, :description, :status, :created_at, :updated_at)');
    $stmt->execute([
        ':id' => $productId,
        ':name' => $name,
        ':price' => number_format((float)$price, 2, '.', ''),
        ':description' => $description,
        ':status' => 1,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return null;
}

function admin_update_product($productId, $name, $price, $status, $description = '')
{
    $productId = trim($productId);
    $name = trim($name);
    $price = trim($price);
    $description = trim($description);
    $status = (int)$status === 1 ? 1 : 0;

    if ($productId === '' || $name === '' || $price === '') {
        return 'Product parameters are incomplete';
    }

    if (!is_numeric($price) || (float)$price < 0) {
        return 'Invalid price format';
    }

    $stmt = db()->prepare('UPDATE products SET name = :name, price = :price, description = :description, status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':name' => $name,
        ':price' => number_format((float)$price, 2, '.', ''),
        ':description' => $description,
        ':status' => $status,
        ':updated_at' => date('Y-m-d H:i:s'),
        ':id' => $productId,
    ]);

    return null;
}

function admin_delete_product($productId)
{
    $productId = trim($productId);
    if ($productId === '') {
        return 'Product id is required';
    }

    $cardCountStmt = db()->prepare('SELECT COUNT(*) FROM cards WHERE product_id = :product_id');
    $cardCountStmt->execute([':product_id' => $productId]);
    if ((int)$cardCountStmt->fetchColumn() > 0) {
        return 'This product still has cards and cannot be deleted';
    }

    $orderCountStmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE product_id = :product_id');
    $orderCountStmt->execute([':product_id' => $productId]);
    if ((int)$orderCountStmt->fetchColumn() > 0) {
        return 'This product already has orders and cannot be deleted';
    }

    $stmt = db()->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $productId]);
    return null;
}

function admin_card_list($page = 1, $pageSize = 30, $filters = [])
{
    $page = max(1, (int)$page);
    $pageSize = max(1, min(100, (int)$pageSize));
    $offset = ($page - 1) * $pageSize;
    $where = [];
    $params = [];

    if (!empty($filters['product_id'])) {
        $where[] = 'product_id = :product_id';
        $params[':product_id'] = $filters['product_id'];
    }

    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['keyword'])) {
        $where[] = '(card_code LIKE :keyword OR order_id LIKE :keyword)';
        $params[':keyword'] = '%' . $filters['keyword'] . '%';
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $countStmt = db()->prepare('SELECT COUNT(*) FROM cards' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $stmt = db()->prepare('SELECT id, product_id, card_code, status, order_id, created_at, sold_at FROM cards' . $whereSql . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'pages' => max(1, (int)ceil($total / $pageSize)),
    ];
}

function admin_delete_card($id)
{
    $stmt = db()->prepare('DELETE FROM cards WHERE id = :id AND status = :status');
    $stmt->execute([
        ':id' => (int)$id,
        ':status' => 'unused',
    ]);
}

function admin_delete_cards(array $ids)
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'DELETE FROM cards WHERE status = ? AND id IN (' . $placeholders . ')';
    $params = array_merge(['unused'], $ids);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function admin_delete_all_orders()
{
    $pdo = db();
    // 闂傚倸鍊搁崐鎼佸磹妞嬪海鐭嗗〒姘ｅ亾妤犵偛顦甸弫宥夊礋椤掍焦顔囬梻浣虹帛閸旀洟顢氶鐘典笉濡わ絽鍟悡鍐喐濠婂牆绀堟慨妯块哺瀹曞弶绻涢幋娆忕仼鐎瑰憡绻冮妵鍕箻閸楃偟浠奸悗娈垮枟濠㈡鐏冮梺缁橈耿濞佳勭濠婂懐纾煎璺猴功缁夎櫣鈧娲樺浠嬪春閳ь剚銇勯幒宥夋濞存粍绮撻弻鐔兼倻濡櫣浠村銈呯箚閺呯娀骞冨Δ鈧～婵嬵敆婢跺瑩锕€顪冮妶搴″绩婵炲娲熼獮鎴﹀礋椤栨矮绱堕梺鍛婃处閸樺€熲叿闂傚倸鍊风粈渚€骞夐敓鐘冲殞濡わ絽鍟壕褰掓煕瑜庨〃鍛存嫅?    $stmt = $pdo->prepare("DELETE FROM orders");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    // 闂傚倸鍊搁崐鎼佸磹妞嬪海鐭嗗〒姘ｅ亾妤犵偛顦甸弫鎾绘偐閸愬弶鐤勫┑掳鍊х徊浠嬪疮椤愩倐鍋撳顒夋Ч闁靛洤瀚伴獮鎺楀幢濡炴儳顥氬┑锛勫亼閸娿倖绂嶅鍫濈柈闁哄鍨归弳锕傛煙閻戞ê鐒炬繛灏栨櫊閺屾洘寰勯崼婵堜患婵炲瓨绮撶粻鏍蓟閻斿吋鍊绘慨妤€妫欓悾鍫曟倵閻熺増鍟炵紒璇插暣婵＄敻宕熼姘敤闂侀潧臎閳ь剙危閸儲鈷戦梺顐ゅ仜閼活垱鏅舵导瀛樼厱閻庯絻鍔岄埀顒佺箓椤曪絿鎷犲ù瀣潔濠电姴锕ら幊鎰板级閹间焦鈷戦悷娆忓缁€鍐煕閳哄倻澧甸柟顔筋殜椤㈡瑩鎮惧畝鈧鏇㈡煟鎼达絾鏆╂い顓炵墕閻☆參姊绘担铏瑰笡妞ゃ劌妫濋獮鎴﹀炊椤掑倸绁﹂梺绯曞墲缁嬫垹绮堥崘鈹夸簻闁哄啫娲ゆ禍瑙勩亜閿旇姤绶叉い顏勫暣婵″爼宕卞Δ鈧～鍥⒑閹肩偛濡兼繝鈧潏鈺傤潟闁绘劕妯婂銊╂煃瑜滈崜娆擄綖韫囨梻绡€婵﹩鍓涢敍婊冣攽椤旀枻渚涢柛蹇旂〒閼鸿鲸绂掔€ｎ偀鎷洪梺鍛婄☉閿曪箓骞夐崸妤佺厱閻庯綆鍋呯亸顓熴亜椤忓嫬鏆ｅ┑鈥崇埣瀹曞崬螣閻撳骸姹插┑鐘垫暩閸嬬偟绮婇幘顔肩柧闁绘ê鍘栫换?    $pdo->prepare("DELETE FROM closed_orders")->execute();
    
    return $count;
}

function admin_update_payment_config($siteName, $siteUrl, $vpayBaseUrl, $vpayKey)
{
    $siteName = trim($siteName);
    $siteUrl = trim($siteUrl);
    $vpayBaseUrl = trim($vpayBaseUrl);
    $vpayKey = trim($vpayKey);

    if ($siteName === '' || $siteUrl === '' || $vpayBaseUrl === '' || $vpayKey === '') {
        return 'Payment config fields are required';
    }

    set_system_setting('site_name', $siteName);
    set_system_setting('site_url', $siteUrl);
    set_system_setting('vpay_base_url', $vpayBaseUrl);
    set_system_setting('vpay_key', $vpayKey);

    return null;
}

function admin_update_account($username, $password)
{
    $username = trim($username);
    $password = trim($password);

    if ($username === '' || $password === '') {
        return 'Admin username and password are required';
    }

    $stmt = db()->prepare('UPDATE admins SET username = :username, password_hash = :password_hash, updated_at = :updated_at WHERE id = (SELECT id2 FROM (SELECT id AS id2 FROM admins WHERE status = 1 ORDER BY id ASC LIMIT 1) AS t)');
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);

    ensure_session_started();
    $_SESSION['admin_username'] = $username;
    return null;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
