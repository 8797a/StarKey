<?php
require __DIR__ . '/../includes/lib.php';
require_admin_login();

$products = get_products();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $productId = post_value('product_id');
    $cards = post_value('cards');
    list($count, $importError) = import_cards($productId, $cards);
    if ($importError !== null) {
        $error = $importError;
    } else {
        $message = '成功导入 ' . $count . ' 条卡密';
        $products = get_products();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>上传卡密 - <?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --success: #10b981;
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: 1px solid rgba(255, 255, 255, 0.8);
            --glass-shadow: 0 10px 40px rgba(31, 38, 135, 0.05);
            --radius-lg: 16px;
            --radius-md: 10px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, "PingFang SC", sans-serif;
            color: var(--text-main); background-color: var(--bg-color); min-height: 100vh; padding: 40px 20px; line-height: 1.6;
        }

        .aurora-bg { position: fixed; inset: 0; z-index: -1; background: var(--bg-color); overflow: hidden; pointer-events: none; }
        .aurora-blob { position: absolute; filter: blur(120px); border-radius: 50%; opacity: 0.6; animation: move 20s infinite alternate ease-in-out; }
        .blob1 { width: 600px; height: 600px; background: #93c5fd; top: -200px; left: -100px; animation-duration: 25s; }
        .blob2 { width: 500px; height: 500px; background: #c4b5fd; bottom: -150px; right: -150px; animation-delay: -5s; animation-duration: 22s; }
        .blob3 { width: 400px; height: 400px; background: #86efac; top: 30%; left: 40%; animation-delay: -10s; animation-duration: 28s; opacity: 0.5; }
        @keyframes move { 0% { transform: translate(0, 0) scale(1) rotate(0deg); } 33% { transform: translate(5vw, -5vh) scale(1.05) rotate(15deg); } 66% { transform: translate(-5vw, 5vh) scale(0.95) rotate(-10deg); } 100% { transform: translate(2vw, 2vh) scale(1) rotate(5deg); } }

        .glass-panel { background: var(--glass-bg); backdrop-filter: blur(24px) saturate(150%); border: var(--glass-border); box-shadow: var(--glass-shadow); border-radius: var(--radius-lg); }
        .wrap { max-width: 900px; margin: 0 auto; position: relative; z-index: 1; }
        .box { padding: 40px; margin-bottom: 32px; }

        h1 { margin: 0 0 24px; font-size: 26px; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em; }
        
        .nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 32px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 24px; }
        .nav a {
            display: inline-block; padding: 10px 18px; border-radius: 8px; text-decoration: none;
            font-size: 14px; font-weight: 500; color: var(--text-muted); background: rgba(255,255,255,0.5);
            border: 1px solid rgba(0,0,0,0.05); transition: all 0.2s ease;
        }
        .nav a:hover, .nav a.active { color: var(--primary); background: rgba(255,255,255,0.9); border-color: var(--primary); }

        label { display: block; margin: 20px 0 10px; font-size: 14px; font-weight: 600; color: var(--text-main); }
        
        textarea, select {
            width: 100%; background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.08);
            border-radius: var(--radius-md); color: var(--text-main); font-size: 15px; padding: 16px;
            outline: none; transition: all 0.2s; font-family: inherit; line-height: 1.6;
        }
        textarea { min-height: 280px; resize: vertical; font-family: monospace; font-size: 14px; }
        textarea:focus, select:focus { border-color: var(--primary); background: rgba(255,255,255,0.9); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; padding-right: 48px; cursor: pointer; }
        select option { background: #fff; color: var(--text-main); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 14px 24px; border-radius: var(--radius-md);
            font-size: 16px; font-weight: 600; color: #fff; background: var(--primary); border: 1px solid #2563eb;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); cursor: pointer; margin-top: 24px; min-width: 160px;
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }

        .msg { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px; font-weight: 600; }
        .ok { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
        .err { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>

<div class="aurora-bg"><div class="aurora-blob blob1"></div><div class="aurora-blob blob2"></div><div class="aurora-blob blob3"></div></div>

<div class="wrap">
    <div class="box glass-panel">
        <h1>上传卡密</h1>
        <div class="nav">
            <a href="admin.php">后台首页</a>
            <a href="admin_upload.php" class="active">上传卡密</a>
            <a href="admin_cards.php">卡密管理</a>
            <a href="admin_orders.php">订单管理</a>
            <a href="admin_logout.php" style="margin-left: auto;">退出登录</a>
        </div>

        <?php if ($message !== ''): ?><div class="msg ok"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="msg err"><?php echo h($error); ?></div><?php endif; ?>

        <form method="post">
            <?php echo csrf_input(); ?>
            <label>选择商品</label>
            <select name="product_id" required>
                <?php foreach ($products as $productId => $product): ?>
                    <option value="<?php echo h($productId); ?>"><?php echo h($product['name']); ?>（<?php echo h($productId); ?>）</option>
                <?php endforeach; ?>
            </select>

            <label>卡密内容</label>
            <textarea name="cards" placeholder="一行一条卡密，例如：&#10;A001&#10;A002&#10;A003" required></textarea>
            
            <div><button class="btn" type="submit">批量导入</button></div>
        </form>
    </div>
</div>
</body>
</html>