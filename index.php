<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';
require_once __DIR__ . '/includes/app_url.php';
$__iru = (string) ($_SERVER['REQUEST_URI'] ?? '');
$carkifelek_return_url = ($__iru !== '' && $__iru[0] === '/') ? $__iru : app_url('', [], $pdo);
require_once 'tracking.php';
require_once __DIR__ . '/includes/promo_banner_render.php';
require_once __DIR__ . '/includes/page_meta_load.php';
require_once __DIR__ . '/includes/page_seo.php';
require_once __DIR__ . '/includes/site_footer.php';
require_once __DIR__ . '/includes/abandoned_capture.php';
require_once __DIR__ . '/includes/conv_trial.php';

date_default_timezone_set('Europe/Istanbul');

// Referans (?ref=) tracking.php içinde işlenir (oturum + çerez)

$page_name = basename(__FILE__);
$ip_address = $_SERVER['REMOTE_ADDR'];
$visit_time = date('Y-m-d H:i:s');
$stmt = $pdo->query('SELECT * FROM notification_settings WHERE id = 1');
$notification = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$settings = $notification;
$stmt = $pdo->prepare("INSERT INTO page_views (page_name, ip_address, visit_time) VALUES (?, ?, ?)");
$stmt->execute([$page_name, $ip_address, $visit_time]);

// $stmt = $pdo->prepare("INSERT INTO page_visits (page_name, ip_address, visit_time) VALUES (?, ?, ?)");
// $stmt->execute([$page_name, $ip_address, $visit_time]);

$stmt = $pdo->prepare('SELECT * FROM products WHERE status = "visible" ORDER BY display_order ASC, product_id ASC');
$stmt->execute();
$products = $stmt->fetchAll();

$meta = page_meta_load($pdo, $page_name) ?? [];
$seo = page_seo_resolve($pdo, $page_name, $meta);
$page_title = htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8');

$stmt = $pdo->query('SELECT * FROM slider_images ORDER BY display_order ASC');
$slider_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fakeAlerts = [];
try {
    $fakeAlerts = $pdo->query(
        'SELECT customer_name, city_name, time_label FROM fake_notifications WHERE is_active = 1 ORDER BY weight DESC, sort_order DESC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    $fakeAlerts = [];
}

$hpDefaults = [
    'section_enabled' => 1,
    'show_heading' => 1,
    'show_heading_main' => 1,
    'show_heading_sub' => 1,
    'heading_main' => '',
    'heading_sub' => '',
    'heading_main_color' => '#f97316',
    'heading_sub_color' => '#283458',
    'heading_main_font' => '',
    'heading_sub_font' => '',
    'card_name_color' => '#15803d',
    'card_description_color' => '#374151',
    'card_original_price_color' => '#6b7280',
    'card_sale_price_color' => '#15803d',
    'cta_bg_color' => '#5fbd0f',
];
$hpSec = $hpDefaults;
try {
    $hRow = $pdo->query('SELECT * FROM homepage_product_section WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if ($hRow) {
        $hpSec = array_merge($hpDefaults, $hRow);

    }

} catch (Throwable $t) {
    $hpSec = $hpDefaults;

}

if (trim((string) ($hpSec['heading_main'] ?? '')) === '' && trim((string) ($hpSec['heading_sub'] ?? '')) === '') {
    try {
        $leg = $pdo->query('SELECT home_heading_main, home_heading_sub FROM footer_images WHERE id = 8')->fetch(PDO::FETCH_ASSOC);

        if ($leg) {
            $hpSec['heading_main'] = (string) ($leg['home_heading_main'] ?? '');
            $hpSec['heading_sub'] = (string) ($leg['home_heading_sub'] ?? '');
        }

    } catch (Throwable $t2) {

    }

    if (trim((string) $hpSec['heading_main']) === '' && trim((string) $hpSec['heading_sub']) === '') {
        $hpSec['heading_main'] = 'Ürünlerimiz';
        $hpSec['heading_sub'] = 'Güvenli alışveriş';
    }
}

$cvOffer = conv_trial_on() ? conv_trial_offer(is_array($products) ? $products : [], $hpSec, $pdo) : null;

$fayansHomeVideo = null;
$fayansHomeVideoConfigPath = __DIR__ . '/includes/fayans_home_video.php';
if (is_file($fayansHomeVideoConfigPath)) {
    $fayansHomeVideoCfg = require $fayansHomeVideoConfigPath;
    if (is_array($fayansHomeVideoCfg) && trim((string) ($fayansHomeVideoCfg['video_src'] ?? '')) !== '') {
        $fayansVideoSrc = str_replace('\\', '/', trim((string) $fayansHomeVideoCfg['video_src']));
        $fayansVideoSrc = preg_replace('#^\.\./+#', '', $fayansVideoSrc);
        $fayansVideoSrc = ltrim($fayansVideoSrc, '/');
        if (strpos($fayansVideoSrc, 'uploads/') !== 0 && ($p = strpos($fayansVideoSrc, 'uploads/')) !== false) {
            $fayansVideoSrc = substr($fayansVideoSrc, $p);
        }
        $fayansHomeVideo = [
            'trigger_image' => basename(str_replace('\\', '/', (string) ($fayansHomeVideoCfg['trigger_image'] ?? ''))),
            'trigger_product_position' => (int) ($fayansHomeVideoCfg['trigger_product_position'] ?? 0),
            'trigger_slider_position' => (int) ($fayansHomeVideoCfg['trigger_slider_position'] ?? 0),
            'video_src' => $fayansVideoSrc,
        ];
    }
}

$hpVideoPopupMatches = static function (string $imagePath, int $position = 0, string $positionKey = 'trigger_product_position') use ($fayansHomeVideo): bool {
    if ($fayansHomeVideo === null || trim($imagePath) === '') {
        return false;
    }
    $triggerImage = trim((string) ($fayansHomeVideo['trigger_image'] ?? ''));
    $triggerPosition = (int) ($fayansHomeVideo[$positionKey] ?? 0);
    if ($triggerPosition > 0 && $position > 0 && $position === $triggerPosition) {
        return true;
    }

    return $triggerImage !== '' && basename(str_replace('\\', '/', $imagePath)) === $triggerImage;
};

$hpNormalizeUploadPath = static function (string $path): string {
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#^(\.\./)+#', '', $path);
    $path = preg_replace('#^uploads/\.\./uploads/#', 'uploads/', $path);
    $path = ltrim($path, '/');
    if ($path === '') {
        return '';
    }
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }

    return 'uploads/' . $path;
};

