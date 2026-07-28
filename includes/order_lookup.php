<?php

declare(strict_types=1);

/**
 * Sipariş sorgulama — local (3dhesap DB) veya panel (YQ API).
 */

function order_lookup_source(PDO $pdo): string
{
    try {
        $has = $pdo->query("SHOW COLUMNS FROM checkout_module_settings LIKE 'order_lookup_source'");
        if ($has instanceof PDOStatement && ! $has->fetch()) {
            return 'local';
        }
        $src = $pdo->query("SELECT COALESCE(order_lookup_source, 'local') FROM checkout_module_settings WHERE id = 1")->fetchColumn();

        return in_array((string) $src, ['local', 'panel'], true) ? (string) $src : 'local';
    } catch (Throwable $e) {
        return 'local';
    }
}

/**
 * @return array{ok: bool, message?: string, order?: array<string, mixed>, source?: string}
 */
function order_lookup_by_phone(PDO $pdo, string $customerPhone): array
{
    $cleaned = preg_replace('/\s+/', '', $customerPhone) ?? '';
    $digits = preg_replace('/[^0-9]/', '', $cleaned) ?? '';

    if (strlen($digits) < 10) {
        return ['ok' => false, 'message' => 'Telefon numarası en az 10 haneli olmalıdır.'];
    }

    $source = order_lookup_source($pdo);

    if ($source === 'panel') {
        return order_lookup_from_panel($digits);
    }

    return order_lookup_from_local_db($pdo, $digits);
}

/**
 * @return array{ok: bool, message?: string, order?: array<string, mixed>, source?: string}
 */
function order_lookup_from_local_db(PDO $pdo, string $digits): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT o.*, s.status_name,
                    i.product_id, i.quantity, i.price, p.product_name, p.product_image
             FROM orders o
             JOIN order_status s ON o.order_status_id = s.order_status_id
             JOIN order_items i ON o.order_id = i.order_id
             JOIN products p ON i.product_id = p.product_id
             WHERE REPLACE(REPLACE(REPLACE(o.customer_phone, " ", ""), "-", ""), "+", "") LIKE CONCAT("%", ?, "%")
             ORDER BY o.order_date DESC
             LIMIT 1'
        );
        $stmt->execute([substr($digits, -10)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! is_array($row) || empty($row['order_id'])) {
            return ['ok' => false, 'message' => 'Bu telefon numarasıyla ilgili bir sipariş bulunamadı.', 'source' => 'local'];
        }

        return [
            'ok' => true,
            'source' => 'local',
            'order' => order_lookup_normalize_local_row($pdo, $row),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Sorgu sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.', 'source' => 'local'];
    }
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function order_lookup_normalize_local_row(PDO $pdo, array $row): array
{
    $orderNotes = (string) ($row['order_notes'] ?? '');
    $variants = '';
    if (! empty($row['variants'])) {
        $variants = (string) $row['variants'];
    } elseif ($orderNotes !== '' && preg_match('/Varyant(?:lar)?:\s*(.+)/i', $orderNotes, $m)) {
        $variants = trim($m[1]);
        $orderNotes = trim(preg_replace('/Varyant(?:lar)?:\s*(.+)/i', '', $orderNotes) ?? $orderNotes);
    }

    $cityName = (string) ($row['customer_city'] ?? '');
    $distName = (string) ($row['customer_district'] ?? '');
    if (ctype_digit($cityName)) {
        try {
            $cStmt = $pdo->prepare('SELECT city_name FROM cities WHERE city_id = ? LIMIT 1');
            $cStmt->execute([(int) $cityName]);
            $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($cRow) && ! empty($cRow['city_name'])) {
                $cityName = (string) $cRow['city_name'];
            }
        } catch (Throwable $e) {
        }
    }
    if (ctype_digit($distName)) {
        try {
            $dStmt = $pdo->prepare('SELECT district_name FROM districts WHERE district_id = ? LIMIT 1');
            $dStmt->execute([(int) $distName]);
            $dRow = $dStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($dRow) && ! empty($dRow['district_name'])) {
                $distName = (string) $dRow['district_name'];
            }
        } catch (Throwable $e) {
        }
    }

    $statusId = (string) ($row['order_status_id'] ?? '');

    return [
        'order_id' => (int) $row['order_id'],
        'customer_name' => (string) ($row['customer_name'] ?? ''),
        'customer_phone' => (string) ($row['customer_phone'] ?? ''),
        'customer_address' => (string) ($row['customer_address'] ?? ''),
        'customer_city' => $cityName,
        'customer_district' => $distName,
        'product_name' => (string) ($row['product_name'] ?? ''),
        'price' => (float) ($row['price'] ?? 0),
        'order_notes' => $orderNotes,
        'variants' => $variants,
        'status_id' => $statusId,
        'status_name' => (string) ($row['status_name'] ?? order_lookup_status_fallback($statusId)),
        'reference' => (string) ($row['order_id'] ?? ''),
    ];
}

