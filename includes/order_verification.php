<?php

declare(strict_types=1);

/**
 * Sipariş SMS doğrulama — ov_* fonksiyonları (kosar1 orders tablosu).
 */
require_once __DIR__ . '/transactional_sms.php';

function ov_now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function ov_ensure_table(PDO $db): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS siparis_dogrulama (
            id INT AUTO_INCREMENT PRIMARY KEY,
            siparis_id INT NOT NULL,
            siparis_tel VARCHAR(32) NOT NULL DEFAULT '',
            otp_code VARCHAR(8) NOT NULL DEFAULT '',
            token_hash VARCHAR(128) NOT NULL DEFAULT '',
            durum TINYINT NOT NULL DEFAULT 0,
            dogrulama_kanali VARCHAR(20) NOT NULL DEFAULT '',
            hata_sayisi INT NOT NULL DEFAULT 0,
            son_gonderim DATETIME NULL,
            son_kontrol DATETIME NULL,
            son_dogrulama DATETIME NULL,
            bitis_tarihi DATETIME NOT NULL,
            olusturma_tarihi DATETIME NOT NULL,
            UNIQUE KEY uq_siparis_id (siparis_id),
            KEY idx_token_hash (token_hash),
            KEY idx_durum (durum),
            KEY idx_bitis_tarihi (bitis_tarihi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ready = true;
}

function ov_normalize_tel($tel): string
{
    $d = preg_replace('/\D+/', '', (string) $tel);
    if (strlen($d) === 12 && strncmp($d, '90', 2) === 0) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11 && $d[0] === '0') {
        $d = substr($d, 1);
    }

    return $d;
}

function ov_mask_tel($tel): string
{
    $d = ov_normalize_tel($tel);
    if (strlen($d) !== 10) {
        return '***';
    }

    return substr($d, 0, 3) . '***' . substr($d, -3);
}

function ov_generate_token(): string
{
    return bin2hex(random_bytes(24));
}

function ov_generate_otp(): string
{
    return (string) random_int(100000, 999999);
}

/**
 * @return array{otp: string, token: string, expires_at: string}|null
 */
