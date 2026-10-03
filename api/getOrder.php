<?php
require __DIR__ . '/../includes/lib.php';

$orderId = get_value('orderId');
if ($orderId !== '') {
    $order = find_order($orderId);
    if ($order) {
        json_response($order);
    }
}
json_response(['error' => 'not found']);

