<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ödeme işlemi</title>
    <style>body{font-family:system-ui,sans-serif;max-width:520px;margin:48px auto;padding:20px;text-align:center;} p{color:#475569;}</style>
</head>
<body>
    <h1>Ödeme sonucu işleniyor</h1>
    <p>Ödemeniz alındıysa siparişiniz kısa süre içinde onaylanacaktır. Bildirim URL üzerinden doğrulama yapılmaktadır.</p>
    <p><a href="<?= htmlspecialchars(app_url('', [], $pdo)) ?>">Ana sayfaya dön</a></p>
</body>
</html>
