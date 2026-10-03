<?php
$debugLog = __DIR__ . '/storage/order_debug.log';
@file_put_contents($debugLog, "\n==== " . date('Y-m-d H:i:s') . " ====\n", FILE_APPEND);
@file_put_contents($debugLog, 'QUERY: ' . ($_SERVER['QUERY_STRING'] ?? '') . "\n", FILE_APPEND);

try {
    require __DIR__ . '/includes/lib.php';
    $orderId = get_value('orderId');
    @file_put_contents($debugLog, 'ORDER_ID: ' . $orderId . "\n", FILE_APPEND);
    $order = find_order($orderId);
    @file_put_contents($debugLog, 'ORDER_FOUND: ' . ($order ? 'yes' : 'no') . "\n", FILE_APPEND);
    if ($order) {
        @file_put_contents($debugLog, 'ORDER_DATA: ' . json_encode($order, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
    if (!$order) {
        exit('订单不存在');
    }
} catch (Throwable $e) {
    @file_put_contents($debugLog, 'ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
    @file_put_contents($debugLog, 'FILE: ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
    @file_put_contents($debugLog, 'TRACE: ' . $e->getTraceAsString() . "\n", FILE_APPEND);
    exit('ORDER_DEBUG_ERROR');
}

$isPaid = ($order['status'] ?? '') === 'paid';
$cardValue = (string)($order['card'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>订单详情 - <?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: 1px solid rgba(255, 255, 255, 0.8);
            --glass-shadow: 0 10px 40px rgba(31, 38, 135, 0.05);
            --radius-lg: 16px;
            --radius-md: 10px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            color: var(--text-main); background-color: var(--bg-color); overflow-x: hidden; min-height: 100vh; line-height: 1.6;
            display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px;
        }
        
        .aurora-bg { position: fixed; inset: 0; z-index: -1; background: var(--bg-color); overflow: hidden; pointer-events: none; }
        .aurora-blob { position: absolute; filter: blur(120px); border-radius: 50%; opacity: 0.6; animation: move 20s infinite alternate ease-in-out; }
        .blob1 { width: 600px; height: 600px; background: #93c5fd; top: -200px; left: -100px; animation-duration: 25s; }
        .blob2 { width: 500px; height: 500px; background: #c4b5fd; bottom: -150px; right: -150px; animation-delay: -5s; animation-duration: 22s; }
        .blob3 { width: 400px; height: 400px; background: #86efac; top: 30%; left: 40%; animation-delay: -10s; animation-duration: 28s; opacity: 0.5; }
        @keyframes move { 0% { transform: translate(0, 0) scale(1) rotate(0deg); } 33% { transform: translate(5vw, -5vh) scale(1.05) rotate(15deg); } 66% { transform: translate(-5vw, 5vh) scale(0.95) rotate(-10deg); } 100% { transform: translate(2vw, 2vh) scale(1) rotate(5deg); } }

        .glass-panel { background: var(--glass-bg); backdrop-filter: blur(24px) saturate(150%); border: var(--glass-border); box-shadow: var(--glass-shadow); border-radius: var(--radius-lg); }
        
        .ticket-wrapper { width: 100%; max-width: 600px; margin: auto; position: relative; z-index: 1; }
        .ticket { overflow: hidden; }
        .ticket-header { padding: 40px 40px 30px; text-align: center; }
        
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 16px; border-radius: 999px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .status-paid { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .status-created { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .status-closed { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        
        .price { font-size: 42px; font-weight: 800; color: var(--text-main); line-height: 1; margin: 0 0 10px; }
        .product-name { color: var(--text-muted); font-size: 16px; font-weight: 500; }

        .ticket-divider { height: 1px; width: 100%; position: relative; }
        .ticket-divider::before { content: ""; position: absolute; left: 24px; right: 24px; top: 0; border-top: 2px dashed rgba(0,0,0,0.08); }
        .ticket-divider::after, .ticket-divider::before { z-index: 2; }

        .ticket-body { padding: 30px 40px; }
        .t-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; font-size: 15px; }
        .t-label { color: var(--text-muted); font-weight: 500; }
        .t-val { color: var(--text-main); font-weight: 600; text-align: right; font-family: monospace; font-size: 14px; word-break: break-all; max-width: 65%; }

        .card-code-box {
            margin-top: 10px; padding: 24px; border-radius: var(--radius-md); text-align: center;
            background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.15); position: relative;
        }
        .cc-label { font-size: 13px; color: #059669; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; opacity: 0.9; }
        .cc-value { font-size: 20px; font-weight: 700; color: #059669; font-family: monospace; letter-spacing: 1px; word-break: break-all; line-height: 1.4; user-select: all; }
        
        .warning-box {
            margin-top: 10px; padding: 20px; border-radius: var(--radius-md); text-align: center;
            background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.15);
        }
        .warning-box span { color: #d97706; font-size: 15px; font-weight: 600; }

        .action-btns { display: flex; gap: 16px; margin-top: 30px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; flex: 1;
            padding: 14px 24px; border-radius: var(--radius-md); font-size: 15px; font-weight: 600;
            color: #fff; background: var(--primary); border: 1px solid #2563eb;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }
        .btn-secondary { background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.06); color: var(--text-main); box-shadow: none; }
        .btn-secondary:hover { background: rgba(0,0,0,0.06); border-color: rgba(0,0,0,0.1); }

        @media (max-width: 600px) {
            body { padding: 20px 16px; justify-content: flex-start; }
            .ticket-header { padding: 32px 24px 24px; }
            .ticket-body { padding: 24px; }
            .price { font-size: 36px; }
            .action-btns { flex-direction: column; gap: 12px; }
        }
    </style>
</head>
<body>
    <div class="aurora-bg">
        <div class="aurora-blob blob1"></div><div class="aurora-blob blob2"></div><div class="aurora-blob blob3"></div>
    </div>

    <div class="ticket-wrapper">
        <div class="ticket glass-panel">
            
            <?php 
                $s = (string)($order['status'] ?? '');
                $sClass = 'created'; $sText = '待支付';
                if($s === 'paid') { $sClass = 'paid'; $sText = '已支付'; }
                else if($s === 'closed') { $sClass = 'closed'; $sText = '已关闭'; }
            ?>
            <div class="ticket-header">
                <div class="status-badge status-<?php echo $sClass; ?>"><?php echo $sText; ?></div>
                <div class="price">￥<?php echo htmlspecialchars($order['price'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="product-name"><?php echo htmlspecialchars($order['product_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <div class="ticket-divider"></div>

            <div class="ticket-body">
                <div class="t-row">
                    <span class="t-label">订单编号</span>
                    <span class="t-val"><?php echo htmlspecialchars($order['order_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="t-row">
                    <span class="t-label">交易单号</span>
                    <span class="t-val"><?php echo htmlspecialchars($order['pay_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="t-row">
                    <span class="t-label">支付方式</span>
                    <span class="t-val" style="font-family: inherit;"><?php echo htmlspecialchars(payment_name($order['type']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="t-row">
                    <span class="t-label">创建时间</span>
                    <span class="t-val"><?php echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                
                <?php if ($isPaid && $cardValue !== ''): ?>
                    <div class="card-code-box">
                        <div class="cc-label">发货卡密 (请妥善保存)</div>
                        <div class="cc-value"><?php echo htmlspecialchars($cardValue, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php else: ?>
                    <div class="warning-box">
                        <span>订单处理中，请耐心等待状态更新...</span>
                    </div>
                <?php endif; ?>

                <div class="action-btns">
                    <a class="btn btn-secondary" href="index.php">返回首页</a>
                    <a class="btn" href="order.php?orderId=<?php echo urlencode($order['order_id']); ?>">刷新订单</a>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>