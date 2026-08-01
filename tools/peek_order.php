<?php
declare(strict_types=1);
require dirname(__DIR__) . '/db.php';
$id = isset($argv[1]) ? (int) $argv[1] : 31;
$st = $pdo->prepare(
    'SELECT o.*, pm.method_name, pm.gateway_code
     FROM orders o LEFT JOIN payment_methods pm ON pm.payment_method_id = o.payment_method_id
     WHERE o.order_id = ?'
);
$st->execute([$id]);
echo json_encode($st->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
