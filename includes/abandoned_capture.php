<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';

/**
 * Yarım kalan sipariş yakalama ayarları (checkout_module_settings).
 *
 * Kapalı (enabled=0): sipariş sayfası form/autofill kaydı (order-capture.js).
 * Açık (enabled=1): ana sayfada kaydırma VEYA ürün etkileşimi (abandoned-track.js).
 *
 * @return array{
 *   enabled: bool,
 *   auto_trigger: string,
 *   trigger_form: bool,
 *   trigger_scroll: bool,
 *   trigger_products: bool,
 *   trigger_order_page: bool,
 *   scroll_pct: int,
 *   product_only: bool
 * }
 */
function abandoned_capture_settings(PDO $pdo): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $defaults = [
        'enabled' => false,
        'auto_trigger' => 'scroll',
        'trigger_form' => true,
        'trigger_scroll' => false,
        'trigger_products' => false,
        'trigger_order_page' => false,
        'scroll_pct' => 50,
        'product_only' => true,
    ];

    try {
        $row = $pdo->query(
            'SELECT
                COALESCE(abandoned_capture_enabled, 0) AS ace,
                COALESCE(abandoned_auto_trigger, \'scroll\') AS aat,
                COALESCE(abandoned_scroll_pct, 50) AS asp,
                COALESCE(abandoned_product_only, 1) AS apo
             FROM checkout_module_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $enabled = (int) $row['ace'] === 1;
            $auto = (string) $row['aat'];
            if (! in_array($auto, ['scroll', 'product'], true)) {
                $auto = 'scroll';
            }

            $pct = (int) $row['asp'];
            if ($pct < 10) {
                $pct = 10;
            } elseif ($pct > 95) {
                $pct = 95;
            }

            $cache = [
                'enabled' => $enabled,
                'auto_trigger' => $auto,
                'trigger_form' => true,
                'trigger_scroll' => $enabled && $auto === 'scroll',
                'trigger_products' => $enabled && $auto === 'product',
                'trigger_order_page' => $enabled && $auto === 'product',
                'scroll_pct' => $pct,
                'product_only' => (int) $row['apo'] === 1,
            ];

            return $cache;
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('abandoned_capture_settings: ' . $e->getMessage());
        }
    }

    $cache = $defaults;

    return $cache;
}

/**
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $product
 */
function abandoned_capture_js_config(array $settings, array $product = [], string $page = 'home'): array
{
    $pid = (int) ($product['product_id'] ?? 0);
    $name = trim((string) ($product['product_name'] ?? ''));
    $price = trim((string) ($product['product_price'] ?? ''));

    return [
        'enabled' => ! empty($settings['enabled']),
        'page' => $page,
        'endpoint' => 'ajax/abandoned_save.php',
        'triggers' => [
            'form' => ! empty($settings['trigger_form']),
            'scroll' => ! empty($settings['trigger_scroll']),
            'products' => ! empty($settings['trigger_products']),
            'orderPage' => ! empty($settings['trigger_order_page']) && $page === 'order',
        ],
        'scrollPct' => (int) ($settings['scroll_pct'] ?? 50),
        'productOnly' => ! empty($settings['product_only']),
        'product' => [
            'id' => $pid,
            'name' => $name,
            'price' => $price,
        ],
        'fields' => [
            'name' => 'customer_name',
            'phone' => 'customer_phone',
        ],
        'productsSelector' => '#products, #products-heading',
    ];
}

/**
 * Sipariş formu — PHP ile önceki yarım kalan kaydından ad/telefon (IP veya çerez).
 *
 * @return array{ad: string, tel: string, source: string}
 */
function abandoned_prefill_contact(PDO $pdo): array
{
    $result = ['ad' => '', 'tel' => '', 'source' => ''];

    try {
        $cols = [];
        $q = $pdo->query('SHOW COLUMNS FROM yarim_kalanlar');
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }

        $activeClause = isset($cols['is_converted']) ? ' AND IFNULL(is_converted, 0) = 0' : '';
        $ip = app_client_ip();

        $sess = $_COOKIE['yarim_kalan_sid'] ?? null;
        if ($sess !== null && ! preg_match('/^[a-zA-Z0-9_-]{16,96}$/', (string) $sess)) {
            $sess = null;
        }

        $row = null;
        if ($sess !== null && isset($cols['session_id'])) {
            $st = $pdo->prepare(
                'SELECT ad, tel FROM yarim_kalanlar WHERE session_id = ?' . $activeClause . ' ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$sess]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $result['source'] = 'session';
            }
        }

        if (! $row) {
            $st = $pdo->prepare(
                'SELECT ad, tel FROM yarim_kalanlar WHERE ip = ?' . $activeClause . ' ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$ip]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $result['source'] = 'ip';
            }
        }

        if (! $row) {
            return $result;
        }

        $result['ad'] = trim((string) ($row['ad'] ?? ''));
        $result['tel'] = trim((string) ($row['tel'] ?? ''));

        return $result;
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('abandoned_prefill_contact: ' . $e->getMessage());
        }

        return $result;
    }
}

/**
 * Sipariş sayfası yarım kalan payload (her zaman aktif — blur + tarayıcı autofill).
 *
 * @param array<string, mixed> $product
 * @return array<string, mixed>
 */
function abandoned_capture_order_payload(array $product, int $productId, array $prefill = [], array $settings = []): array
{
    $allowProductOnly = ! empty($settings['enabled'])
        && ! empty($settings['product_only'])
        && ($settings['auto_trigger'] ?? '') === 'product';

    return [
        'endpoint' => 'ajax/abandoned_save.php',
        'allowProductOnly' => $allowProductOnly,
        'product' => [
            'id' => $productId,
            'name' => trim((string) ($product['product_name'] ?? '')),
            'price' => trim((string) ($product['product_price'] ?? '')),
        ],
        'fields' => [
            'name' => 'customer_name',
            'phone' => 'customer_phone',
        ],
        'prefill' => [
            'ad' => trim((string) ($prefill['ad'] ?? '')),
            'tel' => trim((string) ($prefill['tel'] ?? '')),
            'source' => trim((string) ($prefill['source'] ?? '')),
        ],
        'triggerBrowserAutofill' => true,
    ];
}

/**
 * @deprecated abandoned-order-capture.js kullanılıyor
 * @param array<string, mixed> $product
 */
function abandoned_capture_legacy_script(array $product, int $productId): string
{
    return '';
}
