<?php
declare(strict_types=1);

/**
 * Reklam geçidi — cloaker muaf; kendi filtre mantığını çalıştırır.
 * URL: /go.php?k=TOKEN (panelden otomatik üretilir)
 */
require __DIR__ . '/db.php';

if (!class_exists('CloakerService', false)) {
    require_once __DIR__ . '/includes/CloakerService.php';
}

$token = isset($_GET['k']) && is_scalar($_GET['k']) ? (string) $_GET['k'] : '';
CloakerService::runGateway($pdo, $token);
