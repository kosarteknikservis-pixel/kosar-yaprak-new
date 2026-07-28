<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
if ($orderId < 1) {
    header('Location: ' . app_url('error', ['error' => 'missing_fields'], $pdo));
    exit;
}

require_once dirname(__DIR__) . '/includes/gateways/IyzicoGateway.php';
require_once dirname(__DIR__) . '/includes/gateways/OrderPaymentFinalize.php';

$order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
if (!$order || ($order['gateway_code'] ?? '') !== 'iyzico') {
    header('Location: ' . app_url('error', ['error' => 'invalid_payment_method'], $pdo));
    exit;
}

$iyz = new IyzicoGateway($pdo);
$result = $iyz->initializeCheckoutForm($order);
if (!$result['ok']) {
    require_once dirname(__DIR__) . '/includes/app_log.php';
    app_log('payment', 'iyzico init: ' . ($result['error'] ?? ''));
    header('Location: ' . app_url('error', ['error' => 'payment_gateway', 'msg' => $result['error'] ?? ''], $pdo));
    exit;
}

$token = (string) ($result['token'] ?? '');
$pdo->prepare('UPDATE orders SET gateway_token = ?, payment_status = ?, payment_merchant_oid = COALESCE(payment_merchant_oid, ?) WHERE order_id = ?')
    ->execute([$token, 'pending', OrderPaymentFinalize::merchantOidForOrder($orderId), $orderId]);

$pageUrl = (string) ($result['payment_page_url'] ?? '');
if ($pageUrl !== '') {
    header('Location: ' . $pageUrl);
    exit;
}

$content = (string) ($result['checkout_form_content'] ?? '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Güvenli Ödeme — iyzico</title>
</head>
<body>
<?= $content ?>
</body>
</html>
