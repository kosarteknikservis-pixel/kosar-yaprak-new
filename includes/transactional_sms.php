<?php

declare(strict_types=1);

/**
 * Mutlucell + NETGSM transactional SMS (sendTransactionalSms merkezi).
 * Ayarlar: netgsm_settings tablosu (sms_provider: mutlucell | netgsm).
 */

function sms_last_error_set(string $message): void
{
    $GLOBALS['sms_last_error'] = $message;
}

function sms_last_response_set(string $message): void
{
    $GLOBALS['sms_last_response'] = substr($message, 0, 500);
}

function smsLastErrorGet(): string
{
    return isset($GLOBALS['sms_last_error']) ? (string) $GLOBALS['sms_last_error'] : '';
}

function smsLastResponseGet(): string
{
    return isset($GLOBALS['sms_last_response']) ? (string) $GLOBALS['sms_last_response'] : '';
}

/**
 * @return array<string, mixed>|null
 */
function sms_load_settings(PDO $pdo): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query('SELECT * FROM netgsm_settings WHERE id = 1 LIMIT 1');
        $row = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        $cache = is_array($row) ? $row : null;
    } catch (Throwable $e) {
        $cache = null;
    }

    return $cache;
}

function sms_credentials_usable(array $cfg): bool
{
    $user = trim((string) ($cfg['username'] ?? ''));
    $pass = trim((string) ($cfg['password'] ?? ''));
    $header = trim((string) ($cfg['header'] ?? ''));

    return $user !== '' && $pass !== '' && $header !== '';
}

function sms_normalize_gsm_for_mutlucell(string $gsm): string
{
    $no = preg_replace('/\D/', '', $gsm);
    if (strlen($no) === 10 && $no[0] === '5') {
        return '90' . $no;
    }
    if (strlen($no) === 11 && $no[0] === '0' && $no[1] === '5') {
        return '9' . $no;
    }
    if (strlen($no) === 12 && strncmp($no, '90', 2) === 0) {
        return $no;
    }

    return '';
}

/**
 * @param  array<string, mixed>|null  $cfg
 */
function mutluCellSend(string $gsm, string $message, ?array $cfg = null): bool
{
    sms_last_error_set('');
    sms_last_response_set('');

    try {
        global $pdo;
        if ($cfg === null && $pdo instanceof PDO) {
            $cfg = sms_load_settings($pdo);
        }

        if (! is_array($cfg) || ((int) ($cfg['is_enabled'] ?? 0)) !== 1) {
            sms_last_error_set('SMS gönderimi pasif veya ayar bulunamadı.');

            return false;
        }

        $user = trim((string) ($cfg['username'] ?? ''));
        $pass = trim((string) ($cfg['password'] ?? ''));
        $header = trim((string) ($cfg['header'] ?? ''));
        if ($user === '' || $pass === '' || $header === '') {
            sms_last_error_set('Mutlucell kullanıcı, API key veya başlık eksik.');

            return false;
        }

        $no = sms_normalize_gsm_for_mutlucell($gsm);
        if ($no === '' || strlen($no) !== 12 || strncmp($no, '90', 2) !== 0) {
            sms_last_error_set('Telefon formatı geçersiz (90XXXXXXXXXX gerekli).');

            return false;
        }

        $msg = trim($message);
        if ($msg === '') {
            sms_last_error_set('Mesaj boş.');

            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<smspack ka="' . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . '"'
            . ' pwd="' . htmlspecialchars($pass, ENT_QUOTES, 'UTF-8') . '"'
            . ' org="' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '">'
            . '<mesaj>'
            . '<metin>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</metin>'
            . '<nums>' . htmlspecialchars($no, ENT_QUOTES, 'UTF-8') . '</nums>'
            . '</mesaj>'
            . '</smspack>';

        if (! extension_loaded('curl')) {
            sms_last_error_set('cURL yüklü değil.');

            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://smsgw.mutlucell.com/smsgw-ws/sndblkex',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=UTF-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
        ]);
        $body = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $body === '') {
            if ($curlErrNo !== 0) {
                sms_last_error_set('Mutlucell cURL hata #' . $curlErrNo . ': ' . $curlErr);
            } elseif ($httpCode > 0) {
                sms_last_error_set('Mutlucell boş cevap döndü. HTTP: ' . $httpCode);
            } else {
                sms_last_error_set('Mutlucell boş cevap döndü.');
            }

            return false;
        }

        $resp = trim(strip_tags((string) $body));
        sms_last_response_set($resp);

        $respCompact = preg_replace('/\s+/', '', $resp);
        $isNumericSuccess = is_string($respCompact) && preg_match('/^\d+$/', $respCompact) && (int) $respCompact > 1000;
        $isDollarSuccess = is_string($respCompact) && strpos($respCompact, '$') !== false;
        $isSuffixZeroOk = is_string($respCompact) && preg_match('/[;:]0$/', $respCompact) === 1;

        if ($isNumericSuccess || $isDollarSuccess || $isSuffixZeroOk) {
            sms_last_error_set('');

            return true;
        }

        sms_last_error_set('Mutlucell yanıtı: ' . substr($resp, 0, 160));

        return false;
    } catch (Throwable $e) {
        sms_last_error_set('Mutlucell exception: ' . $e->getMessage());

        return false;
    }
}

