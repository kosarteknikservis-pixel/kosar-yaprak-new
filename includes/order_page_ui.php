<?php
declare(strict_types=1);

/**
 * Sipariş sayfası (order.php) vitrin blokları — panelden özelleştirilir.
 */

function order_page_ui_defaults(): array
{
    return [
        'countdown' => [
            // Varsayılan: index.php ile aynı görünüm (taban countdown-banner.css). Panelden özel stil açılabilir.
            'style_enabled' => false,
            'message_override' => '',
            'grad_start' => '#ff6b6b',
            'grad_end' => '#ee5a24',
            'banner_text_color' => '#ffffff',
            'label_font_px' => 14,
            'timer_font_px' => 18,
            'timer_box_bg' => 'rgba(255,255,255,0.2)',
            'timer_digit_color' => '#ffffff',
            'lbl_gun' => 'Gün',
            'lbl_saat' => 'Saat',
            'lbl_dakika' => 'Dakika',
            'lbl_saniye' => 'Saniye',
        ],
        'cart_bar' => [
            'show' => true,
            'title' => 'SEPETİNİZ',
            'bg' => '#5fbd0f',
            'color' => '#ffffff',
            'font_px' => 30,
            'font_weight' => 700,
            'padding_y_px' => 27,
            'show_arrows' => true,
        ],
        'hero' => [
            'mode' => 'product',
            'custom_image' => '',
            'default_piece_qty' => 2,
            'overlay_enabled' => false,
            'overlay_pos' => 'left',
            'line1_tpl' => '{qty} Adet · {short_name}',
            'line2_tpl' => '{price_fmt}',
            'line3_tpl' => 'Ücretsiz Kargo',
            'line4_cta_tpl' => 'SEÇİNİZ',
            'line1_size_px' => 16,
            'line2_size_px' => 36,
            'line3_size_px' => 13,
            'line4_size_px' => 14,
            'line1_color' => '#ffffff',
            'line2_color' => '#ffffff',
            'line3_color' => '#ffffff',
            'line4_color' => '#1e293b',
            'line4_bg' => '#ffffff',
            'overlay_pad_px' => 14,
            'overlay_top_pct' => 8,
            'overlay_left_pct' => 6,
            'overlay_width_pct' => 88,
        ],
        'product' => [
            'show_heading' => true,
            'name_color' => '#15803d',
            'name_font_px' => 21,
            'name_font_weight' => 700,
            'show_strikethrough' => true,
            'old_price_color' => '#6b7280',
            'old_price_font_px' => 17,
            'price_color' => '#15803d',
            'price_font_px' => 26,
            'price_font_weight' => 800,
            'spacing_name_price_px' => 0,
            'shipping_notice' => [
                'show' => true,
                'text' => 'Kargonuz 1–3 iş günü içinde kargoya verilir.',
                'color' => '#FF7F00',
                'font_px' => 18,
                'font_weight' => 700,
                'align' => 'center',
                'margin_top_px' => 8,
            ],
        ],
        'post_footer_msg' => [
            'prefer_settings_text' => false,
            'settings_text' => '',
            'use_footer_id_7' => true,
            'color' => '#FF7F00',
            'font_px' => 18,
            'font_weight' => 700,
        ],
        'notification_strip' => [
            'inherit_notification_settings' => true,
            'bar_bg' => '',
            'bar_color' => '#283458',
            'bar_font_px' => 22,
            'bar_padding_px' => 12,
            'below_prices_instead_of_float' => true,
        ],
    ];
}

/**
 * @param  array<mixed>|null  $decoded
 */
function order_page_ui_merge(?array $decoded): array
{
    $decoded = is_array($decoded) ? $decoded : [];

    return array_replace_recursive(order_page_ui_defaults(), $decoded);
}

function order_page_ui_get(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT config_json FROM order_page_ui WHERE id = 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $raw = isset($row['config_json']) && is_string($row['config_json']) ? $row['config_json'] : '';
        $json = $raw !== '' ? json_decode($raw, true) : [];

        return order_page_ui_merge(is_array($json) ? $json : []);

    } catch (Throwable $e) {
        return order_page_ui_merge([]);

    }

}

function order_page_ui_hex(?string $h, string $fallback): string
{
    $h = is_string($h) ? trim($h) : '';

    return preg_match('/^#[0-9A-Fa-f]{6}$/', $h) ? $h : $fallback;

}

/**
 * Sipariş satırında kısaltılmış ad.
 */
function order_page_ui_short_name(string $name): string
{
    $name = trim($name);

    return function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 24
        ? mb_substr($name, 0, 22, 'UTF-8') . '…'
        : $name;

}

/**
 * Şablon: {qty}, {product_name}, {short_name}, {price_fmt}, {old_price_fmt}, {price}, {old_price}
 *
 * @param  array<string, string|int|float>  $map
 */
function order_page_ui_replace_tokens(string $tpl, array $map): string
{
    $out = $tpl;

    foreach ($map as $k => $v) {

        $out = str_replace('{' . $k . '}', (string) $v, $out);

    }


    return $out;

}

