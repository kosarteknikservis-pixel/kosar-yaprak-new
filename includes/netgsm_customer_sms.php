<?php
declare(strict_types=1);

/**
 * Müşteriye giden NETGSM SMS — panel anahtarları ve durum tetikleri.
 */

function netgsm_normalize_gsm_for_tr(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '') {
        return '';
    }
    if (strlen($d) === 10 && $d[0] === '5') {
        return '90' . $d;
    }
    if (strlen($d) === 11 && strncmp($d, '05', 2) === 0) {
        return '90' . substr($d, 1);
    }
    if (strlen($d) === 12 && strpos($d, '90') === 0) {
        return $d;
    }

    return $d;
}

/**
 * NETGSM satırından kullanıcı/şifre/başlık dolu mu?
 *
 * @param  array<string, mixed>  $cfg
 */
function netgsm_credentials_usable(array $cfg): bool
{
    $u = trim((string) ($cfg['username'] ?? ''));
    $p = (string) ($cfg['password'] ?? '');
    $h = trim((string) ($cfg['header'] ?? ''));

    return $u !== '' && $p !== '' && $h !== '';
}

/**
 * @param  array<string, mixed>  $order
 */
function netgsm_customer_message_fill(string $template, array $order, string $orderIdStr): string
{
    $trackingNr = isset($order['tracking_number'])
        ? (string) $order['tracking_number']
        : '';

    return str_replace(
        ['{customer_name}', '{order_id}', '{tracking_number}', '{products}', '{total_price}'],
        [
            (string) ($order['customer_name'] ?? ''),
            $orderIdStr,
            $trackingNr,
            (string) ($order['products'] ?? ''),
            number_format((float) ($order['total_price'] ?? 0), 2, ',', '.'),
        ],
        $template
    );
}

/**
 * @return array{ok: bool, detail: string, raw?: ?string}
 */
function netgsm_api_send_get(
    string $usercode,
    string $password,
    string $msgheader,
    string $gsmno,
    string $message,
    string $appkey
): array {
    if (!extension_loaded('curl')) {
        return ['ok' => false, 'detail' => 'cURL yüklü değil'];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.netgsm.com.tr/sms/send/get',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'usercode' => $usercode,
            'password' => $password,
            'gsmno' => $gsmno,
            'message' => $message,
            'msgheader' => $msgheader,
            'filter' => '0',
            'startdate' => '',
            'stopdate' => '',
            'appkey' => $appkey,
        ],
    ]);

    /** @disregard */
    $response = curl_exec($curl);

    /** @disregard */
    if (curl_errno($curl)) {
        /** @disregard */
        $err = curl_error($curl);
        curl_close($curl);

        return ['ok' => false, 'detail' => 'cURL: ' . ($err ?: 'bilinmeyen'), 'raw' => null];
    }

    curl_close($curl);
    $raw = is_string($response) ? $response : '';
    $trim = trim(str_replace("\xEF\xBB\xBF", '', $raw));
    $first = trim((string) strtok($trim, ','));

    if ($first === '') {
        return ['ok' => false, 'detail' => 'Boş API yanıtı', 'raw' => $raw];
    }

    if (strlen($first) === 2 && ctype_digit($first)) {
        $ok = $first === '00';

        return $ok
            ? ['ok' => true, 'detail' => 'Gönderildi', 'raw' => $raw]
            : ['ok' => false, 'detail' => 'Netgsm kod: ' . $first, 'raw' => $raw];
    }

    if (str_starts_with($first, '00')) {
        return ['ok' => true, 'detail' => 'Gönderildi', 'raw' => $raw];
    }

    if (ctype_digit(str_replace(' ', '', $first))) {
        return ['ok' => true, 'detail' => 'Kuyruk / görev yanıtı', 'raw' => $raw];
    }

    return ['ok' => true, 'detail' => 'Yanıt: ' . $first, 'raw' => $raw];
}

/**
 * Teşekkür sayfası (yeni sipariş tamamlandığında müşteri SMS).
 *
 * @param  array<string, mixed>  $order
 */
