<?php

declare(strict_types=1);

/**
 * Görseller: arşiv senkronu + eksik dosya onarımı.
 * Öneri: */15 * * * * php /path/public_html/cron/media_integrity.php
 */

define('INSTALL_GUARD_SKIP', true);

$root = dirname(__DIR__);
require $root . '/db.php';
require_once $root . '/includes/media_guard.php';

media_guard_sync_uploads_to_archive();
$result = media_guard_verify_and_restore($pdo);

if (PHP_SAPI === 'cli') {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
