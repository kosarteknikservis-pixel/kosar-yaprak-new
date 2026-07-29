<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/netgsm_customer_sms.php';

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
        'sms_provider' => 'mutlucell',
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

$smsVerifySettings = ['order_sms_verify_enabled' => 1, 'order_sms_front_otp_enabled' => 1];
try {
    $svRow = $pdo->query(
        'SELECT order_sms_verify_enabled, order_sms_front_otp_enabled FROM checkout_module_settings WHERE id = 1 LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if (is_array($svRow)) {
        $smsVerifySettings = array_merge($smsVerifySettings, $svRow);
    }
} catch (Throwable $e) {
    /* ignore */
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'save'));

    if ($action === 'sms_test' || $action === 'sms_api_test') {
        require_once dirname(__DIR__) . '/includes/transactional_sms.php';

        $testPhone = preg_replace('/\D+/', '', (string) ($_POST['test_phone'] ?? ''));
        if (strlen($testPhone) === 11 && str_starts_with($testPhone, '05')) {
            $testPhone = substr($testPhone, 1);
        }
        if (strlen($testPhone) !== 10 || $testPhone[0] !== '5') {
            $_SESSION['message'] = 'Test telefonu 10 haneli olmalı (5XXXXXXXXX).';
            $_SESSION['message_type'] = 'error';
        } else {
            $cfg = is_array($settings) ? $settings : [];
            $cfg['username'] = trim((string) ($_POST['username'] ?? $cfg['username'] ?? ''));
            $cfg['password'] = trim((string) ($_POST['password'] ?? $cfg['password'] ?? ''));
            $cfg['header'] = trim((string) ($_POST['header'] ?? $cfg['header'] ?? ''));
            $cfg['appkey'] = trim((string) ($_POST['appkey'] ?? $cfg['appkey'] ?? ''));
            $cfg['is_enabled'] = 1;
            $provider = strtolower(trim((string) ($_POST['sms_provider'] ?? $cfg['sms_provider'] ?? 'mutlucell')));
            $cfg['sms_provider'] = in_array($provider, ['mutlucell', 'netgsm'], true) ? $provider : 'mutlucell';

            $msg = $action === 'sms_api_test'
                ? 'API TEST ' . date('Y-m-d H:i:s')
                : 'Test mesaji — ' . date('d.m.Y H:i');

            $ok = sendTransactionalSms($testPhone, $msg, $cfg);
            $resp = smsLastResponseGet();
            $err = smsLastErrorGet();

            if ($ok) {
                $_SESSION['message'] = 'Test SMS gönderildi.'
                    . ($action === 'sms_api_test' && $resp !== '' ? ' Yanıt: ' . $resp : '');
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Test SMS gönderilemedi: ' . ($err !== '' ? $err : 'bilinmeyen hata')
                    . ($resp !== '' ? ' | Yanıt: ' . $resp : '');
                $_SESSION['message_type'] = 'error';
            }
        }

        header('Location: netgsm_settings.php');
        exit();
    }

    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $sms_provider = strtolower(trim((string) ($_POST['sms_provider'] ?? 'mutlucell')));
    if (! in_array($sms_provider, ['mutlucell', 'netgsm'], true)) {
        $sms_provider = 'mutlucell';
    }
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
            $err = 'SMS açıkken kullanıcı adı, API key/şifre ve mesaj başlığı zorunludur.';
        }
    }

    if ($err !== '') {
        $_SESSION['message'] = $err;
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            $sql = 'INSERT INTO netgsm_settings (
                id, username, password, header, appkey, message,
                is_enabled, sms_provider, sms_new_order_enabled, sms_status_change_enabled,
                sms_status_trigger_id, message_on_status
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                password = VALUES(password),
                header = VALUES(header),
                appkey = VALUES(appkey),
                message = VALUES(message),
                is_enabled = VALUES(is_enabled),
                sms_provider = VALUES(sms_provider),
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
                $sms_provider,
                $sms_new_order_enabled,
                $sms_status_change_enabled,
                $sms_status_trigger_id,
                $message_on_status,
            ]);

            $orderSmsVerifyEnabled = isset($_POST['order_sms_verify_enabled']) ? 1 : 0;
            $orderSmsFrontOtpEnabled = isset($_POST['order_sms_front_otp_enabled']) ? 1 : 0;
            try {
                $pdo->prepare(
                    'UPDATE checkout_module_settings SET order_sms_verify_enabled = ?, order_sms_front_otp_enabled = ? WHERE id = 1'
                )->execute([$orderSmsVerifyEnabled, $orderSmsFrontOtpEnabled]);
            } catch (Throwable $e) {
                /* kolon yoksa feature_schema sonraki istekte ekler */
            }

            $_SESSION['message'] = 'SMS ayarları kaydedildi.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }

    header('Location: netgsm_settings.php');
    exit();
}

