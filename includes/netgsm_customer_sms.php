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
 * @return array<string, mixed>|null
 */
function netgsm_load_settings(PDO $pdo): ?array
{
    $stmt = $pdo->query('SELECT * FROM netgsm_settings WHERE id = 1 LIMIT 1');
    $row = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    return is_array($row) ? $row : null;
}

function netgsm_default_new_order_message(): string
{
    return 'Sayın {customer_name}, siparişiniz alınmıştır. Ürün: {products}. Siparişiniz 1-3 iş günü içinde kargoya verilecektir. Kargoya verildiğinde SMS ile bilgilendirileceksiniz. Teşekkür ederiz.';
}

function netgsm_default_status_message(): string
{
    return 'Sayın {customer_name}, siparişiniz (No:{order_id}) kargoya verildi. Takip no: {tracking_number}. Teşekkür ederiz.';
}

function netgsm_is_placeholder_sms_template(string $template): bool
{
    $t = trim($template);
    if ($t === '') {
        return true;
    }
    if (preg_match('/^\d{1,8}$/', $t)) {
        return true;
    }
    if (mb_strlen($t) < 15) {
        return true;
    }

    return false;
}

function netgsm_order_product_names(PDO $pdo, int $orderId): string
{
    if ($orderId <= 0) {
        return '';
    }

    try {
        $st = $pdo->prepare(
            'SELECT GROUP_CONCAT(p.product_name ORDER BY oi.order_item_id SEPARATOR ", ") AS names
             FROM order_items oi
             INNER JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = ?'
        );
        $st->execute([$orderId]);

        return trim((string) ($st->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * @param  array<string, mixed>  $order
 */
function netgsm_prepare_order_for_sms(PDO $pdo, array $order, string $orderIdStr): array
{
    $orderId = (int) ($order['order_id'] ?? $orderIdStr);
    $names = netgsm_order_product_names($pdo, $orderId);
    if ($names !== '') {
        $order['products'] = $names;
    }

    return $order;
}

function netgsm_resolve_new_order_message(string $template, array $order, string $orderIdStr): string
{
    $tpl = trim($template);
    if (netgsm_is_placeholder_sms_template($tpl)) {
        $tpl = netgsm_default_new_order_message();
    }

    return html_entity_decode(
        strip_tags(netgsm_customer_message_fill($tpl, $order, $orderIdStr)),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
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
        $cfg = netgsm_load_settings($pdo);

        if (
            !is_array($cfg)
            || ((int) ($cfg['is_enabled'] ?? 1)) !== 1
            || ((int) ($cfg['sms_new_order_enabled'] ?? 1)) !== 1
        ) {
            return;
        }

        if (!netgsm_credentials_usable($cfg)) {
            error_log('sms: yeni sipariş SMS kapalı — eksik API bilgisi');

            return;
        }

        $gsmno = (string) ($order['customer_phone'] ?? '');
        if (trim($gsmno) === '') {
            return;
        }

        $order = netgsm_prepare_order_for_sms($pdo, $order, $orderIdStr);
        $message = netgsm_resolve_new_order_message((string) ($cfg['message'] ?? ''), $order, $orderIdStr);

        require_once __DIR__ . '/transactional_sms.php';
        $ok = sendTransactionalSms($gsmno, $message, $cfg);

        if (! $ok && isset($_SERVER['HTTP_HOST'])) {
            error_log('Yeni sipariş SMS hatası: ' . smsLastErrorGet());
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
            error_log('sms: durum SMS eksik API bilgisi');

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

        $gsmno = (string) ($order['customer_phone'] ?? '');
        if (trim($gsmno) === '') {
            return;
        }

        $orderIdStr = (string) $orderPk;
        $order = netgsm_prepare_order_for_sms($pdo, $order, $orderIdStr);
        $tpl = trim((string) ($cfg['message_on_status'] ?? ''));
        if (netgsm_is_placeholder_sms_template($tpl)) {
            $tpl = netgsm_default_status_message();
        }

        $message = html_entity_decode(
            strip_tags(netgsm_customer_message_fill($tpl, $order, $orderIdStr)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        require_once __DIR__ . '/transactional_sms.php';
        $ok = sendTransactionalSms($gsmno, $message, $cfg);

        if (! $ok && isset($_SERVER['HTTP_HOST'])) {
            error_log('Durum SMS hatası (sipariş ' . $orderPk . '): ' . smsLastErrorGet());
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('NETGSM durum SMS istisna: ' . $e->getMessage());
        }
    }
}
