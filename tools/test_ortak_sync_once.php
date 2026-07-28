<?php

declare(strict_types=1);

require __DIR__ . '/../includes/laravel4_sync.php';

$orderId = 'TEST-' . date('YmdHis');
$ok = laravel4_sync_order([
    'external_order_id' => $orderId,
    'total_amount' => 1299.0,
    'customer_name' => 'Test Müşteri',
    'customer_phone' => '05551234567',
    'customer_city' => 'İstanbul',
    'customer_district' => 'Kadıköy',
    'customer_address' => 'Test adres',
    'payment_method' => 'Kapıda ödeme',
    'order_notes' => 'Ortak panel test',
    'site_url' => 'http://localhost/yaprak-new-panel/kosar1',
    'items' => [
        [
            'product_name' => 'Test Ürün',
            'quantity' => 1,
            'product_price' => 1299.0,
        ],
    ],
]);

echo $ok ? "OK: {$orderId}\n" : "FAIL: {$orderId}\n";
