<?php

declare(strict_types=1);

/**
 * 3dhesap → YQ Panel entegrasyonu (sipariş API + form webhook).
 * Ayarlar: includes/laravel4_config.php
 */

function laravel4_sync_config(): array
{
    static $cfg = null;

    if ($cfg !== null) {
        return $cfg;
    }

    $fileCfg = [];
    $configPath = __DIR__.'/laravel4_config.php';

    if (is_file($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            $fileCfg = $loaded;
        }
    }

    $cfg = [
        'panel_base_url' => rtrim(
            (string) (getenv('LARAVEL4_PANEL_URL') ?: ($fileCfg['panel_base_url'] ?? 'http://laravel4.test')),
            '/'
        ),
        'order_api_key' => (string) (getenv('LARAVEL4_API_KEY') ?: ($fileCfg['order_api_key'] ?? '')),
        'site_origin' => (string) (getenv('ORTAK_SITE_ORIGIN') ?: ($fileCfg['site_origin'] ?? '')),
        'order_source_key' => (string) (getenv('LARAVEL_INTEGRATION_SOURCE_KEY') ?: ($fileCfg['order_source_key'] ?? 'quattro_web')),
        'form_source_key' => (string) (getenv('LARAVEL4_FORM_SOURCE_KEY') ?: ($fileCfg['form_source_key'] ?? 'website')),
        'webhook_secret' => (string) (getenv('LARAVEL4_WEBHOOK_SECRET') ?: ($fileCfg['webhook_secret'] ?? '')),
    ];

    return $cfg;
}

function laravel4_sync_log(string $message): void
{
    error_log('['.date('Y-m-d H:i:s').'] '.$message."\n", 3, dirname(__DIR__).'/hata_loglari.log');
}

/**
 * @param  array<string, mixed>  $orderData
 * @param  array<string, string>  $cfg
 * @return array<string, mixed>
 */
function laravel4_order_payload_for_ortak_panel(array $orderData, array $cfg): array
{
    $items = is_array($orderData['items'] ?? null) ? $orderData['items'] : [];
    $productName = 'Ürün';
    $quantity = 1;

    if ($items !== []) {
        $names = [];
        $quantity = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['product_name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
            $quantity += max(1, (int) ($item['quantity'] ?? 1));
        }
        if ($names !== []) {
            $productName = implode(' + ', $names);
        }
        $quantity = max(1, $quantity);
    }

    $siteOrigin = trim((string) ($cfg['site_origin'] ?? ''));
    if ($siteOrigin === '' && ! empty($orderData['site_url'])) {
        $host = parse_url((string) $orderData['site_url'], PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $siteOrigin = $host;
        }
    }
    if ($siteOrigin === '') {
        $siteOrigin = 'kosarvantilator.com';
    }

    $payload = [
        'api_key' => (string) ($cfg['order_api_key'] ?? ''),
        'site_origin' => $siteOrigin,
        'client_order_id' => (string) ($orderData['external_order_id'] ?? ''),
        'order_total' => (float) ($orderData['total_amount'] ?? 0),
        'customer_name' => (string) ($orderData['customer_name'] ?? ''),
        'customer_phone' => (string) ($orderData['customer_phone'] ?? ''),
        'customer_city' => (string) ($orderData['customer_city'] ?? ''),
        'customer_district' => (string) ($orderData['customer_district'] ?? ''),
        'customer_address' => (string) ($orderData['customer_address'] ?? ''),
        'product_name' => $productName,
        'product_quantity' => $quantity,
        'payment_method' => (string) ($orderData['payment_method'] ?? ''),
        'order_note' => trim((string) ($orderData['order_notes'] ?? '')),
        'customer_ip' => $orderData['customer_ip'] ?? null,
        'order_date' => $orderData['order_date'] ?? null,
        'invoice_tax_id' => $orderData['invoice_vkn'] ?? null,
        'invoice_tax_office' => $orderData['invoice_tax_office'] ?? null,
        'invoice_company' => $orderData['invoice_company_name'] ?? null,
        'invoice_address' => $orderData['invoice_address'] ?? null,
        'items' => $items,
    ];

    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
        if (! empty($orderData[$key])) {
            $payload[$key] = $orderData[$key];
        }
    }

    // Ortak panel: ref = kampanya kodu (?ref= / orders.referrer). ad_source (Meta/Google) ref'e yazılmaz.
    $campaignRef = '';
    if (is_file(__DIR__.'/attribution_helpers.php')) {
        require_once __DIR__.'/attribution_helpers.php';
        if (function_exists('attribution_campaign_ref')) {
            $campaignRef = (string) (attribution_campaign_ref($orderData) ?? '');
        }
    }
    if ($campaignRef === '') {
        foreach (['ref', 'referrer'] as $key) {
            $cand = trim((string) ($orderData[$key] ?? ''));
            if ($cand !== '' && ! preg_match('#^https?://#i', $cand)) {
                $campaignRef = mb_substr($cand, 0, 128);
                break;
            }
        }
    }
    if ($campaignRef !== '') {
        $payload['ref'] = $campaignRef;
    }

    if (! empty($orderData['ad_source'])) {
        $payload['ad_source'] = $orderData['ad_source'];
    }

    // referrer / referrer_url = HTTP sayfa referrer (kampanya kodu değil)
    $pageReferrer = trim((string) ($orderData['referrer_url'] ?? ''));
    if ($pageReferrer === '') {
        $cand = trim((string) ($orderData['referrer'] ?? ''));
        if ($cand !== '' && preg_match('#^https?://#i', $cand)) {
            $pageReferrer = $cand;
        }
    }
    if ($pageReferrer !== '') {
        $payload['referrer'] = $pageReferrer;
        $payload['referrer_url'] = $pageReferrer;
    }

    return $payload;
}

