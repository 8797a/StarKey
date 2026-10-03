<?php
require __DIR__ . '/../includes/lib.php';
require_admin_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $action = post_value('action');
    try {
        if ($action === 'delete_all_orders') {
            $deleted = admin_delete_all_orders();
            $message = "清理完成！共清空了 $deleted 条订单记录。";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$page = max(1, (int)get_value('page', '1'));
$status = get_value('status');
$keyword = get_value('keyword');
$result = admin_order_list($page, 20, ['status' => $status, 'keyword' => $keyword]);
$orders = $result['items'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>订单管理 - <?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
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

        .filter-form {
            display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; align-items: center;
            background: rgba(255,255,255,0.4); padding: 20px; border-radius: var(--radius-md); border: 1px solid rgba(0,0,0,0.05);
        }
        
        input, select {
            padding: 12px 16px; background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.08);
            border-radius: var(--radius-md); color: var(--text-main); font-size: 14px; outline: none; transition: all 0.2s; font-family: inherit;
        }
        input:focus, select:focus { border-color: var(--primary); background: rgba(255,255,255,0.9); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; cursor: pointer; }
        select option { background: #fff; color: var(--text-main); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; border-radius: var(--radius-md);
            font-size: 14px; font-weight: 600; color: #fff; background: var(--primary); border: 1px solid #2563eb;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); cursor: pointer; text-decoration: none;
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }

        .btn-danger { background: var(--danger); border-color: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: #dc2626; box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3); }

        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; background: rgba(255,255,255,0.4); }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.04); text-align: left; font-size: 14px; vertical-align: middle; color: var(--text-main); }
        th { color: var(--text-muted); font-weight: 500; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; background: rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.06); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(0,0,0,0.01); }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-created { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .status-paid { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .status-closed { background: rgba(239, 68, 68, 0.15); color: #dc2626; }

        .pager { margin-top: 32px; display: flex; gap: 8px; flex-wrap: wrap; }
        .pager a {
            display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px;
            padding: 0 12px; border: 1px solid rgba(0,0,0,0.05); border-radius: 8px; text-decoration: none;
            font-size: 14px; font-weight: 500; color: var(--text-muted); background: rgba(255,255,255,0.4); transition: all 0.2s;
        }
        .pager a:hover { color: var(--primary); background: rgba(255,255,255,0.8); border-color: rgba(0,0,0,0.1); }
        .pager a.active { color: #fff; background: var(--primary); border-color: #2563eb; }
    </style>
</head>
<body>

<div class="aurora-bg"><div class="aurora-blob blob1"></div><div class="aurora-blob blob2"></div><div class="aurora-blob blob3"></div></div>

<div class="wrap">
    <div class="box glass-panel">
        <h1>订单管理</h1>
        <div class="nav">
            <a href="admin.php">后台首页</a>
            <a href="admin_upload.php">上传卡密</a>
            <a href="admin_cards.php">卡密管理</a>
            <a href="admin_orders.php" class="active">订单管理</a>
            <a href="admin_logout.php" style="margin-left: auto;">退出登录</a>
        </div>

        <?php if ($message !== ''): ?><div class="msg ok" style="padding:15px; background:rgba(16,185,129,0.1); color:#059669; border-radius:8px; margin-bottom:15px;"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="msg err" style="padding:15px; background:rgba(239,68,68,0.1); color:#dc2626; border-radius:8px; margin-bottom:15px;"><?php echo h($error); ?></div><?php endif; ?>

        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom: 24px; align-items:center; background: rgba(255,255,255,0.4); padding: 20px; border-radius: var(--radius-md); border: 1px solid rgba(0,0,0,0.05);">
            <form method="get" class="filter-form" style="margin:0; padding:0; background:transparent; border:none; display:flex; gap:16px; flex-wrap:wrap; align-items:center; flex:1;">
                <input type="text" name="keyword" value="<?php echo h($keyword); ?>" placeholder="搜索订单号/支付单号/商品/卡密" style="flex:1; min-width: 250px;">
                <select name="status">
                    <option value="">全部状态</option>
                    <option value="created" <?php echo $status === 'created' ? 'selected' : ''; ?>>created</option>
                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>paid</option>
                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>closed</option>
                </select>
                <button class="btn" type="submit" style="padding:12px 24px;">筛选</button>
            </form>
            
            <form method="post" style="margin:0;" onsubmit="return confirm('⚠️警告：确定要清空全部订单记录吗？清空后数据无法恢复！');">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete_all_orders">
                <button type="submit" class="btn btn-danger" style="padding:12px 24px; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    一键清空全部订单
                </button>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                <tr><th>订单号</th><th>支付单号</th><th>商品名称</th><th>金额</th><th>支付方式</th><th>状态</th><th>卡密</th><th>创建时间</th><th>支付时间</th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td style="font-family: monospace; font-size: 13px; color: var(--text-muted);"><?php echo h($order['order_id']); ?></td>
                        <td style="font-family: monospace; font-size: 13px; color: var(--text-muted);"><?php echo h($order['pay_id']); ?></td>
                        <td>
                            <div style="font-weight:600; color:var(--text-main);"><?php echo h($order['product_name']); ?></div>
                            <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?php echo h($order['product_id']); ?></div>
                        </td>
                        <td style="font-weight: 700; color: var(--text-main);">￥<?php echo h($order['price']); ?></td>
                        <td><?php echo h(payment_name($order['type'])); ?></td>
                        <td><span class="badge status-<?php echo h($order['status']); ?>"><?php echo h($order['status']); ?></span></td>
                        <td style="max-width: 200px; word-break: break-all; white-space: normal; font-family: monospace; font-size: 13px; color: #059669; font-weight: 600;"><?php echo h($order['card']); ?></td>
                        <td style="font-size: 13px; color: var(--text-muted);"><?php echo h($order['created_at']); ?></td>
                        <td style="font-size: 13px; color: var(--text-muted);"><?php echo h($order['paid_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pager">
            <?php for ($i = 1; $i <= (int)$result['pages']; $i++): ?>
                <a href="admin_orders.php?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&keyword=<?php echo urlencode($keyword); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
</body>
</html>