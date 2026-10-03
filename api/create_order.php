<?php
require __DIR__ . '/../includes/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = post_value('product_id');
    $payType = post_value('type');

    list($order, $error) = create_order_record($productId, $payType);
    if ($error !== null) {
        exit($error);
    }

    $url = build_vpay_url($order, notify_url(), return_url($order['order_id']));
    header("Location: $url");
    exit;
}

exit('Invalid Request');