/**
 * @param  array<string, mixed>  $orderData
 */
function laravel4_sync_order(array $orderData): bool
{
    try {
        $cfg = laravel4_sync_config();

        if ($cfg['order_api_key'] === '') {
            laravel4_sync_log('Ortak panel order sync SKIPPED: order_api_key boş');

            return false;
        }

        $url = $cfg['panel_base_url'].'/api/receive.php';
        $payload = laravel4_order_payload_for_ortak_panel($orderData, $cfg);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $orderId = (string) ($orderData['external_order_id'] ?? '?');
        $responseData = is_string($response) ? json_decode($response, true) : null;
        $ok = $httpCode === 200
            && is_array($responseData)
            && (($responseData['status'] ?? '') === 'success');

        if ($ok) {
            laravel4_sync_log("Ortak panel order sync SUCCESS: Order #{$orderId}");
            laravel4_mark_order_synced($orderId);

            return true;
        }

        laravel4_sync_log("Ortak panel order sync FAILED: Order #{$orderId} | HTTP: {$httpCode} | Response: {$response} | cURL: {$curlError}");

        return false;
    } catch (Throwable $e) {
        laravel4_sync_log('Ortak panel order sync EXCEPTION: '.$e->getMessage());

        return false;
    }
}

/**
 * "panel_synced" kolonu yoksa ekler (idempotent, güvenli). İstek başına bir kez.
 */
function laravel4_ensure_sync_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $col = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'panel_synced'")->fetch();
        if (! $col) {
            $pdo->exec('ALTER TABLE `orders` ADD COLUMN `panel_synced` TINYINT(1) NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE `orders` ADD INDEX `orders_panel_synced_idx` (`panel_synced`)');
            // ÖNEMLİ: kolon YENİ eklendi → mevcut tüm siparişler zaten panelde/işlenmiş kabul edilir (=1).
            // Böylece retry bunları YENİDEN göndermez; panelde var olan sipariş güncellenip çağrı-merkezi
            // durum/atama verisi EZİLMEZ. Yalnızca bundan SONRA gelen (default 0) siparişler retry kapsamına girer.
            $pdo->exec('UPDATE `orders` SET `panel_synced` = 1');
        }
    } catch (Throwable $e) {
        laravel4_sync_log('panel_synced kolon ensure hatasi: '.$e->getMessage());
    }
}

/**
 * Başarılı gönderim sonrası siparişi "panele iletildi" işaretler. Checkout'u ASLA bozmaz (try/catch).
 */
function laravel4_mark_order_synced(string $externalOrderId): void
{
    if (! ctype_digit($externalOrderId)) {
        return;
    }

    global $pdo;
    if (! ($pdo instanceof PDO)) {
        return;
    }

    try {
        laravel4_ensure_sync_column($pdo);
        $pdo->prepare('UPDATE `orders` SET `panel_synced` = 1 WHERE `order_id` = ?')
            ->execute([(int) $externalOrderId]);
    } catch (Throwable $e) {
        laravel4_sync_log('panel_synced işaretleme hatasi #'.$externalOrderId.': '.$e->getMessage());
    }
}

/**
 * @param  array<string, mixed>  $orderRow
 */
function laravel4_gateway_code_for_order(PDO $pdo, array $orderRow): string
{
    $code = trim((string) ($orderRow['gateway_code'] ?? ''));
    if ($code !== '') {
        return $code;
    }

    $paymentMethodId = (int) ($orderRow['payment_method_id'] ?? 0);
    if ($paymentMethodId > 0) {
        $st = $pdo->prepare('SELECT gateway_code FROM payment_methods WHERE payment_method_id = ? LIMIT 1');
        $st->execute([$paymentMethodId]);
        $code = trim((string) ($st->fetchColumn() ?: ''));
        if ($code !== '') {
            return $code;
        }
    }

    return 'cod';
}

/**
 * PayTR / iyzico: ortak panel ödemesiz (pending) sipariş kabul etmez; yalnızca paid sonrası gönder.
 */
function laravel4_should_skip_online_unpaid_sync(PDO $pdo, int $orderId): bool
{
    $st = $pdo->prepare('SELECT payment_status, payment_method_id, gateway_code FROM orders WHERE order_id = ? LIMIT 1');
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (! is_array($row)) {
        return true;
    }

    $gateway = laravel4_gateway_code_for_order($pdo, $row);
    if (! in_array($gateway, ['paytr', 'iyzico'], true)) {
        return false;
    }

    return ($row['payment_status'] ?? '') !== 'paid';
}

/**
 * @return array{total: float, items: list<array<string, mixed>>}
 */
