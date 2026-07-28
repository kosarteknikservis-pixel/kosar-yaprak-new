<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/gateways/IyzicoGateway.php';
require_once dirname(__DIR__) . '/includes/gateways/OrderPaymentFinalize.php';
require_once dirname(__DIR__) . '/includes/app_log.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo 'TOKEN_MISSING';
    exit;
}

$st = $pdo->prepare('SELECT order_id, payment_merchant_oid, payment_status FROM orders WHERE gateway_token = ? LIMIT 1');
$st->execute([$token]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    app_log('payment', 'iyzico callback: token eşleşmedi');
    echo 'OK';
    exit;
}

$orderId = (int) $row['order_id'];
$conversationId = (string) ($row['payment_merchant_oid'] ?? OrderPaymentFinalize::merchantOidForOrder($orderId));

if (($row['payment_status'] ?? '') === 'paid') {
    header('Location: ' . app_url('thankyou', ['order_id' => $orderId], $pdo));
    exit;
}

$iyz = new IyzicoGateway($pdo);
$result = $iyz->retrieveCheckoutResult($token, $conversationId);
if (!$result['ok']) {
    app_log('payment', 'iyzico retrieve: ' . ($result['error'] ?? ''));
    header('Location: ' . app_url('payment/iyzico_fail', ['order_id' => $orderId], $pdo));
    exit;
}

if (!empty($result['paid'])) {
    OrderPaymentFinalize::markPaymentPaid($pdo, $orderId, 'iyzico', (string) ($result['payment_id'] ?? $token));
    $order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
    if ($order) {
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
        ]);
    }
    header('Location: ' . app_url('thankyou', ['order_id' => $orderId], $pdo));
    exit;
}

OrderPaymentFinalize::markPaymentFailed($pdo, $orderId);
header('Location: ' . app_url('payment/iyzico_fail', ['order_id' => $orderId], $pdo));
exit;
