<?php
require __DIR__ . '/../includes/lib.php';
admin_logout();
header('Location: admin_login.php');
exit;