function laravel4_order_items_payload(PDO $pdo, int $orderId, string $variantText = ''): array
{
    $items = [];
    $total = 0.0;

    $st = $pdo->prepare(
        'SELECT oi.quantity, oi.price, p.product_id, p.product_name, p.sku
         FROM order_items oi
         INNER JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?'
    );
    $st->execute([$orderId]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $qty = max(1, (int) ($row['quantity'] ?? 1));
        $price = (float) ($row['price'] ?? 0);
        $total += $price * $qty;
        $items[] = [
            'product_id' => (string) ($row['product_id'] ?? ''),
            'product_sku' => $row['sku'] ?? null,
            'product_name' => (string) ($row['product_name'] ?? 'Ürün'),
            'product_price' => $price,
            'quantity' => $qty,
            'variants' => $variantText !== '' ? $variantText : null,
        ];
    }

    return ['total' => $total, 'items' => $items];
}

/**
 * Ortak panel: kredi kartı (PayTR/iyzico) ödemesi onaylandıysa sipariş tutarı her zaman 1 TL gönderilir.
 * Panel order_total=0 kabul etmez; online ödeme = paid + 1 TL işareti.
 */
function laravel4_ortak_panel_order_total(PDO $pdo, int $orderId, float $computedTotal): float
{
    $st = $pdo->prepare('SELECT payment_status, payment_method_id, gateway_code FROM orders WHERE order_id = ? LIMIT 1');
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (! is_array($row)) {
        return $computedTotal > 0 ? $computedTotal : 0.0;
    }

    $gateway = laravel4_gateway_code_for_order($pdo, $row);
    if (in_array($gateway, ['paytr', 'iyzico'], true) && ($row['payment_status'] ?? '') === 'paid') {
        return 1.0;
    }

    return $computedTotal;
}

/**
 * @param  array<string, scalar|null>  $fields
 * @param  array<string, mixed>  $extra
 */
/**
 * Checkout tamamlandıktan sonra (varyant + çarkıfelek notları dahil) panele gönderir.
 *
 * @param  array<string, mixed>  $ctx
 */
