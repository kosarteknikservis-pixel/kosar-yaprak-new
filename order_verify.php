<?php

declare(strict_types=1);

require 'db.php';
require_once __DIR__ . '/includes/app_url.php';
require_once __DIR__ . '/includes/order_verification.php';

$token = trim((string) ($_GET['t'] ?? ''));
$result = ov_verify_by_token($pdo, $token, 'link');

if (! empty($result['siparis_id'])) {
    $orderId = (int) $result['siparis_id'];
    if ($result['ok']) {
        $flag = ($result['reason'] ?? '') === 'already' ? 'already' : 'ok';
        header('Location: ' . app_url('thankyou', ['order_id' => $orderId, 'verify' => $flag], $pdo));
        exit;
    }
    if (($result['reason'] ?? '') === 'expired') {
        header('Location: ' . app_url('thankyou', ['order_id' => $orderId, 'verify' => 'expired'], $pdo));
        exit;
    }
}

header('Location: ' . app_url('thankyou', ['verify' => 'no'], $pdo));
exit;
