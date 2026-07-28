<?php
require '../db.php';
require 'auth.php';

// Mevcut ayarları çek
$stmt = $pdo->query('SELECT * FROM smtp_settings WHERE id = 1');
$smtp_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Varsayılan değerler
if (!$smtp_settings) {
    $smtp_settings = [
        'is_enabled' => 0,
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => '',
        'encryption' => '',
        'charset' => 'UTF-8',
        'sender_email' => '',
        'sender_name' => ''
    ];
}

// SMTP ayarlarını güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $host = trim($_POST['host'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $port = (int) ($_POST['port'] ?? 0);
    $encryption = trim($_POST['encryption'] ?? '');
    $charset = trim($_POST['charset'] ?? 'UTF-8');
    $sender_email = trim($_POST['sender_email'] ?? '');
    $sender_name = trim($_POST['sender_name'] ?? '');

    if ($is_enabled === 1) {
        if ($host === '' || $username === '' || $password === '' || $port < 1 || $encryption === '' || $sender_email === '' || $sender_name === '') {
            $_SESSION['message'] = 'SMTP açıkken tüm alanlar zorunludur!';
            $_SESSION['message_type'] = 'error';
        } elseif (!filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = 'Geçerli bir e-posta adresi giriniz!';
            $_SESSION['message_type'] = 'error';
        } elseif ($port < 1 || $port > 65535) {
            $_SESSION['message'] = 'Port numarası 1-65535 arasında olmalıdır!';
            $_SESSION['message_type'] = 'error';
        } elseif (!in_array(strtolower($encryption), ['tls', 'ssl'], true)) {
            $_SESSION['message'] = 'Şifreleme türü TLS veya SSL olmalıdır!';
            $_SESSION['message_type'] = 'error';
        }
    }

    if (!isset($_SESSION['message_type']) || $_SESSION['message_type'] !== 'error') {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO smtp_settings (id, is_enabled, host, username, password, port, encryption, charset, sender_email, sender_name)
                 VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), host = VALUES(host), username = VALUES(username),
                     password = IF(VALUES(password) = "", password, VALUES(password)),
                     port = VALUES(port), encryption = VALUES(encryption), charset = VALUES(charset),
                     sender_email = VALUES(sender_email), sender_name = VALUES(sender_name)'
            );
            $stmt->execute([$is_enabled, $host, $username, $password, $port, $encryption, $charset, $sender_email, $sender_name]);

            $_SESSION['message'] = $is_enabled ? 'SMTP ayarları kaydedildi (açık).' : 'SMTP kapatıldı — e-posta gönderilmez.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }

    header('Location: admin_smtp_settings.php');
    exit();
}

