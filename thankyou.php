<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'db.php';
require_once __DIR__ . '/includes/i18n.php';
i18n_boot($pdo);

date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/tracking.php';
require_once __DIR__ . '/includes/page_meta_load.php';
require_once __DIR__ . '/includes/page_seo.php';
require_once __DIR__ . '/includes/app_url.php';
require_once __DIR__ . '/includes/order_sms_verify.php';
require_once __DIR__ . '/includes/order_verification.php';

$page_name = 'thankyou.php';
require_once __DIR__ . '/includes/page_view_log.php';
page_view_log($pdo, $page_name);
$ip_address = app_client_ip();
$stmt = $pdo->query("SELECT show_post_order_msg, post_order_msg_text FROM footer_images WHERE id = 7");
$post_order_msg = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['show_post_order_msg' => 0, 'post_order_msg_text' => ''];

// Ayrıca page_visits tablosuna da kaydet
// $stmt = $pdo->prepare("INSERT INTO page_visits (page_name, ip_address, visit_time) VALUES (?, ?, ?)");
// $stmt->execute([$page_name, $ip_address, $visit_time]);

// Sipariş bilgilerini al
$order_id = $_GET['order_id'] ?? null;
$thankyouInvalid = false;
$thankyouInvalidMessage = function_exists('t') ? t('thankyou.not_found', 'Sipariş bulunamadı') : 'Sipariş bilgisine ulaşılamadı. Sipariş numaranızı kontrol edin veya müşteri hizmetlerimizle iletişime geçin.';
$order = false;
$variantItems = [];
$cleanNotes = '';
$district_show = '';
$city_show = '';
$active_bank_accounts = [];
$show_bank_transfer_box = false;
$purchaseClientPayloadJson = '{}';
$purchaseSignalsJson = '{}';
$orderSmsPending = false;
$otpUiMessage = '';
$otpUiType = '';

if (!$order_id || !preg_match('/^\d+$/', (string) $order_id)) {
    $thankyouInvalid = true;
} else {
    $stmt = $pdo->prepare('
        SELECT o.order_id, o.customer_name, o.customer_phone, o.customer_address,
               o.customer_city, o.customer_district, o.order_notes, o.order_date, s.status_name,
               GROUP_CONCAT(CONCAT(p.product_name, " (", oi.price, " TL)") SEPARATOR ", ") AS products,
               GROUP_CONCAT(p.product_image SEPARATOR ", ") AS product_images,
               pm.method_name AS payment_method_name,
               SUM(oi.price) AS total_price
        FROM orders o
        JOIN order_status s ON o.order_status_id = s.order_status_id
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
        WHERE o.order_id = ?
        GROUP BY o.order_id, pm.method_name
    ');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        $thankyouInvalid = true;
    }
}

