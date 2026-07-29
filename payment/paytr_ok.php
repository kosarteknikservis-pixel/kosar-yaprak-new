<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
$target = $orderId > 0
    ? app_url('thankyou', ['order_id' => $orderId], $pdo)
    : app_url('', [], $pdo);

header('Location: ' . $target);
exit;
