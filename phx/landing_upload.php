<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

header('Content-Type: application/json; charset=utf-8');

function lp_upload_fail(string $msg): void
{
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['csrf_landing']) || !hash_equals((string) $_SESSION['csrf_landing'], (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    lp_upload_fail('Oturum güvenliği doğrulanamadı.');
}

if (!isset($_FILES['image']) || !is_array($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    lp_upload_fail('Dosya alınamadı.');
}

$file = $_FILES['image'];
if ((int) $file['size'] > 6 * 1024 * 1024) {
    lp_upload_fail('Dosya çok büyük (en fazla 6 MB).');
}

$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    lp_upload_fail('Geçerli bir görsel değil.');
}
$allowed = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_WEBP => 'webp',
];
$typeConst = (int) ($info[2] ?? 0);
if (!isset($allowed[$typeConst])) {
    lp_upload_fail('Yalnız JPG, PNG, GIF veya WEBP yükleyin.');
}
$ext = $allowed[$typeConst];

$dir = dirname(__DIR__) . '/uploads/landing';
if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    lp_upload_fail('Yükleme klasörü oluşturulamadı.');
}

$name = 'lp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $dir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    lp_upload_fail('Dosya kaydedilemedi.');
}

echo json_encode(['ok' => true, 'path' => 'uploads/landing/' . $name], JSON_UNESCAPED_UNICODE);
