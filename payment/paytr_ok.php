<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

header('Location: ' . app_url('payment/paytr_ok', [], $pdo));
exit;
