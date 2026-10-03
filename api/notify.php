<?php
require __DIR__ . '/../includes/lib.php';

// 姝ゅ浠呬负杩樺師缁撴瀯鍗犱綅銆傚疄闄呬腑锛屾牴鎹?V鍏嶇 寮傛閫氱煡绛惧悕瑙勫垯杩涜鏍￠獙
$payId = get_value('payId');
$param = get_value('param'); // param 閫氬父绛変簬 order_id
$type = get_value('type');
$price = get_value('price');
$sign = get_value('sign');

$vpayKey = system_setting('vpay_key');

$calculatedSign = md5($payId . $param . $type . $price . $vpayKey);

if ($sign === $calculatedSign) {
    complete_order($param);
    echo 'success';
} else {
    echo 'fail';
}

