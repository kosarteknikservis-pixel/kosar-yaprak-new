<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'db.php';
require 'telegram.php';
require_once 'tracking.php';
require_once __DIR__ . '/includes/promo_banner_render.php';
require_once __DIR__ . '/includes/page_meta_load.php';
require_once __DIR__ . '/includes/page_seo.php';
require_once __DIR__ . '/includes/site_footer.php';
require_once __DIR__ . '/includes/attribution_helpers.php';
require_once __DIR__ . '/includes/laravel4_sync.php';
require_once __DIR__ . '/includes/app_url.php';
require_once __DIR__ . '/includes/gateways/OrderPaymentFinalize.php';
require_once __DIR__ . '/includes/variant_helpers.php';
require_once __DIR__ . '/includes/order_guard_helpers.php';
require_once __DIR__ . '/includes/abandoned_capture.php';
require_once __DIR__ . '/includes/order_sms_verify.php';
require_once __DIR__ . '/includes/order_verification.php';

date_default_timezone_set('Europe/Istanbul');

$page_name = basename(__FILE__);
$ip_address = app_client_ip();
$visit_time = date('Y-m-d H:i:s');
$sourceHost = parse_url(app_site_url($pdo), PHP_URL_HOST);
$source = is_string($sourceHost) && $sourceHost !== ''
    ? $sourceHost
    : (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

$stmt = $pdo->prepare("INSERT INTO page_views (page_name, ip_address, visit_time) VALUES (?, ?, ?)");
$stmt->execute([$page_name, $ip_address, $visit_time]);

// Ayrıca page_visits tablosuna da kaydet
// $stmt = $pdo->prepare("INSERT INTO page_visits (page_name, ip_address, visit_time) VALUES (?, ?, ?)");
// $stmt->execute([$page_name, $ip_address, $visit_time]);

$stmt = $pdo->query("SELECT discount_rate, show_whatsapp, whatsapp_number, show_instagram, instagram_username FROM notification_settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM notification_settings WHERE id = 1");
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM cities ORDER BY city_name ASC");
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT show_order_note, order_note_text FROM footer_images WHERE id = 5");
$order_note = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['show_order_note' => 0, 'order_note_text' => ''];

$checkoutCorporateOn = false;
$checkoutGuard = order_guard_load($pdo);
$checkoutGuard['utm_capture_enabled'] = 1;
try {
    $coRow = $pdo->query(
        'SELECT corporate_invoice_enabled, COALESCE(utm_capture_enabled,1) AS utmcap
         FROM checkout_module_settings WHERE id = 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($coRow) {
        $checkoutCorporateOn = (int) ($coRow['corporate_invoice_enabled'] ?? 0) === 1;
        $checkoutGuard['utm_capture_enabled'] = (int) ($coRow['utmcap'] ?? 1) !== 0 ? 1 : 0;
    }
} catch (Throwable $e) {
    $checkoutCorporateOn = false;
}

// Sipariş sonrası mesajını çek (id=7)
$stmt = $pdo->query("SELECT show_post_order_msg, post_order_msg_text FROM footer_images WHERE id = 7");
$post_order_msg = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['show_post_order_msg' => 0, 'post_order_msg_text' => ''];

// Eski varyant değişkenleri kaldırıldı

$meta = page_meta_load($pdo, $page_name) ?? [];

$product_id = (int) ($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    header('Location: ' . app_url('error', ['error' => 'no_product'], $pdo));
    exit;
}

// Yeni sınırsız varyant sistemi - veritabanından çek
try {
    $stmt = $pdo->prepare("
        SELECT vt.*, pva.is_required, pva.display_order AS assignment_display_order
        FROM product_variation_types vt
        JOIN product_variation_assignments pva ON vt.type_id = pva.type_id
        WHERE pva.product_id = ? AND vt.is_active = 1
        ORDER BY COALESCE(pva.display_order, vt.display_order), vt.type_name
    ");
    $stmt->execute([$product_id]);
    $unlimited_variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Her varyant türü için seçenekleri çek
    $variant_options = [];
    foreach ($unlimited_variants as $variant) {
        $stmt = $pdo->prepare("
            SELECT option_id, option_name, option_color, option_color_enabled
            FROM product_variation_options
            WHERE type_id = ? AND is_active = 1
            ORDER BY display_order, option_name
        ");
        $stmt->execute([$variant['type_id']]);
        $variant_options[$variant['type_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Veritabanı tabloları henüz oluşturulmamışsa geçici varyantlar kullan
    $unlimited_variants = [
        ['type_id' => 1, 'type_name' => 'Renk', 'is_required' => 1],
        ['type_id' => 2, 'type_name' => 'Boyut', 'is_required' => 1],
        ['type_id' => 3, 'type_name' => 'Malzeme', 'is_required' => 1]
    ];

    $variant_options = [
        1 => [
            ['option_id' => 1, 'option_name' => 'Kırmızı'],
            ['option_id' => 2, 'option_name' => 'Mavi'],
            ['option_id' => 3, 'option_name' => 'Yeşil'],
            ['option_id' => 4, 'option_name' => 'Siyah'],
            ['option_id' => 5, 'option_name' => 'Beyaz']
        ],
        2 => [
            ['option_id' => 6, 'option_name' => 'Küçük (S)'],
            ['option_id' => 7, 'option_name' => 'Orta (M)'],
            ['option_id' => 8, 'option_name' => 'Büyük (L)'],
            ['option_id' => 9, 'option_name' => 'XL']
        ],
        3 => [
            ['option_id' => 10, 'option_name' => 'Pamuk'],
            ['option_id' => 11, 'option_name' => 'Polyester'],
            ['option_id' => 12, 'option_name' => 'Keten']
        ]
    ];
}

// Eski sistem kaldırıldı - sadece yeni sınırsız varyant sistemi kullanılıyor

$page_title = htmlspecialchars(page_seo_resolve($pdo, $page_name, $meta)['title'], ENT_QUOTES, 'UTF-8');

$stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . app_url('error', ['error' => 'product_not_found'], $pdo));
    exit;
}

require_once __DIR__ . '/includes/payment_methods_sync.php';
payment_methods_sync_online_gateways($pdo);

$stmt = $pdo->query('SELECT payment_method_id, method_name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, method_name');
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/order_page_ui.php';
$orderPageUi = order_page_ui_get($pdo);

$abandonedPrefill = abandoned_prefill_contact($pdo);

$showFrontOtpStep = order_front_otp_is_active() || (isset($_GET['otp']) && (string) $_GET['otp'] === '1');
$otpPrefill = [];
if ($showFrontOtpStep && order_front_otp_is_active()) {
    $sessOtp = $_SESSION['front_order_verify']['post_data'] ?? [];
    if (is_array($sessOtp)) {
        $otpPrefill = $sessOtp;
    }
}
$formPrefill = static function (string $key, string $default = '') use ($otpPrefill, $abandonedPrefill, $showFrontOtpStep): string {
    if ($showFrontOtpStep && isset($otpPrefill[$key])) {
        return (string) $otpPrefill[$key];
    }
    if ($key === 'customer_name' || $key === 'customer_phone') {
        return (string) ($abandonedPrefill[$key === 'customer_name' ? 'ad' : 'tel'] ?? $default);
    }

    return $default;
};

$otpModalError = '';
if ($showFrontOtpStep) {
    $otpErrKey = (string) ($_GET['otp_err'] ?? '');
    if ($otpErrKey === 'expired') {
        $otpModalError = 'Kodun süresi doldu. Yeni kod gönderin.';
    } elseif ($otpErrKey === 'invalid') {
        $otpModalError = 'Kod hatalı. Lütfen tekrar deneyin.';
    }
}
$otpMaskedPhone = '';
if ($showFrontOtpStep && $otpPrefill !== []) {
    require_once __DIR__ . '/includes/order_verification.php';
    $otpMaskedPhone = ov_mask_tel((string) ($otpPrefill['customer_phone'] ?? ''));
}

// Eski varyant sorguları kaldırıldı

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Yeni sınırsız varyant sistemi
    $selected_variants = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'variant_') === 0) {
            $type_id = str_replace('variant_', '', $key);
            $selected_variants[$type_id] = $value;
        }
    }

    // Eski sistem değişkenleri kaldırıldı

    $customer_name = $_POST['customer_name'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_address = $_POST['customer_address'] ?? '';
    $customer_city = $_POST['customer_city'] ?? '';
    $customer_district = $_POST['customer_district'] ?? '';
    $order_notes = $_POST['order_notes'] ?? '';
    $cark_odul_in = isset($_POST['carkifelek_odul']) ? trim((string) $_POST['carkifelek_odul']) : '';
    $payment_method_id = (int) ($_POST['payment_method_id'] ?? 0);
    $invoice_vkn = '';
    $invoice_tax_office = '';
    $invoice_company_name = '';
    $invoice_address = '';
    try {
        $coPost = $pdo->query('SELECT corporate_invoice_enabled FROM checkout_module_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if ($coPost && (int)$coPost['corporate_invoice_enabled'] === 1) {
            // Panel uyumu: siparis_fatura_vn — yalnızca rakam, en fazla 11 hane
            $invoice_vkn = mb_substr(preg_replace('/[^0-9]/', '', (string)($_POST['invoice_vkn'] ?? '')), 0, 11);
            $invoice_tax_office = mb_substr(trim((string)($_POST['invoice_tax_office'] ?? '')), 0, 128);
            $invoice_company_name = mb_substr(trim((string)($_POST['invoice_company_name'] ?? '')), 0, 255);
            // Panel textarea max 3000
            $invoice_address = mb_substr(trim((string)($_POST['invoice_address'] ?? '')), 0, 3000);
        }
    } catch (Throwable $e) {
        /* ignore */
    }
    $reklam = $_SESSION['reklam'] ?? 'Reklam Olmayabilir';
    $referrer = null;
    if (!empty($_SESSION['referral_code'])) {
        $referrer = mb_substr(trim((string) $_SESSION['referral_code']), 0, 128);
    } elseif (!empty($_COOKIE['referrer'])) {
        $referrer = mb_substr(trim((string) $_COOKIE['referrer']), 0, 128);
    }
    if ($referrer === '') {
        $referrer = null;
    }

    require_once __DIR__ . '/includes/block_check.php';
    $blockMsg = order_block_guard($pdo, $ip_address, $customer_phone);
    if ($blockMsg !== null) {
        header('Location: error.php?error=blocked&product_id=' . urlencode((string)$product_id));
        exit;
    }

    if (!$customer_name || !$customer_phone || !$customer_address || !$customer_city || !$customer_district || $payment_method_id <= 0) {
        header("Location: error.php?error=missing_fields&product_id=$product_id");
        exit;
    }

    $pmCheck = $pdo->prepare('SELECT payment_method_id FROM payment_methods WHERE payment_method_id = ? AND is_active = 1');
    $pmCheck->execute([$payment_method_id]);
    if (!$pmCheck->fetch()) {
        header("Location: error.php?error=invalid_payment_method&product_id=$product_id");
        exit;
    }

    $frontOtpResult = order_front_otp_handle_post($pdo, $_POST, (string) $customer_phone, $payment_method_id);
    if ($frontOtpResult === 'pending') {
        header('Location: ' . app_url('order', ['product_id' => $product_id, 'otp' => '1'], $pdo));
        exit;
    }
    if ($frontOtpResult === 'invalid') {
        header('Location: ' . app_url('order', ['product_id' => $product_id, 'otp' => '1', 'otp_err' => 'invalid'], $pdo));
        exit;
    }
    if ($frontOtpResult === 'expired') {
        header('Location: ' . app_url('order', ['product_id' => $product_id, 'otp' => '1', 'otp_err' => 'expired'], $pdo));
        exit;
    }
    if ($frontOtpResult === 'sms_fail') {
        header('Location: error.php?error=sms_fail&product_id=' . urlencode((string) $product_id));
        exit;
    }

    $frontOtpPassed = $frontOtpResult === 'passed';
    $needsSmsVerify = order_payment_needs_sms_verify($pdo, $payment_method_id);
    $skipLayerB = $frontOtpPassed;
    $initialOrderStatusId = order_sms_verify_approved_status_id($pdo);
    $smsVerifyPending = false;
    if ($needsSmsVerify && ! $skipLayerB) {
        $initialOrderStatusId = order_sms_verify_pending_status_id($pdo);
        $smsVerifyPending = true;
    }

    $cookie_lifetime = (int) ($checkoutGuard['order_cookie_seconds'] ?? 60);
    $phone_digits = order_guard_phone_digits((string) $customer_phone);

    $serverDupe = order_guard_server_duplicate($pdo, $checkoutGuard, $ip_address, $phone_digits);
    if ($serverDupe['blocked']) {
        $reason = $serverDupe['reason'] ?? '';
        $qs = 'error=duplicate_order&product_id=' . urlencode((string) $product_id);
        if ($reason !== '') {
            $qs .= '&reason=' . urlencode($reason);
        }
        header('Location: error.php?' . $qs);
        exit;
    }

    if (order_guard_browser_blocks($checkoutGuard)) {
        header('Location: error.php?error=browser_block&product_id=' . urlencode((string) $product_id));
        exit;
    }

    $utmCaptureOn = (int) ($checkoutGuard['utm_capture_enabled'] ?? 1) === 1;
    [
        $utm_source,
        $utm_medium,
        $utm_campaign,
        $utm_content,
        $utm_term,
        $attribution_click_json,
        $attribution_landing_url,
    ] = attribution_order_values_for_db($utmCaptureOn);

    $stmt = $pdo->prepare('INSERT INTO orders (customer_name, customer_phone, customer_address, customer_city, customer_district, order_notes, payment_method_id, order_status_id, customer_ip, source, reklam, referrer, utm_source, utm_medium, utm_campaign, utm_content, utm_term, attribution_click_json, attribution_landing_url, invoice_vkn, invoice_tax_office, invoice_company_name, invoice_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $customer_name, $customer_phone, $customer_address, $customer_city, $customer_district, $order_notes, $payment_method_id,
        $initialOrderStatusId,
        $ip_address, $source, $reklam, $referrer,
        $utm_source,
        $utm_medium,
        $utm_campaign,
        $utm_content,
        $utm_term,
        $attribution_click_json,
        $attribution_landing_url,
        $invoice_vkn !== '' ? $invoice_vkn : null,
        $invoice_tax_office !== '' ? $invoice_tax_office : null,
        $invoice_company_name !== '' ? $invoice_company_name : null,
        $invoice_address !== '' ? $invoice_address : null,

    ]);
    $order_id = (int) $pdo->lastInsertId();
    if ((int) ($checkoutGuard['order_cookie_gate_enabled'] ?? 1) === 1) {
        order_guard_set_browser_cookie($cookie_lifetime);
    }
    require_once __DIR__ . '/includes/app_log.php';
    app_log('order', 'created', [
        'order_id' => $order_id,
        'phone' => $customer_phone,
        'product_id' => $product_id,
        'source' => $source,
    ]);
    $gwCode = OrderPaymentFinalize::gatewayCodeForMethod($pdo, $payment_method_id);
    $merchant_oid = OrderPaymentFinalize::merchantOidForOrder($order_id);
    if (in_array($gwCode, ['paytr', 'iyzico'], true)) {
        $pdo->prepare('UPDATE orders SET payment_status = ?, payment_merchant_oid = ?, gateway_code = ? WHERE order_id = ?')
            ->execute(['pending', $merchant_oid, $gwCode, $order_id]);
    } else {
        $pdo->prepare('UPDATE orders SET payment_status = ?, gateway_code = ? WHERE order_id = ?')
            ->execute(['paid', $gwCode !== '' ? $gwCode : 'cod', $order_id]);
    }

        // Sipariş edilen ürünü kaydet
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, 1, ?)');
        $stmt->execute([$order_id, $product_id, $product['product_price']]);

        // Yeni sınırsız varyant detaylarını kaydet
        if (!empty($selected_variants)) {
            // Varyantları order_variation_details tablosuna kaydet
            foreach ($selected_variants as $type_id => $option_id) {
                $stmt = $pdo->prepare('INSERT INTO order_variation_details (order_id, type_id, option_id) VALUES (?, ?, ?)');
                $stmt->execute([$order_id, $type_id, $option_id]);
            }

            // Varyant bilgilerini sipariş notlarına da ekle (geriye dönük uyumluluk için)
            $variant_text = "Seçilen Varyantlar: ";
            $variant_parts = [];
            foreach ($selected_variants as $type_id => $option_id) {
                $stmt = $pdo->prepare("SELECT type_name FROM product_variation_types WHERE type_id = ?");
                $stmt->execute([$type_id]);
                $type_name = variant_type_display_name((string) ($stmt->fetchColumn() ?: "Varyant $type_id"));

                $stmt = $pdo->prepare("SELECT option_name FROM product_variation_options WHERE option_id = ?");
                $stmt->execute([$option_id]);
                $option_name = $stmt->fetchColumn() ?: "Seçenek $option_id";

                $variant_parts[] = "$type_name: $option_name";

            }

            $variant_text .= implode(", ", $variant_parts);
            $order_notes = $variant_text . ($order_notes ? "\n\nEk Notlar: " . $order_notes : "");
            if ($cark_odul_in !== '') {
                $order_notes = ($order_notes ? $order_notes . "\n\n" : '') . 'Çarkıfelek ödülü: ' . $cark_odul_in;

            }

            $stmt = $pdo->prepare('UPDATE orders SET order_notes = ? WHERE order_id = ?');
            $stmt->execute([$order_notes, $order_id]);
        } elseif ($cark_odul_in !== '') {
            $order_notes = ($order_notes ? $order_notes . "\n\n" : '') . 'Çarkıfelek ödülü: ' . $cark_odul_in;
            $stmt = $pdo->prepare('UPDATE orders SET order_notes = ? WHERE order_id = ?');
            $stmt->execute([$order_notes, $order_id]);
        }

        $finalizeCtx = [
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_address' => $customer_address,
            'customer_city' => $customer_city,
            'customer_district' => $customer_district,
            'order_notes' => $order_notes,
            'payment_method_id' => $payment_method_id,
            'product_id' => $product_id,
            'product' => $product,
            'selected_variants' => $selected_variants,
            'reklam' => $reklam,
            'source' => $source,
            'utm_capture_on' => $utmCaptureOn,
            'invoice_vkn' => $invoice_vkn,
            'invoice_tax_office' => $invoice_tax_office,
            'invoice_company_name' => $invoice_company_name,
            'invoice_address' => $invoice_address,
            'ip_address' => $ip_address,
            'cookie_lifetime' => $cookie_lifetime,
        ];

        if ($gwCode === 'paytr') {
            header('Location: ' . app_url('payment/paytr', ['order_id' => $order_id], $pdo));
            exit;
        }
        if ($gwCode === 'iyzico') {
            header('Location: ' . app_url('payment/iyzico', ['order_id' => $order_id], $pdo));
            exit;
        }

        if ($smsVerifyPending) {
            $ovData = ov_create_or_refresh($pdo, $order_id, $customer_phone, 20);
            if (is_array($ovData)) {
                $verifyLink = app_url('order_verify', ['t' => $ovData['token']], $pdo);
                ov_send_otp_sms($customer_phone, $ovData['otp'], $verifyLink);
            }
        }

        OrderPaymentFinalize::afterOrderConfirmed($pdo, $order_id, $finalizeCtx);

        header('Location: ' . app_url('thankyou', ['order_id' => $order_id], $pdo));
        exit;
}

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
    <link rel="stylesheet" href="css/korp.css">
    <link rel="stylesheet" href="css/site-footer.css">
