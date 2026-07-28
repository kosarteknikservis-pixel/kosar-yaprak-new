<?php
declare(strict_types=1);

/**
 * @param PDO $pdo
 */
function cloaker_bootstrap(PDO $pdo): void
{
    if (!class_exists('CloakerService', false)) {
        require_once __DIR__ . '/CloakerService.php';
    }
    CloakerService::run($pdo);
}
