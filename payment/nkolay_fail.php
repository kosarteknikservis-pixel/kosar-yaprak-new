<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ödeme başarısız</title>
    <style>body{font-family:system-ui,sans-serif;max-width:520px;margin:48px auto;padding:20px;text-align:center;}</style>
</head>
<body>
    <h1>Ödeme tamamlanamadı</h1>
    <p>Sipariş #<?= $orderId ?> için N Kolay ödemesi başarısız oldu veya doğrulanamadı.</p>
    <p><a href="<?= htmlspecialchars(app_url('', [], $pdo)) ?>">Ana sayfaya dön</a></p>
</body>
</html>
