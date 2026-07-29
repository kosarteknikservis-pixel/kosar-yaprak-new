<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$query = $pdo->query('SELECT * FROM telegram_settings WHERE id = 1');
/** @var array<string, mixed>|false $settings */
$settings = $query->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = [
        'bot_token' => '',
        'chat_id' => '',
        'is_enabled' => 1,
        'notify_new_order' => 1,
        'notify_support' => 1,
        'notify_partner' => 1,
        'notify_admin_status' => 0,
    ];
}

$g = static function (string $key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bot_token = trim((string) ($_POST['bot_token'] ?? ''));
    $chat_id = trim((string) ($_POST['chat_id'] ?? ''));

    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $notify_new_order = isset($_POST['notify_new_order']) ? 1 : 0;
    $notify_support = isset($_POST['notify_support']) ? 1 : 0;
    $notify_partner = isset($_POST['notify_partner']) ? 1 : 0;
    $notify_admin_status = isset($_POST['notify_admin_status']) ? 1 : 0;

    $err = '';

    if ($is_enabled) {
        if ($bot_token === '' || $chat_id === '') {
            $err = 'Telegram açıkken Bot Token ve Chat ID zorunludur.';
        } elseif (!preg_match('/^-?\d+$/', $chat_id)) {
            $err = 'Chat ID yalnızca rakamlardan (veya grupta başında -) oluşmalıdır.';
        } elseif (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $bot_token)) {
            $err = 'Bot Token formatı geçersiz. (Örnek: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz)';
        }
    }

    if ($err !== '') {
        $_SESSION['message'] = $err;
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO telegram_settings (
                    id, bot_token, chat_id,
                    is_enabled, notify_new_order, notify_support, notify_partner, notify_admin_status
                ) VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    bot_token = VALUES(bot_token),
                    chat_id = VALUES(chat_id),
                    is_enabled = VALUES(is_enabled),
                    notify_new_order = VALUES(notify_new_order),
                    notify_support = VALUES(notify_support),
                    notify_partner = VALUES(notify_partner),
                    notify_admin_status = VALUES(notify_admin_status)'
            );
            $stmt->execute([
                $bot_token,
                $chat_id,
                $is_enabled,
                $notify_new_order,
                $notify_support,
                $notify_partner,
                $notify_admin_status,
            ]);

            $_SESSION['message'] = 'Telegram ayarları kaydedildi.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }

    header('Location: telegram_settings.php');
    exit();
}

$page_title = 'Telegram Ayarları';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="mb-0"><i class="fab fa-telegram me-2"></i>Telegram Ayarları</h2>
            <span class="text-muted">Genel anahtar + olay bazlı bildirimler</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] === 'error' ? 'danger' : 'success' ?> d-flex align-items-center">
            <i class="fas fa-<?= $_SESSION['message_type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
            <?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        Telemetriyi tamamen kapatabilir veya yalnızca <strong>yeni sipariş</strong>, <strong>destek</strong>, <strong>bayilik</strong> ve isteğe bağlı
        <strong>panel durum değişimi</strong> için açabilirsiniz. Hatalar sunucu günlüğüne yazılır; sipariş akışı kesilmez.
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= !empty($g('is_enabled', 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_enabled">Telegram bildirimleri açık</label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_new_order" id="notify_new_order" <?= !empty($g('notify_new_order', 1)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_new_order">Yeni sipariş (vitrin + manuel)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_support" id="notify_support" <?= !empty($g('notify_support', 1)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_support">Destek talebi formu</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_partner" id="notify_partner" <?= !empty($g('notify_partner', 1)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_partner">Bayilik başvurusu</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_admin_status" id="notify_admin_status" <?= !empty($g('notify_admin_status')) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_admin_status">Panelde sipariş durumu değişince</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label for="bot_token" class="form-label">Bot Token</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-robot"></i></span>
                        <input type="text" class="form-control" id="bot_token" name="bot_token"
                               value="<?= htmlspecialchars((string) $g('bot_token')) ?>"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                        <button class="btn btn-outline-secondary" type="button" id="toggleBotToken">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="col-12">
                    <label for="chat_id" class="form-label">Chat ID</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-comments"></i></span>
                        <input type="text" class="form-control" id="chat_id" name="chat_id"
                               value="<?= htmlspecialchars((string) $g('chat_id')) ?>" placeholder="-1001234567890 veya 123456789">
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('toggleBotToken').addEventListener('click', function() {
    const tokenInput = document.getElementById('bot_token');
    const icon = this.querySelector('i');
    if (tokenInput.type === 'password') {
        tokenInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        tokenInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.getElementById('bot_token');
    if (tokenInput && tokenInput.value) tokenInput.type = 'password';
});
</script>

<?php include 'admin_footer_common.php'; ?>