$page_title = 'SMS Ayarları';
include 'admin_header.php';
$currentProvider = strtolower(trim((string) $s('sms_provider', 'mutlucell')));
if (! in_array($currentProvider, ['mutlucell', 'netgsm'], true)) {
    $currentProvider = 'mutlucell';
}
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="mb-0"><i class="fas fa-sms me-2"></i>SMS Ayarları</h2>
            <span class="text-muted">Mutlucell veya NETGSM — sipariş bildirimleri</span>
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
        <strong>Nasıl çalışır?</strong> Sağlayıcı olarak <strong>Mutlucell</strong> veya <strong>NETGSM</strong> seçin.
        <em>Yeni sipariş</em> SMS’i teşekkür sayfasında; <em>durum</em> SMS’i panelden sipariş durumu seçilen statüye geçince gider.
        Mutlucell için şifre alanına <strong>API key</strong> yazın.
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3" id="sms-settings-form">
                <input type="hidden" name="action" value="save">

                <div class="col-md-6">
                    <label for="sms_provider" class="form-label">SMS sağlayıcı</label>
                    <select class="form-select" name="sms_provider" id="sms_provider">
                        <option value="mutlucell"<?= $currentProvider === 'mutlucell' ? ' selected' : '' ?>>Mutlucell</option>
                        <option value="netgsm"<?= $currentProvider === 'netgsm' ? ' selected' : '' ?>>NETGSM</option>
                    </select>
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= !empty($s('is_enabled', 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_enabled">SMS gönderimi açık</label>
                    </div>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-12">
                    <h5 class="mb-2"><i class="fas fa-shield-alt me-1"></i> Sipariş SMS doğrulama</h5>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="order_sms_verify_enabled" id="order_sms_verify_enabled" <?= ! empty($smsVerifySettings['order_sms_verify_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="order_sms_verify_enabled">Kapıda ödeme / havale için SMS doğrulama açık</label>
                    </div>
                    <div class="form-text">PayTR ve online kart ödemelerinde otomatik kapalıdır.</div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="order_sms_front_otp_enabled" id="order_sms_front_otp_enabled" <?= ! empty($smsVerifySettings['order_sms_front_otp_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="order_sms_front_otp_enabled">Form öncesi OTP (5 dk, siparişten önce)</label>
                    </div>
                    <div class="form-text">Kapalıysa yalnızca sipariş sonrası OTP + link gönderilir.</div>
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
                    <label for="username" class="form-label" id="label-username">Kullanıcı adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars((string) $s('username')) ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label" id="label-password">API key / şifre</label>
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

                <div class="col-md-6" id="appkey-wrap">
                    <label for="appkey" class="form-label">AppKey (NETGSM, isteğe bağlı)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="text" class="form-control" id="appkey" name="appkey"
                               value="<?= htmlspecialchars((string) $s('appkey')) ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label for="message" class="form-label">Yeni sipariş mesaj şablonu</label>
                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="<?= htmlspecialchars(netgsm_default_new_order_message(), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $s('message')) ?></textarea>
                    <div class="form-text">
                        Test için kısa metin (ör. «123») yazmayın; boş veya geçersiz şablonda varsayılan metin kullanılır.
                        Değişkenler: <code>{customer_name}</code> <code>{order_id}</code> <code>{tracking_number}</code> <code>{products}</code> <code>{total_price}</code>
                    </div>
                </div>

                <div class="col-12">
                    <label for="message_on_status" class="form-label">Durum tetik SMS şablonu (kargoya verildi)</label>
                    <textarea class="form-control" id="message_on_status" name="message_on_status" rows="3" placeholder="<?= htmlspecialchars(netgsm_default_status_message(), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $s('message_on_status')) ?></textarea>
                    <div class="form-text">“Durum SMS” açıkken zorunlu. Aynı değişkenler + boş bırakılırsa ön yüzde kısa varsayılan metin kullanılır.</div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>

            <hr class="my-4">

            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="test_phone" class="form-label">Test telefonu</label>
                    <input type="text" class="form-control" id="test_phone" placeholder="5XXXXXXXXX" maxlength="11">
                    <div class="form-text">10 hane, 5 ile başlamalı</div>
                </div>
                <div class="col-md-8 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="submitSmsTest('sms_test')">
                        <i class="fas fa-paper-plane me-1"></i> Test SMS gönder
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="submitSmsTest('sms_api_test')">
                        <i class="fas fa-code me-1"></i> API ham yanıt testi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncSmsProviderUi() {
    var provider = document.getElementById('sms_provider').value;
    var appkeyWrap = document.getElementById('appkey-wrap');
    var labelPass = document.getElementById('label-password');
    if (provider === 'mutlucell') {
        appkeyWrap.style.display = 'none';
        labelPass.textContent = 'Mutlucell API key';
    } else {
        appkeyWrap.style.display = '';
        labelPass.textContent = 'NETGSM şifre';
    }
}
document.getElementById('sms_provider').addEventListener('change', syncSmsProviderUi);
syncSmsProviderUi();

function submitSmsTest(action) {
    var main = document.getElementById('sms-settings-form');
    var phone = document.getElementById('test_phone').value;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'netgsm_settings.php';
    ['username','password','header','appkey','sms_provider'].forEach(function(name) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = main.querySelector('[name=' + name + ']').value;
        form.appendChild(input);
    });
    var act = document.createElement('input');
    act.type = 'hidden';
    act.name = 'action';
    act.value = action;
    form.appendChild(act);
    var tel = document.createElement('input');
    tel.type = 'hidden';
    tel.name = 'test_phone';
    tel.value = phone;
    form.appendChild(tel);
    document.body.appendChild(form);
    form.submit();
}

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
