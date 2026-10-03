<?php
require __DIR__ . '/../includes/lib.php';

$orderId = get_value('orderId');
if ($orderId !== '') {
    $order = find_order($orderId);
    if ($order && ($order['status'] ?? '') === 'paid') {
        echo '1';
        exit;
    }
}
echo '0';