function ov_create_or_refresh(PDO $db, $siparisId, $siparisTel, $validMinutes = 20): ?array
{
    ov_ensure_table($db);
    $siparisId = (int) $siparisId;
    $tel = ov_normalize_tel($siparisTel);
    if ($siparisId < 1 || strlen($tel) !== 10) {
        return null;
    }

    $otp = ov_generate_otp();
    $token = ov_generate_token();
    $tokenHash = hash('sha256', $token);
    $now = ov_now_utc();
    $exp = gmdate('Y-m-d H:i:s', time() + (max(5, (int) $validMinutes) * 60));

    $q = $db->prepare('SELECT id FROM siparis_dogrulama WHERE siparis_id = :sid LIMIT 1');
    $q->execute(['sid' => $siparisId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $u = $db->prepare(
            'UPDATE siparis_dogrulama
             SET siparis_tel=:tel, otp_code=:otp, token_hash=:th, durum=0, dogrulama_kanali=\'\',
                 hata_sayisi=0, son_gonderim=:sg, son_kontrol=NULL, son_dogrulama=NULL, bitis_tarihi=:bt
             WHERE siparis_id=:sid'
        );
        $u->execute([
            'tel' => $tel,
            'otp' => $otp,
            'th' => $tokenHash,
            'sg' => $now,
            'bt' => $exp,
            'sid' => $siparisId,
        ]);
    } else {
        $i = $db->prepare(
            'INSERT INTO siparis_dogrulama
             SET siparis_id=:sid, siparis_tel=:tel, otp_code=:otp, token_hash=:th, durum=0,
                 dogrulama_kanali=\'\', hata_sayisi=0, son_gonderim=:sg, son_kontrol=NULL, son_dogrulama=NULL,
                 bitis_tarihi=:bt, olusturma_tarihi=:ot'
        );
        $i->execute([
            'sid' => $siparisId,
            'tel' => $tel,
            'otp' => $otp,
            'th' => $tokenHash,
            'sg' => $now,
            'bt' => $exp,
            'ot' => $now,
        ]);
    }

    return [
        'otp' => $otp,
        'token' => $token,
        'expires_at' => $exp,
    ];
}

function ov_fetch_by_order(PDO $db, $siparisId): ?array
{
    ov_ensure_table($db);
    $q = $db->prepare('SELECT * FROM siparis_dogrulama WHERE siparis_id = :sid LIMIT 1');
    $q->execute(['sid' => (int) $siparisId]);

    $row = $q->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function ov_is_expired(array $row): bool
{
    if (empty($row['bitis_tarihi'])) {
        return true;
    }

    return strtotime((string) $row['bitis_tarihi']) < time();
}

function ov_mark_order_verified(PDO $db, int $orderId): void
{
    require_once __DIR__ . '/order_sms_verify.php';
    $approvedId = order_sms_verify_approved_status_id($db);
    $db->prepare('UPDATE orders SET order_status_id = ? WHERE order_id = ?')
        ->execute([$approvedId, $orderId]);
}

/**
 * @return array{ok: bool, reason: string, siparis_id?: int}
 */
function ov_verify_by_token(PDO $db, $token, $channel = 'link'): array
{
    ov_ensure_table($db);
    $token = trim((string) $token);
    if ($token === '') {
        return ['ok' => false, 'reason' => 'empty'];
    }

    $th = hash('sha256', $token);
    $q = $db->prepare('SELECT * FROM siparis_dogrulama WHERE token_hash=:th LIMIT 1');
    $q->execute(['th' => $th]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (! $row) {
        return ['ok' => false, 'reason' => 'not_found'];
    }

    $orderId = (int) $row['siparis_id'];
    if ((int) $row['durum'] === 1) {
        return ['ok' => true, 'reason' => 'already', 'siparis_id' => $orderId];
    }
    if (ov_is_expired($row)) {
        return ['ok' => false, 'reason' => 'expired', 'siparis_id' => $orderId];
    }

    $now = ov_now_utc();
    $u = $db->prepare(
        'UPDATE siparis_dogrulama
         SET durum=1, dogrulama_kanali=:ch, son_kontrol=:sk, son_dogrulama=:sd
         WHERE id=:id'
    );
    $u->execute([
        'ch' => substr((string) $channel, 0, 20),
        'sk' => $now,
        'sd' => $now,
        'id' => (int) $row['id'],
    ]);

    ov_mark_order_verified($db, $orderId);

    return ['ok' => true, 'reason' => 'verified', 'siparis_id' => $orderId];
}

/**
 * @return array{ok: bool, reason: string}
 */
function ov_verify_by_otp(PDO $db, $siparisId, $otp): array
{
    ov_ensure_table($db);
    $otp = preg_replace('/\D+/', '', (string) $otp);
    $q = $db->prepare('SELECT * FROM siparis_dogrulama WHERE siparis_id=:sid LIMIT 1');
    $q->execute(['sid' => (int) $siparisId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (! $row) {
        return ['ok' => false, 'reason' => 'not_found'];
    }
    if ((int) $row['durum'] === 1) {
        return ['ok' => true, 'reason' => 'already'];
    }
    if (ov_is_expired($row)) {
        return ['ok' => false, 'reason' => 'expired'];
    }

    $now = ov_now_utc();
    if ($otp !== (string) $row['otp_code']) {
        $u = $db->prepare('UPDATE siparis_dogrulama SET hata_sayisi=hata_sayisi+1, son_kontrol=:sk WHERE id=:id');
        $u->execute(['sk' => $now, 'id' => (int) $row['id']]);

        return ['ok' => false, 'reason' => 'invalid'];
    }

    $u = $db->prepare(
        'UPDATE siparis_dogrulama
         SET durum=1, dogrulama_kanali=\'otp\', son_kontrol=:sk, son_dogrulama=:sd
         WHERE id=:id'
    );
    $u->execute(['sk' => $now, 'sd' => $now, 'id' => (int) $row['id']]);

    ov_mark_order_verified($db, (int) $row['siparis_id']);

    return ['ok' => true, 'reason' => 'verified'];
}

function ov_send_otp_sms($tel, $otp, $verifyLink): bool
{
    $masked = ov_mask_tel($tel);
    $msg = 'Siparisinizi dogrulamak icin kodunuz: ' . $otp
        . '. Link: ' . $verifyLink
        . ' (20 dk gecerli). Tel: ' . $masked;

    return sendTransactionalSms((string) $tel, $msg);
}

function ov_send_front_otp_sms($tel, $otp): bool
{
    $msg = 'Siparisinizi tamamlamak icin kodunuz: ' . $otp . ' (5 dk gecerli).';

    return sendTransactionalSms((string) $tel, $msg);
}

function ov_order_is_pending(PDO $pdo, int $orderId): bool
{
    require_once __DIR__ . '/order_sms_verify.php';
    $st = $pdo->prepare('SELECT order_status_id FROM orders WHERE order_id = ? LIMIT 1');
    $st->execute([$orderId]);
    $statusId = (int) $st->fetchColumn();

    return $statusId === order_sms_verify_pending_status_id($pdo);
}
