<?php

declare(strict_types=1);

require '../db.php';
require 'auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Yönteme izin verilmiyor'], JSON_UNESCAPED_UNICODE);
    exit;
}

$order_id = isset($_POST['order_id']) ? (string) $_POST['order_id'] : '';
$new_status_raw = isset($_POST['order_status_id']) ? (string) $_POST['order_status_id'] : '';

if ($order_id === '' || ! ctype_digit($order_id)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Geçersiz sipariş'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($new_status_raw === '' || ! ctype_digit($new_status_raw)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Durum seçin'], JSON_UNESCAPED_UNICODE);
    exit;
}

$new_status_id = (int) $new_status_raw;
$order_id_int = (int) $order_id;

$current_user = $_SESSION['admin_username']
    ?? ($_SESSION['username'] ?? 'Bilinmeyen Kullanıcı');

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT order_status_id FROM orders WHERE order_id = ?');
    $stmt->execute([$order_id_int]);
    $old_status_id = $stmt->fetchColumn();

    if ($old_status_id === false) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sipariş bulunamadı']);
        exit;
    }

    $update = $pdo->prepare(
        'UPDATE orders
            SET order_status_id = ?, updated_by = ?, edit_order_date = NOW()
            WHERE order_id = ?'
    );
    $update->execute([$new_status_id, $current_user, $order_id_int]);

    $log = $pdo->prepare(
        'INSERT INTO order_status_logs
            (order_id, old_status_id, new_status_id, changed_by)
            VALUES (?, ?, ?, ?)'
    );
    $log->execute([$order_id_int, $old_status_id, $new_status_id, $current_user]);

    $pdo->commit();

    try {
        require_once dirname(__DIR__) . '/includes/netgsm_customer_sms.php';
        netgsm_try_send_customer_sms_on_status_transition(
            $pdo,
            $order_id_int,
            (int) $old_status_id,
            $new_status_id
        );
    } catch (Throwable $e) {
        error_log('netgsm_try_send_customer_sms_on_status_transition: ' . $e->getMessage());
    }

    try {
        require_once dirname(__DIR__) . '/telegram.php';
        telegram_notify_admin_status_change($pdo, $order_id_int, (int) $old_status_id, $new_status_id);
    } catch (Throwable $e) {
        error_log('telegram_notify_admin_status_change: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Durum başarıyla güncellendi'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