if (!$thankyouInvalid) {
    $orderSmsPending = ov_order_is_pending($pdo, (int) $order_id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['otp_verify_submit'])) {
            $verifyRes = ov_verify_by_otp($pdo, (int) $order_id, (string) ($_POST['otp_code'] ?? ''));
            if ($verifyRes['ok']) {
                $orderSmsPending = false;
                $otpUiMessage = 'Siparişiniz doğrulandı. Teşekkür ederiz!';
                $otpUiType = 'success';
                $stmt = $pdo->prepare('
                    SELECT o.order_id, o.customer_name, o.customer_phone, o.customer_address,
                           o.customer_city, o.customer_district, o.order_notes, o.order_date, s.status_name,
                           GROUP_CONCAT(CONCAT(p.product_name, " (", oi.price, " TL)") SEPARATOR ", ") AS products,
                           GROUP_CONCAT(p.product_image SEPARATOR ", ") AS product_images,
                           pm.method_name AS payment_method_name,
                           SUM(oi.price) AS total_price
                    FROM orders o
                    JOIN order_status s ON o.order_status_id = s.order_status_id
                    JOIN order_items oi ON o.order_id = oi.order_id
                    JOIN products p ON oi.product_id = p.product_id
                    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
                    WHERE o.order_id = ?
                    GROUP BY o.order_id, pm.method_name
                ');
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: $order;
            } else {
                $reason = (string) ($verifyRes['reason'] ?? '');
                if ($reason === 'expired') {
                    $otpUiMessage = 'Doğrulama kodunun süresi doldu. Yeni kod gönderin.';
                } elseif ($reason === 'invalid') {
                    $otpUiMessage = 'Doğrulama kodu hatalı. Lütfen tekrar deneyin.';
                } else {
                    $otpUiMessage = 'Doğrulama yapılamadı. Lütfen tekrar deneyin.';
                }
                $otpUiType = 'danger';
            }
        } elseif (isset($_POST['otp_resend_submit']) && $orderSmsPending) {
            $ovData = ov_create_or_refresh($pdo, (int) $order_id, (string) ($order['customer_phone'] ?? ''), 20);
            if (is_array($ovData)) {
                $verifyLink = app_url('order_verify', ['t' => $ovData['token']], $pdo);
                if (ov_send_otp_sms((string) ($order['customer_phone'] ?? ''), $ovData['otp'], $verifyLink)) {
                    $otpUiMessage = 'Yeni doğrulama kodu telefonunuza gönderildi.';
                    $otpUiType = 'success';
                } else {
                    $otpUiMessage = 'SMS gönderilemedi. Lütfen daha sonra tekrar deneyin.';
                    $otpUiType = 'danger';
                }
            }
        }
    }

    $verifyFlag = trim((string) ($_GET['verify'] ?? ''));
    if ($verifyFlag === 'ok' || $verifyFlag === 'already') {
        $orderSmsPending = false;
        $otpUiMessage = 'Siparişiniz link ile doğrulandı.';
        $otpUiType = 'success';
        $stmt = $pdo->prepare('
            SELECT o.order_id, o.customer_name, o.customer_phone, o.customer_address,
                   o.customer_city, o.customer_district, o.order_notes, o.order_date, s.status_name,
                   GROUP_CONCAT(CONCAT(p.product_name, " (", oi.price, " TL)") SEPARATOR ", ") AS products,
                   GROUP_CONCAT(p.product_image SEPARATOR ", ") AS product_images,
                   pm.method_name AS payment_method_name,
                   SUM(oi.price) AS total_price
            FROM orders o
            JOIN order_status s ON o.order_status_id = s.order_status_id
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
            WHERE o.order_id = ?
            GROUP BY o.order_id, pm.method_name
        ');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: $order;
    } elseif ($verifyFlag === 'expired') {
        $otpUiMessage = 'Doğrulama linkinin süresi doldu. Aşağıdan yeni kod isteyebilirsiniz.';
        $otpUiType = 'warning';
    } elseif ($verifyFlag === 'no') {
        $otpUiMessage = 'Doğrulama linki geçersiz.';
        $otpUiType = 'danger';
    }
}