/**
 * @return array{ok: bool, message?: string, order?: array<string, mixed>, source?: string}
 */
function order_lookup_from_panel(string $digits): array
{
    if (! is_file(__DIR__.'/laravel4_sync.php')) {
        return ['ok' => false, 'message' => 'Panel bağlantı dosyası bulunamadı.', 'source' => 'panel'];
    }

    require_once __DIR__.'/laravel4_sync.php';
    $cfg = laravel4_sync_config();

    if ($cfg['order_api_key'] === '' || $cfg['panel_base_url'] === '') {
        return ['ok' => false, 'message' => 'Panel API ayarları eksik (laravel4_config.php).', 'source' => 'panel'];
    }

    $url = $cfg['panel_base_url'].'/api/external-sync/order-lookup';
    $body = json_encode([
        'customer_phone' => $digits,
        'integration_source_key' => $cfg['order_source_key'],
    ], JSON_UNESCAPED_UNICODE);

    if ($body === false) {
        return ['ok' => false, 'message' => 'İstek oluşturulamadı.', 'source' => 'panel'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer '.$cfg['order_api_key'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode === 404) {
        return ['ok' => false, 'message' => 'Bu telefon numarasıyla ilgili bir sipariş bulunamadı.', 'source' => 'panel'];
    }

    $decoded = json_decode((string) $response, true);
    if ($httpCode === 401) {
        return ['ok' => false, 'message' => 'Panel API anahtarı geçersiz.', 'source' => 'panel'];
    }
    if ($httpCode >= 400 || ! is_array($decoded) || empty($decoded['success'])) {
        $msg = is_array($decoded) ? (string) ($decoded['message'] ?? 'Panel sorgusu başarısız.') : 'Panel sorgusu başarısız.';

        return ['ok' => false, 'message' => $msg, 'source' => 'panel'];
    }

    $d = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

    return [
        'ok' => true,
        'source' => 'panel',
        'order' => [
            'order_id' => (int) ($d['panel_order_id'] ?? 0),
            'customer_name' => (string) ($d['customer_name'] ?? ''),
            'customer_phone' => (string) ($d['customer_phone'] ?? ''),
            'customer_address' => (string) ($d['customer_address'] ?? ''),
            'customer_city' => (string) ($d['customer_city'] ?? ''),
            'customer_district' => (string) ($d['customer_district'] ?? ''),
            'product_name' => (string) ($d['product_name'] ?? ''),
            'price' => (float) ($d['price'] ?? 0),
            'order_notes' => (string) ($d['order_notes'] ?? ''),
            'customer_notes' => (string) ($d['customer_notes'] ?? ''),
            'variants' => (string) ($d['variant_summary'] ?? ''),
            'status_id' => (string) ($d['status_id'] ?? ''),
            'status_name' => (string) ($d['status_name'] ?? 'Bilinmiyor'),
            'reference' => (string) ($d['reference'] ?? $d['external_order_id'] ?? ''),
            'cargo_company' => (string) ($d['cargo_company'] ?? ''),
            'payment_method' => (string) ($d['payment_method'] ?? ''),
        ],
    ];
}

function order_lookup_status_fallback(string $statusId): string
{
    return match ($statusId) {
        '1' => 'Beklemede',
        '2' => 'Kargoya Verildi',
        '3' => 'Teslim Edildi',
        '7' => 'Arandı',
        '12' => 'Arandı 2',
        '13' => 'Ulaşılamadı',
        '14' => 'İptal',
        '15' => 'İade',
        '16' => 'Onaylandı',
        default => 'Bilinmeyen Durum',
    };
}

function order_lookup_status_css_class(string $statusId): string
{
    return match ($statusId) {
        '1' => 'text-danger',
        '2', '3', '16' => 'text-success',
        '7', '12', '13' => 'text-warning',
        '14', '15' => 'text-muted',
        default => 'text-secondary',
    };
}
