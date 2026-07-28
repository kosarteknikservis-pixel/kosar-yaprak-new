<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 2) . '/includes/admin_menu_search.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = admin_menu_search($q, 15);
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
