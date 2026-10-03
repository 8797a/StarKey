<?php
require __DIR__ . '/../includes/lib.php';

$orderId = get_value('orderId');
if ($orderId !== '') {
    $order = find_order($orderId);
    if ($order) {
        echo $order['status'] ?? 'unknown';
        exit;
    }
}
echo 'not_found';