if (!$thankyouInvalid) {
// Varyantları ayrı bir sorgu ile çek (veritabanı şemasını değiştirmeden)
$variantItems = [];
try {
    $vstmt = $pdo->prepare("SELECT variants FROM order_items WHERE order_id = ? AND variants IS NOT NULL AND TRIM(variants) <> ''");
    $vstmt->execute([$order_id]);
    $vrows = $vstmt->fetchAll(PDO::FETCH_COLUMN);
    if ($vrows) {
        foreach ($vrows as $v) {
            $variantItems[] = trim($v);
        }
    }
} catch (Exception $e) { /* sessiz geç */ }

// Eski sistemde not içine yazılmış varyant bilgisini temizle
$cleanNotes = trim((string)($order['order_notes'] ?? ''));
if ($cleanNotes !== '') {
    // Satır bazlı temizleme: "Varyant", "Varyantlar", "Seçilen Varyantlar" içeren satırları at
    $lines = preg_split('/\r\n|\r|\n/', $cleanNotes);
    $filtered = [];
    foreach ($lines as $ln) {
        if (!preg_match('/\bVaryant(lar)?\b|Seçilen\s+Varyantlar/i', $ln)) {
            $filtered[] = $ln;
        }
    }
    $cleanNotes = trim(implode("\n", $filtered));
    // Tek satırda yazılmışsa, iki nokta sonrası varyant cümlesini kırp
    $cleanNotes = preg_replace('/Varyant(lar)?\s*[:：].*$/iu', '', $cleanNotes);
    $cleanNotes = trim($cleanNotes);
}

$district_show = '';
$city_show = '';
try {
    if (!empty($order['customer_city'])) {
        $cst = $pdo->prepare('SELECT city_name FROM cities WHERE city_id = ? LIMIT 1');
        $cst->execute([(int)$order['customer_city']]);
        $city_show = trim((string)($cst->fetchColumn() ?: ''));
    }
    if (!empty($order['customer_district'])) {
        $dst = $pdo->prepare('SELECT district_name FROM districts WHERE district_id = ? LIMIT 1');
        $dst->execute([(int)$order['customer_district']]);
        $district_show = trim((string)($dst->fetchColumn() ?: ''));
    }
} catch (Throwable $e) {
}

$active_bank_accounts = [];
try {
    $bst = $pdo->query('SELECT bank_name, account_holder, iban, branch, notes FROM bank_accounts WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    $active_bank_accounts = $bst ? $bst->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $active_bank_accounts = [];
}
$payment_method_label = strtolower((string)($order['payment_method_name'] ?? ''));
$show_bank_transfer_box = count($active_bank_accounts) > 0
    && (bool)preg_match('/havale|eft|iban|banka|transfer|havalesi|dekont/i', $payment_method_label);

require_once __DIR__ . '/includes/conversion_tracking.php';

$pidStmt = $pdo->prepare('SELECT product_id FROM order_items WHERE order_id = ?');
$pidStmt->execute([(int)$order_id]);
$purchaseProductIds = $pidStmt->fetchAll(PDO::FETCH_COLUMN);

$purchaseOrderLines = [];

try {

    $olist = $pdo->prepare(
        'SELECT oi.product_id, oi.quantity, oi.price, p.product_name
         FROM order_items oi
         INNER JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.order_item_id ASC'
    );

    $olist->execute([(int)$order_id]);
    $purchaseOrderLines = $olist->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $t) {

    $purchaseOrderLines = [];

}

$cipStmt = $pdo->prepare('SELECT customer_ip FROM orders WHERE order_id = ?');
$cipStmt->execute([(int)$order_id]);
$orderCustIp = (string)($cipStmt->fetchColumn() ?: '');

conversion_send_after_purchase($pdo, [
    'order_id' => (string)$order_id,
    'value' => (float)($order['total_price'] ?? 0),
    'currency' => 'TRY',
    'customer_phone' => (string)($order['customer_phone'] ?? ''),
    'ip' => $orderCustIp !== '' ? $orderCustIp : $ip_address,
    'product_ids' => array_map('strval', (array)$purchaseProductIds),
    'order_lines' => $purchaseOrderLines,
    'client_user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'fbc_cookie' => (string)($_COOKIE['_fbc'] ?? ''),
    'fbp_cookie' => (string)($_COOKIE['_fbp'] ?? ''),
    'fbclid_session' => (string)($_SESSION['attr_fbclid'] ?? ''),
    'ttclid_session' => (string)($_SESSION['attr_ttclid'] ?? ''),
]);

$purchaseClientPayloadJson = json_encode(
    conversion_build_purchase_tracking_payload(
        $purchaseOrderLines,
        (string) $order_id,
        (float) ($order['total_price'] ?? 0),
        'TRY'
    ),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE
);

$convSignalsRow = $pdo->query('SELECT yandex_metrica_counter_id, microsoft_clarity_project_id FROM conversion_api_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$thankyouYandexCounter = preg_replace('/\D+/', '', trim((string) ($convSignalsRow['yandex_metrica_counter_id'] ?? '')));
$thankyouClarityId = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) ($convSignalsRow['microsoft_clarity_project_id'] ?? '')));