function laravel4_sync_checkout_order(array $ctx): void
{
    try {
        /** @var PDO $pdo */
        $pdo = $ctx['pdo'];
        $orderId = (int) ($ctx['order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        if (laravel4_should_skip_online_unpaid_sync($pdo, $orderId)) {
            laravel4_sync_log('Ortak panel sync SKIPPED: Order #'.$orderId.' online ödeme henüz onaylanmadı');

            return;
        }

        $customerCity = $ctx['customer_city'] ?? '';
        $customerDistrict = $ctx['customer_district'] ?? '';
        $paymentMethodId = (int) ($ctx['payment_method_id'] ?? 0);
        $productId = (int) ($ctx['product_id'] ?? 0);
        $product = is_array($ctx['product'] ?? null) ? $ctx['product'] : [];
        $selectedVariants = is_array($ctx['selected_variants'] ?? null) ? $ctx['selected_variants'] : [];
        $orderNotes = (string) ($ctx['order_notes'] ?? '');
        $reklam = (string) ($ctx['reklam'] ?? '');
        $sourceFallback = (string) ($ctx['source'] ?? '');
        $utmCaptureOn = (bool) ($ctx['utm_capture_on'] ?? true);

        $variantText = '';
        if ($selectedVariants !== []) {
            $variantParts = [];
            foreach ($selectedVariants as $typeId => $optionId) {
                $vtStmt = $pdo->prepare('SELECT type_name FROM product_variation_types WHERE type_id = ?');
                $vtStmt->execute([$typeId]);
                $typeName = $vtStmt->fetchColumn() ?: "Varyant $typeId";

                $voStmt = $pdo->prepare('SELECT option_name FROM product_variation_options WHERE option_id = ?');
                $voStmt->execute([$optionId]);
                $optionName = $voStmt->fetchColumn() ?: "Seçenek $optionId";

                $variantParts[] = "$typeName: $optionName";
            }
            $variantText = implode(', ', $variantParts);
        }

        $cityName = '';
        $districtName = '';
        if ($customerCity !== '') {
            $cityStmt = $pdo->prepare('SELECT city_name FROM cities WHERE city_id = ?');
            $cityStmt->execute([$customerCity]);
            $cityName = (string) ($cityStmt->fetchColumn() ?: '');
        }
        if ($customerDistrict !== '') {
            $districtStmt = $pdo->prepare('SELECT district_name FROM districts WHERE district_id = ?');
            $districtStmt->execute([$customerDistrict]);
            $districtName = (string) ($districtStmt->fetchColumn() ?: '');
        }

        $paymentMethodName = '';
        if ($paymentMethodId > 0) {
            $pmStmt = $pdo->prepare('SELECT method_name FROM payment_methods WHERE payment_method_id = ?');
            $pmStmt->execute([$paymentMethodId]);
            $paymentMethodName = (string) ($pmStmt->fetchColumn() ?: 'Belirtilmemiş');
        }

        $siteStmt = $pdo->query('SELECT site_url, site_name FROM settings WHERE id = 1 LIMIT 1');
        $siteInfo = $siteStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $siteName = (string) ($siteInfo['site_name'] ?? 'Web Sitesi');
        $siteUrl = (string) ($siteInfo['site_url'] ?? '');

        $adSourceText = '';
        if ($reklam !== '' && $reklam !== 'Reklam Olmayabilir') {
            $adSourceText = $reklam;
        } elseif ($sourceFallback !== '') {
            $adSourceText = $sourceFallback;
        }

        $productSku = $product['sku'] ?? null;

        $refLink = '';
        $customerIp = null;
        $orderAttrRow = null;
        try {
            $metaStmt = $pdo->prepare('SELECT referrer, customer_ip, utm_source, utm_medium, utm_campaign, utm_content, utm_term, attribution_click_json, attribution_landing_url FROM orders WHERE order_id = ? LIMIT 1');
            $metaStmt->execute([$orderId]);
            $metaRow = $metaStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($metaRow)) {
                $orderAttrRow = $metaRow;
                if (! empty($metaRow['referrer'])) {
                    $refLink = trim((string) $metaRow['referrer']);
                }
                $ipRaw = trim((string) ($metaRow['customer_ip'] ?? ''));
                if ($ipRaw !== '' && filter_var($ipRaw, FILTER_VALIDATE_IP)) {
                    $customerIp = $ipRaw;
                }
            }
        } catch (Throwable $e) {
        }
        if ($refLink === '') {
            foreach (['ref', 'referrer', 'ref_link'] as $cookieKey) {
                if (! empty($_COOKIE[$cookieKey])) {
                    $refLink = trim((string) $_COOKIE[$cookieKey]);
                    break;
                }
            }
        }

        $invoiceVkn = trim((string) ($ctx['invoice_vkn'] ?? ''));
        $invoiceTaxOffice = trim((string) ($ctx['invoice_tax_office'] ?? ''));
        $invoiceCompany = trim((string) ($ctx['invoice_company_name'] ?? ''));
        $invoiceAddress = trim((string) ($ctx['invoice_address'] ?? ''));

        $customerNotes = trim((string) ($ctx['customer_notes'] ?? ''));

        $integrationSourceKey = laravel4_sync_config()['order_source_key'];

        $itemsPayload = laravel4_order_items_payload($pdo, $orderId, $variantText);
        $syncItems = $itemsPayload['items'];
        $totalAmount = $itemsPayload['total'];

        if ($syncItems === []) {
            $syncItems = [
                [
                    'product_id' => (string) $productId,
                    'product_sku' => $productSku,
                    'product_name' => (string) ($product['product_name'] ?? 'Ürün'),
                    'product_price' => (float) ($product['product_price'] ?? 0),
                    'quantity' => 1,
                    'variants' => $variantText !== '' ? $variantText : null,
                ],
            ];
            $totalAmount = (float) ($product['product_price'] ?? 0);
        }

        $totalAmount = laravel4_ortak_panel_order_total($pdo, $orderId, $totalAmount);

        if ($totalAmount <= 0) {
            laravel4_sync_log('Ortak panel sync SKIPPED: Order #'.$orderId.' tutar 0 TL (panel kabul etmiyor)');

            return;
        }

        $attribution = [];
        if (is_file(__DIR__.'/attribution_helpers.php')) {
            require_once __DIR__.'/attribution_helpers.php';
            if (function_exists('attribution_api_payload_slice')) {
                $attribution = attribution_api_payload_slice($utmCaptureOn, $orderAttrRow);
            }
        }

        $orderData = array_merge([
            'external_order_id' => (string) $orderId,
            'integration_source_key' => $integrationSourceKey,
            'source' => $siteName,
            'platform' => 'website',
            'ad_source' => $adSourceText !== '' ? $adSourceText : null,
            'ref' => $refLink !== '' ? $refLink : null,
            'referrer' => $refLink !== '' ? $refLink : null,
            'customer_name' => (string) ($ctx['customer_name'] ?? ''),
            'customer_phone' => (string) ($ctx['customer_phone'] ?? ''),
            'customer_address' => (string) ($ctx['customer_address'] ?? ''),
            'customer_city' => $cityName,
            'customer_district' => $districtName,
            'total_amount' => $totalAmount,
            'payment_method' => $paymentMethodName !== '' ? $paymentMethodName : 'Belirtilmemiş',
            'order_notes' => $orderNotes !== '' ? $orderNotes : null,
            'customer_notes' => $customerNotes !== '' ? $customerNotes : null,
            'customer_ip' => $customerIp,
            'order_date' => date('Y-m-d H:i:s'),
            'order_status_id' => 1,
            'site_url' => $siteUrl,
            'invoice_vkn' => $invoiceVkn !== '' ? $invoiceVkn : null,
            'invoice_tax_office' => $invoiceTaxOffice !== '' ? $invoiceTaxOffice : null,
            'invoice_company_name' => $invoiceCompany !== '' ? $invoiceCompany : null,
            'invoice_address' => $invoiceAddress !== '' ? $invoiceAddress : null,
            'items' => $syncItems,
        ], $attribution);

        if ($refLink !== '') {
            $orderData['ref'] = $refLink;
            $orderData['referrer'] = $refLink;
        }

        laravel4_sync_order($orderData);
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 checkout sync EXCEPTION: '.$e->getMessage());
    }
}

/**
 * Admin manuel sipariş → panel (UTM gönderilmez; operasyonel alanlar yeterli).
 */
