<?php
require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/gateways/PaytrGateway.php';

$ps = $pdo->query('SELECT is_enabled, merchant_id, test_mode FROM paytr_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$pm = $pdo->query('SELECT payment_method_id, method_name, is_active, gateway_code FROM payment_methods WHERE gateway_code = \'paytr\' LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$gw = new PaytrGateway($pdo);

echo json_encode([
    'paytr_settings' => $ps,
    'payment_method' => $pm,
    'gateway_enabled' => $gw->enabled(),
    'gateway_configured' => $gw->configured(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
