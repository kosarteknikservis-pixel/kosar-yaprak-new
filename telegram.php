<?php
declare(strict_types=1);

/**
 * Telegram bildirimleri — anahtar + olay bazlı (yeni sipariş, destek, bayilik, panel durumu).
 */

/**
 * @return array<string, mixed>|null
 */
function telegram_load_settings(?PDO $pdo = null): ?array
{
    if (!$pdo instanceof PDO) {
        if (!is_file(__DIR__ . '/db.php')) {
            return null;
        }
        require __DIR__ . '/db.php';
    }

    try {
        $stmt = $pdo->query('SELECT * FROM telegram_settings WHERE id = 1 LIMIT 1');

        /** @var array<string, mixed>|false $row */
        $row = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        if (is_file(__DIR__ . '/includes/app_log.php')) {
            require_once __DIR__ . '/includes/app_log.php';
            app_log('telegram', 'load_settings: ' . $e->getMessage());
        } else {
            error_log('telegram_load_settings: ' . $e->getMessage());
        }

        return null;
    }
}

/**
 * @param 'new_order'|'support'|'partner'|'admin_status' $event
 */
function telegram_event_is_allowed(?array $row, string $event): bool
{
    $isOn = array_key_exists('is_enabled', $row) ? (int) $row['is_enabled'] : 1;
    if ($isOn !== 1) {
        return false;
    }

    $yv = static function (string $column, int $defaultOnMissing) use ($row): int {
        if (!array_key_exists($column, $row)) {
            return $defaultOnMissing;
        }

        return (int) $row[$column];
    };

    return match ($event) {
        'new_order' => $yv('notify_new_order', 1) === 1,
        'support' => $yv('notify_support', 1) === 1,
        'partner' => $yv('notify_partner', 1) === 1,
        'admin_status' => $yv('notify_admin_status', 0) === 1,
        default => false,
    };
}

/**
 * @param 'new_order'|'support'|'partner'|'admin_status' $event
 */
function sendTelegramNotification(PDO $pdo, string $event, string $message_text): string
{
    $tg = telegram_load_settings($pdo);
    if ($tg === null) {
        return 'Telegram: ayar kaydı bulunamadı.';
    }

    if (!telegram_event_is_allowed($tg, $event)) {
        return 'Telegram: Bu olay türü veya tüm bildirimler kapalı.';
    }

    $bot_token = trim((string) ($tg['bot_token'] ?? ''));
    $chat_id = trim((string) ($tg['chat_id'] ?? ''));

    if ($bot_token === '' || $chat_id === '') {
        return 'Telegram: Bot token veya chat ID eksik.';
    }

    if (!extension_loaded('curl')) {
        return 'Telegram: sunucuda cURL yüklü değil.';
    }

    /** @disregard */
    $api_url = 'https://api.telegram.org/bot' . $bot_token . '/sendMessage';
    /** @disregard */
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message_text,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    /** @disregard */
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        /** @disregard */
        $err = curl_error($ch);
        curl_close($ch);

        return 'Telegram cURL: ' . ($err ?: 'bilinmeyen hata');
    }

    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return 'Telegram: boş yanıt';
    }

    /** @var array<string, mixed>|null $response_data */
    $response_data = json_decode($response, true);
    if (!is_array($response_data)) {
        return 'Telegram: geçersiz JSON yanıtı';
    }

    if (!empty($response_data['ok'])) {
        $result = 'Mesaj başarıyla gönderildi.';
        telegram_log_send_result($event, $result);

        return $result;
    }

    $desc = is_string($response_data['description'] ?? null)
        ? (string) $response_data['description']
        : 'bilinmeyen API hatası';

    if (isset($_SERVER['HTTP_HOST'])) {
        error_log('Telegram API: ' . $desc);
    }

    $result = 'Telegram API: ' . $desc;
    telegram_log_send_result($event, $result);

    return $result;
}

function telegram_log_send_result(string $event, string $result): void
{
    if (! is_file(__DIR__ . '/includes/app_log.php')) {
        return;
    }
    require_once __DIR__ . '/includes/app_log.php';
    $ok = str_starts_with($result, 'Mesaj başarıyla');
    app_log('telegram', $ok ? 'sent' : 'fail', [
        'event' => $event,
        'result' => $result,
    ]);
}

/**
 * Sipariş kaydından Telegram yeni sipariş bildirimi (vitrin + panel manuel).
 */
