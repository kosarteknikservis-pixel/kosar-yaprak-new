<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
if ($orderId < 1) {
    header('Location: ' . app_url('error', ['error' => 'missing_fields'], $pdo));
    exit;
}

require_once dirname(__DIR__) . '/includes/gateways/PaytrGateway.php';
require_once dirname(__DIR__) . '/includes/gateways/OrderPaymentFinalize.php';

$order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
if (!$order || ($order['gateway_code'] ?? '') !== 'paytr') {
    header('Location: ' . app_url('error', ['error' => 'invalid_payment_method'], $pdo));
    exit;
}

$paytr = new PaytrGateway($pdo);
$result = $paytr->createIframeToken($order);
if (!$result['ok']) {
    require_once dirname(__DIR__) . '/includes/app_log.php';
    app_log('payment', 'PayTR iframe: ' . ($result['error'] ?? ''));
    header('Location: ' . app_url('error', ['error' => 'payment_gateway', 'msg' => $result['error'] ?? ''], $pdo));
    exit;
}

$iframeUrl = (string) ($result['iframe_url'] ?? '');
$page_title = 'Güvenli Ödeme — PayTR';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f1f5f9; }
        .wrap { max-width: 720px; margin: 24px auto; padding: 16px; }
        .head { background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .head h1 { margin: 0 0 6px; font-size: 1.15rem; }
        .head p { margin: 0; color: #64748b; font-size: .9rem; }
        .frame-box { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>Kredi kartı ile ödeme</h1>
        <p>Sipariş #<?= (int) $orderId ?> — PayTR güvenli ödeme ekranı</p>
    </div>
    <div class="frame-box">
        <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
        <iframe src="<?= htmlspecialchars($iframeUrl) ?>" id="paytriframe" frameborder="0" scrolling="no" style="width:100%;min-height:520px;"></iframe>
        <script>iFrameResize({}, '#paytriframe');</script>
    </div>
</div>
</body>
</html>
