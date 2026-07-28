<?php
declare(strict_types=1);

/**
 * @return non-empty-string|null Engelleme mesajı; null ise siparişe izin var.
 */
function order_block_guard(PDO $pdo, string $ip, string $phoneRaw): ?string
{
    $digits = preg_replace('/\D+/', '', $phoneRaw) ?? '';
    $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

    $bq = $pdo->prepare('SELECT id FROM blocked_ips WHERE ip = ? LIMIT 1');
    $bq->execute([$ip]);
    if ($bq->fetch()) {
        return 'Bu IP adresinden sipariş kabul edilmiyor.';
    }

    if ($last10 !== '') {
        $pq = $pdo->prepare(
            'SELECT id FROM blocked_phones WHERE phone_digits = ? OR phone_digits = ? LIMIT 1'
        );
        $pq->execute([$last10, ltrim($last10, '0') ?: $last10]);
        if ($pq->fetch()) {
            return 'Bu telefon numarasıyla sipariş kabul edilmiyor.';
        }
    }

    return null;
}
