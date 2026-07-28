<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/admin_rbac.php';

$page_title = 'Şifre Değiştir';

$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $requestedId > 0 ? $requestedId : $sessionUserId;

if ($user_id <= 0) {
    admin_abort_redirect('Oturum bulunamadı. Lütfen tekrar giriş yapın.', 'login.php');
}

$isSelf = $user_id === $sessionUserId;
if (! $isSelf && ! admin_is_super()) {
    admin_abort_redirect('Başka kullanıcıların şifresini yalnızca süper admin değiştirebilir.', 'index.php');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new_password) < 6) {
        $message = 'Şifre en az 6 karakter olmalıdır.';
        $messageType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Şifreler eşleşmiyor.';
        $messageType = 'danger';
    } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');

        if ($stmt->execute([$hashedPassword, $user_id])) {
            $message = 'Şifre başarıyla güncellendi.';
            $messageType = 'success';
        } else {
            $message = 'Şifre güncellenirken bir hata oluştu.';
            $messageType = 'danger';
        }
    }
}

$stmt = $pdo->prepare('SELECT username, full_name FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (! $user) {
    admin_abort_redirect('Kullanıcı bulunamadı.', $isSelf ? 'index.php' : 'user_management.php');
}
?>

<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <div class="top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bar-left">
                <h2 class="mb-0"><i class="fas fa-key me-2"></i><?= $isSelf ? 'Hesabım — şifre' : 'Şifre değiştir' ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="bar-right">
                <?php if (! $isSelf): ?>
                    <a href="user_management.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Geri</a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Panel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" id="passwordForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="new_password" class="form-label">Yeni şifre</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Şifre tekrar</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($messageType === 'success' && ! $isSelf): ?>
<script>setTimeout(function(){ window.location.href='user_management.php'; }, 1800);</script>
<?php endif; ?>

<?php include 'admin_footer_common.php'; ?>


