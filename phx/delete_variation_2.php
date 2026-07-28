<?php
require '../db.php';
require 'auth.php';

$variation_id = $_GET['variation_id'] ?? null;

if (!$variation_id) {
    header('Location: add_variation_2.php?error=missing_id');
    exit;
}

try {
    // İlk olarak varyasyonun ilişkili olduğu tüm varyantları silmeliyiz
    $stmt = $pdo->prepare("DELETE FROM product_variants_2 WHERE variation_id = ?");
    $stmt->execute([$variation_id]);

    // Daha sonra varyasyonu sil
    $stmt = $pdo->prepare("DELETE FROM product_variations_2 WHERE variation_id = ?");
    $stmt->execute([$variation_id]);

    // Başarı mesajını ayarla
    $_SESSION['message'] = "Varyasyon ve bağlı varyantlar başarıyla silindi!";

    // Başarılı işlemden sonra yönlendirme
    header("Location: add_variation_2.php?deleted=true");
    exit();
} catch (Exception $e) {
    // Hata durumunda mesaj göster
    $_SESSION['message'] = "Varyasyon silinirken bir hata oluştu: " . $e->getMessage();
    header("Location: add_variation_2.php?deleted=false");
    exit();
}
?>