$purchaseSignalsJson = json_encode(
    [
        'ym' => $thankyouYandexCounter !== '' ? $thankyouYandexCounter : '',
        'clr' => $thankyouClarityId !== '' ? $thankyouClarityId : '',
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE
);

require_once __DIR__ . '/includes/netgsm_customer_sms.php';
$skipThankyouOrderSms = false;
try {
    $smsGwStmt = $pdo->prepare(
        'SELECT o.payment_status, pm.gateway_code
         FROM orders o
         LEFT JOIN payment_methods pm ON pm.payment_method_id = o.payment_method_id
         WHERE o.order_id = ?
         LIMIT 1'
    );
    $smsGwStmt->execute([(int) $order_id]);
    $smsGwRow = $smsGwStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($smsGwRow)) {
        $gw = trim((string) ($smsGwRow['gateway_code'] ?? ''));
        $skipThankyouOrderSms = ($smsGwRow['payment_status'] ?? '') === 'paid'
            && in_array($gw, ['paytr', 'iyzico'], true);
    }
} catch (Throwable $e) {
    $skipThankyouOrderSms = false;
}
if (! $orderSmsPending && ! $skipThankyouOrderSms) {
    netgsm_send_new_order_sms_if_enabled($pdo, $order, (string) $order_id);
}

} // !$thankyouInvalid

$page_name = basename(__FILE__);
$meta = page_meta_load($pdo, $page_name) ?? [];
?>

<!DOCTYPE html>
<html <?= function_exists('i18n_html_attrs') ? i18n_html_attrs() : 'lang="tr" dir="ltr"' ?>>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($thankyouInvalid ? 'Sipariş bulunamadı' : (page_seo_resolve($pdo, $page_name, $meta)['title']), ENT_QUOTES, 'UTF-8') ?></title>
    <?php page_seo_render($pdo, $page_name, $meta, ['robots' => 'noindex, follow']); ?>
    <?php if ($meta && !empty($meta['head_content'])): ?>
        <?= $meta['head_content']; ?>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/site_tracking_head.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/thanks.css">
</head>
<body class="site-shell-app thankyou-page-view">
<?php require'menu.php'; ?>


    <?php if ($meta && !empty($meta['body_content'])): ?>
        <?= $meta['body_content']; ?>
    <?php endif; ?>

<div class="thankyou-shell">
<?php if ($thankyouInvalid): ?>
    <div class="ty-card ty-card--hero ty-card--error">
        <div class="ty-hero-icon-wrap" aria-hidden="true">
            <span class="ty-error-icon"><i class="fas fa-circle-exclamation"></i></span>
        </div>
        <h1><?= te('thankyou.not_found', 'Sipariş bulunamadı') ?></h1>
        <p class="ty-error-sub"><?= htmlspecialchars($thankyouInvalidMessage) ?></p>
    </div>
    <div class="ty-actions">
        <a href="index.php" class="btn-custom btn-custom--primary"><i class="fas fa-home"></i> <?= te('common.back_home', 'Ana sayfaya dön') ?></a>
        <a href="sorgula.php" class="btn-custom btn-custom--secondary"><i class="fas fa-search"></i> <?= te('thankyou.query', 'Sipariş sorgula') ?></a>
    </div>
