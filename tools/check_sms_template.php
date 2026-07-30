<?php
declare(strict_types=1);
require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/netgsm_customer_sms.php';
$default = netgsm_default_new_order_message();
$pdo->prepare('UPDATE netgsm_settings SET message = ? WHERE id = 1')->execute([$default]);
echo $default;
