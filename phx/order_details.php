<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
if ($order_id < 1) {
    header('Location: orders.php');
    exit;
}
header('Location: order_manage.php?order_id=' . $order_id);
exit;
