<?php
require '../db.php';
require 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;

    if (!$product_id || !$new_status) {
        echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
        exit;
    }

    if (!in_array($new_status, ['visible', 'hidden'])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz durum']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET status = ? WHERE product_id = ?');
        $stmt->execute([$new_status, $product_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Durum güncellendi',
                'new_status' => $new_status,
                'new_status_text' => $new_status === 'visible' ? 'Görünür' : 'Gizli'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ürün bulunamadı']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
}
?>