$hpProductImageSrc = static function (int $productId, ?string $productImageCol, PDO $pdo, callable $normalize): string {
    $placeholder = 'uploads/txrik.gif';
    $candidates = [];

    $imageStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY image_id ASC');
    $imageStmt->execute([$productId]);
    foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $norm = $normalize((string) ($row['image_path'] ?? ''));
        if ($norm !== '') {
            $candidates[] = $norm;
        }
    }

    $legacy = trim((string) $productImageCol);
    if ($legacy !== '') {
        $norm = $normalize($legacy);
        if ($norm !== '' && ! in_array($norm, $candidates, true)) {
            $candidates[] = $norm;
        }
    }

    $root = __DIR__;
    foreach ($candidates as $rel) {
        $full = $root . '/' . $rel;
        if (is_file($full)) {
            return $rel;
        }
    }

    if (is_file($root . '/' . $placeholder)) {
        return $placeholder;
    }

    return $candidates[0] ?? $placeholder;
};

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>

    <?php page_seo_render($pdo, $page_name, $meta); ?>
    <?php if ($meta && !empty($meta['head_content'])): ?>
        <?= $meta['head_content']; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/site_tracking_head.php'; ?>
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index-1.css">
    <link rel="stylesheet" href="css/payment-trust.css">
    <link rel="stylesheet" href="css/site-footer.css">
<?php if (conv_trial_on() && $cvOffer): ?>
    <link rel="stylesheet" href="css/conv-trial.css?v=20260817a">
<?php endif; ?>
<?php if ($fayansHomeVideo !== null): ?>
    <link rel="stylesheet" href="css/fayans-home-video.css">
<?php endif; ?>
<style>
    /* Sosyal kanıt: indirim bandından bağımsız — altta orta; giriş/çıkış animasyonu için .fk-live-toast__slide */
    .fk-live-toast {
        display: none;
        position: fixed;
        top: auto;
        bottom: calc(88px + env(safe-area-inset-bottom, 0px));
        left: 50%;
        transform: translateX(-50%);
        z-index: 1050;
        width: min(calc(100% - 20px), 360px);
        max-width: 92vw;
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        border: none;
        background: transparent;
        pointer-events: none;
        text-align: center;
    }
    @media (max-width: 480px) {
        .fk-live-toast {
            bottom: calc(76px + env(safe-area-inset-bottom, 0px));
            width: min(calc(100% - 16px), 320px);
        }
        .fk-live-toast__inner {
            padding: 5px 9px;
            gap: 6px;
        }
        .fk-live-toast__line {
            font-size: 12px;
        }
        .fk-live-toast__muted {
            font-size: 11px;
        }
    }
    .fk-live-toast__slide {
        transition: opacity 0.28s ease, transform 0.28s ease;
        opacity: 1;
        transform: translateY(0);
    }
    .fk-live-toast__slide--leave {
        opacity: 0;
        transform: translateY(10px);
        pointer-events: none;
    }
    .fk-live-toast__slide.fk-live-toast__slide--enter {
        opacity: 0;
        transform: translateY(-8px);
    }
    .fk-live-toast__inner {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 11px;
        margin: 0 auto;
        width: fit-content;
        max-width: 100%;
        background: rgba(255, 255, 255, 0.97);
        border-radius: 999px;
        border: 1px solid rgba(40, 52, 88, 0.1);
        box-shadow:
            0 2px 8px rgba(15, 23, 42, 0.06),
            0 1px 2px rgba(15, 23, 42, 0.04);
    }
    /* Tek küçük canlı gösterge — büyük ikon yok */
    .fk-live-toast__dot {
        flex-shrink: 0;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
    }
    .fk-live-toast__line {
        margin: 0;
        min-width: 0;
        font-size: 12.5px;
        line-height: 1.35;
        color: #475569;
        text-align: left;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        word-break: break-word;
    }
    .fk-live-toast__line strong {
        color: #1e293b;
        font-weight: 600;
    }
    .fk-live-toast__muted {
        color: #94a3b8;
        font-weight: 500;
        font-size: 11.5px;
    }
    </style>
</head>
<body class="site-shell-app homepage-view<?= (conv_trial_on() && $cvOffer) ? ' cv-trial-on' : '' ?>" style="background-color: white;">
<div id="popup-modal" class="popup-modal" onclick="closeModalOnBackdrop(event)">
    <span class="close-btn" onclick="closeModal(event)" aria-label="Kapat">✖</span>
    <div class="popup-modal__panel" onclick="event.stopPropagation()">
        <img id="popup-image" src="" alt="Büyütülmüş Görsel">
