<?php
declare(strict_types=1);

/**
 * Çarkıfelek — tarayıcıda JS kullanmadan form POST ile çevir (panel ayarları aynı).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/db.php';
require __DIR__ . '/includes/carkifelek_spin_core.php';

/** @var PDO $pdo */

$ret = isset($_POST['cf_return']) ? carkifelek_sanitize_return_path((string) $_POST['cf_return']) : '/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['spin'])) {
    header('Location: ' . $ret, true, 302);
    exit;
}

$t = $_SESSION['carkifelek_csrf'] ?? '';

if ($t === '' || !hash_equals($t, trim((string) ($_POST['csrf'] ?? '')))) {
    $_SESSION['carkifelek_flash'] = ['type' => 'limit', 'message' => 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.'];

    header('Location: ' . $ret, true, 302);
    exit;
}

$res = carkifelek_try_spin_and_log($pdo);

if (($res['status'] ?? '') === 'blocked') {

    $_SESSION['carkifelek_flash'] = ['type' => 'limit', 'message' => (string) ($res['message'] ?? '')];

    header('Location: ' . $ret, true, 302);
    exit;
}

$n = isset($res['oduller']) && is_array($res['oduller']) ? count($res['oduller']) : 0;

$n = max(4, $n);

$deg = carkifelek_rotation_degrees((int) $res['seg_index'], $n);

$_SESSION['carkifelek_odul'] = (string) $res['odul'];
$_SESSION['carkifelek_flash'] = [
    'type' => 'won',
    'rotate_deg' => $deg,
    'odul' => (string) $res['odul'],
];

$exp = time() + 86400;
setcookie('carkifelek_kullanildi', '1', [
    'expires' => $exp,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || (string) $_SERVER['HTTPS'] === '1'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

header('Location: ' . $ret, true, 302);
header('Cache-Control: no-store');

exit;
