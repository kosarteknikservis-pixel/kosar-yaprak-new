<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$stmt = $pdo->query('SELECT * FROM netgsm_settings WHERE id = 1');
/** @var array<string, mixed>|false $settings */
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = [
        'username' => '',
        'password' => '',
        'header' => '',
        'appkey' => '',
        'message' => '',
        'is_enabled' => 1,
        'sms_new_order_enabled' => 1,
        'sms_status_change_enabled' => 0,
        'sms_status_trigger_id' => 16,
        'message_on_status' => '',
    ];
}

$s = static function (string $key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

$statuses = $pdo->query('SELECT order_status_id, status_name FROM order_status ORDER BY order_status_id ASC')
    ->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $sms_new_order_enabled = isset($_POST['sms_new_order_enabled']) ? 1 : 0;
    $sms_status_change_enabled = isset($_POST['sms_status_change_enabled']) ? 1 : 0;
    $sms_status_trigger_id = max(1, min(99999, (int) ($_POST['sms_status_trigger_id'] ?? 16)));

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));
    $header = trim((string) ($_POST['header'] ?? ''));
    $appkey = trim((string) ($_POST['appkey'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $message_on_status = trim((string) ($_POST['message_on_status'] ?? ''));

    $err = '';

    if ($is_enabled) {
        if ($username === '' || $password === '' || $header === '') {
            $err = 'NETGSM açıkken kullanıcı adı, şifre ve mesaj başlığı zorunludur.';
        }
    }

    if ($err !== '') {
        $_SESSION['message'] = $err;
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            $sql = 'INSERT INTO netgsm_settings (
                id, username, password, header, appkey, message,
                is_enabled, sms_new_order_enabled, sms_status_change_enabled,
                sms_status_trigger_id, message_on_status
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                password = VALUES(password),
                header = VALUES(header),
                appkey = VALUES(appkey),
                message = VALUES(message),
                is_enabled = VALUES(is_enabled),
                sms_new_order_enabled = VALUES(sms_new_order_enabled),
                sms_status_change_enabled = VALUES(sms_status_change_enabled),
                sms_status_trigger_id = VALUES(sms_status_trigger_id),
                message_on_status = VALUES(message_on_status)';

            $stmtUp = $pdo->prepare($sql);
            $stmtUp->execute([
                $username,
                $password,
                $header,
                $appkey,
                $message,
                $is_enabled,
                $sms_new_order_enabled,
                $sms_status_change_enabled,
                $sms_status_trigger_id,
                $message_on_status,
            ]);

            $_SESSION['message'] = 'NETGSM ayarları kaydedildi.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }

    header('Location: netgsm_settings.php');
    exit();
}

$page_title = 'NETGSM Ayarları';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="mb-0"><i class="fas fa-sms me-2"></i>NETGSM Ayarları</h2>
            <span class="text-muted">SMS anahtarı ve hangi olayda gideceğini seçin</span>
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
        <strong>Nasıl çalışır?</strong> NETGSM’i tamamen kapatabilir veya yalnızca belirli kurallar için açabilirsiniz.
        <em>Yeni sipariş</em> SMS’i teşekkür sayfasında; <em>durum</em> SMS’i panelden sipariş durumu seçilen statüye geçince gider (ör. “Onaylandı” ID’si).
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= !empty($s('is_enabled', 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_enabled">NETGSM SMS (genel) açık</label>
                    </div>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="sms_new_order_enabled" id="sms_new_order_enabled" <?= !empty($s('sms_new_order_enabled', 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sms_new_order_enabled">Yeni siparişte müşteriye SMS (thank you)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="sms_status_change_enabled" id="sms_status_change_enabled" <?= !empty($s('sms_status_change_enabled')) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sms_status_change_enabled">Belirli sipariş durumuna geçince müşteriye SMS</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="sms_status_trigger_id" class="form-label">Tetikleyen durum</label>
                    <?php if (empty($statuses)): ?>
                        <input type="number" class="form-control" name="sms_status_trigger_id" id="sms_status_trigger_id" min="1" max="99999"
                               value="<?= (int) $s('sms_status_trigger_id', 16) ?>">
                        <div class="form-text">order_status kaydı bulunamadı; doğrudan ID girin.</div>
                    <?php else: ?>
                        <select class="form-select" name="sms_status_trigger_id" id="sms_status_trigger_id">
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= (int) $st['order_status_id'] ?>"<?= (int) $s('sms_status_trigger_id', 16) === (int) $st['order_status_id'] ? ' selected' : '' ?>>
                                    <?= htmlspecialchars((string) $st['order_status_id']) ?> — <?= htmlspecialchars((string) $st['status_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Yalnızca “durum SMS” açıksa ve sipariş <strong>bu</strong> statüye geçince gönderilir.</div>
                    <?php endif; ?>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-6">
                    <label for="username" class="form-label">Kullanıcı adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars((string) $s('username')) ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               value="<?= htmlspecialchars((string) $s('password')) ?>">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="header" class="form-label">SMS başlığı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-heading"></i></span>
                        <input type="text" class="form-control" id="header" name="header"
                               value="<?= htmlspecialchars((string) $s('header')) ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="appkey" class="form-label">AppKey (isteğe bağlı)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="text" class="form-control" id="appkey" name="appkey"
                               value="<?= htmlspecialchars((string) $s('appkey')) ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label for="message" class="form-label">Yeni sipariş mesaj şablonu</label>
                    <textarea class="form-control" id="message" name="message" rows="4"><?= htmlspecialchars((string) $s('message')) ?></textarea>
                    <div class="form-text">
                        Boş bırakırsanız sabit sipariş alındı metni kullanılır.
                        Değişkenler: <code>{customer_name}</code> <code>{order_id}</code> <code>{tracking_number}</code> <code>{products}</code> <code>{total_price}</code>
                    </div>
                </div>

                <div class="col-12">
                    <label for="message_on_status" class="form-label">Durum tetik SMS şablonu</label>
                    <textarea class="form-control" id="message_on_status" name="message_on_status" rows="3"><?= htmlspecialchars((string) $s('message_on_status')) ?></textarea>
                    <div class="form-text">“Durum SMS” açıkken zorunlu. Aynı değişkenler + boş bırakılırsa ön yüzde kısa varsayılan metin kullanılır.</div>
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
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>

<?php include 'admin_footer_common.php'; ?>