</head>
<body class="site-shell-app order-page-view">
<?php require'menu.php'; ?>
    <div class="order-page-shell" style="margin-top: 0;">

    <?php
    $GLOBALS['ORDER_PAGE_UI_ACTIVE'] = true;
    $GLOBALS['ORDER_PAGE_COUNTDOWN_SKIN'] = $orderPageUi['countdown'];
    include __DIR__ . '/countdown_timer.php';
    unset($GLOBALS['ORDER_PAGE_UI_ACTIVE'], $GLOBALS['ORDER_PAGE_COUNTDOWN_SKIN']);
    ?>
    <?php if ($meta && !empty($meta['body_content'])): ?>
        <?= $meta['body_content']; ?>
    <?php endif; ?>


<?php include __DIR__ . '/includes/order_product_vitrin.php'; ?>


    <?php if ($notification['is_active']): ?>
        <?= promo_banner_markup((string) ($notification['message'] ?? '')) ?>
    <?php endif; ?>


<div class="full-width-section order-form<?= $showFrontOtpStep ? ' order-form--otp-pending' : '' ?>">
    <form method="POST" id="order-checkout-form" autocomplete="on">
        <input type="hidden" name="carkifelek_odul" id="carkifelek_odul" value="<?= htmlspecialchars((string) ($_SESSION['carkifelek_odul'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
        <h2 class="order-form__title">Sipariş Bilgileriniz</h2>

        <?php if ($showFrontOtpStep && $otpPrefill !== []): ?>
        <div class="order-otp-hidden-fields" aria-hidden="true">
            <?php foreach ($otpPrefill as $hk => $hv): ?>
                <?php if (! is_scalar($hv)) {
                    continue;
                } ?>
                <input type="hidden" name="<?= htmlspecialchars((string) $hk, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $hv, ENT_QUOTES, 'UTF-8') ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($showFrontOtpStep): ?>
        <p class="order-otp-wait-msg"><i class="fas fa-mobile-alt"></i> Telefonunuza SMS gönderildi. Doğrulama penceresinden kodu girerek siparişinizi onaylayın.</p>
        <?php else: ?>

        <!-- Yeni Sınırsız Varyant Sistemi (başlığın ALTINDA) -->
        <?php if (!empty($unlimited_variants)): ?>
        <div id="variants-container">
            <?php foreach ($unlimited_variants as $variant): ?>
            <?php $variantLabel = variant_type_display_name((string) ($variant['type_name'] ?? '')); ?>
            <div class="form-group">
                <label>
                    <?= htmlspecialchars($variantLabel) ?>:
                    <?php if ($variant['is_required']): ?>
                        <span class="order-form__required">(Zorunlu)</span>
                    <?php endif; ?>
                </label>
                <div class="opui-variant-row">
                <span class="opui-variant-swatch" aria-hidden="true"></span>
                <select class="form-control opui-variant-select"
                        name="variant_<?= $variant['type_id'] ?>"
                        <?= $variant['is_required'] ? 'required' : '' ?>>
                    <option value="">Seçiniz</option>
                    <?php if (isset($variant_options[$variant['type_id']])): ?>
                        <?php foreach ($variant_options[$variant['type_id']] as $option): ?>
                            <option
                                value="<?= (int) $option['option_id'] ?>"
                                data-color="<?= htmlspecialchars((string) ($option['option_color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-color-enabled="<?= !empty((int) ($option['option_color_enabled'] ?? 1)) ? '1' : '0' ?>"
                            ><?= htmlspecialchars((string) ($option['option_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>


        <div class="form-group">
            <label for="customer_name"><?= te('order.name_label', 'Adınız ve Soyadınız:') ?></label>
            <input type="text" class="form-control" id="customer_name" name="customer_name" required maxlength="30" autocomplete="name" value="<?= htmlspecialchars($formPrefill('customer_name'), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="customer_phone"><?= te('order.phone_label', 'Telefon Numaranız:') ?></label>
            <input type="tel"
                   class="form-control"
                   id="customer_phone"
                   name="customer_phone"
                   required
                   pattern="\d*"
                   inputmode="numeric"
                   autocomplete="tel-national"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                   maxlength="12"
                   value="<?= htmlspecialchars($formPrefill('customer_phone'), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="order-form__row order-form__row--2">
        <div class="form-group">
            <label for="customer_city"><?= te('order.city_label', 'İl:') ?></label>
            <select class="form-control" id="customer_city" name="customer_city" required>
                <option value=""><?= te('order.city_select', 'İl Seçiniz') ?></option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?= $city['city_id'] ?>"<?= $formPrefill('customer_city') === (string) $city['city_id'] ? ' selected' : '' ?>><?= htmlspecialchars($city['city_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="customer_district"><?= te('order.district_label', 'İlçe:') ?></label>
            <select class="form-control" id="customer_district" name="customer_district" required disabled>
                <option value=""><?= te('order.district_first', 'Önce İl Seçiniz') ?></option>
            </select>
        </div>
        </div>


        <div class="form-group">
            <label for="customer_address"><?= te('order.address_label', 'Teslimat Yapılacak Adres:') ?></label>
            <textarea class="form-control" id="customer_address" name="customer_address" required maxlength="200"><?= htmlspecialchars($formPrefill('customer_address'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <?php if ($order_note['show_order_note'] == 1): ?>
        <div class="form-group">
            <label for="order_notes">
                <?= htmlspecialchars($order_note['order_note_text']); ?>
            </label>
            <textarea class="form-control"
                      id="order_notes"
                      name="order_notes" maxlength="200"><?= htmlspecialchars($formPrefill('order_notes'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <?php endif; ?>

        <?php if ($checkoutCorporateOn): ?>
        <details class="kurumsal-fatura-details">
            <summary>
                <span><i class="fas fa-building" aria-hidden="true"></i> <?= te('order.invoice_section', 'Kurumsal fatura bilgileri') ?></span>
                <span class="kurumsal-fatura-details__hint"><?= te('order.invoice_optional', 'İsteğe bağlı · tıklayın') ?></span>
            </summary>
            <div class="kurumsal-fatura-details__body">
                <div class="form-group">
                    <label for="invoice_vkn" style="font-size: 16px; color: #283458;"><?= te('order.invoice_vkn', 'Vergi numarası') ?></label>
                    <input type="text" class="form-control" style="border-radius: 12px;" id="invoice_vkn" name="invoice_vkn" maxlength="11" inputmode="numeric" pattern="[0-9]*" autocomplete="off" placeholder="10 hane VKN veya 11 hane T.C. (sadece rakam)" title="Sadece rakam, en fazla 11 hane">
                </div>
                <div class="form-group">
                    <label for="invoice_tax_office" style="font-size: 16px; color: #283458;"><?= te('order.invoice_tax_office', 'Vergi dairesi') ?></label>
                    <input type="text" class="form-control" style="border-radius: 12px;" id="invoice_tax_office" name="invoice_tax_office" maxlength="128" autocomplete="organization" placeholder="Örn: Kadıköy">
                </div>
                <div class="form-group">
                    <label for="invoice_company_name" style="font-size: 16px; color: #283458;"><?= te('order.invoice_company', 'Firma ünvanı') ?></label>
                    <input type="text" class="form-control" style="border-radius: 12px;" id="invoice_company_name" name="invoice_company_name" maxlength="255" autocomplete="organization" placeholder="Ticari unvan">
                </div>
                <div class="form-group mb-0">
                    <label for="invoice_address" style="font-size: 16px; color: #283458;"><?= te('order.invoice_address', 'Fatura adresi') ?></label>
                    <textarea class="form-control" style="border-radius: 12px; min-height: 88px;" id="invoice_address" name="invoice_address" maxlength="3000" rows="4" autocomplete="street-address" placeholder="Fatura kesilecek açık adres"></textarea>
                </div>
            </div>
        </details>
        <style>
            .kurumsal-fatura-details summary::after { content: '\25BC'; font-size: 10px; color: #94a3b8; margin-left: 8px; }
            .kurumsal-fatura-details[open] summary::after { transform: rotate(180deg); display: inline-block; }
        </style>
        <script>
        (function () {
            var fvn = document.getElementById('invoice_vkn');
            if (!fvn) return;
            fvn.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);
            });
        })();
        </script>
        <?php endif; ?>

        <h2 class="order-form__section-title"><?= te('order.payment_title', 'Ödeme Yöntemi Seçiniz') ?></h2>
        <div class="form-group">
            <select class="form-control" id="payment_method_id" name="payment_method_id" required>
                <option value=""><?= te('order.payment_select', 'Seçiniz...') ?></option>
                <?php foreach ($payment_methods as $pm): ?>
                    <option value="<?= htmlspecialchars($pm['payment_method_id']) ?>"<?= $formPrefill('payment_method_id') === (string) $pm['payment_method_id'] ? ' selected' : '' ?>>
                        <?= htmlspecialchars($pm['method_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-custom order-form__submit">
            <?= te('order.submit_btn', 'SİPARİŞİ TAMAMLA') ?>
        </button>

        <?php endif; ?>

        <?php if ($showFrontOtpStep): ?>
        <input type="hidden" name="otp_stage_action" id="otp_stage_action" value="">
        <div class="modal fade order-otp-modal" id="orderOtpModal" tabindex="-1" role="dialog" aria-labelledby="orderOtpModalTitle" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content order-otp-modal__content">
                    <div class="modal-header order-otp-modal__header">
                        <h5 class="modal-title" id="orderOtpModalTitle"><i class="fas fa-shield-alt"></i> SMS Doğrulama</h5>
                    </div>
                    <div class="modal-body order-otp-modal__body">
                        <p class="order-otp-modal__lead">Telefonunuza gönderilen 6 haneli kodu girin.</p>
                        <?php if ($otpMaskedPhone !== '' && $otpMaskedPhone !== '***'): ?>
                        <p class="order-otp-modal__tel"><i class="fas fa-phone"></i> <?= htmlspecialchars($otpMaskedPhone) ?></p>
                        <?php endif; ?>
                        <p class="order-otp-modal__hint">Kod 5 dakika geçerlidir. Doğrulama sonrası siparişiniz onaylanır.</p>
                        <?php if ($otpModalError !== ''): ?>
                        <div class="alert alert-danger order-otp-modal__error" role="alert"><?= htmlspecialchars($otpModalError) ?></div>
                        <?php endif; ?>
                        <div class="form-group mb-0">
                            <label for="otp_code_front">Doğrulama kodu</label>
                            <input type="text" class="form-control order-otp-modal__input" id="otp_code_front" name="otp_code_front" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="6 haneli kod" required>
                        </div>
                    </div>
                    <div class="modal-footer order-otp-modal__footer flex-column">
                        <button type="submit" name="otp_stage_verify_submit" value="1" class="btn-custom order-form__submit order-otp-modal__btn-verify w-100" data-otp-action="verify">Kodu doğrula ve siparişi onayla</button>
                        <button type="submit" name="otp_stage_resend_submit" value="1" class="btn btn-link order-otp-modal__btn-resend" data-otp-action="resend">Kodu yeniden gönder</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($showFrontOtpStep): ?>
<style>
.order-form--otp-pending { position: relative; }
.order-otp-wait-msg {
    margin: 0 0 1rem;
    padding: 12px 14px;
    border-radius: 12px;
    background: #e8f4fd;
    color: #1e3a5f;
    font-size: 15px;
}
.order-otp-modal .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
    overflow: hidden;
}
.order-otp-modal__header {
    background: linear-gradient(135deg, #1a2a44 0%, #243b55 100%);
    color: #fff;
    border: none;
    justify-content: center;
    padding: 1.1rem 1.25rem;
}
.order-otp-modal__header .modal-title { font-weight: 700; font-size: 1.15rem; }
.order-otp-modal__body { padding: 1.25rem 1.35rem 0.5rem; text-align: center; }
.order-otp-modal__lead { font-size: 1rem; color: #334155; margin-bottom: 0.35rem; }
.order-otp-modal__tel { font-weight: 600; color: #1a2a44; margin-bottom: 0.25rem; }
.order-otp-modal__hint { font-size: 0.875rem; color: #64748b; margin-bottom: 1rem; }
.order-otp-modal__input {
    text-align: center;
    font-size: 1.5rem;
    letter-spacing: 0.35em;
    font-weight: 700;
    border-radius: 12px;
    padding: 0.65rem 0.5rem;
}
.order-otp-modal__footer {
    border: none;
    padding: 0 1.35rem 1.35rem;
    gap: 0.35rem;
}
.order-otp-modal__btn-resend { font-size: 0.9rem; color: #64748b; }
.order-otp-modal.show { display: block; background: rgba(15, 23, 42, 0.55); }
</style>
<?php endif; ?>


<?php include 'social_buttons.php'; ?>

    <?php site_footer_render($pdo); ?>

    <script>
document.getElementById('customer_city').addEventListener('change', function() {
    var cityId = this.value;
    var districtSelect = document.getElementById('customer_district');

    if (!cityId) {
        districtSelect.disabled = true;
        districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.district_first', 'Önce İl Seçiniz')) ?></option>';
        districtSelect.style.backgroundColor = '#f8f9fa';
        districtSelect.style.color = '#6c757d';
        return;
    }

    districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.district_loading', 'İlçeler yükleniyor...')) ?></option>';
    districtSelect.disabled = true;

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_districts.php?city_id=' + cityId, true);
    xhr.onload = function() {
        if (xhr.status == 200) {
            try {
                var districts = JSON.parse(xhr.responseText);
                districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.district_select', 'İlçe Seçiniz')) ?></option>';

                if (districts.length > 0) {
                    districts.forEach(function(district) {
                        var option = document.createElement('option');
                        option.value = district.district_id;
                        option.textContent = district.district_name;
                        districtSelect.appendChild(option);
                    });
                } else {
                    districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.district_none', 'Bu il için ilçe bulunamadı')) ?></option>';
                }

                districtSelect.disabled = false;
                districtSelect.style.backgroundColor = '#ffffff';
                districtSelect.style.color = '#000000';
            } catch (e) {
                districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.district_error', 'Hata: İlçeler yüklenemedi')) ?></option>';
                console.error('JSON parse error:', e);
            }
        } else {
            districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.district_error', 'Hata: İlçeler yüklenemedi')) ?></option>';
        }
    };

    xhr.onerror = function() {
        districtSelect.innerHTML = '<option value=""><?= addslashes(t('order.conn_error', 'Bağlantı hatası')) ?></option>';
    };

    xhr.send();
});

</script>

<script>
    (function () {
        var dpEl = document.querySelector('[data-meta-price="true"]');
        if (!dpEl) return;
        var discountedPrice = parseFloat(String(dpEl.textContent).replace(',', '.'), 10);
        if (typeof discountedPrice !== 'number' || isNaN(discountedPrice)) return;

        var productId = '<?= htmlspecialchars((string) ($product['product_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>';
        var productName = <?= json_encode((string) ($product['product_name'] ?? 'Ürün'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>;
        var pixelPayload = {
            value: discountedPrice,
            currency: 'TRY',
            content_ids: productId ? [productId] : [],
            content_type: 'product',
            contents: productId ? [{ id: productId, quantity: 1, item_price: discountedPrice }] : []
        };

        if (typeof fbq === 'function') {
            fbq('track', 'ViewContent', pixelPayload);
            fbq('track', 'InitiateCheckout', pixelPayload);
        }

        if (typeof window.gtag === 'function') {
            window.gtag('event', 'view_item', {
                currency: 'TRY',
                value: discountedPrice,
                items: [{
                    item_id: productId || 'product',
                    item_name: productName,
                    price: discountedPrice,
                    quantity: 1
                }]
            });
            window.gtag('event', 'begin_checkout', {
                currency: 'TRY',
                value: discountedPrice,
                items: [{
                    item_id: productId || 'product',
                    item_name: productName,
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
                    item_id: productId || 'product',
                    item_name: productName,
                    price: discountedPrice,
                    quantity: 1
                }]
            }
        });
        window.dataLayer.push({
            event: 'begin_checkout',
            ecommerce: {
                currency: 'TRY',
                value: discountedPrice,
                items: [{
                    item_id: productId || 'product',
                    item_name: productName,
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
                content_id: productId,
                content_name: productName
            });
            window.ttq.track('InitiateCheckout', {
                value: discountedPrice,
                currency: 'TRY',
                content_type: 'product',
                content_id: productId,
                content_name: productName
            });
        }

        if (typeof window.paTrackSafe === 'function') {
            window.paTrackSafe('view_item', { value: discountedPrice, currency: 'TRY' });
            window.paTrackSafe('begin_checkout', { value: discountedPrice, currency: 'TRY' });
        }

        if (typeof window.ymEcomDataLayerPush === 'function') {
            var ymProd = window.ymEcomProduct(productId || 'product', productName, discountedPrice, 1);
            window.ymEcomDataLayerPush({
                currencyCode: 'TRY',
                detail: { products: [ymProd] }
            });
            window.ymEcomDataLayerPush({
                currencyCode: 'TRY',
                checkout: {
                    actionField: { step: 1 },
                    products: [ymProd]
                }
            });
        }
    })();

<?php
$__abCap = abandoned_capture_settings($pdo);
$__abOrderPayload = abandoned_capture_order_payload(is_array($product) ? $product : [], (int) $product_id, $abandonedPrefill, $__abCap);
?>
(function () {
    window.__abandonedOrderPayload = <?= json_encode($__abOrderPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
<?php if (! empty($__abCap['enabled'])): ?>
    window.__abandonedConfig = <?= json_encode(
        abandoned_capture_js_config($__abCap, is_array($product) ? $product : [], 'order'),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    ) ?>;
<?php endif; ?>
})();
</script>
<script src="js/abandoned-order-capture.js" defer></script>
<?php if (! empty($__abCap['enabled'])): ?>
<script src="js/abandoned-track.js" defer></script>
<?php endif; ?>

<script>
// Form validasyonu + çift tıklama koruması
(function () {
    var form = document.getElementById('order-checkout-form');
    if (!form) return;
    var submitting = false;
    var otpPending = <?= $showFrontOtpStep ? 'true' : 'false' ?>;

    form.addEventListener('submit', function (e) {
        var submitter = e.submitter;
        var actionInput = document.getElementById('otp_stage_action');
        var isOtpAction = false;

        if (otpPending && actionInput) {
            if (submitter && submitter.getAttribute('data-otp-action')) {
                actionInput.value = submitter.getAttribute('data-otp-action');
            } else if (!actionInput.value) {
                var otpInput = document.getElementById('otp_code_front');
                var otpLen = otpInput ? otpInput.value.replace(/\D/g, '').length : 0;
                actionInput.value = otpLen === 6 ? 'verify' : '';
            }
        }

        isOtpAction = actionInput && (actionInput.value === 'verify' || actionInput.value === 'resend');
        if (!isOtpAction && submitter) {
            isOtpAction = submitter.name === 'otp_stage_verify_submit' || submitter.name === 'otp_stage_resend_submit';
        }

        if (!isOtpAction && !otpPending) {
            var citySelect = document.getElementById('customer_city');
            var districtSelect = document.getElementById('customer_district');
            if (citySelect && !citySelect.value) {
                e.preventDefault();
                alert('Lütfen il seçiniz.');
                citySelect.focus();
                return false;
            }
            if (districtSelect && !districtSelect.value) {
                e.preventDefault();
                alert('Lütfen ilçe seçiniz.');
                districtSelect.focus();
                return false;
            }
        }

        if (isOtpAction) {
            var otpInput = document.getElementById('otp_code_front');
            var verifyClicked = (actionInput && actionInput.value === 'verify') ||
                (submitter && submitter.name === 'otp_stage_verify_submit');
            if (verifyClicked && otpInput && otpInput.value.replace(/\D/g, '').length !== 6) {
                e.preventDefault();
                alert('Lütfen 6 haneli doğrulama kodunu girin.');
                otpInput.focus();
                return false;
            }
        }

        if (submitting) {
            e.preventDefault();
            return false;
        }
        submitting = true;
        var busyBtn = (submitter && isOtpAction) ? submitter : form.querySelector('.order-form__submit:not([hidden])');
        if (busyBtn) {
            busyBtn.disabled = true;
            busyBtn.setAttribute('aria-busy', 'true');
        }
    });
})();
</script>

<script>
(function () {
    function updateSwatch(selectEl) {
        var row = selectEl.closest('.opui-variant-row');
        if (!row) return;
        var sw = row.querySelector('.opui-variant-swatch');
        if (!sw) return;

        var opt = selectEl.options[selectEl.selectedIndex];
        if (!opt || !opt.value) {
            sw.classList.remove('is-visible');
            sw.style.backgroundColor = 'transparent';
            return;
        }

        var color = (opt.getAttribute('data-color') || '').trim();
        var enabled = (opt.getAttribute('data-color-enabled') || '0') === '1';
        if (!enabled || color === '') {
            sw.classList.remove('is-visible');
            sw.style.backgroundColor = 'transparent';
            return;
        }
        sw.classList.add('is-visible');
        sw.style.backgroundColor = color;
    }

    function init() {
        var selects = document.querySelectorAll('select.opui-variant-select');
        selects.forEach(function (sel) {
            sel.addEventListener('change', function () { updateSwatch(sel); });
            updateSwatch(sel);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<?php if ($showFrontOtpStep): ?>
<script>
(function () {
    var $modal = $('#orderOtpModal');
    var actionInput = document.getElementById('otp_stage_action');
    document.querySelectorAll('[data-otp-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (actionInput) {
                actionInput.value = btn.getAttribute('data-otp-action') || '';
            }
        });
    });
    if (!$modal.length) return;
    $modal.modal({ backdrop: 'static', keyboard: false, show: true });
    setTimeout(function () {
        var inp = document.getElementById('otp_code_front');
        if (inp) inp.focus();
    }, 350);
    $modal.on('hidden.bs.modal', function () {
        $modal.modal({ backdrop: 'static', keyboard: false, show: true });
    });
})();
</script>
<?php endif; ?>

    <!-- Varyant sistemi artık tamamen PHP ile çalışıyor -->

<?php
 $__ru = (string) ($_SERVER['REQUEST_URI'] ?? '');
 $carkifelek_return_url = ($__ru !== '' && $__ru[0] === '/') ? $__ru : '/order.php';

?>
<?php include __DIR__ . '/includes/carkifelek_public.php'; ?>
</body>
</html>
