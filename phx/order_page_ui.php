<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require __DIR__ . '/auth.php';

$page_title = 'Sipariş Sayfası Görünümü';

require dirname(__DIR__) . '/includes/order_page_ui.php';

function opui_post_int(string $k, int $min, int $max, int $def): int
{
    $v = (int) ($_POST[$k] ?? $def);

    return max($min, min($max, $v));
}

function opui_post_float(string $k, float $min, float $max, float $def): float
{
    $v = (float) ($_POST[$k] ?? $def);

    return max($min, min($max, $v));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prev = order_page_ui_get($pdo);
    $heroCustom = trim((string) ($_POST['op_hero_custom_existing'] ?? ''));

    if (!empty($_FILES['op_hero_upload']['tmp_name']) && is_uploaded_file($_FILES['op_hero_upload']['tmp_name'])) {
        /** @disregard */
        $ext = strtolower(pathinfo((string) ($_FILES['op_hero_upload']['name'] ?? ''), PATHINFO_EXTENSION));
        /** @disregard */
        $ok = $ext !== '' && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        if (!$ok) {

            /** @disregard */

            $chk = @getimagesize($_FILES['op_hero_upload']['tmp_name']);


            $ok = $chk !== false;

        }

        if ($ok) {
            $bn = 'order_pg_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
            $target = dirname(__DIR__) . '/uploads/' . $bn;

            if (@move_uploaded_file($_FILES['op_hero_upload']['tmp_name'], $target)) {
                $heroCustom = $bn;
            }
        }
    }

    $cfg = $prev;
    $cfg['cart_bar']['show'] = isset($_POST['op_cart_show']) ? 1 : 0;


    $cfg['cart_bar']['title'] = mb_substr(trim((string) ($_POST['op_cart_title'] ?? '')), 0, 120) ?: 'SEPETİNİZ';


    $cfg['cart_bar']['bg'] = trim((string) ($_POST['op_cart_bg'] ?? '#5fbd0f'));


    $cfg['cart_bar']['color'] = trim((string) ($_POST['op_cart_fg'] ?? '#ffffff'));

    $cfg['cart_bar']['font_px'] = opui_post_int('op_cart_font_px', 12, 64, 30);

    $cfg['cart_bar']['font_weight'] = opui_post_int('op_cart_fw', 400, 900, 700);


    $cfg['cart_bar']['padding_y_px'] = opui_post_int('op_cart_pad_y', 8, 80, 27);



    $cfg['cart_bar']['show_arrows'] = isset($_POST['op_cart_arrows']) ? 1 : 0;


    $cfg['countdown']['style_enabled'] = isset($_POST['op_cd_style']) ? 1 : 0;


    $cfg['countdown']['message_override'] = mb_substr(trim((string) ($_POST['op_cd_msg'] ?? '')), 0, 200);


    $cfg['countdown']['grad_start'] = trim((string) ($_POST['op_cd_g1'] ?? '#ff6b6b'));


    $cfg['countdown']['grad_end'] = trim((string) ($_POST['op_cd_g2'] ?? '#ee5a24'));


    $cfg['countdown']['banner_text_color'] = trim((string) ($_POST['op_cd_txt'] ?? '#ffffff'));

    $cfg['countdown']['label_font_px'] = opui_post_int('op_cd_lab_fs', 10, 42, 14);

    $cfg['countdown']['timer_font_px'] = opui_post_int('op_cd_digit_fs', 10, 52, 18);

    $cfg['countdown']['timer_box_bg'] = mb_substr(trim((string) ($_POST['op_cd_digit_bg'] ?? 'rgba(255,255,255,0.2)')), 0, 80);


    $cfg['countdown']['timer_digit_color'] = trim((string) ($_POST['op_cd_digit_col'] ?? '#ffffff'));


    $cfg['countdown']['lbl_gun'] = mb_substr(trim((string) ($_POST['op_cd_lbl_d'] ?? 'Gün')), 0, 12);


    $cfg['countdown']['lbl_saat'] = mb_substr(trim((string) ($_POST['op_cd_lbl_h'] ?? 'Saat')), 0, 12);


    $cfg['countdown']['lbl_dakika'] = mb_substr(trim((string) ($_POST['op_cd_lbl_m'] ?? 'Dakika')), 0, 12);


    $cfg['countdown']['lbl_saniye'] = mb_substr(trim((string) ($_POST['op_cd_lbl_s'] ?? 'Saniye')), 0, 12);


    $cfg['hero']['mode'] = (isset($_POST['op_hero_mode']) && $_POST['op_hero_mode'] === 'custom') ? 'custom' : 'product';


    $cfg['hero']['custom_image'] = $heroCustom;


    $cfg['hero']['default_piece_qty'] = max(1, min(999, (int) ($_POST['op_hero_qty'] ?? 2)));

    $cfg['hero']['overlay_enabled'] = isset($_POST['op_overlay']) ? 1 : 0;

    $cfg['hero']['overlay_pos'] = (isset($_POST['op_overlay_align']) && $_POST['op_overlay_align'] === 'center') ? 'center' : 'left';

    $cfg['hero']['overlay_top_pct'] = opui_post_float('op_ol_top', 0.0, 90.0, 8.0);

    $cfg['hero']['overlay_left_pct'] = opui_post_float('op_ol_left', 0.0, 90.0, 6.0);

    $cfg['hero']['overlay_width_pct'] = opui_post_float('op_ol_w', 30.0, 100.0, 88.0);

    $cfg['hero']['overlay_pad_px'] = opui_post_int('op_ol_pad', 4, 40, 14);

    $cfg['hero']['line1_tpl'] = mb_substr((string) ($_POST['op_ol_l1'] ?? ''), 0, 200);

    $cfg['hero']['line2_tpl'] = mb_substr((string) ($_POST['op_ol_l2'] ?? ''), 0, 200);

    $cfg['hero']['line3_tpl'] = mb_substr((string) ($_POST['op_ol_l3'] ?? ''), 0, 200);

    $cfg['hero']['line4_cta_tpl'] = mb_substr((string) ($_POST['op_ol_l4'] ?? ''), 0, 80);

    $cfg['hero']['line1_size_px'] = opui_post_int('op_l1s', 8, 80, 16);

    $cfg['hero']['line2_size_px'] = opui_post_int('op_l2s', 8, 96, 36);

    $cfg['hero']['line3_size_px'] = opui_post_int('op_l3s', 8, 48, 13);

    $cfg['hero']['line4_size_px'] = opui_post_int('op_l4s', 8, 36, 14);

    $cfg['hero']['line1_color'] = trim((string) ($_POST['op_l1c'] ?? '#ffffff'));

    $cfg['hero']['line2_color'] = trim((string) ($_POST['op_l2c'] ?? '#ffffff'));

    $cfg['hero']['line3_color'] = trim((string) ($_POST['op_l3c'] ?? '#ffffff'));

    $cfg['hero']['line4_color'] = trim((string) ($_POST['op_l4c'] ?? '#1e293b'));


    $cfg['hero']['line4_bg'] = trim((string) ($_POST['op_l4bg'] ?? '#ffffff'));


    $cfg['product']['show_heading'] = isset($_POST['op_ph_show']) ? 1 : 0;

    $cfg['product']['name_color'] = trim((string) ($_POST['op_name_c'] ?? '#283458'));

    $cfg['product']['name_font_px'] = opui_post_int('op_name_fs', 12, 48, 25);

    $cfg['product']['name_font_weight'] = opui_post_int('op_name_fw', 400, 900, 700);

    $cfg['product']['show_strikethrough'] = isset($_POST['op_old_show']) ? 1 : 0;

    $cfg['product']['old_price_color'] = trim((string) ($_POST['op_old_c'] ?? '#6b7280'));
    $cfg['product']['old_price_font_px'] = opui_post_int('op_old_fs', 12, 40, 22);

    $cfg['product']['price_color'] = trim((string) ($_POST['op_pri_c'] ?? '#16a34a'));

    $cfg['product']['price_font_px'] = opui_post_int('op_pri_fs', 12, 48, 25);

    $cfg['product']['price_font_weight'] = opui_post_int('op_pri_fw', 400, 900, 700);

    $cfg['product']['spacing_name_price_px'] = max(-120, min(80, (int) ($_POST['op_pri_mt'] ?? -25)));
    $cfg['product']['shipping_notice']['show'] = isset($_POST['op_ship_show']) ? 1 : 0;

    $cfg['product']['shipping_notice']['text'] = mb_substr(trim((string) ($_POST['op_ship_txt'] ?? '')), 0, 800);

    $cfg['product']['shipping_notice']['color'] = trim((string) ($_POST['op_ship_c'] ?? '#FF7F00'));

    $cfg['product']['shipping_notice']['font_px'] = opui_post_int('op_ship_fs', 10, 32, 18);

    $cfg['product']['shipping_notice']['font_weight'] = opui_post_int('op_ship_fw', 400, 900, 700);

    $cfg['product']['shipping_notice']['align'] = (isset($_POST['op_ship_al']) && $_POST['op_ship_al'] === 'left') ? 'left'
        : ((isset($_POST['op_ship_al']) && $_POST['op_ship_al'] === 'right') ? 'right' : 'center');
    $cfg['product']['shipping_notice']['margin_top_px'] = max(0, min(120, (int) ($_POST['op_ship_mt'] ?? 8)));

    $cfg['post_footer_msg']['prefer_settings_text'] = isset($_POST['op_pf_own']) ? 1 : 0;
    $cfg['post_footer_msg']['settings_text'] = mb_substr(trim((string) ($_POST['op_pf_txt'] ?? '')), 0, 400);
    $cfg['post_footer_msg']['use_footer_id_7'] = isset($_POST['op_pf_f7']) ? 1 : 0;

    $cfg['post_footer_msg']['color'] = trim((string) ($_POST['op_pf_col'] ?? '#FF7F00'));

    $cfg['post_footer_msg']['font_px'] = opui_post_int('op_pf_fs', 10, 32, 18);

    $cfg['post_footer_msg']['font_weight'] = opui_post_int('op_pf_fw', 400, 900, 700);

    $cfg['notification_strip']['inherit_notification_settings'] = ($_POST['op_ns_mode'] ?? 'inherit') === 'inherit';


    $cfg['notification_strip']['bar_bg'] = trim((string) ($_POST['op_ns_bg'] ?? ''));


    $cfg['notification_strip']['bar_color'] = trim((string) ($_POST['op_ns_fg'] ?? '#283458'));

    $cfg['notification_strip']['bar_font_px'] = opui_post_int('op_ns_fs', 12, 40, 22);

    $cfg['notification_strip']['bar_padding_px'] = opui_post_int('op_ns_pad', 6, 48, 12);

    try {
        $up = $pdo->prepare('UPDATE order_page_ui SET config_json = ? WHERE id = 1');
        $up->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $_SESSION['message'] = 'Sipariş sayfası görünümü kaydedildi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Kayıt hatası: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }


    header('Location: order_page_ui.php');


    exit;

}

$u = order_page_ui_get($pdo);

$c = $u['cart_bar'] ?? [];
$cd = $u['countdown'] ?? [];
$h = $u['hero'] ?? [];
$p = $u['product'] ?? [];
$sn = $u['product']['shipping_notice'] ?? [];
$pf = $u['post_footer_msg'] ?? [];
$ns = $u['notification_strip'] ?? [];

include 'admin_header.php';

?>


<style>.opui-mini { font-size: 0.8rem; }</style>

<div class="container-fluid py-3" style="max-width:980px;">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <?php $shipAl = (string) ($sn['align'] ?? 'center'); $ovlAl = (($h['overlay_pos'] ?? '') === 'center') ? 'center' : 'left'; ?>

    <h1 class="h4 mb-2"><i class="fas fa-cart-shopping text-primary"></i> Sipariş sayfası — vitrin blokları</h1>
    <p class="text-muted small mb-4">Sepet şeridi, geri sayım, üst görsel / katman metinleri, ürün başlığı ve fiyatlar ile bildirim çubuğu (isteğe bağlı) buradan özelleştirilir. Katman şablonlarında <code>{qty}</code>, <code>{short_name}</code>, <code>{price_fmt}</code>, <code>{old_price_fmt}</code> kullanılabilir.</p>

    <form method="post" enctype="multipart/form-data" class="mb-5">
        <input type="hidden" name="op_hero_custom_existing" value="<?= htmlspecialchars((string) ($h['custom_image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-cart-arrow-down text-success me-1"></i> Sepet şeridi</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="op_cart_show" id="op_cart_show" <?= !empty($c['show']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="op_cart_show">Göster</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small" for="op_cart_title">Başlık</label>
                        <input class="form-control form-control-sm" id="op_cart_title" name="op_cart_title" value="<?= htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cart_bg">Arka plan</label>
                        <input class="form-control form-control-sm" id="op_cart_bg" name="op_cart_bg" value="<?= htmlspecialchars((string) ($c['bg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cart_fg">Yazı rengi</label>
                        <input class="form-control form-control-sm" id="op_cart_fg" name="op_cart_fg" value="<?= htmlspecialchars((string) ($c['color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cart_font_px">Font px</label>
                        <input type="number" min="12" max="64" class="form-control form-control-sm" id="op_cart_font_px" name="op_cart_font_px" value="<?= (int) ($c['font_px'] ?? 30) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cart_fw">Kalınlık</label>
                        <input type="number" min="400" max="900" step="100" class="form-control form-control-sm" id="op_cart_fw" name="op_cart_fw" value="<?= (int) ($c['font_weight'] ?? 700) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cart_pad_y">Dikey iç boşluk (px)</label>
                        <input type="number" min="8" max="80" class="form-control form-control-sm" id="op_cart_pad_y" name="op_cart_pad_y" value="<?= (int) ($c['padding_y_px'] ?? 27) ?>">
                    </div>
                    <div class="col-md-3 align-self-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="op_cart_arrows" id="op_cart_arrows" <?= !empty($c['show_arrows']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="op_cart_arrows">Okları göster</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-hourglass-half text-danger me-1"></i> Geri sayım</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="op_cd_style" id="op_cd_style" <?= !empty($cd['style_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="op_cd_style">Özel gradyan / sayaç stilleri (sipariş sayfasında)</label>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small" for="op_cd_msg">Üst mesaj (boşsa site varsayılanı)</label>
                        <input class="form-control form-control-sm" id="op_cd_msg" name="op_cd_msg" maxlength="200" value="<?= htmlspecialchars((string) ($cd['message_override'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cd_g1">Gradyan başlangıç</label>
                        <input class="form-control form-control-sm" id="op_cd_g1" name="op_cd_g1" value="<?= htmlspecialchars((string) ($cd['grad_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cd_g2">Gradyan bitiş</label>
                        <input class="form-control form-control-sm" id="op_cd_g2" name="op_cd_g2" value="<?= htmlspecialchars((string) ($cd['grad_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cd_txt">Yazı rengi</label>
                        <input class="form-control form-control-sm" id="op_cd_txt" name="op_cd_txt" value="<?= htmlspecialchars((string) ($cd['banner_text_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cd_lab_fs">Etiket px</label>
                        <input type="number" min="10" max="42" class="form-control form-control-sm" id="op_cd_lab_fs" name="op_cd_lab_fs" value="<?= (int) ($cd['label_font_px'] ?? 14) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cd_digit_fs">Rakamlar px</label>
                        <input type="number" min="10" max="52" class="form-control form-control-sm" id="op_cd_digit_fs" name="op_cd_digit_fs" value="<?= (int) ($cd['timer_font_px'] ?? 18) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_cd_digit_bg">Sayı kutusu arka plan</label>
                        <input class="form-control form-control-sm opui-mini" id="op_cd_digit_bg" name="op_cd_digit_bg" value="<?= htmlspecialchars((string) ($cd['timer_box_bg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small" for="op_cd_digit_col">Rakam rengi</label>
                        <input class="form-control form-control-sm" id="op_cd_digit_col" name="op_cd_digit_col" value="<?= htmlspecialchars((string) ($cd['timer_digit_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <?php foreach (['d' => ['lbl' => 'Gün', 'field' => 'lbl_gun'], 'h' => ['lbl' => 'Saat', 'field' => 'lbl_saat'], 'm' => ['lbl' => 'Dakika', 'field' => 'lbl_dakika'], 's' => ['lbl' => 'Saniye', 'field' => 'lbl_saniye']] as $k => $lab): ?>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_cd_lbl_<?= $k ?>">Etiket <?= htmlspecialchars($lab['lbl'], ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control form-control-sm" id="op_cd_lbl_<?= $k ?>" name="op_cd_lbl_<?= $k ?>" maxlength="12" value="<?= htmlspecialchars((string) ($cd[$lab['field']] ?? $lab['lbl']), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-image text-primary me-1"></i> Üst görsel (hero)</div>
            <div class="card-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-8">
                        <label class="form-label small">Kaynak</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="op_hero_mode" id="op_hero_mode_prod" value="product" <?= (($h['mode'] ?? '') !== 'custom') ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="op_hero_mode_prod">Ürün görseli</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="op_hero_mode" id="op_hero_mode_cust" value="custom" <?= (($h['mode'] ?? '') === 'custom') ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="op_hero_mode_cust">Özel görsel (yüklenen dosya)</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_hero_qty">Varsayılan adet</label>
                        <input type="number" min="1" max="999" class="form-control form-control-sm" id="op_hero_qty" name="op_hero_qty" value="<?= (int) ($h['default_piece_qty'] ?? 2) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="op_hero_upload">Özel görsel yükle</label>
                        <input class="form-control form-control-sm" type="file" name="op_hero_upload" id="op_hero_upload" accept="image/*">
                        <?php $hi = trim((string) ($h['custom_image'] ?? '')); if ($hi !== ''): ?>
                            <div class="mt-2"><span class="opui-mini text-muted">Aktif: </span><a href="<?= htmlspecialchars('../uploads/' . $hi, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($hi, ENT_QUOTES, 'UTF-8') ?></a></div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="op_overlay" id="op_overlay" <?= !empty($h['overlay_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="op_overlay">Görsel üstü yazıları göster</label>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Hizalama</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="op_overlay_align" id="op_ov_l" value="left" <?= $ovlAl !== 'center' ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="op_ov_l">Sol</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="op_overlay_align" id="op_ov_c" value="center" <?= $ovlAl === 'center' ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="op_ov_c">Orta</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="op_ol_top">Üst %</label>
                        <input type="number" step="0.1" min="0" max="90" class="form-control form-control-sm" id="op_ol_top" name="op_ol_top" value="<?= htmlspecialchars((string) ($h['overlay_top_pct'] ?? 8), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="op_ol_left">Sol %</label>
                        <input type="number" step="0.1" min="0" max="90" class="form-control form-control-sm" id="op_ol_left" name="op_ol_left" value="<?= htmlspecialchars((string) ($h['overlay_left_pct'] ?? 6), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="op_ol_w">Genişlik %</label>
                        <input type="number" step="0.1" min="30" max="100" class="form-control form-control-sm" id="op_ol_w" name="op_ol_w" value="<?= htmlspecialchars((string) ($h['overlay_width_pct'] ?? 88), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="op_ol_pad">İç px</label>
                        <input type="number" min="4" max="40" class="form-control form-control-sm" id="op_ol_pad" name="op_ol_pad" value="<?= (int) ($h['overlay_pad_px'] ?? 14) ?>">
                    </div>
                </div>
                <?php foreach ([['1', 'satır 1'], ['2', 'satır 2'], ['3', 'satır 3']] as [$num, $lab]): ?>
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-lg-8">
                            <label class="form-label small" for="op_ol_l<?= $num ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></label>
                            <input class="form-control form-control-sm" id="op_ol_l<?= $num ?>" name="op_ol_l<?= $num ?>" maxlength="200" value="<?= htmlspecialchars((string) ($h['line' . $num . '_tpl'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-lg-1 col-4">
                            <label class="form-label small" for="op_l<?= $num ?>s">px</label>
                            <input type="number" min="8" max="96" class="form-control form-control-sm" id="op_l<?= $num ?>s" name="op_l<?= $num ?>s" value="<?= (int) ($h['line' . $num . '_size_px'] ?? (($num === '1') ? 16 : (($num === '2') ? 36 : 13))) ?>">
                        </div>
                        <div class="col-lg-3 col-8">
                            <label class="form-label small" for="op_l<?= $num ?>c">renk</label>
                            <input class="form-control form-control-sm" id="op_l<?= $num ?>c" name="op_l<?= $num ?>c" value="<?= htmlspecialchars((string) ($h['line' . $num . '_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-lg-8">
                        <label class="form-label small" for="op_ol_l4">CTA (satır 4)</label>
                        <input class="form-control form-control-sm" id="op_ol_l4" name="op_ol_l4" maxlength="80" value="<?= htmlspecialchars((string) ($h['line4_cta_tpl'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-lg-1 col-4">
                        <label class="form-label small" for="op_l4s">px</label>
                        <input type="number" min="8" max="36" class="form-control form-control-sm" id="op_l4s" name="op_l4s" value="<?= (int) ($h['line4_size_px'] ?? 14) ?>">
                    </div>
                    <div class="col-lg-3 col-8 row g-1">
                        <div class="col-6">
                            <label class="form-label small" for="op_l4c">yazı</label>
                            <input class="form-control form-control-sm" id="op_l4c" name="op_l4c" value="<?= htmlspecialchars((string) ($h['line4_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="op_l4bg">arka plan</label>
                            <input class="form-control form-control-sm" id="op_l4bg" name="op_l4bg" value="<?= htmlspecialchars((string) ($h['line4_bg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-tags text-secondary me-1"></i> Ürün başlığı &amp; fiyat</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="op_ph_show" id="op_ph_show" <?= !empty($p['show_heading']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="op_ph_show">Ürün adını göster</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small" for="op_name_c">Ad rengi</label>
                        <input class="form-control form-control-sm" id="op_name_c" name="op_name_c" value="<?= htmlspecialchars((string) ($p['name_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_name_fs">Ad px</label>
                        <input type="number" min="12" max="48" class="form-control form-control-sm" id="op_name_fs" name="op_name_fs" value="<?= (int) ($p['name_font_px'] ?? 25) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_name_fw">Ad kalınlık</label>
                        <input type="number" min="400" max="900" step="100" class="form-control form-control-sm" id="op_name_fw" name="op_name_fw" value="<?= (int) ($p['name_font_weight'] ?? 700) ?>">
                    </div>
                    <div class="col-12"><hr></div>
                    <div class="col-12">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="op_old_show" id="op_old_show" <?= !empty($p['show_strikethrough']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="op_old_show">Eski fiyat (üstü çizili)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="op_old_c">Eski fiyat rengi</label>
                        <input class="form-control form-control-sm" id="op_old_c" name="op_old_c" value="<?= htmlspecialchars((string) ($p['old_price_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="op_old_fs">Eski px</label>
                        <input type="number" min="12" max="40" class="form-control form-control-sm" id="op_old_fs" name="op_old_fs" value="<?= (int) ($p['old_price_font_px'] ?? 22) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pri_c">Güncel fiyat rengi</label>
                        <input class="form-control form-control-sm" id="op_pri_c" name="op_pri_c" value="<?= htmlspecialchars((string) ($p['price_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pri_fs">Güncel px</label>
                        <input type="number" min="12" max="48" class="form-control form-control-sm" id="op_pri_fs" name="op_pri_fs" value="<?= (int) ($p['price_font_px'] ?? 25) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pri_fw">Güncel kalınlık</label>
                        <input type="number" min="400" max="900" step="100" class="form-control form-control-sm" id="op_pri_fw" name="op_pri_fw" value="<?= (int) ($p['price_font_weight'] ?? 700) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pri_mt">Ad–fiyat mesafesi (px, negatif olabilir)</label>
                        <input type="number" min="-120" max="80" class="form-control form-control-sm" id="op_pri_mt" name="op_pri_mt" value="<?= (int) ($p['spacing_name_price_px'] ?? -25) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-truck text-warning me-1"></i> Kargo satırı</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="op_ship_show" id="op_ship_show" <?= !empty($sn['show']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="op_ship_show">Göster</label>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small" for="op_ship_txt">Metin</label>
                        <textarea class="form-control form-control-sm" id="op_ship_txt" name="op_ship_txt" rows="3" maxlength="800"><?= htmlspecialchars((string) ($sn['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_ship_c">Renk</label>
                        <input class="form-control form-control-sm" id="op_ship_c" name="op_ship_c" value="<?= htmlspecialchars((string) ($sn['color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_ship_fs">px</label>
                        <input type="number" min="10" max="32" class="form-control form-control-sm" id="op_ship_fs" name="op_ship_fs" value="<?= (int) ($sn['font_px'] ?? 18) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_ship_fw">Kalınlık</label>
                        <input type="number" min="400" max="900" step="100" class="form-control form-control-sm" id="op_ship_fw" name="op_ship_fw" value="<?= (int) ($sn['font_weight'] ?? 700) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="op_ship_mt">Üst boşluk (px)</label>
                        <input type="number" min="0" max="120" class="form-control form-control-sm" id="op_ship_mt" name="op_ship_mt" value="<?= (int) ($sn['margin_top_px'] ?? 8) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Hiza</label>
                        <select class="form-select form-select-sm" id="op_ship_al" name="op_ship_al">
                            <option value="center" <?= $shipAl === 'center' ? 'selected' : '' ?>>Orta</option>
                            <option value="left" <?= $shipAl === 'left' ? 'selected' : '' ?>>Sol</option>
                            <option value="right" <?= $shipAl === 'right' ? 'selected' : '' ?>>Sağ</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-notes-medical text-info me-1"></i> Alt animasyon mesajı</div>
            <div class="card-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="op_pf_own" id="op_pf_own" <?= !empty($pf['prefer_settings_text']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="op_pf_own">Panelde yazılmış metni kullan (aşağıdaki kutu)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label small" for="op_pf_txt">Özel metin</label>
                    <textarea class="form-control form-control-sm" id="op_pf_txt" name="op_pf_txt" rows="3" maxlength="400"><?= htmlspecialchars((string) ($pf['settings_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="op_pf_f7" id="op_pf_f7" <?= !empty($pf['use_footer_id_7']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="op_pf_f7">Alt görsel/footer id=7 girdisi yoksa yedek kaynak olarak kullan</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pf_col">Renk</label>
                        <input class="form-control form-control-sm" id="op_pf_col" name="op_pf_col" value="<?= htmlspecialchars((string) ($pf['color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pf_fs">px</label>
                        <input type="number" min="10" max="32" class="form-control form-control-sm" id="op_pf_fs" name="op_pf_fs" value="<?= (int) ($pf['font_px'] ?? 18) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="op_pf_fw">Kalınlık</label>
                        <input type="number" min="400" max="900" step="100" class="form-control form-control-sm" id="op_pf_fw" name="op_pf_fw" value="<?= (int) ($pf['font_weight'] ?? 700) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-bell text-muted me-1"></i> Üst bildirim şeridi (promo)</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small d-block">Çalışma şekli</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="op_ns_mode" id="op_ns_i" value="inherit" <?= !empty($ns['inherit_notification_settings']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="op_ns_i">Global bildirim ayarları (tema ile aynı)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="op_ns_mode" id="op_ns_c" value="custom" <?= empty($ns['inherit_notification_settings']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="op_ns_c">Sipariş sayfası için özelleştir</label>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small" for="op_ns_bg">Arka plan (boş = varsayılan)</label>
                        <input class="form-control form-control-sm" id="op_ns_bg" name="op_ns_bg" value="<?= htmlspecialchars((string) ($ns['bar_bg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="op_ns_fg">Yazı rengi</label>
                        <input class="form-control form-control-sm" id="op_ns_fg" name="op_ns_fg" value="<?= htmlspecialchars((string) ($ns['bar_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="op_ns_fs">Font px</label>
                        <input type="number" min="12" max="40" class="form-control form-control-sm" id="op_ns_fs" name="op_ns_fs" value="<?= (int) ($ns['bar_font_px'] ?? 22) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="op_ns_pad">Dikey iç boşluk (px)</label>
                        <input type="number" min="6" max="48" class="form-control form-control-sm" id="op_ns_pad" name="op_ns_pad" value="<?= (int) ($ns['bar_padding_px'] ?? 12) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Kaydet</button>
        </div>
    </form>

</div>

<?php include 'admin_footer_common.php'; ?>
