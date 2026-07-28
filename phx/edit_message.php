<?php
require '../db.php';
require 'auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    admin_abort_redirect('Mesaj ID belirtilmedi.', 'index.php');
}

$stmt = $pdo->prepare('SELECT * FROM whatsapp_messages WHERE id = ?');
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    admin_abort_redirect('Mesaj bulunamadı.', 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_text = trim((string) ($_POST['message_text'] ?? ''));

    if ($message_text !== '') {
        try {
            $stmt = $pdo->prepare('UPDATE whatsapp_messages SET message_text = ? WHERE id = ?');
            $stmt->execute([$message_text, $id]);

            admin_abort_redirect('Mesaj güncellendi.', 'index.php', 'success');
        } catch (PDOException $e) {
            admin_abort_redirect('Veritabanı hatası: kayıt güncellenemedi.', 'edit_message.php?id=' . $id);
        }
    } else {
        admin_abort_redirect('Mesaj içeriği eksik.', 'edit_message.php?id=' . $id);
    }
}

$page_title = 'Mesaj düzenle';
include 'admin_header.php';
?>

<div class="container-fluid px-0" style="max-width: 720px;">
    <h1 class="h4 mb-3"><i class="fas fa-comment-dots me-2 text-muted"></i>Mesaj düzenle</h1>

    <form action="" method="POST">
        <div class="mb-3">
            <label for="message_text" class="form-label">Mesaj içeriği</label>
            <textarea id="message_text" name="message_text" class="form-control" rows="6" required><?= htmlspecialchars($message['message_text']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <a href="admin_view_messages.php" class="btn btn-outline-secondary ms-2">İptal</a>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
