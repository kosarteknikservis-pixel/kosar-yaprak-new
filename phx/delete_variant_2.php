<?php
require '../db.php';
require 'auth.php';

$variant_id = $_GET['variant_id'] ?? null;
$variation_id = $_GET['variation_id'] ?? null;

if (!$variant_id || !$variation_id) {
    header('Location: add_variation_2.php?error=missing_id');
    exit;
}

// Varyantı veritabanından sil
$stmt = $pdo->prepare("DELETE FROM product_variants_2 WHERE variant_id = ?");
$stmt->execute([$variant_id]);

// Başarı mesajını ayarla
$_SESSION['message'] = "Varyant başarıyla silindi!";

// Silme işleminden sonra edit_variation_2.php sayfasına yönlendir ve variation_id'yi koru
header("Location: edit_variation_2.php?variation_id=$variation_id&deleted=true");
exit();
?>
