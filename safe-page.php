<?php
declare(strict_types=1);

/**
 * Cloaker güvenli sayfa — blog tarzı landing (bot / inceleme trafiği).
 */
if (! isset($pdo) || ! ($pdo instanceof PDO)) {
    require __DIR__ . '/db.php';
}

require_once __DIR__ . '/includes/safe_page_service.php';

SafePageService::render($pdo);
