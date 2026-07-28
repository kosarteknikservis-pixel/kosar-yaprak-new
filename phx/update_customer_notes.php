<?php
require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $customer_notes = $_POST['customer_notes'];

    $stmt = $pdo->prepare('UPDATE orders SET customer_notes = ? WHERE order_id = ?');
    $stmt->execute([$customer_notes, $order_id]);

    echo 'Success';
}
?>
