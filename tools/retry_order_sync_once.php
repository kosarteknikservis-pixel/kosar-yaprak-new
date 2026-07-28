<?php

declare(strict_types=1);

define('INSTALL_GUARD_SKIP', true);
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/laravel4_sync.php';

$orderId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($orderId <= 0) {
    fwrite(STDERR, "Kullanim: php tools/retry_order_sync_once.php ORDER_ID\n");
    exit(1);
}

$ok = laravel4_sync_pending_order($orderId, $pdo);
echo $ok ? "OK: #{$orderId}\n" : "FAIL: #{$orderId}\n";
exit($ok ? 0 : 1);
