<?php

declare(strict_types=1);

define('INSTALL_GUARD_SKIP', true);
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/gateways/OrderPaymentFinalize.php';
require_once __DIR__ . '/../includes/netgsm_customer_sms.php';

$orderId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($orderId <= 0) {
    fwrite(STDERR, "Kullanim: php tools/send_order_sms_once.php ORDER_ID\n");
    exit(1);
}

$order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
if (!$order) {
    fwrite(STDERR, "Siparis bulunamadi: #{$orderId}\n");
    exit(1);
}

netgsm_send_new_order_sms_if_enabled($pdo, $order, (string) $orderId);
echo "SMS gonderim denemesi tamamlandi: #{$orderId}\n";
