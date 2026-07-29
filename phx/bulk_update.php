<?php
require 'auth.php';
require '../db.php';
require_once __DIR__ . '/../includes/orders/order_delete_service.php';

// Güncellenecek siparişler ve yeni durum
$selected_orders = isset($_POST['selected_orders']) ? $_POST['selected_orders'] : [];
$bulk_status = isset($_POST['bulk_status']) ? $_POST['bulk_status'] : '';
$bulk_delete = isset($_POST['bulk_delete']) && (string) $_POST['bulk_delete'] === '1';
$current_user = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'admin');

if ($bulk_delete) {
    if (empty($selected_orders)) {
        $_SESSION['message'] = 'Silmek için en az bir sipariş seçin.';
        header('Location: orders.php');
        exit;
    }

    try {
        $deleted = order_delete_ids($pdo, $selected_orders);
        $_SESSION['message'] = $deleted > 0
            ? $deleted . ' sipariş kalıcı olarak silindi.'
            : 'Seçili siparişler silinemedi (bulunamadı).';
    } catch (Throwable $e) {
        error_log('bulk_delete_orders: ' . $e->getMessage());
        $_SESSION['message'] = 'Silme hatası: ' . $e->getMessage();
    }

    header('Location: orders.php');
    exit;
}

// Eğer sipariş seçilmişse ve durum belirtilmişse, güncelleme işlemini yap
if (!empty($selected_orders) && !empty($bulk_status)) {
    try {
        $pdo->beginTransaction();

        // Yeni durum id'sini al
        $statusStmt = $pdo->prepare("SELECT order_status_id FROM order_status WHERE status_name = ?");
        $statusStmt->execute([$bulk_status]);
        $new_status_id = (int)$statusStmt->fetchColumn();

        if (!$new_status_id) {
            throw new Exception('Geçersiz durum adı');
        }

        // Eski durumları al ve her sipariş için güncelle + logla
        $getOld = $pdo->prepare('SELECT order_status_id FROM orders WHERE order_id = ?');
        $update = $pdo->prepare("UPDATE orders SET order_status_id = ?, updated_by = ?, edit_order_date = NOW() WHERE order_id = ?");
        $logIns = $pdo->prepare("INSERT INTO order_status_logs (order_id, old_status_id, new_status_id, changed_by) VALUES (?, ?, ?, ?)");

        $updatedCount = 0;
        foreach ($selected_orders as $order_id) {
            $order_id = (int)$order_id;
            $getOld->execute([$order_id]);
            $old_status_id = (int)$getOld->fetchColumn();

            // Güncelle
            $update->execute([$new_status_id, $current_user, $order_id]);

            // Logla
            $logIns->execute([$order_id, $old_status_id, $new_status_id, $current_user]);
            try {
                require_once dirname(__DIR__) . '/includes/netgsm_customer_sms.php';
                netgsm_try_send_customer_sms_on_status_transition(
                    $pdo,
                    $order_id,
                    $old_status_id,
                    $new_status_id
                );
            } catch (Throwable $e) {
                error_log('netgsm_try_send_customer_sms_on_status_transition: ' . $e->getMessage());
            }
            try {
                require_once dirname(__DIR__) . '/telegram.php';
                telegram_notify_admin_status_change($pdo, $order_id, $old_status_id, $new_status_id);
            } catch (Throwable $e) {
                error_log('telegram_notify_admin_status_change: ' . $e->getMessage());
            }
            $updatedCount++;
        }

        $pdo->commit();
        $_SESSION['message'] = $updatedCount . ' sipariş güncellendi.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Hata: ' . $e->getMessage();
    }
} else {
    $_SESSION['message'] = "Lütfen güncellemek için en az bir sipariş seçin ve yeni bir durum belirtin.";
}

// Geri yönlendirme
header('Location: orders.php');
exit();