<?php else: ?>
    <div class="ty-card ty-card--hero">
        <div class="ty-hero-icon-wrap" aria-hidden="true">
            <span class="ty-success-icon"><i class="fas fa-check"></i></span>
        </div>
        <h1><?= te('thankyou.thanks', 'Teşekkürler!') ?></h1>
        <p class="ty-success-sub"><?= $orderSmsPending ? te('thankyou.sms_pending', 'Siparişiniz alındı. Lütfen telefonunuza gelen kod ile doğrulayın.') : te('thankyou.received', 'Siparişiniz başarıyla alındı.') ?></p>
        <p class="ty-order-id"><i class="fas fa-receipt"></i> <?= te('thankyou.order_no', 'Sipariş') ?> #<?= htmlspecialchars((string) $order_id) ?></p>
    </div>

    <?php if ($orderSmsPending || $otpUiMessage !== ''): ?>
    <div class="ty-card">
        <h2 class="ty-card__title ty-card__title--green"><?= te('thankyou.sms_title', 'SMS Doğrulama') ?></h2>
        <?php if ($otpUiMessage !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($otpUiType !== '' ? $otpUiType : 'info', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($otpUiMessage) ?></div>
        <?php endif; ?>
        <?php if ($orderSmsPending): ?>
        <p><?= te('thankyou.sms_help', 'Telefonunuza gönderilen 6 haneli kodu girin veya SMS’teki linke tıklayın. Kod 20 dakika geçerlidir.') ?></p>
        <form method="POST" class="mb-3">
            <div class="form-group">
                <label for="otp_code"><?= te('order.otp_code', 'Doğrulama kodu') ?></label>
                <input type="text" class="form-control" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="otp_verify_submit" value="1" class="btn-custom btn-custom--primary"><?= te('thankyou.verify', 'Doğrula') ?></button>
                <button type="submit" name="otp_resend_submit" value="1" class="btn-custom btn-custom--secondary"><?= te('thankyou.resend', 'Kodu yeniden gönder') ?></button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ty-card">
        <h2 class="ty-card__title ty-card__title--green"><?= te('thankyou.info', 'Sipariş Bilgileri') ?></h2>
        <div class="ty-info-list">
            <div class="ty-info-row">
                <span class="ty-info-row__icon"><i class="fas fa-user"></i></span>
                <div class="ty-info-row__body">
                    <span class="ty-info-row__label"><?= te('common.name', 'Ad Soyad') ?></span>
                    <span class="ty-info-row__value"><?= htmlspecialchars($order['customer_name']) ?></span>
                </div>
            </div>
            <div class="ty-info-row">
                <span class="ty-info-row__icon"><i class="fas fa-phone"></i></span>
                <div class="ty-info-row__body">
                    <span class="ty-info-row__label"><?= te('common.phone', 'Telefon') ?></span>
                    <span class="ty-info-row__value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                </div>
            </div>
            <?php
            $addrDistrict = $district_show !== '' ? $district_show : (string)($order['customer_district'] ?? '');
            $addrCity = $city_show !== '' ? $city_show : (string)($order['customer_city'] ?? '');
            ?>
            <div class="ty-info-row">
                <span class="ty-info-row__icon"><i class="fas fa-map-marker-alt"></i></span>
                <div class="ty-info-row__body">
                    <span class="ty-info-row__label"><?= te('common.address', 'Adres') ?></span>
                    <span class="ty-info-row__value"><?= htmlspecialchars($order['customer_address']) ?>, <?= htmlspecialchars($addrDistrict) ?>, <?= htmlspecialchars($addrCity) ?></span>
                </div>
            </div>
            <?php if ($cleanNotes !== ''): ?>
            <div class="ty-info-row">
                <span class="ty-info-row__icon"><i class="fas fa-sticky-note"></i></span>
                <div class="ty-info-row__body">
                    <span class="ty-info-row__label"><?= te('common.note', 'Not') ?></span>
                    <span class="ty-info-row__value"><?= htmlspecialchars($cleanNotes) ?></span>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($variantItems)): ?>
            <div class="ty-info-row">
                <span class="ty-info-row__icon"><i class="fas fa-palette"></i></span>
                <div class="ty-info-row__body">
                    <span class="ty-info-row__label"><?= te('thankyou.variants', 'Varyantlar') ?></span>
                    <span class="ty-info-row__value"><?= htmlspecialchars(implode(', ', $variantItems)) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="ty-meta-grid">
            <div class="ty-meta-item">
                <span class="ty-meta-item__label"><?= te('thankyou.payment', 'Ödeme') ?></span>
                <span class="ty-meta-item__value"><?= htmlspecialchars(function_exists('content_t') ? content_t('payment_method', (int) ($order['payment_method_id'] ?? 0), 'name', (string) ($order['payment_method_name'] ?? '')) : (string) ($order['payment_method_name'] ?? '')) ?></span>
            </div>
            <div class="ty-meta-item">
                <span class="ty-meta-item__label"><?= te('thankyou.date', 'Tarih') ?></span>
                <span class="ty-meta-item__value"><?= htmlspecialchars($order['order_date']) ?></span>
            </div>
        </div>
        <div class="ty-status-wrap">
            <span class="ty-status"><?= htmlspecialchars($order['status_name']) ?></span>
        </div>
        <?php if (!empty($show_bank_transfer_box)): ?>
        <div class="bank-transfer-stack">
            <?php foreach ($active_bank_accounts as $bk): ?>
                <div class="bank-transfer-card">
                    <h4><i class="fas fa-university"></i> <?= htmlspecialchars((string)($bk['bank_name'] ?? '')) ?></h4>
                    <?php if (trim((string)($bk['branch'] ?? '')) !== ''): ?>
                        <p class="small text-muted mb-1"><strong><?= te('thankyou.branch', 'Şube') ?>:</strong> <?= htmlspecialchars((string)$bk['branch']) ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><strong><?= te('thankyou.holder', 'Alıcı adı soyadı') ?></strong></p>
                    <p class="mb-2"><?= htmlspecialchars((string)($bk['account_holder'] ?? '')) ?></p>
                    <p class="mb-1"><strong><?= te('thankyou.iban', 'IBAN') ?></strong></p>
                    <div class="iban-line"><?= htmlspecialchars((string)($bk['iban'] ?? '')) ?></div>
                    <div class="copy-actions">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-copy-holder="<?= htmlspecialchars((string)($bk['account_holder'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= te('thankyou.copy_name', 'Ad soyadı kopyala') ?>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" data-copy-iban="<?= htmlspecialchars((string)($bk['iban'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= te('thankyou.copy_iban', 'IBAN kopyala') ?>
                        </button>
                    </div>
                    <?php if (trim((string)($bk['notes'] ?? '')) !== ''): ?>
                        <p class="small text-muted mt-2 mb-0"><?= nl2br(htmlspecialchars((string)$bk['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="ty-card">
        <h2 class="ty-card__title ty-card__title--orange"><?= te('thankyou.details', 'Sipariş Detayları') ?></h2>
        <?php if (!empty($post_order_msg['show_post_order_msg']) && (int)$post_order_msg['show_post_order_msg'] === 1 && trim($post_order_msg['post_order_msg_text']) !== ''): ?>
        <p class="ty-post-msg"><i class="fas fa-truck"></i> <?= htmlspecialchars($post_order_msg['post_order_msg_text']) ?></p>
        <?php endif; ?>
        <div class="ty-product-box">
            <div class="ty-images">
            <?php
            $images = array_filter(explode(", ", (string)($order['product_images'] ?? '')), function ($v) {
                return trim($v) !== '';
            });
            if (!empty($images)):
                foreach ($images as $image): ?>
                    <img src="uploads/<?= htmlspecialchars($image) ?>" alt="Ürün Görseli" class="product-image" onerror="this.style.display='none'">
                <?php endforeach;
            else: ?>
                <img src="uploads/txrik.gif" alt="Varsayılan Görsel" class="product-image">
            <?php endif; ?>
            </div>
            <div class="ty-product-box__body">
                <p class="ty-products"><?= htmlspecialchars($order['products']) ?></p>
                <div class="ty-total" data-meta-price="false">
                    <span class="ty-total__label"><?= te('thankyou.total', 'Toplam Tutar') ?></span>
                    <span class="ty-total__amount"><?= function_exists('money') ? htmlspecialchars(money((float) ($order['total_price'] ?? 0))) : number_format((float)($order['total_price'] ?? 0), 2, ',', '.') . ' TL' ?></span>
                </div>
            </div>
        </div>
        <p style="display:none;" data-meta-price="true"><?= number_format((float)($order['total_price'] ?? 0), 2, '.', '') ?></p>
    </div>

    <p class="ty-trust"><i class="fas fa-lock"></i> <?= te('thankyou.trust', 'Siparişiniz güvenle kaydedildi. En kısa sürede sizinle iletişime geçilecektir.') ?></p>

    <div class="ty-actions">
        <a href="index.php" class="btn-custom btn-custom--primary"><i class="fas fa-shopping-bag"></i> <?= te('thankyou.continue', 'Alışverişe Devam Et') ?></a>
        <a href="sorgula.php" class="btn-custom btn-custom--secondary"><i class="fas fa-search"></i> <?= te('thankyou.where', 'Siparişim Nerede?') ?></a>
    </div>
<?php endif; ?>
</div>

    <script>
(function(){
  function copyText(btn, text) {
    var orig = btn.textContent;
    function flash(ok) {
      btn.textContent = ok ? 'Kopyalandı' : 'Kopyalanamadı';
      setTimeout(function(){ btn.textContent = orig; }, 1700);
    }
    function fallback() {
      try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        document.execCommand('copy');
        document.body.removeChild(ta);
        flash(true);
      } catch (e) { flash(false); }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function(){ flash(true); }).catch(fallback);
    } else fallback();
  }
  document.addEventListener('click', function(ev) {
    var btn = ev.target.closest('button[data-copy-iban], button[data-copy-holder]');
    if (!btn) return;
    var text = btn.getAttribute('data-copy-iban') || btn.getAttribute('data-copy-holder') || '';
    if (!text.length) return;
    copyText(btn, text);
  });
})();
</script>

<?php if (!$thankyouInvalid): ?>
<script>
    (function () {
        var totalPrice = <?= (float) ($order['total_price'] ?? 0) ?>;

        if (typeof window.paTrackSafe === 'function') {
            window.paTrackSafe('purchase', { value: totalPrice, currency: 'TRY' });
        }

    var P = <?= $purchaseClientPayloadJson ?: '{}' ?>;
    var SIG = <?= $purchaseSignalsJson ?: '{}' ?>;

    var v = Number(P.value);
    if (typeof v !== 'number' || isNaN(v) || !(v >= 0)) {
        return;
    }

    if (typeof fbq === 'function') {
        fbq(
            'track',
            'Purchase',
            {
                value: v,
                currency: P.currency,
                content_ids: P.content_ids,
                contents: P.meta_contents,
                content_type: 'product',
                num_items: P.num_items,
                order_id: String(P.transaction_id)
            },
            { eventID: P.meta_event_id }
        );
    }

    window.dataLayer = window.dataLayer || [];

    /** @preserve GA4 + GTM: önceki ecommerce nesnesini temizleyip yeniden bas (çift tetik sorununu azaltır) */
    window.dataLayer.push({ ecommerce: null });

    window.dataLayer.push({
        event: 'purchase',
        ecommerce: {
            transaction_id: String(P.transaction_id),
            value: v,
            currency: P.currency,
            items: P.ga_items || []
        }
    });

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'purchase', {
            transaction_id: String(P.transaction_id),
            value: v,
            currency: P.currency,
            items: P.ga_items || []
        });
    }

    if (typeof window.ttq !== 'undefined' && typeof window.ttq.track === 'function') {
        window.ttq.track('CompletePayment', {
            value: v,
            currency: P.currency,
            content_type: 'product',
            contents: P.tt_contents || [],
            order_id: String(P.transaction_id)
        }, {
            event_id: P.tiktok_event_id
        });
    }

    if (typeof window.ymEcomDataLayerPush === 'function') {
        var ymProducts = window.ymEcomFromGaItems(P.ga_items || []);
        if (!ymProducts.length) {
            ymProducts.push(window.ymEcomProduct(String(P.transaction_id), 'Sipariş', v, P.num_items || 1));
        }
        window.ymEcomDataLayerPush({
            currencyCode: String(P.currency || 'TRY'),
            purchase: {
                actionField: {
                    id: String(P.transaction_id),
                    revenue: v
                },
                products: ymProducts
            }
        });
    }

    if (SIG && SIG.clr && typeof window.clarity === 'function') {
        try {
            window.clarity('set', 'order_id', String(P.transaction_id));
            window.clarity('set', 'order_value', String(v));
        } catch (eClr) {}

    }


})();

</script>

<?php
echo conversion_render_google_ads_purchase_script($pdo, (float)($order['total_price'] ?? 0), 'TRY', (string) $order_id);
include 'social_buttons.php';
endif;
?>

</body>
</html>
