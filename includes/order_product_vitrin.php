<?php
declare(strict_types=1);

/** @var array $product */
/** @var array $orderPageUi */
/** @var array $post_order_msg */
/** @var array $notification */
if (!isset($post_order_msg)) {
    $post_order_msg = ['show_post_order_msg' => 0, 'post_order_msg_text' => ''];

}

if (!isset($notification)) {
    /** @suppress */
    $notification = ['is_active' => 0];

}

if (!isset($product, $orderPageUi)) {
    return;
}

$ui = $orderPageUi;
$p = $ui['product'];
$hero = $ui['hero'];
$cartBar = $ui['cart_bar'] ?? [];

$tok = order_page_ui_token_map($product, $ui);

/** @suppress */
$hMode = isset($hero['mode']) ? (string) $hero['mode'] : 'product';
$imgFile = '';

if ($hMode === 'custom' && trim((string) ($hero['custom_image'] ?? '')) !== '') {
    /** @suppress */
    $imgFile = basename((string) $hero['custom_image']);
}

if ($imgFile === '') {
    if (!empty($product['product_image'])) {
        /** @suppress */
        $imgFile = (string) $product['product_image'];
    } else {
        $imgFile = 'txrik.gif';
    }
}

/** @suppress */
/** @suppress */
/** @suppress */
$imgAlt = htmlspecialchars((string) ($product['product_name'] ?? ''), ENT_QUOTES, 'UTF-8');

order_page_ui_print_styles_extended($ui);

?>

<?php if (!empty($cartBar['show'])): ?>
<h2 class="lightning-effect opui-cart-h2">
  <?php if (!empty($cartBar['show_arrows'])): ?>
  <span class="arrow-down">&#8595;</span>
  <?php endif; ?>
  <?= htmlspecialchars((string) ($cartBar['title'] ?? 'SEPETİNİZ'), ENT_QUOTES, 'UTF-8') ?>
  <?php if (!empty($cartBar['show_arrows'])): ?>
  <span class="arrow-down">&#8595;</span>
  <?php endif; ?>
</h2>
<?php endif; ?>