$page_title = 'SMTP Ayarları';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-envelope me-2"></i>SMTP Ayarları</h2>
            <span class="text-muted">E-posta gönderim ayarlarını yönetin</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] === 'error' ? 'danger' : 'success' ?> d-flex align-items-center">
            <i class="fas fa-<?= $_SESSION['message_type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Önemli:</strong> Gireceğiniz e-posta adreslerinin önceden oluşturulması gerekmektedir. Hosting sağlayıcınızdan SMTP bilgilerini alınız.
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3" id="smtpForm">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1"
                               <?= !empty($smtp_settings['is_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_enabled"><strong>SMTP gönderimini aç</strong></label>
                    </div>
                    <div class="form-text">Kapalıyken destek cevapları yalnızca panelde kaydedilir; e-posta gitmez.</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="host" class="form-label">SMTP Host</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-server"></i></span>
                        <input type="text" class="form-control" id="host" name="host"
                               value="<?= htmlspecialchars($smtp_settings['host']) ?>"
                               placeholder="mail.siteadi.com" required>
                    </div>
                    <div class="form-text">Örnek: mail.siteadi.com veya webmail.siteadi.com</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="username" class="form-label">SMTP Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="email" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($smtp_settings['username']) ?>"
                               placeholder="info@siteadi.com" required>
                    </div>
                    <div class="form-text">E-posta adresinizi girin</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="password" class="form-label">SMTP Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               value="<?= htmlspecialchars($smtp_settings['password']) ?>"
                               placeholder="E-posta şifreniz" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">E-posta hesabınızın şifresi</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="port" class="form-label">SMTP Port</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-plug"></i></span>
                        <input type="number" class="form-control" id="port" name="port"
                               value="<?= htmlspecialchars($smtp_settings['port']) ?>"
                               min="1" max="65535" placeholder="587" required>
                    </div>
                    <div class="form-text">Genellikle 587 (TLS) veya 465 (SSL)</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="encryption" class="form-label">Şifreleme</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                        <select class="form-select" id="encryption" name="encryption" required>
                            <option value="">Şifreleme seçin</option>
                            <option value="tls" <?= $smtp_settings['encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= $smtp_settings['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                    <div class="form-text">TLS (port 587) veya SSL (port 465)</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="charset" class="form-label">Karakter Seti</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-font"></i></span>
                        <input type="text" class="form-control" id="charset" name="charset"
                               value="<?= htmlspecialchars($smtp_settings['charset']) ?>"
                               placeholder="UTF-8">
                    </div>
                    <div class="form-text">Genellikle UTF-8 kullanılır</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="sender_email" class="form-label">Gönderen E-posta</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-paper-plane"></i></span>
                        <input type="email" class="form-control" id="sender_email" name="sender_email"
                               value="<?= htmlspecialchars($smtp_settings['sender_email']) ?>"
                               placeholder="noreply@siteadi.com" required>
                    </div>
                    <div class="form-text">Çıkış yapılacak e-posta adresi</div>
                </div>

                <div class="col-md-6 smtp-field">
                    <label for="sender_name" class="form-label">Gönderen Adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                        <input type="text" class="form-control" id="sender_name" name="sender_name"
                               value="<?= htmlspecialchars($smtp_settings['sender_name']) ?>"
                               placeholder="ABC Şirketi" required>
                    </div>
                    <div class="form-text">E-postalarda görünecek gönderen adı</div>
                </div>

                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-lightbulb me-2"></i>Yaygın SMTP Ayarları</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Gmail</h6>
                                    <ul class="small mb-0">
                                        <li>Host: smtp.gmail.com</li>
                                        <li>Port: 587 (TLS) / 465 (SSL)</li>
                                        <li>Şifreleme: TLS / SSL</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Outlook/Hotmail</h6>
                                    <ul class="small mb-0">
                                        <li>Host: smtp-mail.outlook.com</li>
                                        <li>Port: 587</li>
                                        <li>Şifreleme: TLS</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card bg-warning bg-opacity-10">
                        <div class="card-body">
                            <h6 class="card-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Önemli Notlar</h6>
                            <ul class="mb-0">
                                <li>Gmail kullanıyorsanız "2 Adımlı Doğrulama" ve "Uygulama Şifreleri" aktif olmalıdır</li>
                                <li>Hosting sağlayıcınızdan SMTP bilgilerini doğrulayın</li>
                                <li>Port 587 genellikle TLS, port 465 genellikle SSL kullanır</li>
                                <li>Test e-postası göndererek ayarları doğrulayın</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Şifre göster/gizle
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Sayfa yüklendiğinde şifreyi gizli yap
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    if (passwordInput && passwordInput.value) {
        passwordInput.type = 'password';
    }
    const toggle = document.getElementById('is_enabled');
    const fields = document.querySelectorAll('.smtp-field input, .smtp-field select');
    function syncSmtpRequired() {
        const on = toggle && toggle.checked;
        fields.forEach(el => { el.required = !!on; });
    }
    if (toggle) {
        toggle.addEventListener('change', syncSmtpRequired);
        syncSmtpRequired();
    }
});
</script>

<?php include 'admin_footer_common.php'; ?>
