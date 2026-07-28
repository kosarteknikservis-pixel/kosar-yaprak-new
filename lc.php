<?php
declare(strict_types=1);

/**
 * Geçit Merkezi — reklam linki API (site cloaker’dan bağımsız).
 * Kullanım: /lc?c=TOKEN veya /lc.php?c=TOKEN
 */
require __DIR__ . '/db.php';

require_once __DIR__ . '/includes/link_cloak/LinkCloakService.php';

$token = isset($_GET['c']) && is_scalar($_GET['c']) ? (string) $_GET['c'] : '';
LinkCloakService::handle($pdo, $token);
