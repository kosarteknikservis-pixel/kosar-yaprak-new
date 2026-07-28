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
        return 'Mesaj başarıyla gönderildi.';
    }

    $desc = is_string($response_data['description'] ?? null)
        ? (string) $response_data['description']
        : 'bilinmeyen API hatası';

    if (isset($_SERVER['HTTP_HOST'])) {
        error_log('Telegram API: ' . $desc);
    }

    return 'Telegram API: ' . $desc;
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
