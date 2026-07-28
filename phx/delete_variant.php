<?php
require '../db.php';
require 'auth.php';

$variant_id = $_GET['variant_id'] ?? null;
$variation_id = $_GET['variation_id'] ?? null;

if (!$variant_id || !$variation_id) {
    header('Location: add_variation.php?error=missing_id');
    exit;
}

// Varyantı veritabanından sil
$stmt = $pdo->prepare("DELETE FROM product_variants WHERE variant_id = ?");
$stmt->execute([$variant_id]);

// Başarı mesajını ayarla
$_SESSION['message'] = "Varyant başarıyla silindi!";
header("Location: edit_variation.php?variation_id=$variation_id&deleted=true");
exit();
?>
