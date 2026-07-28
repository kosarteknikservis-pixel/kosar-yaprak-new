<?php

declare(strict_types=1);

/**
 * Panel senkron RETRY (Option B) — panel kapalıyken kaçan (panel_synced=0) siparişleri
 * tekrar panele gönderir. Idempotent: panelde var olan sipariş atlanır (unique index).
 *
 * OS cron örnekleri:
 *   Siparişler (3 dk):  *_/3 * * * * /usr/bin/php /path/to/3dhesap/cron/panel_sync_retry.php orders >> /path/to/3dhesap/cron/retry.log 2>&1
 *   Formlar   (4 dk):  *_/4 * * * * /usr/bin/php /path/to/3dhesap/cron/panel_sync_retry.php forms  >> /path/to/3dhesap/cron/retry.log 2>&1
 *
 * Kullanım: php cron/panel_sync_retry.php [orders|forms|all] [limit]
 */

define('INSTALL_GUARD_SKIP', true);

require __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/laravel4_sync.php';

if (! isset($pdo) || ! ($pdo instanceof PDO)) {
    fwrite(STDERR, "DB baglantisi yok\n");
    exit(1);
}

$mode = isset($argv[1]) ? strtolower((string) $argv[1]) : 'orders';
$limit = isset($argv[2]) ? (int) $argv[2] : 100;

if ($mode === 'orders' || $mode === 'all') {
    $res = laravel4_retry_unsynced_orders($pdo, $limit);
    echo date('c') . ' orders-retry ' . json_encode($res, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

if ($mode === 'forms' || $mode === 'all') {
    $res = laravel4_retry_unsynced_forms($pdo, $limit);
    echo date('c') . ' forms-retry ' . json_encode($res, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

exit(0);
