<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

header('Content-Type: application/json; charset=utf-8');

function avatar_fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!admin_is_super()) {
    avatar_fail(403, 'Yalnızca süper admin.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    avatar_fail(405, 'POST gerekli.');
}

// CSRF
$token = (string) ($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals((string) ($_SESSION['user_avatar_csrf'] ?? ''), $token)) {
    avatar_fail(419, 'Oturum doğrulaması başarısız (CSRF).');
}

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    avatar_fail(400, 'Geçersiz kullanıcı.');
}

$stmt = $pdo->prepare('SELECT user_id, profile_image FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    avatar_fail(404, 'Kullanıcı bulunamadı.');
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    avatar_fail(400, 'Dosya yüklenemedi.');
}

$file = $_FILES['avatar'];
if ($file['size'] > 3 * 1024 * 1024) {
    avatar_fail(413, 'Dosya çok büyük (en fazla 3 MB).');
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($file['tmp_name']);
if (!isset($allowed[$mime])) {
    avatar_fail(415, 'Yalnızca JPG, PNG, WEBP, GIF.');
}

// Görsel gerçekten çözümlenebiliyor mu?
if (@getimagesize($file['tmp_name']) === false) {
    avatar_fail(415, 'Geçersiz görsel.');
}

$ext = $allowed[$mime];
$dir = dirname(__DIR__) . '/uploads';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    avatar_fail(500, 'Klasör oluşturulamadı.');
}

$filename = 'avatar_' . $userId . '_' . date('Ymd_His') . '.' . $ext;
$target = $dir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    avatar_fail(500, 'Dosya kaydedilemedi.');
}

// Eski özel avatarı temizle (varsayılan txrik.gif hariç)
$old = (string) ($user['profile_image'] ?? '');
if ($old !== '' && $old !== 'txrik.gif' && preg_match('/^avatar_\d+_/', $old) === 1) {
    $oldPath = $dir . '/' . basename($old);
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

$upd = $pdo->prepare('UPDATE users SET profile_image = ? WHERE user_id = ?');
$upd->execute([$filename, $userId]);

echo json_encode([
    'ok' => true,
    'user_id' => $userId,
    'filename' => $filename,
    'url' => '../uploads/' . $filename,
], JSON_UNESCAPED_UNICODE);