<?php if ($fayansHomeVideo !== null): ?>
        <video id="popup-video"
               class="popup-modal__video"
               playsinline
               controls
               preload="metadata"
               aria-label="Ürün tanıtım videosu">
            <source src="" type="video/mp4">
        </video>
<?php endif; ?>
    </div>
</div>
    <?php require'menu.php'; ?>

    <?php
    $GLOBALS['COUNTDOWN_STICKY_HOME'] = true;
    include 'countdown_timer.php';
    ?>

    <?php if ($meta && !empty($meta['body_content'])): ?>
        <?= $meta['body_content']; ?>
    <?php endif; ?>

 <?php if ($notification['is_active']): ?>
        <?= promo_banner_markup((string) ($notification['message'] ?? '')) ?>
    <?php endif; ?>

    <div class="slider">
        <?php $sliderIndex = 0; foreach ($slider_images as $image): $sliderIndex++; ?>
            <?php
            $sliderImgPath = $hpNormalizeUploadPath((string) ($image['image_path'] ?? ''));
            $sliderIsVideo = $hpVideoPopupMatches($sliderImgPath, $sliderIndex, 'trigger_slider_position');
            ?>
            <div class="slider-image<?= $sliderIsVideo ? ' js-hp-product-popup' : '' ?>"
                 <?= $sliderIsVideo ? '' : 'data-go-products' ?>
                 <?= $sliderIsVideo ? 'data-hp-popup="video" data-hp-src="' . htmlspecialchars($sliderImgPath, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                 role="button"
                 tabindex="0"
                 aria-label="<?= $sliderIsVideo ? 'Ürün videosunu oynat' : 'Ürünlere git' ?>">
                <img src="<?= htmlspecialchars($sliderImgPath) ?>" alt="">
            </div>
        <?php endforeach; ?>
    </div>

<?php if (conv_trial_on() && $cvOffer && ! empty($cvOffer['show_price'])): ?>
    <aside class="cv-offer" aria-label="Kampanya fiyatı">
        <div class="cv-offer__inner">
            <div class="cv-offer__copy">
                <p class="cv-offer__name"><?= htmlspecialchars((string) $cvOffer['name'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="cv-offer__prices">
<?php if (! empty($cvOffer['show_original'])): ?>
                    <span class="cv-offer__old"><?= htmlspecialchars((string) $cvOffer['original_fmt'], ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
                    <span class="cv-offer__sale"><?= htmlspecialchars((string) $cvOffer['sale_fmt'], ENT_QUOTES, 'UTF-8') ?></span>
<?php if ((int) $cvOffer['discount_pct'] > 0): ?>
                    <span class="cv-offer__off">%<?= (int) $cvOffer['discount_pct'] ?> <?= te('shop.discount', 'indirim') ?></span>
<?php endif; ?>
                </div>
            </div>
            <a class="cv-offer__cta" href="<?= htmlspecialchars((string) $cvOffer['order_url'], ENT_QUOTES, 'UTF-8') ?>" style="background-color: <?= htmlspecialchars((string) $cvOffer['cta_color'], ENT_QUOTES, 'UTF-8') ?>;">
                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                <?= te('shop.order_short', 'Sipariş Ver') ?>
            </a>
        </div>
        <p class="cv-offer__hint"><?= te('shop.offer_hint', 'Kapıda ödeme · Ücretsiz kargo · Kampanyalı fiyat') ?></p>
    </aside>
<?php endif; ?>

<?php
// Reviews section — manuel / YZ ayrı aç-kapa
$show_manual_front = 1;
$show_ai_front = 1;
try {
    $rsRow = $pdo->query(
        'SELECT show_reviews, COALESCE(show_manual_front,1) AS smf, COALESCE(show_ai_front,1) AS saf FROM reviews_settings WHERE id=1'
    )->fetch(PDO::FETCH_ASSOC);

    $showReviews = $rsRow ? (int) $rsRow['show_reviews'] : 1;
    $show_manual_front = $rsRow ? (int) $rsRow['smf'] : 1;
    $show_ai_front = $rsRow ? (int) $rsRow['saf'] : 1;

} catch (Exception $e) {
    try {
        $showRow = $pdo->query('SELECT show_reviews FROM reviews_settings WHERE id=1');
        $showReviews = $showRow ? (int) $showRow->fetchColumn() : 1;
    } catch (Exception $e2) {
        $showReviews = 1;
    }
}

$revVisible = $showReviews && ($show_manual_front || $show_ai_front);

$reviews = [];
try {
    if ($revVisible) {
        $w = [];
        if ($show_manual_front) {
            $w[] = '(COALESCE(r.is_ai,0)=0)';
        }

        if ($show_ai_front) {
            $w[] = '(COALESCE(r.is_ai,0)=1)';
        }

        $ws = $w !== [] ? ' AND (' . implode(' OR ', $w) . ')' : '';

        $revStmt = $pdo->prepare(
            'SELECT r.*, (
        SELECT GROUP_CONCAT(image_path ORDER BY display_order SEPARATOR "||")
        FROM product_review_images pri WHERE pri.review_id = r.review_id
    ) AS images
    FROM product_reviews r WHERE r.is_active = 1' . $ws . ' ORDER BY r.created_at DESC LIMIT 10'
        );

        $revStmt->execute();
        $reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

    }

} catch (Exception $e) {
    $reviews = [];
}

try {
    if ($revVisible) {
        $w = [];
        if ($show_manual_front) {
            $w[] = '(COALESCE(is_ai,0)=0)';
        }

        if ($show_ai_front) {
            $w[] = '(COALESCE(is_ai,0)=1)';
        }

        $ws = $w !== [] ? ' AND (' . implode(' OR ', $w) . ')' : '';

        $avgRow = $pdo->query('SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM product_reviews WHERE is_active=1' . $ws)->fetch(PDO::FETCH_ASSOC);

        $avgRating = $avgRow && $avgRow['avg_rating'] !== null ? round((float) $avgRow['avg_rating'], 1) : 0.0;
        $reviewCount = (int) ($avgRow['cnt'] ?? 0);

    } else {

        $avgRating = 0.0;
        $reviewCount = 0;

    }

} catch (Exception $e) {
    $avgRating = 0.0;
    $reviewCount = 0;

}

?>

<?php if ($revVisible): ?>
<section class="container-fluid" style="margin-top:20px; padding-left: 0; padding-right: 0;">
    <?php
    // Açıklama bölümü
    $introStmt = $pdo->query('SELECT is_active, title, content FROM review_intro_settings WHERE id=1');
    $introSettings = $introStmt ? $introStmt->fetch(PDO::FETCH_ASSOC) : null;

    // Çoklu açıklamaları çek
    $introItems = $pdo->query('SELECT * FROM review_intro_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if ($introSettings && $introSettings['is_active'] && (!empty($introSettings['content']) || !empty($introItems))): ?>
    <div class="mb-4">
        <?php if (!empty($introSettings['content'])): ?>
        <div class="custom-intro-banner">
            <!--<h3><?= htmlspecialchars($introSettings['title']) ?></h3>-->
            <div><?= $introSettings['content'] ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($introItems)): ?>
        <div class="intro-items-container">
            <?php foreach ($introItems as $item): ?>
            <div class="intro-item">
                <?php if ($item['image_path']): ?>
                <div class="intro-item-image">
                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title'] ?: 'Görsel') ?>" loading="lazy">
                </div>
                <?php endif; ?>
                <div class="intro-item-content">
                    <?php if ($item['title']): ?>
                    <h4><?= htmlspecialchars($item['title']) ?></h4>
                    <?php endif; ?>
                    <?php if ($item['content']): ?>
                    <div><?= $item['content'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>


    <style>
    .intro-items-container {
        margin-top: 0;
    }

    .intro-item {
        margin-bottom: 0;
    }

    .intro-item-image img {
        width: 100%;
        height: auto;
        display: block;
    }

    .intro-item-content h4 {
        margin-bottom: 0;
    }

    /* Mobilde yan boşlukları kaldır */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .row {
            margin-left: 0;
            margin-right: 0;
        }

        .col-12 {
            padding-left: 0;
            padding-right: 0;
        }
    }

    /* Countdown timer aktif olduğunda açıklama bölümüne etki etmesin */
    .intro-items-container {
        margin-top: 0 !important;
        padding-top: 0 !important;
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    .intro-item {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .intro-item-content {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .intro-item-content h4 {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .intro-item-content div {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    </style>
    <?php endif; ?>

    <h2 class="text-center mb-2" style="font-weight:700;color:#283458;">Müşteri Yorumları</h2>
    <div class="d-flex justify-content-center mb-3">
        <div class="px-3 py-2" style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:999px;display:flex;align-items:center;gap:10px;">
            <div>
                <?php for ($i=1; $i<=5; $i++): $filled = ($i <= floor($avgRating)); ?>
                    <i class="fa<?= $filled ? 's' : 'r' ?> fa-star" style="color:#f1c40f"></i>
                <?php endfor; ?>
            </div>
            <strong style="color:#283458;"><?= number_format($avgRating,1,',','.') ?>/5</strong>
            <span class="text-muted" style="font-size:13px;">(<?= $reviewCount ?> yorum)</span>
        </div>
    </div>
    <div class="row">
        <?php if (empty($reviews)): ?>
            <div class="col-12">
                <div class="card" style="border-radius:12px;">
                    <div class="card-body text-center text-muted">Henüz yorum bulunmuyor.</div>
                </div>
            </div>
        <?php else: foreach ($reviews as $rev): ?>
            <div class="col-12 mb-3">
                <div class="card" style="border-radius:12px;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <strong><?= htmlspecialchars($rev['reviewer_name']) ?></strong>
                                <?php
                                $cities = ['Adana','Adıyaman','Afyonkarahisar','Ağrı','Amasya','Ankara','Antalya','Artvin','Aydın','Balıkesir','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli','Diyarbakır','Düzce','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkari','Hatay','Iğdır','Isparta','İstanbul','İzmir','Kahramanmaraş','Karabük','Karaman','Kars','Kastamonu','Kayseri','Kilis','Kırıkkale','Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Mardin','Mersin','Muğla','Muş','Nevşehir','Niğde','Ordu','Osmaniye','Rize','Sakarya','Samsun','Şanlıurfa','Siirt','Sinop','Sivas','Şırnak','Tekirdağ','Tokat','Trabzon','Tunceli','Uşak','Van','Yalova','Yozgat','Zonguldak'];
                                $city_index = (int)$rev['review_id'] % count($cities);
                                $random_city = $cities[$city_index];
                                ?>
                                <span class="badge bg-success ms-2" style="font-weight:700;color:#fff;">Satın aldı - <?= htmlspecialchars($random_city) ?></span>
                            </div>
                            <div>
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <i class="fa<?= $i <= (int)$rev['rating'] ? 's' : 'r' ?> fa-star" style="color:#f1c40f"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="mb-2" style="color:#283458;"><?= htmlspecialchars($rev['review_text']) ?></p>
                        <small class="text-muted">
                            <?= date('d.m.Y', strtotime($rev['created_at'])) ?>
                        </small>
                        <?php if (!empty($rev['images'])): ?>
                            <div class="mt-2 d-flex gap-2" style="gap:8px;flex-wrap:wrap;">
                                <?php foreach (explode('||', (string)($rev['images'] ?? '')) as $p): ?>
                                    <?php $p = htmlspecialchars(str_replace('../','',$p)); ?>
                                    <img src="<?= $p ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #eee" onerror="this.style.display='none'">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty((int) ($hpSec['section_enabled'] ?? 1))): ?>
<?php
$hmc = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['heading_main_color'] ?? ''))) ? trim((string) $hpSec['heading_main_color']) : '#f97316', ENT_QUOTES, 'UTF-8');

$hsc = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['heading_sub_color'] ?? ''))) ? trim((string) $hpSec['heading_sub_color']) : '#283458', ENT_QUOTES, 'UTF-8');

