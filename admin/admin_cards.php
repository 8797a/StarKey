<?php
require __DIR__ . '/../includes/lib.php';
require_admin_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $action = post_value('action');
    try {
        if ($action === 'delete_card') {
            admin_delete_card(post_value('id'));
            $message = '卡密删除成功';
        } elseif ($action === 'delete_cards') {
            $deleted = admin_delete_cards($_POST['ids'] ?? []);
            $message = '已删除 ' . $deleted . ' 条未售卡密';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$page = max(1, (int)get_value('page', '1'));
$productId = get_value('product_id');
$status = get_value('status');
$keyword = get_value('keyword');
$products = get_products();
$result = admin_card_list($page, 30, ['product_id' => $productId, 'status' => $status, 'keyword' => $keyword]);
$cards = $result['items'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>卡密管理 - <?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
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
        .wrap { max-width: 1250px; margin: 0 auto; position: relative; z-index: 1; }
        .box { padding: 40px; margin-bottom: 32px; }

        h1 { margin: 0 0 24px; font-size: 26px; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em; }

        .nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 32px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 24px; }
        .nav a {
            display: inline-block; padding: 10px 18px; border-radius: 8px; text-decoration: none;
            font-size: 14px; font-weight: 500; color: var(--text-muted); background: rgba(255,255,255,0.5);
            border: 1px solid rgba(0,0,0,0.05); transition: all 0.2s ease;
        }
        .nav a:hover, .nav a.active { color: var(--primary); background: rgba(255,255,255,0.9); border-color: var(--primary); }

        .filter-form { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; align-items: center; }
        
        input, select {
            padding: 12px 16px; background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.08);
            border-radius: var(--radius-md); color: var(--text-main); font-size: 14px; outline: none; transition: all 0.2s; font-family: inherit;
        }
        input:focus, select:focus { border-color: var(--primary); background: rgba(255,255,255,0.9); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; cursor: pointer; }
        select option { background: #fff; color: var(--text-main); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: var(--radius-md);
            font-size: 14px; font-weight: 600; color: #fff; background: var(--primary); border: 1px solid #2563eb;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); cursor: pointer; text-decoration: none;
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }
        .btn-danger { background: var(--danger); border-color: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: var(--danger-hover); box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3); }

        .table-responsive { width: 100%; overflow-x: auto; margin-top: 16px; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; background: rgba(255,255,255,0.4); }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 16px 16px; border-bottom: 1px solid rgba(0,0,0,0.04); text-align: left; font-size: 14px; vertical-align: middle; color: var(--text-main); }
        th { color: var(--text-muted); font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; background: rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.06); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(0,0,0,0.01); }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-unused { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .status-sold { background: rgba(239, 68, 68, 0.15); color: #dc2626; }

        .pager { margin-top: 32px; display: flex; gap: 8px; flex-wrap: wrap; }
        .pager a {
            display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px;
            padding: 0 12px; border: 1px solid rgba(0,0,0,0.05); border-radius: 8px; text-decoration: none;
            font-size: 14px; font-weight: 500; color: var(--text-muted); background: rgba(255,255,255,0.4); transition: all 0.2s;
        }
        .pager a:hover { color: var(--primary); background: rgba(255,255,255,0.8); border-color: rgba(0,0,0,0.1); }
        .pager a.active { color: #fff; background: var(--primary); border-color: #2563eb; }

        .msg { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px; font-weight: 600; }
        .ok { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
        .err { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
    </style>
</head>
<body>

<div class="aurora-bg"><div class="aurora-blob blob1"></div><div class="aurora-blob blob2"></div><div class="aurora-blob blob3"></div></div>

<div class="wrap">
    <div class="box glass-panel">
        <h1>卡密管理</h1>
        <div class="nav">
            <a href="admin.php">后台首页</a>
            <a href="admin_upload.php">上传卡密</a>
            <a href="admin_cards.php" class="active">卡密管理</a>
            <a href="admin_orders.php">订单管理</a>
            <a href="admin_logout.php" style="margin-left: auto;">退出登录</a>
        </div>

        <?php if ($message !== ''): ?><div class="msg ok"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="msg err"><?php echo h($error); ?></div><?php endif; ?>

        <form method="get" class="filter-form">
            <select name="product_id">
                <option value="">全部商品</option>
                <?php foreach ($products as $pid => $product): ?>
                    <option value="<?php echo h($pid); ?>" <?php echo $productId === $pid ? 'selected' : ''; ?>><?php echo h($product['name']); ?>（<?php echo h($pid); ?>）</option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">全部状态</option>
                <option value="unused" <?php echo $status === 'unused' ? 'selected' : ''; ?>>unused</option>
                <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>sold</option>
            </select>
            <input type="text" name="keyword" value="<?php echo h($keyword); ?>" placeholder="搜索卡密/订单号" style="flex:1; min-width:200px;">
            <button class="btn" type="submit" style="padding:12px 24px;">筛选</button>
        </form>

        <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="delete_cards">
            <div style="margin-bottom:16px;">
                <button class="btn btn-danger" type="submit" onclick="return confirm('确认批量删除所选未售卡密？');" style="padding:10px 16px; font-size:13px;">批量删除未售卡密</button>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 48px; text-align: center;"><input type="checkbox" onclick="document.querySelectorAll('.cb').forEach(x=>x.checked=this.checked)"></th>
                        <th style="width: 60px;">ID</th>
                        <th>商品编号</th>
                        <th>卡密</th>
                        <th>状态</th>
                        <th>订单号</th>
                        <th>导入时间</th>
                        <th>售出时间</th>
                        <th style="width: 100px;">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td style="text-align: center;"><?php if ($card['status'] === 'unused'): ?><input class="cb" type="checkbox" name="ids[]" value="<?php echo (int)$card['id']; ?>"><?php endif; ?></td>
                            <td><?php echo (int)$card['id']; ?></td>
                            <td style="font-family: monospace; font-size: 13px; color: var(--text-muted);"><?php echo h($card['product_id']); ?></td>
                            <td style="max-width: 250px; word-break: break-all; white-space: normal; font-family: monospace; font-size: 13px;"><?php echo h($card['card_code']); ?></td>
                            <td><span class="badge status-<?php echo h($card['status']); ?>"><?php echo h($card['status']); ?></span></td>
                            <td style="font-family: monospace; font-size: 13px;"><?php echo h($card['order_id']); ?></td>
                            <td style="font-size: 13px; color: var(--text-muted);"><?php echo h($card['created_at']); ?></td>
                            <td style="font-size: 13px; color: var(--text-muted);"><?php echo h($card['sold_at']); ?></td>
                            <td>
                                <?php if ($card['status'] === 'unused'): ?>
                                    <form method="post" onsubmit="return confirm('确认删除这条未售卡密？');" style="margin: 0;">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete_card">
                                        <input type="hidden" name="id" value="<?php echo (int)$card['id']; ?>">
                                        <button class="btn btn-danger" type="submit" style="padding: 6px 12px; font-size: 12px; min-width: auto; box-shadow: none;">删除</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div class="pager">
            <?php for ($i = 1; $i <= (int)$result['pages']; $i++): ?>
                <a href="admin_cards.php?page=<?php echo $i; ?>&product_id=<?php echo urlencode($productId); ?>&status=<?php echo urlencode($status); ?>&keyword=<?php echo urlencode($keyword); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
</body>
</html>