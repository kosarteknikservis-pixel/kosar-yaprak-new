<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/gateways/PaytrGateway.php';
require_once dirname(__DIR__) . '/includes/gateways/OrderPaymentFinalize.php';
require_once dirname(__DIR__) . '/includes/app_log.php';

$verified = (new PaytrGateway($pdo))->verifyCallback($_POST);
if (!$verified['ok']) {
    app_log('payment', 'PayTR callback FAIL: ' . ($verified['error'] ?? ''));
    http_response_code(400);
    echo 'FAIL';
    exit;
}

$merchantOid = (string) $verified['merchant_oid'];
$orderId = OrderPaymentFinalize::orderIdFromMerchantOid($merchantOid);
if ($orderId < 1) {
    app_log('payment', 'PayTR callback: geçersiz merchant_oid ' . $merchantOid);
    echo 'OK';
    exit;
}

$order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
if (!$order) {
    app_log('payment', 'PayTR callback: sipariş yok #' . $orderId);
    echo 'OK';
    exit;
}

if (($order['payment_status'] ?? '') === 'paid') {
    echo 'OK';
    exit;
}

if (($verified['status'] ?? '') === 'success') {
    OrderPaymentFinalize::markPaymentPaid($pdo, $orderId, 'paytr', $merchantOid);
    OrderPaymentFinalize::afterOrderConfirmed($pdo, $orderId, [
        'customer_name' => $order['customer_name'],
        'customer_phone' => $order['customer_phone'],
        'customer_address' => $order['customer_address'],
        'customer_city' => $order['customer_city'],
        'customer_district' => $order['customer_district'],
        'order_notes' => $order['order_notes'] ?? '',
        'payment_method_id' => $order['payment_method_id'],
        'product_id' => $order['product_id'],
        'product' => ['product_price' => $order['product_price']],
        'selected_variants' => [],
        'reklam' => $order['reklam'] ?? '',
        'source' => $order['source'] ?? '',
        'utm_capture_on' => 1,
        'invoice_vkn' => $order['invoice_vkn'] ?? '',
        'invoice_tax_office' => $order['invoice_tax_office'] ?? '',
        'invoice_company_name' => $order['invoice_company_name'] ?? '',
        'invoice_address' => $order['invoice_address'] ?? '',
        'ip_address' => $order['customer_ip'] ?? '',
        'cookie_lifetime' => 60,
        'telegram_heading' => 'Yeni Sipariş (ödeme alındı — PayTR)',
    ]);
} else {
    OrderPaymentFinalize::markPaymentFailed($pdo, $orderId);
    require_once dirname(__DIR__) . '/telegram.php';
    telegram_notify_new_order($pdo, $orderId, 'Ödeme başarısız (PayTR)');
    app_log('payment', 'PayTR callback failed for order #' . $orderId);
}

echo 'OK';