$cn = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['card_name_color'] ?? ''))) ? trim((string) $hpSec['card_name_color']) : '#15803d', ENT_QUOTES, 'UTF-8');

$cdr = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['card_description_color'] ?? ''))) ? trim((string) $hpSec['card_description_color']) : '#374151', ENT_QUOTES, 'UTF-8');

$co = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['card_original_price_color'] ?? ''))) ? trim((string) $hpSec['card_original_price_color']) : '#6b7280', ENT_QUOTES, 'UTF-8');

$cs = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['card_sale_price_color'] ?? ''))) ? trim((string) $hpSec['card_sale_price_color']) : '#15803d', ENT_QUOTES, 'UTF-8');

$cta = htmlspecialchars(preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', trim((string) ($hpSec['cta_bg_color'] ?? ''))) ? trim((string) $hpSec['cta_bg_color']) : '#5fbd0f', ENT_QUOTES, 'UTF-8');

$hmf = '';
$tf = trim((string) ($hpSec['heading_main_font'] ?? ''));

if ($tf !== '') {
    $hmf = 'font-family:' . htmlspecialchars(str_replace([';', '<', '>', "\n", "\r"], '', $tf), ENT_QUOTES, 'UTF-8') . ';';

}

$hsf = '';
$tf2 = trim((string) ($hpSec['heading_sub_font'] ?? ''));

if ($tf2 !== '') {
    $hsf = 'font-family:' . htmlspecialchars(str_replace([';', '<', '>', "\n", "\r"], '', $tf2), ENT_QUOTES, 'UTF-8') . ';';

}

require_once __DIR__ . '/includes/payment_trust_badges.php';
$hpPaymentTrustBadges = payment_trust_badges_collect($pdo);

?>
<section id="products" class="homepage-products-scope" style="margin-top:10px;">
<?php if (!empty((int) ($hpSec['show_heading'] ?? 1))): ?>
    <h1 id="products-heading" class="text-center my-5" style="font-weight: bold;">
        <?php if (!empty((int) ($hpSec['show_heading_main'] ?? 1))): ?>
            <span style="<?= $hmf ?>color: <?= $hmc ?>;"><?= htmlspecialchars((string) ($hpSec['heading_main'] ?? '')) ?></span>
        <?php endif; ?>
        <?php if (!empty((int) ($hpSec['show_heading_main'] ?? 1)) && !empty((int) ($hpSec['show_heading_sub'] ?? 1))): ?>
            <br>
        <?php endif; ?>
        <?php if (!empty((int) ($hpSec['show_heading_sub'] ?? 1))): ?>
            <span style="<?= $hsf ?>color: <?= $hsc ?>;"><?= htmlspecialchars((string) ($hpSec['heading_sub'] ?? '')) ?></span>
        <?php endif; ?>
    </h1>
<?php if ($hpPaymentTrustBadges !== []): ?>
    <?php payment_trust_render($hpPaymentTrustBadges, 'payment-trust payment-trust--section'); ?>
<?php endif; ?>
<?php endif; ?>

<div class="container-fluid" style="margin-top:20px;padding-left:0;padding-right:0;">
    <div class="row">
<?php if ($products === []): ?>
    <div class="col-12">
        <div class="hp-empty-products text-center py-5 px-3" role="status">
            <i class="fas fa-box-open fa-2x mb-3" style="color:#94a3b8;" aria-hidden="true"></i>
            <p class="mb-0" style="color:#64748b;font-size:1.05rem;">Şu an listelenecek ürün bulunmuyor.</p>
            <p class="small text-muted mt-2 mb-0">Kısa süre içinde tekrar kontrol edebilirsiniz.</p>
        </div>
    </div>
<?php else: ?>
<?php $hpProductIndex = 0; foreach ($products as $product): $hpProductIndex++; ?>
    <div class="col-12 mb-4">
        <article class="hp-product-card product">

            <?php
            $hpImgPath = $hpProductImageSrc(
                (int) $product['product_id'],
                isset($product['product_image']) ? (string) $product['product_image'] : '',
                $pdo,
                $hpNormalizeUploadPath
            );
            $hpPopupMode = 'image';
            if ($fayansHomeVideo !== null && $hpImgPath !== '' && $hpVideoPopupMatches($hpImgPath, $hpProductIndex, 'trigger_product_position')) {
                $hpPopupMode = 'video';
            }
            ?>

            <div class="hp-product-card__media js-hp-product-popup"
                 data-hp-popup="<?= htmlspecialchars($hpPopupMode, ENT_QUOTES, 'UTF-8') ?>"
                 data-hp-src="<?= htmlspecialchars($hpImgPath, ENT_QUOTES, 'UTF-8') ?>"
                 role="button"
                 tabindex="0"
                 aria-label="<?= $hpPopupMode === 'video' ? 'Ürün videosunu oynat' : 'Görseli büyüt' ?>">
                <img src="<?= htmlspecialchars($hpImgPath) ?>"
                     class="hp-product-card__img product-image"
                     alt="<?= htmlspecialchars($product['product_name']) ?>"
                     onerror="this.onerror=null;this.src='uploads/txrik.gif';">
            </div>

            <div class="hp-product-card__body">
<?php if ($product['show_name_heading']): ?>
                <h3 class="hp-product-card__title lightning-effect" style="color: <?= $cn ?>;">
                    <?= htmlspecialchars(function_exists('content_t') ? content_t('product', (int) $product['product_id'], 'name', (string) $product['product_name']) : (string) $product['product_name']) ?>
                </h3>
<?php endif; ?>

<?php if ($product['show_description'] && trim((string) ($product['product_description'] ?? '')) !== ''): ?>
                <p class="hp-product-card__hint" style="color: <?= $cdr ?>;">
                    <?= htmlspecialchars(function_exists('content_t') ? content_t('product', (int) $product['product_id'], 'description', (string) $product['product_description']) : (string) $product['product_description']) ?>
                </p>
<?php endif; ?>

<?php if ($product['show_price']): ?>
                <?php
                $hpOriginalPrice = (float) ($product['original_price'] ?? 0);
                $hpSalePrice = (float) ($product['product_price'] ?? 0);
                $hpDiscountPct = 0;

                if ($hpOriginalPrice > $hpSalePrice && $hpOriginalPrice > 0) {
                    $hpDiscountPct = (int) round((1 - $hpSalePrice / $hpOriginalPrice) * 100);
                }
                $hpShowOriginal = (int) ($hpSec['show_card_original_price'] ?? 1) === 1;
                ?>
                <div class="hp-product-card__prices">
<?php if ($hpShowOriginal && $hpOriginalPrice > $hpSalePrice): ?>
                    <span class="hp-product-card__price-old price" style="color: <?= $co ?>;">
                        <?= function_exists('money') ? htmlspecialchars(money($hpOriginalPrice)) : number_format($hpOriginalPrice, 2, ',', '.') . ' TL' ?>
                    </span>
<?php endif; ?>
                    <span class="hp-product-card__price-sale price" style="color: <?= $cs ?>;">
                        <?= function_exists('money') ? htmlspecialchars(money($hpSalePrice)) : number_format($hpSalePrice, 2, ',', '.') . ' TL' ?>
                    </span>
                    <span style="display:none;" data-meta-price="true" data-product-id="<?= (int) $product['product_id'] ?>" data-product-name="<?= htmlspecialchars((string) $product['product_name'], ENT_QUOTES, 'UTF-8') ?>"><?= number_format($hpSalePrice, 2, '.', '') ?></span>
                </div>
<?php if ($hpDiscountPct > 0 && (int) ($settings['show_discount_badge_index'] ?? 1) === 1): ?>
                <span class="hp-product-card__discount" aria-label="<?= $hpDiscountPct ?> <?= te('shop.discount', 'indirim') ?>">
                    <span class="hp-product-card__discount-pct">%<?= $hpDiscountPct ?></span>
                    <span class="hp-product-card__discount-label"><?= te('shop.discount', 'indirim') ?></span>
                </span>
<?php endif; ?>
<?php endif; ?>

                <a href="<?= htmlspecialchars(app_url('order', ['product_id' => $product['product_id']], $pdo)) ?>"
                   class="hp-product-card__cta btn btn-primary"
                   style="background-color: <?= $cta ?>;">
                    <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                    <span><?= te('shop.order_now', 'Hemen Sipariş Ver') ?></span>
                </a>
            </div>

        </article>
    </div>
<?php endforeach; ?>
<?php endif; ?>

    </div>
</div>
</section>

<?php else: ?>
<div id="products" aria-hidden="true"></div>
<?php endif; ?>

    <div id="fkLiveBanner" class="fk-live-toast" role="status" aria-live="polite" aria-atomic="true"></div>

    <script>
    (function(){
      var FA = <?= json_encode(array_values($fakeAlerts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
      if (!FA.length) return;
      var bx = document.getElementById('fkLiveBanner');
      if (!bx) return;
      var idx = Math.floor(Math.random() * FA.length);
      /* Ekranda VISIBLE_MS sonra gider; çıkıştan sonra PAUSE_BEFORE_NEXT_MS boşlukta bir sonraki gelir */
      var VISIBLE_MS = 3000;
      var LEAVE_MS = 340;
      var PAUSE_BEFORE_NEXT_MS = 2000;

      function esc(s){ var m=document.createElement('div'); m.textContent=s; return m.innerHTML; }

      function buildInner(d){

        return '<div class="fk-live-toast__inner">' +
          '<span class="fk-live-toast__dot" aria-hidden="true"></span>' +
          '<p class="fk-live-toast__line"><strong>'+esc(d.customer_name||'')+'</strong> · '+esc(d.city_name||'')+
          ' <span class="fk-live-toast__muted">· sipariş verdi · '+esc(d.time_label||'')+'</span></p>' +
          '</div>';
      }

      function animateIn(slideEl){
        requestAnimationFrame(function(){
          requestAnimationFrame(function(){
            slideEl.classList.remove('fk-live-toast__slide--enter');
          });
        });
      }

      function mountNew(d){
          bx.style.display='block';
          bx.innerHTML = '<div class="fk-live-toast__slide fk-live-toast__slide--enter">' + buildInner(d) + '</div>';
          animateIn(bx.firstElementChild);
      }

      function loop(){
        var d = FA[idx++ % FA.length];
        mountNew(d);
        setTimeout(function(){
          var slide = bx.querySelector('.fk-live-toast__slide');
          if (!slide) return;
          slide.classList.add('fk-live-toast__slide--leave');
          setTimeout(function(){
            bx.innerHTML = '';
            bx.style.display = 'none';
            setTimeout(loop, PAUSE_BEFORE_NEXT_MS);
          }, LEAVE_MS);
        }, VISIBLE_MS);
      }

      loop();
    })();
    </script>

    <button type="button" class="scroll-to-top" id="scroll-to-top-btn" aria-label="Sayfanın başına dön" title="Sayfanın başına dön">
        <i class="fas fa-chevron-up" aria-hidden="true"></i>
    </button>

    <?php site_footer_render($pdo); ?>

<script>
(function() {
    var btn = document.getElementById('scroll-to-top-btn');
    if (!btn) return;

    var productsEl = document.getElementById('products');
    var minScroll = 180;
    var ticking = false;

    function getStickyOffset() {
        return document.querySelector('.countdown-banner') ? 162 : 94;
    }

    function updateScrollTopBtn() {
        ticking = false;
        if (window.__PRODUCTS_SCROLL_ACTIVE__) {
            return;
        }
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var show = scrollY > minScroll;

        if (show && productsEl) {
            var productsStart = productsEl.offsetTop - getStickyOffset() - 24;
            if (scrollY >= productsStart) {
                show = false;
            }
        }

        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-hidden', show ? 'false' : 'true');
    }

    function onScroll() {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(updateScrollTopBtn);
        }
    }

    btn.addEventListener('click', function() {
        var smoothTop = !(window.matchMedia && window.matchMedia('(max-width: 768px), (pointer: coarse), (hover: none)').matches);
        window.scrollTo({ top: 0, behavior: smoothTop ? 'smooth' : 'auto' });
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    updateScrollTopBtn();
})();

    function scrollToProducts() {
        if (typeof window.goToProductsSection === 'function') {
            window.goToProductsSection({ smooth: true });
        } else if (typeof window.scrollToProducts === 'function') {
            window.scrollToProducts(0, { smooth: true });
        }
    }

</script>
<script>
// Carousel kodu kaldırıldı - basit görsel gösterimi
</script>
<script>
<?php if ($fayansHomeVideo !== null): ?>
window.__homeProductVideoSrc = <?= json_encode((string) $fayansHomeVideo['video_src'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
<?php endif; ?>

var __popupSavedScrollY = 0;

function lockPopupScroll() {
    __popupSavedScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + __popupSavedScrollY + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    document.body.classList.add('popup-modal-open');
}

function unlockPopupScroll() {
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.body.classList.remove('popup-modal-open');
    window.scrollTo(0, __popupSavedScrollY);
}

function resetPopupVideo() {
    var modalVideo = document.getElementById('popup-video');
    if (!modalVideo) return;
    modalVideo.pause();
    try { modalVideo.currentTime = 0; } catch (e) {}
    modalVideo.style.display = 'none';
    var source = modalVideo.querySelector('source');
    if (source) {
        source.src = '';
    }
    modalVideo.removeAttribute('src');
    modalVideo.load();
}

function openModal(imagePath) {
    var modal = document.getElementById('popup-modal');
    var modalImage = document.getElementById('popup-image');
    if (!modal || !modalImage) return;

    resetPopupVideo();
    modalImage.style.display = 'block';
    modalImage.src = imagePath;
    modal.style.display = 'flex';
    lockPopupScroll();
}

function openHomeProductVideoModal() {
    var modal = document.getElementById('popup-modal');
    var modalImage = document.getElementById('popup-image');
    var modalVideo = document.getElementById('popup-video');
    var videoSrc = window.__homeProductVideoSrc || '';
    if (!modal || !modalVideo || videoSrc === '') {
        return;
    }

    if (modalImage) {
        modalImage.style.display = 'none';
        modalImage.removeAttribute('src');
    }

    var source = modalVideo.querySelector('source');
    if (source) {
        source.src = videoSrc;
    } else {
        modalVideo.src = videoSrc;
    }
    modalVideo.style.display = 'block';
    modalVideo.load();
    modal.style.display = 'flex';
    lockPopupScroll();

    var playPromise = modalVideo.play();
    if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
            /* Tarayıcı otomatik oynatmayı engellediyse controls ile kullanıcı başlatır */
        });
    }
}

function closeModal(event) {
    if (event) {
        event.stopPropagation();
    }
    var modal = document.getElementById('popup-modal');
    if (!modal) return;

    resetPopupVideo();
    var modalImage = document.getElementById('popup-image');
    if (modalImage) {
        modalImage.style.display = 'none';
        modalImage.removeAttribute('src');
    }
    modal.style.display = 'none';
    unlockPopupScroll();
}

function closeModalOnBackdrop(event) {
    if (event && event.target && event.target.id === 'popup-modal') {
        closeModal();
    }
}

function openHomeProductPopupFromTrigger(trigger) {
    if (!trigger) {
        return;
    }
    var mode = trigger.getAttribute('data-hp-popup') || 'image';
    var src = trigger.getAttribute('data-hp-src') || '';
    if (mode === 'video') {
        openHomeProductVideoModal();
    } else if (src !== '') {
        openModal(src);
    }
}

document.addEventListener('click', function (event) {
    var trigger = event.target.closest('.js-hp-product-popup');
    if (!trigger) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    openHomeProductPopupFromTrigger(trigger);
}, true);

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }
    var trigger = event.target.closest('.js-hp-product-popup');
    if (!trigger) {
        return;
    }
    event.preventDefault();
    openHomeProductPopupFromTrigger(trigger);
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const lazyGIFs = document.querySelectorAll("img.lazy-gif");

  lazyGIFs.forEach(img => {
    const gifSrc = img.getAttribute("data-src");

    const realGif = new Image();
    realGif.src = gifSrc;

    realGif.onload = function() {
      img.src = gifSrc;
    };
  });
});
</script>
<script>
    (function () {
        var el = document.querySelector('[data-meta-price="true"]');
        if (!el) return;
        var discountedPrice = parseFloat(String(el.textContent).replace(',', '.'), 10);
        if (typeof discountedPrice !== 'number' || isNaN(discountedPrice)) return;

        if (typeof fbq === 'function') {
            fbq('track', 'ViewContent', { value: discountedPrice, currency: 'TRY' });
        }

        if (typeof window.gtag === 'function') {
            window.gtag('event', 'view_item', {
                currency: 'TRY',
                value: discountedPrice,
                items: [{
                    item_id: 'homepage',
                    item_name: 'Ana Sayfa',
                    price: discountedPrice,
                    quantity: 1
                }]
            });
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'view_item',
            ecommerce: {
                currency: 'TRY',
                value: discountedPrice,
                items: [{
                    item_id: 'homepage',
                    item_name: 'Ana Sayfa',
                    price: discountedPrice,
                    quantity: 1
                }]
            }
        });

        if (typeof window.ttq !== 'undefined' && typeof window.ttq.track === 'function') {
            window.ttq.track('ViewContent', {
                value: discountedPrice,
                currency: 'TRY',
                content_type: 'product',
                content_name: 'Ana Sayfa'
            });
        }

        if (typeof window.paTrackSafe === 'function') {
            window.paTrackSafe('view_item', { value: discountedPrice, currency: 'TRY' });
        }

        if (typeof window.ymEcomDataLayerPush === 'function') {
            var ymNodes = document.querySelectorAll('[data-meta-price="true"][data-product-id]');
            var ymProducts = [];
            ymNodes.forEach(function (node) {
                var ymPrice = parseFloat(String(node.textContent).replace(',', '.'));
                if (typeof ymPrice !== 'number' || isNaN(ymPrice)) {
                    return;
                }
                ymProducts.push(window.ymEcomProduct(
                    node.getAttribute('data-product-id'),
                    node.getAttribute('data-product-name') || 'Ürün',
                    ymPrice,
                    1
                ));
            });
            if (ymProducts.length) {
                window.ymEcomDataLayerPush({
                    currencyCode: 'TRY',
                    impressions: { products: ymProducts }
                });
            }
        }
    })();
