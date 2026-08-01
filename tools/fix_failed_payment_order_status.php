<?php

declare(strict_types=1);

define('INSTALL_GUARD_SKIP', true);
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/gateways/OrderPaymentFinalize.php';

$failedStatusId = OrderPaymentFinalize::paymentFailedStatusId($pdo);
$st = $pdo->prepare(
    'UPDATE orders SET order_status_id = ?
     WHERE payment_status = ? AND order_status_id != ?'
);
$st->execute([$failedStatusId, 'failed', $failedStatusId]);
echo 'Guncellenen: ' . $st->rowCount() . ' (status_id=' . $failedStatusId . " Ödeme Başarısız)\n";
