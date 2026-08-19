<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';
require_once dirname(__DIR__) . '/includes/gateways/NkolayGateway.php';
require_once dirname(__DIR__) . '/includes/gateways/OrderPaymentFinalize.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
if ($orderId < 1) {
    header('Location: ' . app_url('error', ['error' => 'missing_fields'], $pdo));
    exit;
}

$order = OrderPaymentFinalize::loadOrderContext($pdo, $orderId);
if (!$order || ($order['gateway_code'] ?? '') !== 'nkolay') {
    header('Location: ' . app_url('error', ['error' => 'invalid_payment_method'], $pdo));
    exit;
}

$gw = new NkolayGateway($pdo);
$result = $gw->createPaymentForm($order);
if (!$result['ok']) {
    require_once dirname(__DIR__) . '/includes/app_log.php';
    app_log('payment', 'N Kolay init: ' . ($result['error'] ?? ''));
    header('Location: ' . app_url('error', ['error' => 'payment_gateway', 'msg' => $result['error'] ?? ''], $pdo));
    exit;
}

$actionUrl = (string) ($result['url'] ?? '');
$fields = (array) ($result['fields'] ?? []);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Güvenli Ödeme — N Kolay</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 620px; margin: 40px auto; padding: 20px; text-align: center; }
        .card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Güvenli ödeme ekranına yönlendiriliyor...</h1>
        <p>Sipariş #<?= (int) $orderId ?> için N Kolay ödeme sayfasına geçiliyor.</p>
        <form id="nkolayForm" method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($fields as $k => $v): ?>
                <input type="hidden" name="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>">
            <?php endforeach; ?>
            <noscript>
                <button type="submit">Ödeme sayfasına git</button>
            </noscript>
        </form>
    </div>
    <script>
    (function () {
        var f = document.getElementById('nkolayForm');
        if (f) f.submit();
    })();
    </script>
</body>
</html>