function laravel4_sync_admin_order(int $orderId, PDO $pdo): void
{
    try {
        if ($orderId <= 0) {
            return;
        }

        $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? LIMIT 1');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($order)) {
            return;
        }

        $cityName = '';
        $districtName = '';
        $cityId = (int) ($order['customer_city'] ?? 0);
        $districtId = (int) ($order['customer_district'] ?? 0);
        if ($cityId > 0) {
            $cityStmt = $pdo->prepare('SELECT city_name FROM cities WHERE city_id = ?');
            $cityStmt->execute([$cityId]);
            $cityName = (string) ($cityStmt->fetchColumn() ?: '');
        }
        if ($districtId > 0) {
            $districtStmt = $pdo->prepare('SELECT district_name FROM districts WHERE district_id = ?');
            $districtStmt->execute([$districtId]);
            $districtName = (string) ($districtStmt->fetchColumn() ?: '');
        }

        $paymentMethodName = 'Belirtilmemiş';
        $paymentMethodId = (int) ($order['payment_method_id'] ?? 0);
        if ($paymentMethodId > 0) {
            $pmStmt = $pdo->prepare('SELECT method_name FROM payment_methods WHERE payment_method_id = ?');
            $pmStmt->execute([$paymentMethodId]);
            $paymentMethodName = (string) ($pmStmt->fetchColumn() ?: 'Belirtilmemiş');
        }

        $itemsStmt = $pdo->prepare(
            'SELECT oi.quantity, oi.price, p.product_id, p.product_name, p.sku
             FROM order_items oi
             INNER JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = ?'
        );
        $itemsStmt->execute([$orderId]);
        $itemRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $totalAmount = 0.0;
        foreach ($itemRows as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $price = (float) ($row['price'] ?? 0);
            $totalAmount += $price * $qty;
            $items[] = [
                'product_id' => (string) ($row['product_id'] ?? ''),
                'product_sku' => $row['sku'] ?? null,
                'product_name' => (string) ($row['product_name'] ?? 'Ürün'),
                'product_price' => $price,
                'quantity' => $qty,
                'variants' => null,
            ];
        }

        if ($items === []) {
            laravel4_sync_log("Laravel4 admin order sync SKIPPED: Order #{$orderId} — kalem yok");

            return;
        }

        $siteStmt = $pdo->query('SELECT site_url, site_name FROM settings WHERE id = 1 LIMIT 1');
        $siteInfo = $siteStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $siteName = (string) ($siteInfo['site_name'] ?? 'Web Sitesi');
        $siteUrl = (string) ($siteInfo['site_url'] ?? '');

        $customerIp = null;
        $ipRaw = trim((string) ($order['customer_ip'] ?? ''));
        if ($ipRaw !== '' && filter_var($ipRaw, FILTER_VALIDATE_IP)) {
            $customerIp = $ipRaw;
        }

        $orderData = [
            'external_order_id' => (string) $orderId,
            'integration_source_key' => laravel4_sync_config()['order_source_key'],
            'source' => $siteName.' — Admin Manuel',
            'platform' => 'website',
            'ad_source' => 'Manuel',
            'referrer' => null,
            'customer_name' => (string) ($order['customer_name'] ?? ''),
            'customer_phone' => (string) ($order['customer_phone'] ?? ''),
            'customer_address' => (string) ($order['customer_address'] ?? ''),
            'customer_city' => $cityName,
            'customer_district' => $districtName,
            'total_amount' => $totalAmount > 0 ? $totalAmount : (float) ($items[0]['product_price'] ?? 0),
            'payment_method' => $paymentMethodName,
            'order_notes' => trim((string) ($order['order_notes'] ?? '')) !== ''
                ? trim((string) $order['order_notes'])
                : 'Manuel oluşturuldu.',
            'customer_notes' => trim((string) ($order['customer_notes'] ?? '')) !== ''
                ? trim((string) $order['customer_notes'])
                : null,
            'customer_ip' => $customerIp,
            'order_date' => (string) ($order['order_date'] ?? date('Y-m-d H:i:s')),
            'order_status_id' => (int) ($order['order_status_id'] ?? 1),
            'site_url' => $siteUrl,
            'invoice_vkn' => trim((string) ($order['invoice_vkn'] ?? '')) !== '' ? trim((string) $order['invoice_vkn']) : null,
            'invoice_tax_office' => trim((string) ($order['invoice_tax_office'] ?? '')) !== '' ? trim((string) $order['invoice_tax_office']) : null,
            'invoice_company_name' => trim((string) ($order['invoice_company_name'] ?? '')) !== '' ? trim((string) $order['invoice_company_name']) : null,
            'invoice_address' => trim((string) ($order['invoice_address'] ?? '')) !== '' ? trim((string) $order['invoice_address']) : null,
            'items' => $items,
        ];

        laravel4_sync_order($orderData);
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 admin order sync EXCEPTION: '.$e->getMessage());
    }
}

