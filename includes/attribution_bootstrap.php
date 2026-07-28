<?php

declare(strict_types=1);

/**
 * Vitrin sayfalarında UTM / tıklama kimliği oturumunu başlatır (admin hariç).
 */
if (defined('ATTRIBUTION_BOOTSTRAP_DONE')) {
    return;
}

define('ATTRIBUTION_BOOTSTRAP_DONE', true);

$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (str_starts_with($script, 'admin') || str_contains($scriptPath, '/admin/') || str_contains($scriptPath, '/phx/')) {
    return;
}

require_once dirname(__DIR__).'/tracking.php';
