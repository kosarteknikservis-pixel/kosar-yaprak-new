<?php

declare(strict_types=1);

/**
 * WhatsApp / Instagram sabit buton tıklamaları — IP başına kanalda bir kez sayılır.
 */

function social_click_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return '0.0.0.0';
    }
    return mb_substr($ip, 0, 45);
}

/** Türkiye WhatsApp: wa.me için 90 ile başlayan rakam dizisi. */
function social_whatsapp_normalize(string $raw): string
{
    $num = preg_replace('/\D+/', '', trim($raw));
    if ($num === '') {
        return '';
    }

    if (str_starts_with($num, '00')) {
        $num = substr($num, 2);
    }

    if (str_starts_with($num, '0') && ! str_starts_with($num, '90')) {
        $num = substr($num, 1);
    }

    if (strlen($num) === 10 && str_starts_with($num, '5')) {
        return '90' . $num;
    }

    if (str_starts_with($num, '90') && strlen($num) >= 12) {
        return $num;
    }

    if (strlen($num) === 11 && $num[0] === '5') {
        return '90' . $num;
    }

    return $num;
}

function social_click_normalize_channel(string $raw): ?string
{
    $c = strtolower(trim($raw));
    if ($c === 'whatsapp' || $c === 'wa') {
        return 'whatsapp';
    }
    if ($c === 'instagram' || $c === 'ig') {
        return 'instagram';
    }
    return null;
}

function social_click_page_name(): string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        $path = (string) (parse_url($ref, PHP_URL_PATH) ?? '');
        $base = basename($path);
        if ($base !== '' && $base !== '.' && $base !== '/') {
            return mb_substr($base, 0, 128);
        }
    }
    $from = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
    return $from !== '' ? mb_substr($from, 0, 128) : 'index.php';
}

/**
 * @return array{ok:bool, inserted:bool, channel:string}
 */
function social_click_track(PDO $pdo, string $channel, ?string $pageName = null): array
{
    $ch = social_click_normalize_channel($channel);
    if ($ch === null) {
        return ['ok' => false, 'inserted' => false, 'channel' => ''];
    }

    $ip = social_click_client_ip();
    $page = $pageName !== null && $pageName !== '' ? mb_substr($pageName, 0, 128) : social_click_page_name();
    $now = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO social_button_clicks (channel, ip_address, page_name, clicked_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$ch, $ip, $page, $now]);
        $inserted = $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return ['ok' => false, 'inserted' => false, 'channel' => $ch];
    }

    return ['ok' => true, 'inserted' => $inserted, 'channel' => $ch];
}

/**
 * @return array{whatsapp:?string, instagram:?string}
 */
function social_click_target_urls(PDO $pdo): array
{
    $out = ['whatsapp' => null, 'instagram' => null];
    try {
        $row = $pdo->query(
            'SELECT show_whatsapp, whatsapp_number, show_instagram, instagram_username
             FROM notification_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $out;
        }
        if (!empty($row['show_whatsapp']) && trim((string) ($row['whatsapp_number'] ?? '')) !== '') {
            $num = social_whatsapp_normalize((string) $row['whatsapp_number']);
            if ($num !== '') {
                $out['whatsapp'] = 'https://wa.me/' . $num;
            }
        }
        if (!empty($row['show_instagram']) && trim((string) ($row['instagram_username'] ?? '')) !== '') {
            $user = preg_replace('/[^A-Za-z0-9._]/', '', (string) $row['instagram_username']);
            if ($user !== '') {
                $out['instagram'] = 'https://instagram.com/' . $user;
            }
        }
    } catch (Throwable $e) {
        return $out;
    }
    return $out;
}

function social_click_redirect_for(PDO $pdo, string $channel): void
{
    $urls = social_click_target_urls($pdo);
    $ch = social_click_normalize_channel($channel);
    if ($ch === null) {
        header('Location: index.php', true, 302);
        exit;
    }

    social_click_track($pdo, $ch);

    try {
        require_once __DIR__ . '/conversion_tracking.php';
        conversion_send_social_click($pdo, $ch);
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('social_click_conversion: ' . $e->getMessage());
        }
    }

    $target = $urls[$ch] ?? null;
    if ($target === null) {
        header('Location: index.php', true, 302);
        exit;
    }

    header('Location: ' . $target, true, 302);
    exit;
}

/**
 * @return array{whatsapp:int, instagram:int, total:int}
 */
function social_click_stats(PDO $pdo, ?string $dateFrom = null, ?string $dateTo = null): array
{
    $stats = ['whatsapp' => 0, 'instagram' => 0, 'total' => 0];
    $where = '';
    $params = [];

    if ($dateFrom !== null && $dateFrom !== '') {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'clicked_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== null && $dateTo !== '') {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'clicked_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT channel, COUNT(*) AS cnt FROM social_button_clicks' . $where . ' GROUP BY channel'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $ch = (string) ($row['channel'] ?? '');
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($ch === 'whatsapp') {
                $stats['whatsapp'] = $cnt;
            } elseif ($ch === 'instagram') {
                $stats['instagram'] = $cnt;
            }
        }
        $stats['total'] = $stats['whatsapp'] + $stats['instagram'];
    } catch (Throwable $e) {
        return $stats;
    }
    return $stats;
}

/**
 * @return list<array<string,mixed>>
 */
function social_click_list(PDO $pdo, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 200): array
{
    $where = '';
    $params = [];

    if ($dateFrom !== null && $dateFrom !== '') {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'clicked_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== null && $dateTo !== '') {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'clicked_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    $limit = max(1, min(500, $limit));

    try {
        $stmt = $pdo->prepare(
            'SELECT channel, ip_address, page_name, clicked_at
             FROM social_button_clicks' . $where . '
             ORDER BY clicked_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
