<?php
require '../db.php';
require 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$variation_id = isset($_GET['variation_id']) ? (int) $_GET['variation_id'] : 0;

if ($variation_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM product_variants WHERE variation_id = ?');
    $stmt->execute([$variation_id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($variants, JSON_UNESCAPED_UNICODE);
}