/**
 * @param  array<string, mixed>|null  $cfg
 */
function netGsmSend(string $gsm, string $message, ?array $cfg = null): bool
{
    sms_last_error_set('');
    sms_last_response_set('');

    if (! function_exists('netgsm_normalize_gsm_for_tr') || ! function_exists('netgsm_api_send_get')) {
        require_once __DIR__ . '/netgsm_customer_sms.php';
    }

    global $pdo;
    if ($cfg === null && $pdo instanceof PDO) {
        $cfg = sms_load_settings($pdo);
    }

    if (! is_array($cfg) || ((int) ($cfg['is_enabled'] ?? 0)) !== 1) {
        sms_last_error_set('SMS gönderimi pasif veya ayar bulunamadı.');

        return false;
    }

    if (! sms_credentials_usable($cfg)) {
        sms_last_error_set('NETGSM kullanıcı, şifre veya başlık eksik.');

        return false;
    }

    $gsmno = netgsm_normalize_gsm_for_tr($gsm);
    if ($gsmno === '' || strlen($gsmno) < 10) {
        sms_last_error_set('Telefon formatı geçersiz.');

        return false;
    }

    $msg = trim($message);
    if ($msg === '') {
        sms_last_error_set('Mesaj boş.');

        return false;
    }

    $res = netgsm_api_send_get(
        trim((string) ($cfg['username'] ?? '')),
        (string) ($cfg['password'] ?? ''),
        trim((string) ($cfg['header'] ?? '')),
        $gsmno,
        $msg,
        trim((string) ($cfg['appkey'] ?? ''))
    );

    sms_last_response_set((string) ($res['raw'] ?? $res['detail'] ?? ''));
    if (! ($res['ok'] ?? false)) {
        sms_last_error_set((string) ($res['detail'] ?? 'NETGSM gönderim hatası'));

        return false;
    }

    sms_last_error_set('');

    return true;
}

/**
 * @param  array<string, mixed>|null  $cfg
 */
function sendTransactionalSms(string $gsm, string $message, ?array $cfg = null): bool
{
    global $pdo;

    $provider = 'mutlucell';
    if ($cfg !== null) {
        $p = strtolower(trim((string) ($cfg['sms_provider'] ?? '')));
        if ($p !== '') {
            $provider = $p;
        }
    } elseif ($pdo instanceof PDO) {
        $loaded = sms_load_settings($pdo);
        if (is_array($loaded)) {
            $p = strtolower(trim((string) ($loaded['sms_provider'] ?? '')));
            if ($p !== '') {
                $provider = $p;
            }
            if ($cfg === null) {
                $cfg = $loaded;
            }
        }
    }

    if ($provider === 'netgsm') {
        return netGsmSend($gsm, $message, $cfg);
    }

    return mutluCellSend($gsm, $message, $cfg);
}
