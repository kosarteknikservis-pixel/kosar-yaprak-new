<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../db.php';
require 'auth.php';

header('Content-Type: application/json');

$lastOrderId = isset($_SESSION['last_order_id']) ? $_SESSION['last_order_id'] : 0;

$stmt = $pdo->prepare("
    SELECT o.order_id, o.customer_name, o.order_date
    FROM orders o
    WHERE o.order_id > ?
    AND o.order_status_id != 19
    ORDER BY o.order_id DESC
    LIMIT 1
");

$stmt->execute([$lastOrderId]);
$newOrder = $stmt->fetch(PDO::FETCH_ASSOC);

if ($newOrder) {
    $_SESSION['last_order_id'] = $newOrder['order_id'];
    echo json_encode([
        'success' => true,
        'order' => $newOrder
    ]);
} else {
    echo json_encode([
        'success' => false
    ]);
}
