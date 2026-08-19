<?php
declare(strict_types=1);

/** @param PDO $pdo */
function page_cache_bootstrap(PDO $pdo): void
{
    if (! class_exists('PageCacheService', false)) {
        require_once __DIR__ . '/cache/page_cache_service.php';
    }

    $script = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    if ($script === 'index.php') {
        require_once __DIR__ . '/page_view_log.php';
        page_view_log($pdo, 'index.php');
    }

    if (PageCacheService::tryServe($pdo)) {
        return;
    }
    PageCacheService::startBuffer($pdo);
}