function order_page_ui_token_map(array $product, array $ui): array
{

    /** @suppress */
    $qty = max(1, min(999, (int) ($ui['hero']['default_piece_qty'] ?? 2)));

    $pp = (float) ($product['product_price'] ?? 0);

    $op = (float) ($product['original_price'] ?? 0);

    $pfn = (string) ($product['product_name'] ?? '');
    $sn = order_page_ui_short_name((string) ($product['product_name'] ?? ''));

    return [
        'qty' => $qty,

        'product_name' => $pfn,

        'short_name' => $sn,

        'price' => number_format($pp, 2, ',', '.'),

        'price_fmt' => number_format($pp, 2, ',', '.') . ' TL',

        'old_price' => number_format($op, 2, ',', '.'),

        'old_price_fmt' => number_format($op, 2, ',', '.') . ' TL',

    ];

}


function order_page_ui_print_styles(array $ui): void
{
    $cart = $ui['cart_bar'] ?? [];


    ?>


<style id="opui-order-inline-styles">


.order-page-shell .opui-cart-h2 { font-weight: <?= (int) ($cart['font_weight'] ?? 700) ?>;


    font-size: <?= (int) ($cart['font_px'] ?? 30) ?>px;


    color: <?= htmlspecialchars(order_page_ui_hex(isset($cart['color']) ? (string) $cart['color'] : null, '#ffffff'), ENT_QUOTES, 'UTF-8') ?>;


    background-color: <?= htmlspecialchars(order_page_ui_hex(isset($cart['bg']) ? (string) $cart['bg'] : null, '#5fbd0f'), ENT_QUOTES, 'UTF-8') ?>;


    padding-top: <?= (int) ($cart['padding_y_px'] ?? 27) ?>px;


    padding-bottom: <?= (int) ($cart['padding_y_px'] ?? 27) ?>px;


    text-align: center;


    margin: 0;


}


.order-page-shell .opui-hero-wrap { position: relative;


    width: 100%;


    overflow: hidden;


}


.order-page-shell .opui-hero-img { max-width: 100%;


    display: block;


    margin: 0 auto;


}


.order-page-shell .opui-hero-overlay { position: absolute;


    top: <?= (float) ($ui['hero']['overlay_top_pct'] ?? 8) ?>%;


    left: <?= (float) ($ui['hero']['overlay_left_pct'] ?? 6) ?>%;


    width: <?= (float) ($ui['hero']['overlay_width_pct'] ?? 88) ?>%;


    box-sizing: border-box;


    padding: <?= (int) ($ui['hero']['overlay_pad_px'] ?? 14) ?>px;


    text-align: <?= ($ui['hero']['overlay_pos'] ?? 'left') === 'center' ? 'center' : 'left' ?>;


}

<?php
    $pUi = $ui['product'] ?? [];
    $nameColor = htmlspecialchars(order_page_ui_hex(isset($pUi['name_color']) ? (string) $pUi['name_color'] : null, '#15803d'), ENT_QUOTES, 'UTF-8');
    $oldPriceColor = htmlspecialchars(order_page_ui_hex(isset($pUi['old_price_color']) ? (string) $pUi['old_price_color'] : null, '#6b7280'), ENT_QUOTES, 'UTF-8');
    $priceColor = htmlspecialchars(order_page_ui_hex(isset($pUi['price_color']) ? (string) $pUi['price_color'] : null, '#15803d'), ENT_QUOTES, 'UTF-8');
    $nameWeight = max(600, min(900, (int) ($pUi['name_font_weight'] ?? 700)));
    $priceWeight = max(600, min(900, (int) ($pUi['price_font_weight'] ?? 800)));
?>

.order-page-shell .product-summary.op-order-vitrin {
    padding: 0;
    overflow: hidden;
}

.order-page-shell .op-order-vitrin__hero {
    background: #f8fafc;
    line-height: 0;
}

.order-page-shell .op-order-vitrin__img {
    border-radius: 0;
    margin-bottom: 0;
}

.order-page-shell .op-order-vitrin__body {
    padding: 28px 22px 26px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.order-page-shell .op-order-vitrin__title {
    margin: 0 0 12px;
    font-size: 1.3125rem;
    font-weight: <?= $nameWeight ?>;
    line-height: 1.35;
    letter-spacing: -0.01em;
    color: <?= $nameColor ?>;
    text-align: center;
    max-width: 22rem;
}

.order-page-shell .opui-price-block {
    margin-top: 0;
    padding: 0;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.order-page-shell .opui-price-old {
    display: block;
    font-size: 1.0625rem;
    font-weight: 500;
    color: <?= $oldPriceColor ?>;
    text-decoration: line-through;
    opacity: 0.75;
    margin-bottom: 0;
    line-height: 1.2;
}

.order-page-shell .opui-price-current {
    display: block;
    font-size: 1.625rem;
    font-weight: <?= $priceWeight ?>;
    color: <?= $priceColor ?>;
    line-height: 1.2;
    letter-spacing: -0.02em;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.9);
}

.order-page-shell .op-order-vitrin__discount {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    color: #fff;
    background: linear-gradient(135deg, #fb923c 0%, #ef4444 48%, #dc2626 100%);
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.38);
    animation: op-vitrin-discount-glow 2.6s ease-in-out infinite;
}

.order-page-shell .op-order-vitrin__discount-pct {
    font-size: 0.9375rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
}

.order-page-shell .op-order-vitrin__discount-label {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    line-height: 1;
    opacity: 0.96;
}

@keyframes op-vitrin-discount-glow {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.38);
    }
    50% {
        transform: scale(1.04);
        box-shadow: 0 6px 18px rgba(239, 68, 68, 0.48);
    }
}