function laravel4_sync_form(string $templateKey, array $fields, string $externalSubmissionId = '', array $extra = []): bool
{
    try {
        $cfg = laravel4_sync_config();
        $sourceKey = $cfg['form_source_key'];
        $url = $cfg['panel_base_url'].'/integration/webhook/'.rawurlencode($sourceKey);

        $payload = array_merge([
            'type' => 'form',
            'template_key' => $templateKey,
            'external_submission_id' => $externalSubmissionId,
            'fields' => $fields,
        ], $extra);

        if (! isset($payload['utm_source']) && is_file(__DIR__.'/attribution_helpers.php')) {
            require_once __DIR__.'/attribution_helpers.php';
            if (function_exists('attribution_api_payload_slice')) {
                $payload = array_merge($payload, attribution_api_payload_slice(true));
            }
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($cfg['webhook_secret'] !== '') {
            $headers[] = 'X-Webhook-Signature: '.hash_hmac('sha256', $body, $cfg['webhook_secret']);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $ref = $externalSubmissionId !== '' ? $externalSubmissionId : $templateKey;

        if (in_array($httpCode, [200, 201], true)) {
            laravel4_sync_log("Laravel4 form sync SUCCESS: {$templateKey} #{$ref}");

            return true;
        }

        laravel4_sync_log("Laravel4 form sync FAILED: {$templateKey} #{$ref} | HTTP: {$httpCode} | Response: {$response} | cURL: {$curlError}");

        return false;
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 form sync EXCEPTION: '.$e->getMessage());

        return false;
    }
}

/**
 * custom_form_entries.panel_synced kolonu yoksa ekler (idempotent).
 */
function laravel4_ensure_form_sync_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $col = $pdo->query("SHOW COLUMNS FROM `custom_form_entries` LIKE 'panel_synced'")->fetch();
        if (! $col) {
            $pdo->exec('ALTER TABLE `custom_form_entries` ADD COLUMN `panel_synced` TINYINT(1) NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE `custom_form_entries` ADD INDEX `cfe_panel_synced_idx` (`panel_synced`)');
            // Mevcut form kayıtları zaten iletilmiş kabul (=1); retry yalnızca bundan sonrakileri gönderir.
            $pdo->exec('UPDATE `custom_form_entries` SET `panel_synced` = 1');
        }
    } catch (Throwable $e) {
        laravel4_sync_log('form panel_synced kolon ensure hatasi: '.$e->getMessage());
    }
}

function laravel4_mark_form_synced(int $entryId, PDO $pdo): void
{
    if ($entryId <= 0) {
        return;
    }
    try {
        laravel4_ensure_form_sync_column($pdo);
        $pdo->prepare('UPDATE `custom_form_entries` SET `panel_synced` = 1 WHERE `id` = ?')->execute([$entryId]);
    } catch (Throwable $e) {
        laravel4_sync_log('form panel_synced işaretleme hatasi #'.$entryId.': '.$e->getMessage());
    }
}

/**
 * Panele iletilmemiş dinamik form kaydını (custom_form_entries) DB'den kurup tekrar gönderir.
 * dinamik_form.php'deki ile aynı ad/telefon/mesaj kuralı. Başarılıysa panel_synced=1.
 */