<div class="full-width-section product-summary op-order-vitrin">
    <div class="op-order-vitrin__hero opui-hero-wrap">
        <img class="opui-hero-img op-order-vitrin__img" src="uploads/<?= htmlspecialchars($imgFile, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $imgAlt ?>">
        <?php if (!empty($hero['overlay_enabled'])): ?>
        <div class="opui-hero-overlay">
            <div style="font-weight:700;font-size:<?= (int) ($hero['line1_size_px'] ?? 16) ?>px;color:<?= htmlspecialchars(order_page_ui_hex(isset($hero['line1_color']) ? (string) $hero['line1_color'] : null, '#ffffff'), ENT_QUOTES, 'UTF-8') ?>;text-shadow:0 1px 3px rgba(0,0,0,.85);margin-bottom:6px;line-height:1.2;">
                <?= htmlspecialchars(order_page_ui_replace_tokens((string) ($hero['line1_tpl'] ?? ''), $tok), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="font-weight:800;font-size:<?= (int) ($hero['line2_size_px'] ?? 36) ?>px;color:<?= htmlspecialchars(order_page_ui_hex(isset($hero['line2_color']) ? (string) $hero['line2_color'] : null, '#ffffff'), ENT_QUOTES, 'UTF-8') ?>;text-shadow:0 2px 8px rgba(0,0,0,.9);line-height:1;margin-bottom:6px;">
                <?= htmlspecialchars(order_page_ui_replace_tokens((string) ($hero['line2_tpl'] ?? ''), $tok), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="font-size:<?= (int) ($hero['line3_size_px'] ?? 13) ?>px;color:<?= htmlspecialchars(order_page_ui_hex(isset($hero['line3_color']) ? (string) $hero['line3_color'] : null, '#ffffff'), ENT_QUOTES, 'UTF-8') ?>;text-shadow:0 1px 3px rgba(0,0,0,.8);margin-bottom:8px;">
                <?= htmlspecialchars(order_page_ui_replace_tokens((string) ($hero['line3_tpl'] ?? ''), $tok), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="display:inline-block;font-weight:800;font-size:<?= (int) ($hero['line4_size_px'] ?? 14) ?>px;color:<?= htmlspecialchars(order_page_ui_hex(isset($hero['line4_color']) ? (string) $hero['line4_color'] : null, '#1e293b'), ENT_QUOTES, 'UTF-8') ?>;background:<?= htmlspecialchars(order_page_ui_hex(isset($hero['line4_bg']) ? (string) $hero['line4_bg'] : null, '#ffffff'), ENT_QUOTES, 'UTF-8') ?>;padding:8px 18px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.2);">
                <?= htmlspecialchars(order_page_ui_replace_tokens((string) ($hero['line4_cta_tpl'] ?? ''), $tok), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="op-order-vitrin__body card-body text-center">

       <?php if (!empty($product['show_name_heading']) && !empty($p['show_heading'])): ?>
            <h2 class="op-order-vitrin__title lightning-effect"><?= htmlspecialchars(function_exists('content_t') ? content_t('product', (int) ($product['product_id'] ?? 0), 'name', (string) ($product['product_name'] ?? '')) : (string) ($product['product_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php endif; ?>
<?php if (!empty($product['show_price'])): ?>
    <?php
    $originalPrice = (float) ($product['original_price'] ?? 0);
    $salePrice = (float) ($product['product_price'] ?? 0);
    $vitrinDiscountPct = 0;

    if (!empty($p['show_strikethrough']) && $originalPrice > $salePrice && $originalPrice > 0) {
        $vitrinDiscountPct = (int) round((1 - $salePrice / $originalPrice) * 100);
    }
    ?>
    <div class="opui-price-block op-order-vitrin__prices">
        <?php if (!empty($p['show_strikethrough'])): ?>
        <span class="opui-price-old op-order-vitrin__price-old price" data-meta-price="false">
            <?= function_exists('money') ? htmlspecialchars(money($originalPrice)) : number_format($originalPrice, 2, ',', '.') . ' TL' ?>
        </span>
        <?php endif; ?>
        $vitrinSaveStyle = function_exists('shop_use_save_badge') && shop_use_save_badge();
        $vitrinSavedTry = ($originalPrice > $salePrice) ? ($originalPrice - $salePrice) : 0;
        $vitrinInstall = function_exists('shop_interest_free_label') ? shop_interest_free_label($salePrice) : '';
        ?>
        <span class="opui-price-current op-order-vitrin__price-sale price" data-meta-price="false"><?= function_exists('money') ? htmlspecialchars(money($salePrice)) : number_format($salePrice, 2, ',', '.') . ' TL' ?></span>
        <span style="display:none;" data-meta-price="true"><?= number_format($salePrice, 2, '.', '') ?></span>
        <?php if ($vitrinDiscountPct > 0 && (int) ($notification['show_discount_badge_order'] ?? 1) === 1): ?>
        <span class="op-order-vitrin__discount" aria-label="<?= $vitrinSaveStyle ? (function_exists('t') ? t('shop.save', 'TASARRUF') : 'SAVE') : ($vitrinDiscountPct . ' ' . (function_exists('t') ? t('shop.discount', 'indirim') : 'indirim')) ?>">
            <?php if ($vitrinSaveStyle): ?>
            <span class="op-order-vitrin__discount-label"><?= function_exists('te') ? te('shop.save', 'TASARRUF') : 'SAVE' ?></span>
            <span class="op-order-vitrin__discount-pct"><?= htmlspecialchars(function_exists('money_save') ? money_save($vitrinSavedTry) : '') ?></span>
            <?php else: ?>
            <span class="op-order-vitrin__discount-pct">%<?= $vitrinDiscountPct ?></span>
            <span class="op-order-vitrin__discount-label"><?= function_exists('te') ? te('shop.discount', 'indirim') : 'indirim' ?></span>
            <?php endif; ?>
        </span>
        <?php endif; ?>
        <p class="op-order-vitrin__ship"><i class="fas fa-truck-fast" aria-hidden="true"></i> <?= function_exists('te') ? te('shop.fast_shipping', 'Hızlı kargo') : 'Hızlı kargo' ?></p>
        <?php if ($vitrinInstall !== ''): ?>
        <p class="op-order-vitrin__pay"><?= htmlspecialchars($vitrinInstall) ?></p>
        <?php endif; ?>
                <?php
                $pf = $ui['post_footer_msg'] ?? [];
                $noticeTxt = '';

                /** @suppress */
                if (!empty($pf['prefer_settings_text']) && trim((string) ($pf['settings_text'] ?? '')) !== '') {
                    $noticeTxt = trim((string) $pf['settings_text']);
                } elseif (!empty($pf['use_footer_id_7'])
                    && !empty($post_order_msg['show_post_order_msg'])
                    && (int) $post_order_msg['show_post_order_msg'] === 1
                    && trim((string) ($post_order_msg['post_order_msg_text'] ?? '')) !== '') {
                    /** @suppress */
                    $noticeTxt = trim((string) $post_order_msg['post_order_msg_text']);
                }
                ?>
                <?php if ($noticeTxt !== ''): ?>
                <p class="opui-price-notice op-order-vitrin__notice"><?= htmlspecialchars($noticeTxt, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php
                $sn = isset($p['shipping_notice']) && is_array($p['shipping_notice']) ? $p['shipping_notice'] : [];
                ?>

                <?php if (!empty($sn['show']) && trim((string) ($sn['text'] ?? '')) !== ''): ?>

                <p class="opui-price-notice op-order-vitrin__notice op-order-vitrin__notice--shipping"><?= nl2br(htmlspecialchars((string) $sn['text'], ENT_QUOTES, 'UTF-8')) ?></p>

                <?php endif; ?>

    </div>

<?php endif; ?>

    </div>

</div>
