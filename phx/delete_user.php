<?php
require '../db.php';
require 'auth.php';

$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: user_management.php?error=invalid_id');
    exit;
}

// Birincil hesap (user_id = 1) silinemez — kendini kilitlemeyi önle.
if ((int) $user_id === 1) {
    header('Location: user_management.php?error=primary_protected');
    exit;
}

try {
    // Kullanıcıyı silme işlemi
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Eğer silme işlemi başarılı olduysa yönlendirme yap
    header('Location: user_management.php?message=success');
    exit;
} catch (PDOException $e) {
    // Eğer foreign key constraint hatası alırsak
    if ($e->getCode() == '23000') {
        // Hata mesajı ile yönlendirme yap
        header('Location: user_management.php?error=log_exists');
        exit;
    } else {
        header('Location: user_management.php?error=delete_failed');
        exit;
    }
}
?>