function netgsm_send_new_order_sms_if_enabled(PDO $pdo, array $order, string $orderIdStr): void
{
    try {
        $stmt = $pdo->query('SELECT * FROM netgsm_settings WHERE id = 1 LIMIT 1');
        $cfg = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (
            !is_array($cfg)
            || ((int) ($cfg['is_enabled'] ?? 1)) !== 1
            || ((int) ($cfg['sms_new_order_enabled'] ?? 1)) !== 1
        ) {
            return;
        }

        if (!netgsm_credentials_usable($cfg)) {
            error_log('netgsm: yeni sipariş SMS kapalı — eksik API bilgisi');

            return;
        }

        $gsmno = netgsm_normalize_gsm_for_tr((string) ($order['customer_phone'] ?? ''));
        if ($gsmno === '' || strlen($gsmno) < 10) {
            return;
        }

        $smsTpl = trim((string) ($cfg['message'] ?? ''));
        if ($smsTpl !== '') {
            $message = html_entity_decode(
                strip_tags(netgsm_customer_message_fill($smsTpl, $order, $orderIdStr)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        } else {
            $message = 'Sayın ' . ($order['customer_name'] ?? '') . ', Siparişinizi başarıyla aldık. Siparişinizdeki ürünler: '
                . ($order['products'] ?? '') . '. Toplam tutar: '
                . number_format((float) ($order['total_price'] ?? 0), 2, ',', '.')
                . ' TL. Teşekkür eder, iyi günler dileriz.';
        }

        $res = netgsm_api_send_get(
            trim((string) ($cfg['username'] ?? '')),
            (string) ($cfg['password'] ?? ''),
            trim((string) ($cfg['header'] ?? '')),
            $gsmno,
            $message,
            trim((string) ($cfg['appkey'] ?? ''))
        );

        if (!$res['ok'] && isset($_SERVER['HTTP_HOST'])) {
            error_log('NETGSM yeni sipariş SMS hatası: ' . $res['detail']);
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('NETGSM yeni sipariş SMS istisna: ' . $e->getMessage());
        }
    }
}

/**
 * Panel üzerinden durum seçilen siparişe, ayarlı statüye geçişte tek sefer müşteri SMS'i.
 */
function netgsm_try_send_customer_sms_on_status_transition(
    PDO $pdo,
    int $orderPk,
    int $oldStatusId,
    int $newStatusId
): void {
    if ($newStatusId === $oldStatusId) {
        return;
    }

    try {
        $stmt = $pdo->query('SELECT * FROM netgsm_settings WHERE id = 1 LIMIT 1');
        $cfg = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (
            !is_array($cfg)
            || ((int) ($cfg['is_enabled'] ?? 1)) !== 1
            || ((int) ($cfg['sms_status_change_enabled'] ?? 0)) !== 1
        ) {
            return;
        }

        $trigger = (int) ($cfg['sms_status_trigger_id'] ?? 16);
        if ($trigger <= 0 || $newStatusId !== $trigger) {
            return;
        }

        if (!netgsm_credentials_usable($cfg)) {
            error_log('netgsm: durum SMS eksik API bilgisi');

            return;
        }

        $stO = $pdo->prepare(
            'SELECT o.* FROM orders o WHERE o.order_id = ? LIMIT 1'
        );
        $stO->execute([$orderPk]);
        /** @var array<string,mixed>|false $order */
        $order = $stO->fetch(PDO::FETCH_ASSOC);

        if (!is_array($order)) {
            return;
        }

        $gsmno = netgsm_normalize_gsm_for_tr((string) ($order['customer_phone'] ?? ''));
        if ($gsmno === '' || strlen($gsmno) < 10) {
            return;
        }

        $orderIdStr = (string) $orderPk;
        $tpl = trim((string) ($cfg['message_on_status'] ?? ''));
        if ($tpl === '') {
            $tpl = 'Sayın {customer_name}, siparişiniz (No:{order_id}) onay sürecindedir / güncellenmiştir. Teşekkür ederiz.';
        }

        $message = html_entity_decode(
            strip_tags(netgsm_customer_message_fill($tpl, $order, $orderIdStr)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $res = netgsm_api_send_get(
            trim((string) ($cfg['username'] ?? '')),
            (string) ($cfg['password'] ?? ''),
            trim((string) ($cfg['header'] ?? '')),
            $gsmno,
            $message,
            trim((string) ($cfg['appkey'] ?? ''))
        );

        if (!$res['ok'] && isset($_SERVER['HTTP_HOST'])) {
            error_log('NETGSM durum SMS hatası (sipariş ' . $orderPk . '): ' . $res['detail']);
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('NETGSM durum SMS istisna: ' . $e->getMessage());
        }
    }
}