function laravel4_sync_pending_form(int $entryId, PDO $pdo): bool
{
    try {
        if ($entryId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT e.id, e.form_id, e.payload_json, f.title, f.slug
            FROM custom_form_entries e
            LEFT JOIN custom_forms f ON f.id = e.form_id
            WHERE e.id = ? LIMIT 1');
        $stmt->execute([$entryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($entry)) {
            return false;
        }

        $payload = json_decode((string) ($entry['payload_json'] ?? '{}'), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $syncName = '';
        foreach (['ad_soyad', 'ad', 'isim', 'name', 'musteri_adi'] as $k) {
            if (! empty($payload[$k])) {
                $syncName = (string) $payload[$k];
                break;
            }
        }
        $syncPhone = '';
        foreach (['telefon', 'tel', 'phone', 'gsm'] as $k) {
            if (! empty($payload[$k])) {
                $syncPhone = (string) $payload[$k];
                break;
            }
        }

        $syncFields = [
            'ad_soyad' => $syncName !== '' ? $syncName : 'Dinamik Form',
            'telefon' => $syncPhone,
            'mesaj' => 'Form: '.(string) ($entry['title'] ?? '')."\n"
                .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];

        $siteUrl = '';
        try {
            $siteUrl = (string) ($pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn() ?: '');
        } catch (Throwable $e) {
        }

        $ok = laravel4_sync_form('dinamik-form-v1', $syncFields, (string) $entry['id'], [
            'form_title' => (string) ($entry['title'] ?? ''),
            'form_slug' => (string) ($entry['slug'] ?? ''),
            'site_url' => $siteUrl,
        ]);

        if ($ok) {
            laravel4_mark_form_synced((int) $entry['id'], $pdo);
        }

        return $ok;
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 pending form EXCEPTION #'.$entryId.': '.$e->getMessage());

        return false;
    }
}

/**
 * Panele iletilmemiş (panel_synced=0) son dinamik form kayıtlarını tekrar gönderir. OS cron ~4 dk.
 *
 * @return array{scanned:int, ok:int, fail:int}
 */
function laravel4_retry_unsynced_forms(PDO $pdo, int $limit = 100, int $lookbackDays = 30): array
{
    $scanned = 0;
    $ok = 0;
    $fail = 0;

    try {
        laravel4_ensure_form_sync_column($pdo);

        $since = date('Y-m-d H:i:s', time() - max(1, $lookbackDays) * 86400);
        // custom_form_entries created_at kolonu olmayabilir; varsa filtrele, yoksa id'ye göre son N.
        $hasCreated = (bool) $pdo->query("SHOW COLUMNS FROM `custom_form_entries` LIKE 'created_at'")->fetch();

        if ($hasCreated) {
            $stmt = $pdo->prepare('SELECT id FROM custom_form_entries
                WHERE (panel_synced = 0 OR panel_synced IS NULL) AND COALESCE(created_at, NOW()) >= ?
                ORDER BY id ASC LIMIT ?');
            $stmt->bindValue(1, $since, PDO::PARAM_STR);
            $stmt->bindValue(2, max(1, min(1000, $limit)), PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM custom_form_entries
                WHERE (panel_synced = 0 OR panel_synced IS NULL)
                ORDER BY id DESC LIMIT ?');
            $stmt->bindValue(1, max(1, min(1000, $limit)), PDO::PARAM_INT);
        }
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $entryId) {
            $scanned++;
            if (laravel4_sync_pending_form((int) $entryId, $pdo)) {
                $ok++;
            } else {
                $fail++;
            }
            usleep(120000);
        }
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 retry unsynced forms EXCEPTION: '.$e->getMessage());
    }

    return ['scanned' => $scanned, 'ok' => $ok, 'fail' => $fail];
}

/**
 * Panel kapalıyken kaçan bir web siparişini DB'den SADIK şekilde yeniden kurup panele gönderir.
 * (Gerçek site kaynağı + attribution + varyant korunur.) Başarılıysa panel_synced=1 (sync_order içinde).
 */
function laravel4_sync_pending_order(int $orderId, PDO $pdo): bool
{
    try {
        if ($orderId <= 0) {
            return false;
        }

        $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? LIMIT 1');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($order)) {
            return false;
        }

        if (laravel4_should_skip_online_unpaid_sync($pdo, $orderId)) {
            return false;
        }

        $cityName = '';
        $districtName = '';
        $cityId = (int) ($order['customer_city'] ?? 0);
        $districtId = (int) ($order['customer_district'] ?? 0);
        if ($cityId > 0) {
            $s = $pdo->prepare('SELECT city_name FROM cities WHERE city_id = ?');
            $s->execute([$cityId]);
            $cityName = (string) ($s->fetchColumn() ?: '');
        }
        if ($districtId > 0) {
            $s = $pdo->prepare('SELECT district_name FROM districts WHERE district_id = ?');
            $s->execute([$districtId]);
            $districtName = (string) ($s->fetchColumn() ?: '');
        }

        $paymentMethodName = 'Belirtilmemiş';
        $paymentMethodId = (int) ($order['payment_method_id'] ?? 0);
        if ($paymentMethodId > 0) {
            $s = $pdo->prepare('SELECT method_name FROM payment_methods WHERE payment_method_id = ?');
            $s->execute([$paymentMethodId]);
            $paymentMethodName = (string) ($s->fetchColumn() ?: 'Belirtilmemiş');
        }

        // Varyant metni (order_variation_details → tür/seçenek adları)
        $variantText = '';
        try {
            $vStmt = $pdo->prepare('SELECT type_id, option_id FROM order_variation_details WHERE order_id = ?');
            $vStmt->execute([$orderId]);
            $parts = [];
            foreach ($vStmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $tn = $pdo->prepare('SELECT type_name FROM product_variation_types WHERE type_id = ?');
                $tn->execute([(int) $v['type_id']]);
                $typeName = (string) ($tn->fetchColumn() ?: ('Varyant '.$v['type_id']));
                $on = $pdo->prepare('SELECT option_name FROM product_variation_options WHERE option_id = ?');
                $on->execute([(int) $v['option_id']]);
                $optName = (string) ($on->fetchColumn() ?: ('Seçenek '.$v['option_id']));
                $parts[] = $typeName.': '.$optName;
            }
            $variantText = implode(', ', $parts);
        } catch (Throwable $e) {
            $variantText = '';
        }

        $itemsStmt = $pdo->prepare(
            'SELECT oi.quantity, oi.price, p.product_id, p.product_name, p.sku
             FROM order_items oi
             INNER JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = ?'
        );
        $itemsStmt->execute([$orderId]);
        $itemRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $totalAmount = 0.0;
        foreach ($itemRows as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $price = (float) ($row['price'] ?? 0);
            $totalAmount += $price * $qty;
            $items[] = [
                'product_id' => (string) ($row['product_id'] ?? ''),
                'product_sku' => $row['sku'] ?? null,
                'product_name' => (string) ($row['product_name'] ?? 'Ürün'),
                'product_price' => $price,
                'quantity' => $qty,
                'variants' => $variantText !== '' ? $variantText : null,
            ];
        }

        if ($items === []) {
            laravel4_sync_log("Laravel4 pending order SKIPPED: #{$orderId} — kalem yok");

            return false;
        }

        if ($totalAmount <= 0) {
            laravel4_sync_log("Laravel4 pending order SKIPPED: #{$orderId} — tutar 0 TL (panel kabul etmiyor)");

            return false;
        }

        $totalAmount = laravel4_ortak_panel_order_total($pdo, $orderId, $totalAmount);

        $siteStmt = $pdo->query('SELECT site_url, site_name FROM settings WHERE id = 1 LIMIT 1');
        $siteInfo = $siteStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $siteName = (string) ($siteInfo['site_name'] ?? 'Web Sitesi');
        $siteUrl = (string) ($siteInfo['site_url'] ?? '');

        $customerIp = null;
        $ipRaw = trim((string) ($order['customer_ip'] ?? ''));
        if ($ipRaw !== '' && filter_var($ipRaw, FILTER_VALIDATE_IP)) {
            $customerIp = $ipRaw;
        }

        $reklam = trim((string) ($order['reklam'] ?? ''));
        $adSource = ($reklam !== '' && $reklam !== 'Reklam Olmayabilir') ? $reklam : null;
        $referrer = trim((string) ($order['referrer'] ?? ''));

        $attribution = [];
        if (is_file(__DIR__.'/attribution_helpers.php')) {
            require_once __DIR__.'/attribution_helpers.php';
            if (function_exists('attribution_api_payload_slice')) {
                $attribution = attribution_api_payload_slice(true, $order);
            }
        }

        $orderData = array_merge([
            'external_order_id' => (string) $orderId,
            'integration_source_key' => laravel4_sync_config()['order_source_key'],
            'source' => $siteName,
            'platform' => 'website',
            'ad_source' => $adSource,
            'ref' => $referrer !== '' ? $referrer : null,
            'referrer' => $referrer !== '' ? $referrer : null,
            'customer_name' => (string) ($order['customer_name'] ?? ''),
            'customer_phone' => (string) ($order['customer_phone'] ?? ''),
            'customer_address' => (string) ($order['customer_address'] ?? ''),
            'customer_city' => $cityName,
            'customer_district' => $districtName,
            'total_amount' => $totalAmount > 0 ? $totalAmount : (float) ($items[0]['product_price'] ?? 0),
            'payment_method' => $paymentMethodName,
            'order_notes' => trim((string) ($order['order_notes'] ?? '')) !== '' ? trim((string) $order['order_notes']) : null,
            'customer_notes' => trim((string) ($order['customer_notes'] ?? '')) !== '' ? trim((string) $order['customer_notes']) : null,
            'customer_ip' => $customerIp,
            'order_date' => (string) ($order['order_date'] ?? date('Y-m-d H:i:s')),
            'order_status_id' => (int) ($order['order_status_id'] ?? 1),
            'site_url' => $siteUrl,
            'invoice_vkn' => trim((string) ($order['invoice_vkn'] ?? '')) !== '' ? trim((string) $order['invoice_vkn']) : null,
            'invoice_tax_office' => trim((string) ($order['invoice_tax_office'] ?? '')) !== '' ? trim((string) $order['invoice_tax_office']) : null,
            'invoice_company_name' => trim((string) ($order['invoice_company_name'] ?? '')) !== '' ? trim((string) $order['invoice_company_name']) : null,
            'invoice_address' => trim((string) ($order['invoice_address'] ?? '')) !== '' ? trim((string) $order['invoice_address']) : null,
            'items' => $items,
        ], is_array($attribution) ? $attribution : []);

        if ($referrer !== '') {
            $orderData['ref'] = $referrer;
            $orderData['referrer'] = $referrer;
        }

        return laravel4_sync_order($orderData);
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 pending order EXCEPTION #'.$orderId.': '.$e->getMessage());

        return false;
    }
}

/**
 * Panele iletilmemiş (panel_synced=0) son siparişleri tekrar gönderir. OS cron ile ~3 dk'da bir çağrılır.
 * Idempotent: panel unique index sayesinde var olan sipariş atlanır, mükerrer olmaz.
 *
 * @return array{scanned:int, ok:int, fail:int}
 */
function laravel4_retry_unsynced_orders(PDO $pdo, int $limit = 100, int $lookbackDays = 30): array
{
    $scanned = 0;
    $ok = 0;
    $fail = 0;

    try {
        laravel4_ensure_sync_column($pdo);

        $since = date('Y-m-d H:i:s', time() - max(1, $lookbackDays) * 86400);
        $stmt = $pdo->prepare(
            'SELECT order_id FROM orders
             WHERE (panel_synced = 0 OR panel_synced IS NULL)
               AND COALESCE(order_date, NOW()) >= ?
               AND (
                 payment_status = \'paid\'
                 OR gateway_code IS NULL
                 OR gateway_code NOT IN (\'paytr\', \'iyzico\')
                 OR payment_method_id NOT IN (
                   SELECT payment_method_id FROM payment_methods WHERE gateway_code IN (\'paytr\', \'iyzico\')
                 )
               )
             ORDER BY order_id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $since, PDO::PARAM_STR);
        $stmt->bindValue(2, max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $orderId) {
            $scanned++;
            if (laravel4_sync_pending_order((int) $orderId, $pdo)) {
                $ok++;
            } else {
                $fail++;
            }
            usleep(120000);
        }
    } catch (Throwable $e) {
        laravel4_sync_log('Laravel4 retry unsynced EXCEPTION: '.$e->getMessage());
    }

    return ['scanned' => $scanned, 'ok' => $ok, 'fail' => $fail];
}
