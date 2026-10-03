<?php
require __DIR__ . '/../includes/lib.php';

$orderId = get_value('orderId');
if ($orderId === '') {
    $orderId = get_value('out_trade_no');
}

if ($orderId !== '') {
    header('Location: ../order.php?orderId=' . urlencode($orderId));
    exit;
}

header('Location: ../index.php');
exit;