.order-page-shell .op-order-vitrin__notice {
    display: block;
    margin: 14px auto 0;
    padding: 11px 16px 11px 42px;
    font-size: 0.96875rem;
    font-weight: 600;
    line-height: 1.5;
    color: #92400e !important;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1px solid rgba(251, 191, 36, 0.55);
    border-radius: 12px;
    max-width: 22rem;
    text-align: center;
    box-shadow: 0 2px 10px rgba(245, 158, 11, 0.14);
    position: relative;
}

.order-page-shell .op-order-vitrin__notice::before {
    content: "\f095";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.875rem;
    color: #d97706;
    line-height: 1;
}

.order-page-shell .op-order-vitrin__notice--shipping {
    margin-top: 10px;
    padding: 7px 15px;
    font-size: 0.9375rem;
    font-weight: 500;
    line-height: 1.35;
    color: #64748b !important;
    background: #f1f5f9;
    border: none;
    border-radius: 999px;
    box-shadow: none;
}

.order-page-shell .op-order-vitrin__notice--shipping::before {
    content: none;
}

@media (max-width: 479px) {
    .order-page-shell .op-order-vitrin__body {
        padding: 24px 18px 22px;
    }

    .order-page-shell .op-order-vitrin__title {
        font-size: 1.1875rem !important;
    }

    .order-page-shell .opui-price-current {
        font-size: 1.5rem !important;
    }

    .order-page-shell .opui-price-old {
        font-size: 1rem !important;
    }

    .order-page-shell .op-order-vitrin__discount {
        padding: 5px 12px;
    }

    .order-page-shell .op-order-vitrin__discount-pct {
        font-size: 0.875rem;
    }

    .order-page-shell .op-order-vitrin__discount-label {
        font-size: 0.625rem;
    }

    .order-page-shell .op-order-vitrin__notice {
        font-size: 0.9375rem;
        padding: 10px 14px 10px 38px;
        max-width: 100%;
    }

    .order-page-shell .op-order-vitrin__notice::before {
        left: 12px;
        font-size: 0.8125rem;
    }
}

</style>

<?php

}

function order_page_ui_print_styles_extended(array $ui): void
{
    order_page_ui_print_styles($ui);
    /** @suppress */
    $n = isset($ui['notification_strip']) && is_array($ui['notification_strip']) ? $ui['notification_strip'] : [];

    /** @suppress */
    if (($n['inherit_notification_settings'] ?? true)) {
        return;
    }

    $bg = isset($n['bar_bg']) && is_string($n['bar_bg']) ? trim($n['bar_bg']) : '';
    /** @suppress */
    $bc = isset($n['bar_color']) && is_string($n['bar_color']) ? trim($n['bar_color']) : '#283458';
    $bf = max(12, min(72, (int) ($n['bar_font_px'] ?? 22)));
    $bp = max(6, min(48, (int) ($n['bar_padding_px'] ?? 12)));
    /** @suppress */
    $bgOk = ($bg !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $bg));
    $bcOk = htmlspecialchars(order_page_ui_hex($bc !== '' ? $bc : null, '#283458'), ENT_QUOTES, 'UTF-8');

    ?>

<style id="opui-promo-override-styles">


.order-page-shell .promo-banner {

<?php if ($bgOk): ?>
    background-color: <?= htmlspecialchars(strtoupper($bg), ENT_QUOTES, 'UTF-8') ?>;

<?php endif; ?>
color: <?= $bcOk ?>;
font-size: <?= $bf ?>px;


    padding-left: <?= $bp ?>px;


    padding-right: <?= $bp ?>px;


    padding-top: <?= $bp ?>px;


    padding-bottom: <?= $bp ?>px;


    font-weight: 600;


    border-radius: 0;


}


.order-page-shell .promo-banner--split .promo-banner__kicker {


    font-size: <?= max(14, min(22, (int) round($bf * 0.9))) ?>px !important;


    font-weight: 700;


}


.order-page-shell .promo-banner--split .promo-banner__headline {


    font-size: <?= max(20, min(40, (int) round($bf * 1.32))) ?>px !important;


    font-weight: 800;


}


</style>


<?php
}
