<?php
require '../db.php';
require 'auth.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['message'] = 'Geçersiz ID!';
    $_SESSION['message_type'] = 'error';
    header('Location: shipping_process_list.php');
    exit();
}

// Önce kaydın var olup olmadığını kontrol et
$stmt = $pdo->prepare("SELECT id FROM shipping_process WHERE id = ?");
$stmt->execute([$id]);
$exists = $stmt->fetch();

if (!$exists) {
    $_SESSION['message'] = 'Kayıt bulunamadı!';
    $_SESSION['message_type'] = 'error';
    header('Location: shipping_process_list.php');
    exit();
}

// Silme işlemi
$sql = "DELETE FROM shipping_process WHERE id = ?";
$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([$id]);
    $_SESSION['message'] = 'Kayıt başarıyla silindi!';
    $_SESSION['message_type'] = 'success';
} catch (PDOException $e) {
    $_SESSION['message'] = 'Hata: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header('Location: shipping_process_list.php');
exit();
?>
