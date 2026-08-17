<?php
declare(strict_types=1);

/**
 * Dönüşüm denemesi — kapatmak için false yapın (anasayfa şeridi, sticky bar, form sadeleştirme).
 */
const CONV_TRIAL_ENABLED = true;

function conv_trial_on(): bool
{
    return CONV_TRIAL_ENABLED;
}

/**
 * İlk vitrin ürününden fiyat + sipariş linki.
 *
 * @param  array<int, array<string, mixed>>  $products
 * @param  array<string, mixed>  $hpSec
 * @return array<string, mixed>|null
 */
function conv_trial_offer(array $products, array $hpSec, PDO $pdo): ?array
{
    if ($products === []) {
        return null;
    }

    $product = $products[0];
    $sale = (float) ($product['product_price'] ?? 0);
    $original = (float) ($product['original_price'] ?? 0);
    $discountPct = 0;
    if ($original > $sale && $original > 0) {
        $discountPct = (int) round((1 - $sale / $original) * 100);
    }

    $fmt = static function (float $n): string {
        if (function_exists('money')) {
            return money($n);
        }

        return number_format($n, 2, ',', '.') . ' TL';
    };

    $name = function_exists('content_t')
        ? content_t('product', (int) $product['product_id'], 'name', (string) ($product['product_name'] ?? ''))
        : (string) ($product['product_name'] ?? '');

    $ctaColor = preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['cta_bg_color'] ?? '')))
        ? trim((string) $hpSec['cta_bg_color'])
        : '#5fbd0f';

    return [
        'product_id' => (int) $product['product_id'],
        'name' => $name,
        'sale' => $sale,
        'original' => $original,
        'sale_fmt' => $fmt($sale),
        'original_fmt' => $fmt($original),
        'discount_pct' => $discountPct,
        'save_fmt' => ($discountPct > 0 && function_exists('money_save')) ? money_save($original - $sale) : '',
        'save_style' => function_exists('shop_use_save_badge') && shop_use_save_badge($pdo) && $discountPct > 0,
        'show_original' => (int) ($hpSec['show_card_original_price'] ?? 1) === 1 && $original > $sale,
        'show_price' => ! empty($product['show_price']),
        'order_url' => app_url('order', ['product_id' => $product['product_id']], $pdo),
        'cta_color' => $ctaColor,
    ];
}
