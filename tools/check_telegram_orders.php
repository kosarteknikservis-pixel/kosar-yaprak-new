<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/telegram.php';

$orders = $pdo->query(
    'SELECT o.order_id, o.customer_name, o.customer_phone, o.payment_method_id, o.gateway_code,
            o.payment_status, o.order_status_id, o.created_at, pm.method_name
     FROM orders o
     LEFT JOIN payment_methods pm ON pm.payment_method_id = o.payment_method_id
     ORDER BY o.order_id DESC LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);

$tg = telegram_load_settings($pdo);
$tgSummary = [
    'is_enabled' => $tg['is_enabled'] ?? null,
    'notify_new_order' => $tg['notify_new_order'] ?? null,
    'notify_admin_status' => $tg['notify_admin_status'] ?? null,
    'has_token' => trim((string) ($tg['bot_token'] ?? '')) !== '',
    'has_chat_id' => trim((string) ($tg['chat_id'] ?? '')) !== '',
];

echo "TELEGRAM_SETTINGS " . json_encode($tgSummary, JSON_UNESCAPED_UNICODE) . PHP_EOL;

foreach ($orders as $o) {
    echo "ORDER " . json_encode($o, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$test = sendTelegramNotification($pdo, 'new_order', '🔧 Telegram test — sipariş bildirimi kontrolü (' . date('Y-m-d H:i:s') . ')');
echo "TEST_SEND " . $test . PHP_EOL;

$all = $pdo->query('SELECT order_id, payment_status, gateway_code, payment_method_id, created_at FROM orders ORDER BY order_id')->fetchAll(PDO::FETCH_ASSOC);
echo "ALL_ORDERS_COUNT " . count($all) . PHP_EOL;
foreach ($all as $row) {
    echo 'ALL ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
