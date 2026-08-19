<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/page_view_log.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pvPagesIn = page_view_stats_sql_in();
$row = $pdo->query(
    "SELECT
        COUNT(DISTINCT CASE WHEN visit_time >= NOW() - INTERVAL 5 MINUTE THEN ip_address END) AS live_u,
        COUNT(CASE WHEN visit_time >= NOW() - INTERVAL 5 MINUTE THEN 1 END) AS live_t,
        COUNT(DISTINCT CASE WHEN HOUR(visit_time) = HOUR(NOW()) AND DATE(visit_time) = CURDATE() THEN ip_address END) AS hour_u,
        COUNT(CASE WHEN HOUR(visit_time) = HOUR(NOW()) AND DATE(visit_time) = CURDATE() THEN 1 END) AS hour_t
     FROM page_views
     WHERE page_name IN ($pvPagesIn)"
)->fetch(PDO::FETCH_ASSOC) ?: [];

echo json_encode([
    'ok' => true,
    'live_unique' => (int) ($row['live_u'] ?? 0),
    'live_total' => (int) ($row['live_t'] ?? 0),
    'hourly_unique' => (int) ($row['hour_u'] ?? 0),
    'hourly_total' => (int) ($row['hour_t'] ?? 0),
]);
