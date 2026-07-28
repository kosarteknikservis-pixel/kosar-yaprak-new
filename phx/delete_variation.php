<?php
require '../db.php';
require 'auth.php';

$variation_id = $_GET['variation_id'] ?? null;

if (!$variation_id) {
    header('Location: add_variation.php?error=missing_id');
    exit;
}

// Varyasyonu veritabanından sil
$stmt = $pdo->prepare("DELETE FROM product_variations WHERE variation_id = ?");
$stmt->execute([$variation_id]);

// Başarı mesajını ayarla
$_SESSION['message'] = "Varyasyon başarıyla silindi!";

// İlk yönlendirme, parametre ile (mesajı göstermek için)
header("Location: add_variation.php?deleted=true");
exit();

// URL'yi sıfırlamak için kısa bir gecikme ekleyip ardından URL'yi temizleyeceğiz.
header("Refresh:0; url=add_variation.php");
exit();
?>
