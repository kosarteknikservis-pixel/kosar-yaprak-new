<?php
require 'db.php';
require_once 'tracking.php';
require_once __DIR__ . '/includes/page_meta_load.php';
require_once __DIR__ . '/includes/page_seo.php';
require_once __DIR__ . '/includes/site_footer.php';
require_once __DIR__ . '/includes/order_guard_helpers.php';

$page_name = basename(__FILE__);
$meta = page_meta_load($pdo, $page_name) ?? [];
$seo = page_seo_resolve($pdo, $page_name, $meta);

$error_message = 'Bir hata oluştu. Lütfen tekrar deneyin.';
$product_info = '';
$order_details = '';
$orderGuardSettings = order_guard_load($pdo);
$serverWindowLabel = order_guard_format_window((int) ($orderGuardSettings['order_dupe_window_seconds'] ?? 86400));
$browserWindowLabel = order_guard_format_window((int) ($orderGuardSettings['order_cookie_seconds'] ?? 60));

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_fields':
            $error_message = 'Lütfen tüm zorunlu alanları doldurunuz.';
            break;
        case 'blocked':
            $error_message = 'Şu anda bu bilgilerle sipariş kabul edilemiyor. Destek için iletişime geçin.';
            break;
        case 'browser_block':
            $error_message = 'Sipariş işleminiz devam ediyor. Lütfen sayfayı yenilemeden bir kez daha denemeyin. Sorun sürerse birkaç dakika bekleyip tekrar deneyin veya destek talebi açın.';
            break;
        case 'invalid_payment_method':
            $error_message = 'Seçilen ödeme yöntemi geçersiz veya kapalı.';
            break;
        case 'payment_gateway':
            $error_message = 'Ödeme ekranı açılamadı: ' . htmlspecialchars(trim((string) ($_GET['msg'] ?? 'Ayarları kontrol edin.')), ENT_QUOTES, 'UTF-8');
            break;
        case 'no_product':
            $error_message = 'Lütfen vitrinden bir ürün seçerek sipariş sayfasına gidin.';
            break;
        case 'product_not_found':
            $error_message = 'Seçilen ürün bulunamadı veya artık satışta değil.';
            break;
        case 'duplicate_order':
            if (isset($_GET['reason'])) {
                switch ($_GET['reason']) {
                    case 'ip':
                        $error_message = 'Bu IP adresi üzerinden son ' . $serverWindowLabel . ' içinde sipariş oluşturulmuş.';
                        break;
                    case 'phone':
                        $error_message = 'Bu telefon numarası ile son ' . $serverWindowLabel . ' içinde sipariş oluşturulmuş.';
                        break;
                    case 'browser':
                        $error_message = 'Bu tarayıcı üzerinden son ' . $browserWindowLabel . ' içinde sipariş oluşturulmuş.';
                        break;
                    default:
                        $error_message = 'Daha önceden sipariş oluşturmuşsunuz. Müşteri hizmetlerimiz kısa süre içinde sizinle iletişime geçecektir.';
                        break;
                }
            } else {
                $error_message = 'Daha önceden sipariş oluşturmuşsunuz. Müşteri hizmetlerimiz kısa süre içinde sizinle iletişime geçecektir.';
            }
            break;
    }
}

$product_id = $_GET['product_id'] ?? null;

if ($product_id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        $stmt = $pdo->prepare('SELECT o.*, oi.quantity, oi.price FROM orders o
                               JOIN order_items oi ON o.order_id = oi.order_id
                               WHERE oi.product_id = ? ORDER BY o.order_date DESC LIMIT 1');
        $stmt->execute([$product_id]);
        $order = $stmt->fetch();

        $formatted_date = !empty($order['order_date'])
            ? date('d.m.Y H:i', strtotime((string) $order['order_date']))
            : 'Tarih mevcut değil';

        $product_info = '
            <div class="product-summary">
                <img src="uploads/' . htmlspecialchars((string) $product['product_image']) . '" alt="' . htmlspecialchars((string) $product['product_name']) . '">
                <h2>' . htmlspecialchars((string) $product['product_name']) . '</h2>
                <p><strong>Fiyat:</strong> ' . htmlspecialchars((string) $product['product_price']) . ' TL</p>
                <p><strong>Sipariş tarihi:</strong> ' . htmlspecialchars($formatted_date) . '</p>
            </div>';
    }
}

$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $esc($seo['title']) ?></title>
    <?php page_seo_render($pdo, $page_name, $meta); ?>
    <?php if (!empty($meta['head_content'])): ?>
        <?= $meta['head_content'] ?>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/site_tracking_head.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/info-pages.css">
    <link rel="stylesheet" href="css/error.css">
    <link rel="stylesheet" href="css/site-footer.css">
</head>
<body class="site-shell-app info-page-view error-page-view">
    <?php require 'menu.php'; ?>
    <?php if (!empty($meta['body_content'])): ?>
        <?= $meta['body_content'] ?>
    <?php endif; ?>

    <div class="info-page-shell">
        <div class="info-card info-card--content error-card">
            <h1 class="error-card__title"><i class="fas fa-exclamation-circle"></i> Bir sorun oluştu</h1>
            <p class="error-card__message"><?= $esc($error_message) ?></p>
            <div class="error-card__actions">
                <a href="index.php" class="info-btn info-btn--orange"><i class="fas fa-home"></i> Ana Sayfaya Dön</a>
                <?php if ($product_id): ?>
                    <a href="sorgula.php" class="info-btn info-btn--secondary"><i class="fas fa-search"></i> Siparişi Sorgula</a>
                <?php endif; ?>
                <a href="destek_talebi.php" class="info-btn info-btn--green"><i class="fas fa-headset"></i> Destek Talebi</a>
            </div>
            <?php if ($product_info): ?>
                <hr class="info-divider">
                <h2 class="error-card__subtitle">Sipariş özeti</h2>
                <?= $product_info ?>
                <?= $order_details ?>
            <?php endif; ?>
        </div>
    </div>

    <?php site_footer_render($pdo); ?>
    <?php include 'social_buttons.php'; ?>
</body>
</html>
