<?php
require 'db.php';
require_once __DIR__ . '/includes/location_service.php';

header('Content-Type: application/json; charset=utf-8');

$city_id = (int) ($_GET['city_id'] ?? 0);
if ($city_id < 1) {
    echo '[]';
    exit;
}

location_ensure_schema($pdo);
$country = location_checkout_country($pdo);
$st = $pdo->prepare(
    'SELECT d.district_id, d.district_name, d.city_id
     FROM districts d
     INNER JOIN cities c ON c.city_id = d.city_id
     WHERE d.city_id = ? AND c.country_code = ?
     ORDER BY d.district_name ASC'
);
$st->execute([$city_id, $country]);
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
