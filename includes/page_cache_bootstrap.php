<?php
declare(strict_types=1);

/** @param PDO $pdo */
function page_cache_bootstrap(PDO $pdo): void
{
    if (! class_exists('PageCacheService', false)) {
        require_once __DIR__ . '/cache/page_cache_service.php';
    }
    if (PageCacheService::tryServe($pdo)) {
        return;
    }
    PageCacheService::startBuffer($pdo);
}
