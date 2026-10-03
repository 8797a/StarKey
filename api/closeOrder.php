<?php
require __DIR__ . '/../includes/lib.php';

$orderId = post_value('orderId');
if ($orderId !== '') {
    close_order_record($orderId);
    echo 'success';
    exit;
}
echo 'fail';