function telegram_notify_new_order(PDO $pdo, int $orderId, string $heading = 'Yeni Sipariş'): void
{
    try {
        $st = $pdo->prepare(
            'SELECT o.*, c.city_name AS customer_city_name, d.district_name AS customer_district_name,
                    p.product_name, oi.price
             FROM orders o
             LEFT JOIN cities c ON o.customer_city = c.city_id
             LEFT JOIN districts d ON o.customer_district = d.district_id
             LEFT JOIN order_items oi ON oi.order_id = o.order_id
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE o.order_id = ?
             LIMIT 1'
        );
        $st->execute([$orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }

        $customer_name = (string) ($row['customer_name'] ?? '');
        $customer_phone = (string) ($row['customer_phone'] ?? '');
        $customer_address = (string) ($row['customer_address'] ?? '');
        $city = (string) ($row['customer_city_name'] ?? '');
        $district = (string) ($row['customer_district_name'] ?? '');
        $order_notes = (string) ($row['order_notes'] ?? '');
        $source = (string) ($row['source'] ?? '');
        $price = (string) ($row['price'] ?? '');

        $message = $heading . ': '
            . "\nMüşteri: {$customer_name}\nTelefon: {$customer_phone}\nAdres: {$customer_address}, {$district}, {$city}"
            . "\nTutar: {$price} TL\nSipariş ID: {$orderId}\nNotlar: {$order_notes}\nKaynak: {$source}";

        $product_name = trim((string) ($row['product_name'] ?? ''));
        if ($product_name !== '') {
            $message .= "\nÜrün: {$product_name}";
        }

        $invoice_vkn = trim((string) ($row['invoice_vkn'] ?? ''));
        $invoice_tax_office = trim((string) ($row['invoice_tax_office'] ?? ''));
        $invoice_company_name = trim((string) ($row['invoice_company_name'] ?? ''));
        $invoice_address = trim((string) ($row['invoice_address'] ?? ''));
        if ($invoice_vkn !== '' || $invoice_tax_office !== '' || $invoice_company_name !== '' || $invoice_address !== '') {
            $message .= "\n--- Kurumsal fatura ---\nVKN: {$invoice_vkn}\nVergi D.: {$invoice_tax_office}\nÜnvan: {$invoice_company_name}\nFatura adr.: {$invoice_address}";
        }

        sendTelegramNotification($pdo, 'new_order', $message);
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('telegram_notify_new_order: ' . $e->getMessage());
        }
    }
}

/**
 * Online ödeme (PayTR / iyzico) tamamlanmadığında — farklı başlık ve uyarı metni.
 */
function telegram_notify_payment_failed(PDO $pdo, int $orderId, string $gatewayLabel = 'Online ödeme'): void
{
    try {
        $st = $pdo->prepare(
            'SELECT o.*, c.city_name AS customer_city_name, d.district_name AS customer_district_name,
                    p.product_name, oi.price, pm.method_name AS payment_method_name
             FROM orders o
             LEFT JOIN cities c ON o.customer_city = c.city_id
             LEFT JOIN districts d ON o.customer_district = d.district_id
             LEFT JOIN order_items oi ON oi.order_id = o.order_id
             LEFT JOIN products p ON p.product_id = oi.product_id
             LEFT JOIN payment_methods pm ON pm.payment_method_id = o.payment_method_id
             WHERE o.order_id = ?
             LIMIT 1'
        );
        $st->execute([$orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return;
        }

        $customer_name = (string) ($row['customer_name'] ?? '');
        $customer_phone = (string) ($row['customer_phone'] ?? '');
        $customer_address = (string) ($row['customer_address'] ?? '');
        $city = (string) ($row['customer_city_name'] ?? '');
        $district = (string) ($row['customer_district_name'] ?? '');
        $order_notes = (string) ($row['order_notes'] ?? '');
        $source = (string) ($row['reklam'] ?? '');
        if ($source === '' || $source === 'Reklam Olmayabilir') {
            $source = (string) ($row['source'] ?? '');
        }
        $price = (string) ($row['price'] ?? '');
        $paymentMethod = trim((string) ($row['payment_method_name'] ?? ''));
        $product_name = trim((string) ($row['product_name'] ?? ''));

        $message = '⚠️ ÖDEME TAMAMLANMADI (' . $gatewayLabel . ')'
            . "\n━━━━━━━━━━━━━━━━"
            . "\nMüşteri: {$customer_name}"
            . "\nTelefon: {$customer_phone}"
            . "\nAdres: {$customer_address}, {$district}, {$city}"
            . "\nTutar: {$price} TL"
            . "\nSipariş #: {$orderId}";
        if ($paymentMethod !== '') {
            $message .= "\nÖdeme yöntemi: {$paymentMethod}";
        }
        if ($product_name !== '') {
            $message .= "\nÜrün: {$product_name}";
        }
        if ($order_notes !== '') {
            $message .= "\nNot: {$order_notes}";
        }
        if ($source !== '') {
            $message .= "\nKaynak: {$source}";
        }
        $message .= "\n━━━━━━━━━━━━━━━━"
            . "\n→ Kart ödemesi tamamlanmadı. Müşteriyi arayın.";

        sendTelegramNotification($pdo, 'new_order', $message);
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('telegram_notify_payment_failed: ' . $e->getMessage());
        }
    }
}

/**
 * Panelde sipariş durumu değişince (isteğe bağlı) yöneticiye bildirim.
 */
function telegram_notify_admin_status_change(PDO $pdo, int $orderId, int $oldStatusId, int $newStatusId): void
{
    try {
        if ($oldStatusId === $newStatusId) {
            return;
        }

        $tg = telegram_load_settings($pdo);
        if ($tg === null || !telegram_event_is_allowed($tg, 'admin_status')) {
            return;
        }

        $st = $pdo->prepare('SELECT status_name FROM order_status WHERE order_status_id = ?');
        $st->execute([$oldStatusId]);
        $oldName = (string) ($st->fetchColumn() ?: '#' . $oldStatusId);
        $st->execute([$newStatusId]);
        $newName = (string) ($st->fetchColumn() ?: '#' . $newStatusId);

        $text = '📋 Sipariş #' . $orderId . "\n";
        $text .= "Durum: {$oldName} → {$newName}";

        sendTelegramNotification($pdo, 'admin_status', $text);
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('telegram_notify_admin_status_change: ' . $e->getMessage());
        }
    }
}