</script>

<?php
$__abSettings = abandoned_capture_settings($pdo);
$__abFirstProduct = $products[0] ?? [];
if (! empty($__abSettings['enabled'])):
?>
<script>
window.__abandonedConfig = <?= json_encode(
    abandoned_capture_js_config($__abSettings, is_array($__abFirstProduct) ? $__abFirstProduct : [], 'home'),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
) ?>;
</script>
<script src="js/abandoned-track.js" defer></script>
<?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<?php include 'social_buttons.php'; ?>

<?php if (conv_trial_on() && $cvOffer && ! empty($cvOffer['show_price'])): ?>
<div class="cv-sticky" id="cv-sticky-bar" role="region" aria-label="Sabit sipariş çubuğu">
    <div class="cv-sticky__price">
        <span class="cv-sticky__label"><?= te('shop.sale_price', 'Kampanyalı fiyat') ?></span>
        <span class="cv-sticky__sale"><?= htmlspecialchars((string) $cvOffer['sale_fmt'], ENT_QUOTES, 'UTF-8') ?></span>
<?php if (! empty($cvOffer['show_original'])): ?>
        <span class="cv-sticky__old"><?= htmlspecialchars((string) $cvOffer['original_fmt'], ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
    </div>
    <a class="cv-sticky__cta" href="<?= htmlspecialchars((string) $cvOffer['order_url'], ENT_QUOTES, 'UTF-8') ?>" style="background-color: <?= htmlspecialchars((string) $cvOffer['cta_color'], ENT_QUOTES, 'UTF-8') ?>;">
        <?= te('shop.order_now', 'Hemen Sipariş Ver') ?>
    </a>
</div>
<script>
(function () {
    var bar = document.getElementById('cv-sticky-bar');
    var cta = document.querySelector('.homepage-products-scope .hp-product-card__cta');
    if (!bar || !cta || !('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (entries) {
        var vis = entries[0] && entries[0].isIntersecting;
        bar.classList.toggle('is-hidden', !!vis);
    }, { threshold: 0.6 });
    io.observe(cta);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/carkifelek_public.php'; ?>

</body>
</html>
