<?php
require_once dirname(__DIR__) . '/includes/admin_session.php';

admin_session_start();
require '../db.php';
require_once dirname(__DIR__) . '/includes/admin_rbac.php';

function check_rate_limit($pdo, $ip_address) {
    $stmt = $pdo->prepare('SELECT * FROM failed_logins WHERE ip_address = ?');
    $stmt->execute([$ip_address]);
    $record = $stmt->fetch();

    if ($record && $record['attempts'] >= 8) {
        $_SESSION['error'] = 'Çok fazla hatalı deneme. Lütfen bekleyin.';
        return false;
    }
    return true;
}

function log_failed_login($pdo, $ip_address) {
    $stmt = $pdo->prepare('SELECT * FROM failed_logins WHERE ip_address = ?');
    $stmt->execute([$ip_address]);
    $record = $stmt->fetch();

    if ($record) {
        $stmt = $pdo->prepare('UPDATE failed_logins SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address = ?');
        $stmt->execute([$ip_address]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO failed_logins (ip_address, attempts) VALUES (?, 1)');
        $stmt->execute([$ip_address]);
    }
}

function get_ip_location($ip_address) {
    $apiKey = 'bbc9ec29f38db7';
    $url = "http://ipinfo.io/{$ip_address}/json?token={$apiKey}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode((string) $response, true);
}

function log_login_attempt($pdo, $user_id, $username, $ip_address, $country, $city, $success, $message) {
    $ok = ($success === true || $success === 1 || $success === '1') ? 1 : 0;
    $stmt = $pdo->prepare('INSERT INTO login_logs (user_id, username, ip_address, country, city, login_time, success, message) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)');
    $stmt->execute([$user_id, $username, $ip_address, $country, $city, $ok, $message]);
}

function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    return bin2hex(random_bytes(32));
}

function verify_csrf_token($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

$userIP = admin_client_ip();
$rateLimited = false;

try {
    $location_info = get_ip_location($userIP);
    $country = $location_info['country'] ?? 'Bilinmiyor';
    $city = $location_info['city'] ?? 'Bilinmiyor';
} catch (Exception $e) {
    $country = 'Bilinmiyor';
    $city = 'Bilinmiyor';
}

if (!check_rate_limit($pdo, $userIP)) {
    $rateLimited = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        log_login_attempt($pdo, null, $username, $userIP, $country, $city, false, 'Geçersiz token.');
        $_SESSION['error'] = 'Geçersiz oturum jetonu. Sayfayı yenileyin.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_id'] = $user['user_id'];
            $_SESSION['admin_profile_image'] = $user['profile_image'] ?? 'default_profile.png';

            try {
                admin_session_refresh_from_db($pdo, (int) $user['user_id']);
            } catch (Throwable $e) {
                $_SESSION['admin_is_super'] = true;
                $_SESSION['admin_menu_permissions'] = '[]';
            }

            $_SESSION['failed_attempts'] = 0;

            log_login_attempt($pdo, $user['user_id'], $username, $userIP, $country, $city, true, 'Giriş başarılı');

            $stmt = $pdo->prepare('DELETE FROM failed_logins WHERE ip_address = ?');
            $stmt->execute([$userIP]);

            header('Location: index.php');
            exit();
        }

        log_login_attempt($pdo, null, $username, $userIP, $country, $city, false, 'Hatalı kullanıcı adı veya şifre');
        log_failed_login($pdo, $userIP);
        $_SESSION['failed_attempts'] = (int) ($_SESSION['failed_attempts'] ?? 0) + 1;
        $_SESSION['error'] = 'Kimlik doğrulama başarısız.';
    }
}

$loginError = $_SESSION['error'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

$postedUser = isset($_POST['username']) ? htmlspecialchars((string) $_POST['username'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Güvenli Erişim — phxcore0</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="login-page">
    <div class="login-aurora" aria-hidden="true">
        <div class="login-aurora__blob login-aurora__blob--1"></div>
        <div class="login-aurora__blob login-aurora__blob--2"></div>
        <div class="login-aurora__blob login-aurora__blob--3"></div>
    </div>

    <main class="login-shell">
        <div class="login-card">
            <div class="login-status-bar">
                <span class="login-status-bar__dot" aria-hidden="true"></span>
                <span>Secure channel · TLS ready</span>
                <span>LOCKED</span>
            </div>

            <header class="login-header">
                <div class="login-emblem" aria-hidden="true">
                    <i class="fas fa-bug"></i>
                </div>
                <p class="login-brand-name">phxcore0</p>
                <span class="login-bounty-badge"><i class="fas fa-shield-virus"></i> Bug Bounty</span>
                <h1>Yetkili Erişim</h1>
                <p class="login-tagline">Kimlik doğrulama gerekli</p>
            </header>

            <?php if (isset($_GET['installed']) && $_GET['installed'] === '1'): ?>
                <div class="login-alert" role="alert" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857;">
                    <i class="fas fa-check-circle"></i>
                    <span>Kurulum tamamlandı. Panele giriş yapabilirsiniz.</span>
                </div>
            <?php elseif ($rateLimited): ?>
                <div class="login-alert" role="alert">
                    <i class="fas fa-ban"></i>
                    <span>Çok fazla hatalı deneme. Güvenlik kilidi aktif — lütfen bekleyin.</span>
                </div>
            <?php elseif ($loginError): ?>
                <div class="login-alert" role="alert">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="login-form" autocomplete="on"<?= $rateLimited ? ' style="opacity:0.45;pointer-events:none"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-field">
                    <label for="username">
                        <i class="fas fa-user-secret"></i>
                        Operatör ID
                    </label>
                    <input type="text" id="username" name="username" required
                           placeholder="kullanici_adi" autocomplete="username"
                           value="<?= $postedUser ?>">
                </div>

                <div class="form-field">
                    <label for="password">
                        <i class="fas fa-key"></i>
                        Parola
                    </label>
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••••" autocomplete="current-password">
                </div>

                <button type="submit" class="login-submit">
                    <i class="fas fa-fingerprint"></i>
                    Oturum Aç
                </button>
            </form>

            <div class="login-ip">
                NODE :: <?= htmlspecialchars($userIP, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <footer class="login-footer">
                <p>phxcore0 · Yönetim Konsolu</p>
                <span class="login-warn">Yetkisiz erişim denemeleri kayıt altına alınır.</span>
            </footer>
        </div>
    </main>
</body>
</html>
