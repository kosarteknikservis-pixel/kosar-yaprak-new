<?php
declare(strict_types=1);
/**
 * tour_user.php — Tur icin GECICI super-admin kullanici olusturur/siler.
 * Sadece CLI. Gercek 'phx' hesabina DOKUNMAZ.
 *
 *   php tour_user.php create <parola>
 *   php tour_user.php delete
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__, 2) . '/db.php';

$action = $argv[1] ?? '';
$username = '__tour__';

if ($action === 'create') {
    $password = (string) ($argv[2] ?? '');
    if ($password === '') {
        fwrite(STDERR, "parola gerekli\n");
        exit(2);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('DELETE FROM users WHERE username = ?')->execute([$username]);
    $st = $pdo->prepare(
        'INSERT INTO users (username, password, email, full_name, is_super_admin, menu_permissions)
         VALUES (?, ?, ?, ?, 1, ?)'
    );
    $st->execute([$username, $hash, 'tour@localhost.local', 'Tur Botu', '["*"]']);
    echo "created:" . $pdo->lastInsertId() . "\n";
    exit(0);
}

if ($action === 'delete') {
    // Once bagimli kayitlari (FK) temizle, sonra kullaniciyi sil.
    $ids = $pdo->prepare('SELECT user_id FROM users WHERE username = ? AND user_id <> 1');
    $ids->execute([$username]);
    $userIds = $ids->fetchAll(PDO::FETCH_COLUMN);

    foreach ($userIds as $uid) {
        try { $pdo->prepare('DELETE FROM login_logs WHERE user_id = ?')->execute([$uid]); } catch (Throwable $e) {}
    }
    try { $pdo->prepare('DELETE FROM login_logs WHERE username = ?')->execute([$username]); } catch (Throwable $e) {}

    $st = $pdo->prepare('DELETE FROM users WHERE username = ? AND user_id <> 1');
    $st->execute([$username]);
    echo "deleted:" . $st->rowCount() . "\n";
    exit(0);
}

fwrite(STDERR, "kullanim: php tour_user.php create <parola> | delete\n");
exit(2);
