<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/gateways/NkolayGateway.php';
require_once dirname(__DIR__) . '/includes/gateways/OrderPaymentFinalize.php';
require_once dirname(__DIR__) . '/includes/app_log.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$gateway = new NkolayGateway($pdo);
$verified = $gateway->verifyResponse($_POST);
if (!$verified['ok']) {
    app_log('payment', 'N Kolay callback FAIL: ' . ($verified['error'] ?? ''));
    header('Location: ' . app_url('payment/nkolay_fail', [], $pdo));
    exit;
}

$merchantOid = (string) ($verified['merchant_oid'] ?? '');
$orderId = OrderPaymentFinalize::orderIdFromMerchantOid($merchantOid);
if ($orderId < 1) {
    app_log('payment', 'N Kolay callback: geçersiz merchant_oid ' . $merchantOid);
    header('Location: ' . app_url('payment/nkolay_fail', [], $pdo));
    exit;
}

$order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
if (!$order) {
    app_log('payment', 'N Kolay callback: sipariş yok #' . $orderId);
    header('Location: ' . app_url('payment/nkolay_fail', ['order_id' => $orderId], $pdo));
    exit;
}

if (($order['payment_status'] ?? '') === 'paid') {
    header('Location: ' . app_url('thankyou', ['order_id' => $orderId], $pdo));
    exit;
}

if (!empty($verified['success'])) {
    OrderPaymentFinalize::markPaymentPaid($pdo, $orderId, 'nkolay', $merchantOid);
    OrderPaymentFinalize::afterOrderConfirmed($pdo, $orderId, [
        'customer_name' => $order['customer_name'],
        'customer_phone' => $order['customer_phone'],
        'customer_address' => $order['customer_address'],
        'customer_city' => $order['customer_city'],
        'customer_district' => $order['customer_district'],
        'order_notes' => $order['order_notes'] ?? '',
        'payment_method_id' => $order['payment_method_id'],
        'product_id' => $order['product_id'],
        'product' => [
            'product_name' => $order['product_name'] ?? 'Ürün',
            'product_price' => $order['product_price'],
        ],
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
    ]);
    header('Location: ' . app_url('thankyou', ['order_id' => $orderId], $pdo));
    exit;
}

$markedFailed = OrderPaymentFinalize::markPaymentFailed($pdo, $orderId);
app_log('payment', 'N Kolay callback failed for order #' . $orderId);
if ($markedFailed) {
    require_once dirname(__DIR__) . '/telegram.php';
    telegram_notify_payment_failed($pdo, $orderId, 'N Kolay');
}
header('Location: ' . app_url('payment/nkolay_fail', ['order_id' => $orderId], $pdo));
exit;
