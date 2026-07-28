<?php

declare(strict_types=1);

require_once __DIR__ . '/transactional_sms.php';

/**
 * Yarım kalan sipariş — WhatsApp / SMS geri kazanım mesajları.
 */

function abandoned_recovery_settings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'abandoned_sms_enabled' => 1,
        'abandoned_whatsapp_number' => '05527391073',
    ];

    try {
        $row = $pdo->query(
            'SELECT abandoned_sms_enabled, abandoned_whatsapp_number
             FROM checkout_module_settings WHERE id = 1 LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $cache = array_merge($defaults, $row);
        } else {
            $cache = $defaults;
        }
    } catch (Throwable $e) {
        $cache = $defaults;
    }

    return $cache;
}

function abandoned_recovery_whatsapp_digits(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') {
        return '905527391073';
    }
    if (str_starts_with($d, '0')) {
        $d = substr($d, 1);
    }
    if (! str_starts_with($d, '90')) {
        $d = '90' . $d;
    }

    return $d;
}

/**
 * WhatsApp wa.me linki — müşteri tıklayınca mesaj kutusu açılır.
 */
function abandoned_recovery_whatsapp_href(int $yarimId, string $ad, string $urun, ?PDO $pdo = null): string
{
    $cfg = $pdo instanceof PDO ? abandoned_recovery_settings($pdo) : [
        'abandoned_whatsapp_number' => '05527391073',
    ];
    $digits = abandoned_recovery_whatsapp_digits((string) ($cfg['abandoned_whatsapp_number'] ?? '05527391073'));
    $text = abandoned_recovery_whatsapp_prefill($yarimId, $ad, $urun);

    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function abandoned_recovery_whatsapp_prefill(int $yarimId, string $ad, string $urun): string
{
    $name = trim($ad) !== '' ? trim($ad) : 'Merhaba';
    $product = trim($urun) !== '' ? trim($urun) : 'ürün';
    if (mb_strlen($product) > 48) {
        $product = mb_substr($product, 0, 45) . '...';
    }

    return 'Merhaba, ' . $name . '. Koşar Vantilatör\'den yazıyorum. '
        . $product . ' için siparişimi tamamlamak istiyorum. (Ref: YK-' . $yarimId . ')';
}

function abandoned_recovery_sms_body(int $yarimId, string $ad, string $urun, ?PDO $pdo = null): string
{
    $name = trim($ad) !== '' ? trim($ad) : 'Merhaba';
    $product = trim($urun) !== '' ? trim($urun) : 'ürününüz';
    if (mb_strlen($product) > 42) {
        $product = mb_substr($product, 0, 39) . '...';
    }
    $wa = abandoned_recovery_whatsapp_href($yarimId, $ad, $urun, $pdo);

    return 'Merhaba ' . $name . ', Kosar Vantilator\'den yaziyoruz. '
        . $product . ' siparisiniz yarım kaldi. WhatsApp\'tan yazin: ' . $wa;
}

/**
 * Yarım kalan kaydı için bir kez SMS gönder (WhatsApp linki ile).
 */
function abandoned_recovery_send_sms(PDO $pdo, int $yarimId): bool
{
    if ($yarimId <= 0) {
        return false;
    }

    $cfg = abandoned_recovery_settings($pdo);
    if ((int) ($cfg['abandoned_sms_enabled'] ?? 1) !== 1) {
        return false;
    }

    $cols = [];
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM yarim_kalanlar')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }
    } catch (Throwable $e) {
        return false;
    }

    if (! isset($cols['recovery_sms_sent_at'])) {
        return false;
    }

    $st = $pdo->prepare('SELECT ad, tel, urun, recovery_sms_sent_at FROM yarim_kalanlar WHERE id = ? LIMIT 1');
    $st->execute([$yarimId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (! $row || ! empty($row['recovery_sms_sent_at'])) {
        return false;
    }

    $tel = preg_replace('/\D+/', '', (string) ($row['tel'] ?? '')) ?? '';
    if (strlen($tel) === 11 && str_starts_with($tel, '0')) {
        $tel = substr($tel, 1);
    }
    if (strlen($tel) !== 10 || $tel[0] !== '5') {
        return false;
    }

    $msg = abandoned_recovery_sms_body(
        $yarimId,
        (string) ($row['ad'] ?? ''),
        (string) ($row['urun'] ?? ''),
        $pdo
    );

    if (! sendTransactionalSms($tel, $msg)) {
        if (is_file(__DIR__ . '/app_log.php')) {
            require_once __DIR__ . '/app_log.php';
            app_log('abandoned', 'recovery_sms_fail', [
                'yarim_id' => $yarimId,
                'error' => smsLastErrorGet(),
            ]);
        }

        return false;
    }

    $pdo->prepare('UPDATE yarim_kalanlar SET recovery_sms_sent_at = NOW() WHERE id = ? AND recovery_sms_sent_at IS NULL')
        ->execute([$yarimId]);

    if (is_file(__DIR__ . '/app_log.php')) {
        require_once __DIR__ . '/app_log.php';
        app_log('abandoned', 'recovery_sms_sent', ['yarim_id' => $yarimId, 'tel' => $tel]);
    }

    return true;
}

/**
 * @return array<string, true>
 */
function abandoned_recovery_yarim_columns(PDO $pdo): array
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $cols = [];
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM yarim_kalanlar')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string) ($row['Field'] ?? '');
            if ($name !== '') {
                $cols[$name] = true;
            }
        }
    } catch (Throwable $e) {
        $cols = [];
    }

    return $cache[$key] = $cols;
}
