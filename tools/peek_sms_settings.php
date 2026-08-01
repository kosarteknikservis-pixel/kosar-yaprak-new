<?php
declare(strict_types=1);
require dirname(__DIR__) . '/db.php';
$r = $pdo->query('SELECT message, sms_new_order_enabled, is_enabled, sms_provider FROM netgsm_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$c = $pdo->query('SELECT order_sms_verify_enabled, order_sms_front_otp_enabled FROM checkout_module_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
echo json_encode(['netgsm' => $r, 'checkout' => $c], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
