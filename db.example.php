<?php
$host = 'localhost';
$db   = 'VERITABANI_ADI';
$user = 'VERITABANI_KULLANICI';
$pass = 'VERITABANI_SIFRE';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    if (is_file(__DIR__ . '/includes/feature_schema.php')) {
        require_once __DIR__ . '/includes/feature_schema.php';
        ensure_feature_schema($pdo);
    }
    if (is_file(__DIR__ . '/includes/cloaker_bootstrap.php')) {
        require_once __DIR__ . '/includes/cloaker_bootstrap.php';
        cloaker_bootstrap($pdo);
    }
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
