<?php
require __DIR__ . '/includes/lib.php';
require __DIR__ . '/includes/ui.php';
$products = get_products();
$availableProducts = array_filter($products, function ($product) {
    return (int)($product['status'] ?? 0) === 1;
});
$defaultProductId = '';
foreach ($availableProducts as $productId => $product) {
    if ((int)($product['stock'] ?? 0) > 0) {
        $defaultProductId = $productId;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <?php echo faka_ui_head(); ?>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
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
        }
        .glass-panel { background: var(--glass-bg); backdrop-filter: blur(24px) saturate(150%); border: var(--glass-border); box-shadow: var(--glass-shadow); border-radius: var(--radius-lg); }

        .layout-wrapper { width: min(1200px, 100% - 40px); margin: 40px auto; position: relative; z-index: 1; }
        
        .site-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .site-title { font-size: 28px; font-weight: 800; margin: 0; letter-spacing: -0.02em; color: var(--text-main); }
        .header-stats { display: flex; gap: 15px; }
        .h-stat { display: flex; align-items: center; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.03); padding: 8px 16px; border-radius: 999px; font-size: 13px; color: var(--text-muted); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .h-stat span { font-weight: 700; color: var(--text-main); margin-right: 6px; font-size: 15px;}

        .main-form { display: grid; grid-template-columns: 1fr 380px; gap: 32px; align-items: start; }

        .section-title { font-size: 20px; font-weight: 600; color: var(--text-main); margin: 0 0 20px; display: flex; align-items: center; gap: 10px; }
        .section-title::before { content: ""; display: block; width: 4px; height: 18px; background: var(--primary); border-radius: 2px; }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        
        .product-card {
            position: relative; display: block; cursor: pointer;
            background: rgba(255, 255, 255, 0.4); border: 2px solid rgba(255,255,255,0.8);
            border-radius: var(--radius-lg); padding: 24px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .product-card:not(.disabled):hover { border-color: rgba(59, 130, 246, 0.3); background: rgba(255, 255, 255, 0.8); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .product-card input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
        
        .product-card.selected { border-color: var(--primary); background: rgba(59, 130, 246, 0.05); box-shadow: 0 0 0 1px var(--primary), 0 8px 24px rgba(59, 130, 246, 0.1); }
        .product-card.disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(1); }

        .pc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .pc-title { font-size: 18px; font-weight: 600; color: var(--text-main); margin: 0; line-height: 1.4; padding-right: 10px;}
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        
        .pc-price { font-size: 28px; font-weight: 800; color: var(--text-main); margin-bottom: 20px; }
        .pc-price span { font-size: 16px; color: var(--text-muted); font-weight: 500; margin-right: 2px;}
        
        .pc-stock { font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 16px; }

        .sidebar { position: sticky; top: 40px; }
        .checkout-panel { padding: 32px; background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(255,255,255,0.9); }
        
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px dashed rgba(0,0,0,0.08); font-size: 15px; color: var(--text-muted); }
        .summary-row:last-of-type { border-bottom: none; padding-bottom: 0; margin-bottom: 24px; }
        .summary-val { color: var(--text-main); font-weight: 600; text-align: right; max-width: 60%; line-height: 1.4; }
        .total-price { font-size: 32px; font-weight: 800; color: var(--text-main); }

        .pay-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 32px; }
        .pay-method {
            display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px;
            background: rgba(255,255,255,0.6); border: 2px solid rgba(0,0,0,0.05); border-radius: var(--radius-md); 
            cursor: pointer; transition: all 0.2s; font-size: 15px; font-weight: 600; color: var(--text-muted);
        }
        .pay-method input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
        .pay-method.active { border-color: var(--success); background: rgba(16, 185, 129, 0.05); color: #059669; }
        .pay-method:hover:not(.active) { border-color: rgba(0,0,0,0.15); color: var(--text-main); background: rgba(255,255,255,0.9); }

        .btn-submit {
            width: 100%; padding: 16px; background: var(--primary); border: 1px solid #2563eb;
            color: #fff; font-size: 16px; font-weight: 600; border-radius: var(--radius-md); cursor: pointer;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; background: #94a3b8; border-color: #cbd5e1; }

        @media (max-width: 960px) { .main-form { grid-template-columns: 1fr; } .sidebar { position: relative; top: 0; } .header-stats { display: none; } }
        @media (max-width: 600px) { .layout-wrapper { width: 100%; margin: 20px auto; padding: 0 20px; } .site-title { font-size: 24px; } .checkout-panel, .product-card { padding: 24px; } .product-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php echo faka_ui_background(false); ?>

    <div class="layout-wrapper">
        <header class="site-header">
            <h1 class="site-title"><?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="header-stats">
                <div class="h-stat"><span><?php echo count($availableProducts); ?></span>在售商品</div>
                <div class="h-stat"><span><?php echo array_sum(array_map(function ($product) { return (int)($product['stock'] ?? 0); }, $availableProducts)); ?></span>发卡库存</div>
                <div class="h-stat"><span>24H</span>自动发货</div>
            </div>
        </header>

        <?php $announcement = system_setting('site_announcement', ''); if ($announcement !== ''): ?>
        <div class="glass-panel" style="padding: 20px 24px; margin-bottom: 32px; border-left: 4px solid var(--primary); border-radius: 12px; background: rgba(59, 130, 246, 0.05);">
            <div style="font-weight: 600; color: var(--primary); margin-bottom: 8px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                系统公告
            </div>
            <div style="color: var(--text-main); font-size: 14px; line-height: 1.8;">
                <?php echo $announcement; ?>
            </div>
        </div>
        <?php endif; ?>

        <form action="api/create_order.php" method="post" class="main-form">
            <div class="products-area">
                <h2 class="section-title">选择商品</h2>
                <?php if ($availableProducts): ?>
                    <div class="product-grid">
                        <?php foreach ($availableProducts as $productId => $product): ?>
                            <?php $isOutOfStock = (int)$product['stock'] <= 0; ?>
                            <label class="product-card card-3d animate-on-scroll <?php echo $defaultProductId === $productId ? 'selected' : ''; ?> <?php echo $isOutOfStock ? 'disabled' : ''; ?>" 
                                   onclick="if(!<?php echo $isOutOfStock ? 'true' : 'false'; ?>) selectProduct(this, '<?php echo number_format((float)$product['price'], 2, '.', ''); ?>', '<?php echo htmlspecialchars(addslashes($product['name']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes(preg_replace('/\r|\n/', '', $product['description'])), ENT_QUOTES, 'UTF-8'); ?>')">
                                
                                <input type="radio" name="product_id" value="<?php echo htmlspecialchars($productId, ENT_QUOTES, 'UTF-8'); ?>" 
                                       <?php echo $defaultProductId === $productId ? 'checked' : ''; ?> <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                
                                <div class="pc-desc-html" style="display: none;"><?php echo $product['description']; ?></div>
                                <div class="pc-top">
                                    <h3 class="pc-title"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <span class="badge <?php echo $isOutOfStock ? 'badge-danger' : 'badge-success'; ?>">
                                        <?php echo $isOutOfStock ? '缺货' : '充足'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($product['description'])): ?>
                                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo $product['description']; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="pc-price"><span>&yen;</span><?php echo htmlspecialchars(number_format((float)$product['price'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="pc-stock">
                                    <span>编号: <?php echo htmlspecialchars($productId, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>库存: <?php echo (int)$product['stock']; ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-panel" style="padding: 40px; text-align: center; color: var(--text-muted);">
                        当前暂无可售商品，请先在后台新增商品并导入卡密。
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <div class="checkout-panel glass-panel">
                    <h2 class="section-title" style="margin-bottom: 24px;">结算确认</h2>
                    
                    <div class="summary-row">
                        <span>已选商品</span>
                        <span class="summary-val" id="summary-name">
                            <?php echo $defaultProductId !== '' ? htmlspecialchars($availableProducts[$defaultProductId]['name'], ENT_QUOTES, 'UTF-8') : '未选择'; ?>
                        </span>
                    </div>
                    <div id="summary-desc-box" style="margin-bottom: 16px; padding: 12px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.1); border-radius: var(--radius-md); font-size: 13px; color: var(--text-muted); line-height: 1.6; display: <?php echo ($defaultProductId !== '' && !empty($availableProducts[$defaultProductId]['description'])) ? 'block' : 'none'; ?>;">
                        <div id="summary-desc"><?php echo $defaultProductId !== '' ? ($availableProducts[$defaultProductId]['description']) : ''; ?></div>
                    </div>
                    <div class="summary-row">
                        <span>支付总额</span>
                        <span class="total-price" id="summary-price">
                            <?php echo $defaultProductId !== '' ? '&yen;' . number_format((float)$availableProducts[$defaultProductId]['price'], 2, '.', '') : '&yen;0.00'; ?>
                        </span>
                    </div>
                    
                    <div style="margin: 24px 0 12px; font-size: 14px; font-weight: 600; color: var(--text-muted);">支付方式</div>
                    <div class="pay-methods">
                        <label class="pay-method active" onclick="selectPay(this)">
                            <input type="radio" name="type" value="1" checked>
                            微信支付
                        </label>
                        <label class="pay-method" onclick="selectPay(this)">
                            <input type="radio" name="type" value="2">
                            支付宝
                        </label>
                    </div>
                    
                    <button type="submit" id="btn-submit" class="btn-submit" <?php echo $defaultProductId === '' ? 'disabled' : ''; ?>>立即支付</button>
                    <div style="text-align: center; margin-top: 16px; font-size: 12px; color: var(--text-muted);">支付完成后自动获取卡密</div>
                </div>
            </div>
            
        </form>
    </div>

    <footer style="text-align:center; padding:28px 16px 24px; color:var(--text-muted); font-size:13px;">
        开源地址：<a href="https://github.com/8797a/StarKey" target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:none;">https://github.com/8797a/StarKey</a>
    </footer>

    <script>
        function selectProduct(cardEl, price, name) {
            document.querySelectorAll('.product-card').forEach(function(el) {
                el.classList.remove('selected');
            });
            cardEl.classList.add('selected');
            cardEl.querySelector('input[type="radio"]').checked = true;
            
            document.getElementById('summary-name').innerText = name;
            document.getElementById('summary-price').innerHTML = '&yen;' + price;
            
            var descBox = document.getElementById('summary-desc-box');
            var descContent = document.getElementById('summary-desc');
            var descHtml = cardEl.querySelector('.pc-desc-html') ? cardEl.querySelector('.pc-desc-html').innerHTML : '';
            
            if (descHtml && descHtml.trim() !== '') {
                descContent.innerHTML = descHtml;
                descBox.style.display = 'block';
            } else {
                descBox.style.display = 'none';
            }
            
            document.getElementById('btn-submit').disabled = false;
        }
        function selectPay(payEl) {
            document.querySelectorAll('.pay-method').forEach(function(el) {
                el.classList.remove('active');
            });
            payEl.classList.add('active');
            payEl.querySelector('input[type="radio"]').checked = true;
        }
    </script>
    <?php echo faka_ui_script(); ?>
</body>
</html>
