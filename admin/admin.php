<?php
require __DIR__ . '/../includes/lib.php';
require_admin_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $action = post_value('action');

    try {
        if ($action === 'create_product') {
            $error = admin_create_product(post_value('product_id'), post_value('name'), post_value('price'), post_value('description')) ?? '';
            if ($error === '') {
                $message = '商品新增成功';
            }
        } elseif ($action === 'update_product') {
            $error = admin_update_product(post_value('product_id'), post_value('name'), post_value('price'), post_value('status', '0'), post_value('description')) ?? '';
            if ($error === '') {
                $message = '商品更新成功';
            }
        } elseif ($action === 'delete_product') {
            $error = admin_delete_product(post_value('product_id')) ?? '';
            if ($error === '') {
                $message = '商品删除成功';
            }
        } elseif ($action === 'update_payment_config') {
            $error = admin_update_payment_config(post_value('site_name'), post_value('site_url'), post_value('vpay_base_url'), post_value('vpay_key')) ?? '';
            if ($error === '') {
                $message = '支付配置保存成功';
            }
        } elseif ($action === 'update_admin_account') {
            $error = admin_update_account(post_value('admin_username'), post_value('admin_password')) ?? '';
            if ($error === '') {
                $message = '后台账号保存成功';
            }
        } elseif ($action === 'update_announcement') {
            set_system_setting('site_announcement', post_value('site_announcement'));
            $message = '系统公告保存成功';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$admin = admin_config();
$stats = admin_dashboard_stats();
$products = admin_product_stats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理后台 - <?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
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
        .wrap { max-width: 1200px; margin: 0 auto; position: relative; z-index: 1; }
        
        .top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px; padding: 32px; }
        h1 { margin: 0; font-size: 26px; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em; }
        h2 { margin: 0 0 24px; font-size: 20px; font-weight: 600; color: var(--text-main); border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 16px; }

        .nav { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 20px; }
        .nav a {
            display: inline-block; padding: 10px 18px; border-radius: 8px; text-decoration: none;
            font-size: 14px; font-weight: 500; color: var(--text-muted); background: rgba(255,255,255,0.5);
            border: 1px solid rgba(0,0,0,0.05); transition: all 0.2s ease;
        }
        .nav a:hover, .nav a.active { color: var(--primary); background: rgba(255,255,255,0.9); border-color: var(--primary); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: var(--radius-md);
            font-size: 14px; font-weight: 600; color: #fff; background: var(--primary); border: 1px solid #2563eb;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }
        .btn-danger { background: var(--danger); border-color: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: var(--danger-hover); box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3); }

        .box { padding: 32px; margin-bottom: 32px; }

        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; }
        .card { background: rgba(255,255,255,0.4); border: 1px solid rgba(0,0,0,0.04); border-radius: 12px; padding: 24px; text-align: center; color: var(--text-muted); font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .card .num { display: block; font-size: 36px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; line-height: 1; letter-spacing: normal; text-transform: none; }

        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid rgba(0,0,0,0.05); background: rgba(255,255,255,0.4); border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 16px 12px; border-bottom: 1px solid rgba(0,0,0,0.04); text-align: left; font-size: 14px; vertical-align: middle; color: var(--text-main); }
        th { color: var(--text-muted); font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid rgba(0,0,0,0.06); background: rgba(0,0,0,0.02); }
        tr:hover td { background: rgba(0,0,0,0.01); }

        input, select, textarea {
            padding: 12px 16px; background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.08);
            border-radius: 8px; color: var(--text-main); font-size: 14px; outline: none; transition: all 0.2s; font-family: inherit;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); background: rgba(255,255,255,0.9); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; cursor: pointer; }
        select option { background: #fff; color: var(--text-main); }
        
        .inline-form { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        
        .msg { padding: 16px 20px; border-radius: 12px; margin-bottom: 32px; font-size: 14px; font-weight: 600; }
        .ok { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
        .err { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>

<div class="aurora-bg"><div class="aurora-blob blob1"></div><div class="aurora-blob blob2"></div><div class="aurora-blob blob3"></div></div>

<div class="wrap">
    <div class="top glass-panel">
        <div>
            <h1>简单发卡网后台</h1>
            <div class="nav">
                <a href="admin.php" class="active">后台首页</a>
                <a href="admin_upload.php">上传卡密</a>
                <a href="admin_cards.php">卡密管理</a>
                <a href="admin_orders.php">订单管理</a>
                <a href="../index.php" target="_blank">前台首页</a>
            </div>
        </div>
        <a class="btn btn-danger" href="admin_logout.php" style="min-width:auto; padding:10px 16px;">退出登录</a>
    </div>

    <?php if ($message !== ''): ?><div class="msg ok"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="msg err"><?php echo h($error); ?></div><?php endif; ?>

    <div class="box glass-panel">
        <div class="cards">
            <div class="card"><span class="num"><?php echo (int)$stats['products']; ?></span>商品数</div>
            <div class="card"><span class="num"><?php echo (int)$stats['unused_cards']; ?></span>未售卡密</div>
            <div class="card"><span class="num"><?php echo (int)$stats['sold_cards']; ?></span>已售卡密</div>
            <div class="card"><span class="num"><?php echo (int)$stats['paid_orders']; ?></span>已支付订单</div>
        </div>
    </div>

    <div class="box glass-panel">
        <h2>系统公告 (支持HTML)</h2>
        <form method="post" style="margin-bottom:0;">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_announcement">
            <textarea class="form-control" name="site_announcement" rows="3" placeholder="系统公告，显示在前台首页上方。留空则不显示..."><?php echo h(system_setting('site_announcement', '')); ?></textarea>
            <button class="btn" type="submit" style="margin-top: 16px;">保存公告</button>
        </form>
    </div>

    <div class="box glass-panel">
        <h2>新增商品</h2>
        <form method="post" style="display:flex; flex-direction:column; gap:16px;">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="create_product">
            <div class="inline-form" style="margin-bottom:0;">
                <input type="text" name="product_id" placeholder="商品编号，如 card_3" required style="flex:1; min-width:180px;">
                <input type="text" name="name" placeholder="商品名称" required style="flex:2; min-width:200px;">
                <input type="text" name="price" placeholder="价格，例如 9.90" required style="flex:1; min-width:120px;">
            </div>
            <textarea class="form-control" name="description" rows="2" placeholder="商品介绍（支持HTML），显示在商品卡片下方，可留空"></textarea>
            <button class="btn" type="submit" style="align-self:flex-start;">新增商品</button>
        </form>
    </div>

    <div class="box glass-panel">
        <h2>支付配置</h2>
        <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_payment_config">
            <div class="inline-form">
                <input type="text" name="site_name" value="<?php echo h(site_name()); ?>" placeholder="站点名称，例如 9888公益站API" required style="flex:1; min-width:300px;">
            </div>
            <div class="inline-form">
                <input type="text" name="site_url" value="<?php echo h(system_setting('site_url', 'http://localhost')); ?>" placeholder="站点地址，例如 https://你的域名" required style="flex:1; min-width:300px;">
            </div>
            <div class="inline-form">
                <input type="text" name="vpay_base_url" value="<?php echo h(system_setting('vpay_base_url', '')); ?>" placeholder="V免签地址，例如 https://pay.example.com" required style="flex:1; min-width:300px;">
            </div>
            <div class="inline-form">
                <input type="text" name="vpay_key" value="<?php echo h(system_setting('vpay_key', '')); ?>" placeholder="V免签密钥" required style="flex:1; min-width:300px;">
                <button class="btn" type="submit" style="white-space:nowrap;">保存配置</button>
            </div>
        </form>
    </div>

    <div class="box glass-panel">
        <h2>后台账号</h2>
        <form method="post" class="inline-form">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_admin_account">
            <input type="text" name="admin_username" value="<?php echo h($admin['username'] ?? 'admin'); ?>" placeholder="后台账号" required style="flex:1; min-width:200px;">
            <input type="password" name="admin_password" value="" placeholder="新后台密码" required style="flex:1; min-width:200px;">
            <button class="btn" type="submit" style="white-space:nowrap;">保存账号</button>
        </form>
    </div>

    <div class="box glass-panel">
        <h2>商品管理</h2>
        <div class="table-responsive">
            <table>
                <thead>
                <tr><th>商品编号</th><th>商品名称</th><th>价格</th><th>状态</th><th>剩余库存</th><th>已售数量</th><th>总卡密数</th><th>操作</th></tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td style="font-family:monospace;"><?php echo h($product['id']); ?></td>
                        <td>
                            <form method="post" style="display:flex; flex-direction:column; gap:8px;">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="update_product">
                                <input type="hidden" name="product_id" value="<?php echo h($product['id']); ?>">
                                <input type="text" name="name" class="form-control" value="<?php echo h($product['name']); ?>" required style="min-width: 160px; padding: 10px;">
                                <textarea name="description" class="form-control" rows="2" placeholder="商品介绍..." style="padding: 8px; font-size:13px;"><?php echo h($product['description']); ?></textarea>
                        </td>
                        <td><input type="text" name="price" class="form-control" value="<?php echo h(number_format((float)$product['price'], 2, '.', '')); ?>" required style="width: 90px; padding: 10px;"></td>
                        <td>
                            <select name="status" class="form-control" style="width: 100px; padding: 10px;">
                                <option value="1" <?php echo (int)$product['status'] === 1 ? 'selected' : ''; ?>>上架</option>
                                <option value="0" <?php echo (int)$product['status'] !== 1 ? 'selected' : ''; ?>>下架</option>
                            </select>
                        </td>
                        <td style="font-weight:700;"><?php echo (int)$product['stock']; ?></td>
                        <td><?php echo (int)$product['sold_count']; ?></td>
                        <td><?php echo (int)$product['total_cards']; ?></td>
                        <td style="min-width: 160px;">
                                <button class="btn" type="submit" style="padding: 8px 14px; font-size: 13px; min-width:auto;">保存</button>
                            </form>
                            <form method="post" onsubmit="return confirm('确认删除该商品？');" style="display:inline-block; margin-left:6px; margin-top:8px;">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?php echo h($product['id']); ?>">
                                <button class="btn btn-danger" type="submit" style="padding: 8px 14px; font-size: 13px; min-width:auto;">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>