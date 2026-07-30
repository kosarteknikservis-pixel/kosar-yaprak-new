<?php
declare(strict_types=1);
require dirname(__DIR__) . '/db.php';
$ids = $pdo->query('SELECT order_id, payment_status, gateway_code, payment_method_id, panel_synced, created_at FROM orders WHERE order_id >= 10 ORDER BY order_id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($ids as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
