<?php
declare(strict_types=1);

/**
 * Çarkıfelek — JSON istemcisi (manuel / eski araçlar). JS olmadan sayfa için carkifelek_post.php kullanılır.
 */
@ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/includes/carkifelek_spin_core.php';
    require_once dirname(__DIR__) . '/db.php';
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Sunucu hatası'], JSON_UNESCAPED_UNICODE);

    exit;
}

/** @var PDO $pdo */

try {
    $limit_msg = carkifelek_limit_msg($pdo);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_POST['check_limit_only'])) {
        $ip = carkifelek_client_ip();
        if (carkifelek_ip_blocked($pdo, $ip)) {
            echo json_encode(['status' => 'error', 'message' => $limit_msg], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if (!isset($_POST['spin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = carkifelek_try_spin_and_log($pdo);

    if (($res['status'] ?? '') === 'blocked') {
        echo json_encode(['status' => 'error', 'message' => (string) ($res['message'] ?? $limit_msg)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $n = isset($res['oduller']) && is_array($res['oduller']) ? count($res['oduller']) : 0;
    $n = max(4, $n);

    echo json_encode([
        'status' => 'success',
        'odul' => (string) $res['odul'],
        'indirim' => (int) $res['indirim'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Sunucu hatası'], JSON_UNESCAPED_UNICODE);
}
