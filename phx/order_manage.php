<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/variant_helpers.php';
require_once dirname(__DIR__) . '/includes/order_image_payload.php';

$order_id = isset($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;

$om_popup = false;
$om_embed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $om_popup = isset($_POST['popup']) && (string) $_POST['popup'] === '1';
    $om_embed = isset($_POST['embed']) && (string) $_POST['embed'] === '1';
} elseif (isset($_GET['popup']) && (string) $_GET['popup'] === '1') {
    $om_popup = true;
}
if (isset($_GET['embed']) && (string) $_GET['embed'] === '1') {
    $om_embed = true;
    $om_popup = true;
}
$om_popup_q = $om_popup ? '&popup=1' : '';
$om_embed_q = $om_embed ? '&embed=1' : '';

if ($order_id < 1) {
    header('Location: orders.php');
    exit;
}

$flash = '';
if (!empty($_SESSION['order_manage_flash'])) {
    $flash = (string) $_SESSION['order_manage_flash'];
    unset($_SESSION['order_manage_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['parasut_send'])) {
        require_once dirname(__DIR__) . '/includes/ParasutOrderSync.php';
        $oid = (int) ($_POST['order_id'] ?? 0);
        if ($oid >= 1) {
            $force = !empty($_POST['parasut_force']);
            $r = ParasutOrderSync::send($pdo, $oid, $force);
            if (!empty($r['ok'])) {
                $_SESSION['order_manage_flash'] = 'Paraşüt: satış faturası oluşturuldu.'
                    . ((($r['parasut_invoice_id'] ?? '') !== '') ? ' ID: ' . (string) $r['parasut_invoice_id'] : '');
            } else {
                $_SESSION['order_manage_flash'] = 'Paraşüt: ' . ((string) ($r['error'] ?? 'İşlem başarısız.'));
            }
        } else {
            $_SESSION['order_manage_flash'] = 'Paraşüt: geçersiz sipariş.';
        }
        header('Location: order_manage.php?order_id=' . ($oid >= 1 ? $oid : $order_id) . $om_popup_q . $om_embed_q);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $order_item_id = (int) ($_POST['order_item_id'] ?? 0);
        $customer_name = trim((string) ($_POST['customer_name'] ?? ''));
        $customer_phone = trim((string) ($_POST['customer_phone'] ?? ''));
        $customer_address = trim((string) ($_POST['customer_address'] ?? ''));
        $customer_city = (int) ($_POST['customer_city'] ?? 0);
        $customer_district = (int) ($_POST['customer_district'] ?? 0);
        $payment_method_id = (int) ($_POST['payment_method_id'] ?? 0);
        $customer_notes = trim((string) ($_POST['customer_notes'] ?? ''));
        $order_notes = trim((string) ($_POST['order_notes'] ?? ''));
        $invoice_vkn = mb_substr(preg_replace('/[^0-9]/', '', (string) ($_POST['invoice_vkn'] ?? '')), 0, 11);
        $invoice_tax_office = mb_substr(trim((string) ($_POST['invoice_tax_office'] ?? '')), 0, 128);
        $invoice_company_name = mb_substr(trim((string) ($_POST['invoice_company_name'] ?? '')), 0, 255);
        $invoice_address = mb_substr(trim((string) ($_POST['invoice_address'] ?? '')), 0, 3000);
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $product_price = (float) str_replace(',', '.', (string) ($_POST['product_price'] ?? '0'));

        if ($customer_name === '' || $customer_phone === '' || $payment_method_id < 1) {
            throw new InvalidArgumentException('Müşteri adı, telefon ve ödeme yöntemi zorunludur.');
        }

        if ($product_price <= 0) {
            throw new InvalidArgumentException('Ürün fiyatı geçersiz.');
        }

        $stmt = $pdo->prepare(
            'UPDATE orders
            SET customer_name = ?, customer_phone = ?, customer_address = ?, customer_city = ?, customer_district = ?,
                payment_method_id = ?, invoice_vkn = ?, invoice_tax_office = ?, invoice_company_name = ?, invoice_address = ?,
                customer_notes = ?, order_notes = ?, updated_by = ?, edit_order_date = NOW()
            WHERE order_id = ?'
        );

        $current_user = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'admin');

        $stmt->execute([
            $customer_name,
            $customer_phone,
            $customer_address,
            $customer_city ?: null,
            $customer_district ?: null,
            $payment_method_id,
            $invoice_vkn !== '' ? $invoice_vkn : null,
            $invoice_tax_office !== '' ? $invoice_tax_office : null,
            $invoice_company_name !== '' ? $invoice_company_name : null,
            $invoice_address !== '' ? $invoice_address : null,
            $customer_notes,
            $order_notes,
            $current_user,
            $order_id,
        ]);

        if ($order_item_id >= 1 && $product_id >= 1) {
            $oi = $pdo->prepare(
                'UPDATE order_items SET product_id = ?, price = ? WHERE order_item_id = ? AND order_id = ?'
            );
            $oi->execute([$product_id, $product_price, $order_item_id, $order_id]);
        }

        $delVar = $pdo->prepare('DELETE FROM order_variation_details WHERE order_id = ?');
        $delVar->execute([$order_id]);

        foreach ($_POST as $key => $value) {
            if (strpos((string) $key, 'variant_') !== 0) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $type_id = (int) str_replace('variant_', '', (string) $key);
            $option_id = (int) $value;
            if ($type_id > 0 && $option_id > 0) {
                $insVar = $pdo->prepare(
                    'INSERT INTO order_variation_details (order_id, type_id, option_id) VALUES (?, ?, ?)'
                );
                $insVar->execute([$order_id, $type_id, $option_id]);
            }
        }

        $new_status_raw = isset($_POST['order_status_id']) ? trim((string) $_POST['order_status_id']) : '';
        $new_status_id = $new_status_raw !== '' && ctype_digit($new_status_raw) ? (int) $new_status_raw : 0;

        if ($new_status_id > 0) {
            $oldStmt = $pdo->prepare('SELECT order_status_id FROM orders WHERE order_id = ?');
            $oldStmt->execute([$order_id]);
            $old_status_id = (int) $oldStmt->fetchColumn();

            if ($old_status_id !== $new_status_id) {
                $upd = $pdo->prepare(
                    'UPDATE orders SET order_status_id = ?, updated_by = ?, edit_order_date = NOW() WHERE order_id = ?'
                );
                $upd->execute([$new_status_id, $current_user, $order_id]);

                $log = $pdo->prepare(
                    'INSERT INTO order_status_logs (order_id, old_status_id, new_status_id, changed_by) VALUES (?, ?, ?, ?)'
                );
                $log->execute([$order_id, $old_status_id, $new_status_id, $current_user]);

                try {
                    require_once dirname(__DIR__) . '/includes/netgsm_customer_sms.php';
                    netgsm_try_send_customer_sms_on_status_transition(
                        $pdo,
                        $order_id,
                        $old_status_id,
                        $new_status_id
                    );
                } catch (Throwable $e) {
                    error_log('netgsm_try_send_customer_sms_on_status_transition: ' . $e->getMessage());
                }
                try {
                    require_once dirname(__DIR__) . '/telegram.php';
                    telegram_notify_admin_status_change($pdo, $order_id, $old_status_id, $new_status_id);
                } catch (Throwable $e) {
                    error_log('telegram_notify_admin_status_change: ' . $e->getMessage());
                }
            }
        }

        $pdo->commit();

        $_SESSION['order_manage_flash'] = $new_status_id > 0
            ? 'Sipariş kaydedildi.'
            : 'Bilgiler güncellendi (durum değiştirilmedi).';

        header('Location: order_manage.php?order_id=' . $order_id . $om_popup_q . $om_embed_q);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['order_manage_flash'] = 'Hata: ' . $e->getMessage();
        header('Location: order_manage.php?order_id=' . $order_id . $om_popup_q . $om_embed_q);
        exit;
    }
}

// GET: veri yükle
$stmt = $pdo->prepare(
    'SELECT o.*, s.status_name FROM orders o
     LEFT JOIN order_status s ON o.order_status_id = s.order_status_id
     WHERE o.order_id = ?'
);
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['order_manage_flash'] = 'Sipariş bulunamadı.';
    header('Location: orders.php');
    exit;
}

$itemStmt = $pdo->prepare(
    'SELECT order_item_id, product_id, price, quantity FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC'
);
$itemStmt->execute([$order_id]);
$orderItemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

if ($orderItemRows === []) {
    $_SESSION['message'] = 'Bu sipariş için ürün satırı bulunamadı.';
    header('Location: orders.php');
    exit;
}

$firstItem = $orderItemRows[0];
$order['product_id'] = (int) $firstItem['product_id'];
$order['order_item_id'] = (int) $firstItem['order_item_id'];
$order['order_item_price'] = $firstItem['price'];
$multipleOrderItems = count($orderItemRows) > 1;

$cities = $pdo->query('SELECT * FROM cities ORDER BY city_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$districts = $pdo->query('SELECT * FROM districts ORDER BY district_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$payment_methods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order, method_name')
    ->fetchAll(PDO::FETCH_ASSOC);

$products = $pdo->query('SELECT product_id, product_name, product_price FROM products ORDER BY product_name ASC')
    ->fetchAll(PDO::FETCH_ASSOC);

$statuses = $pdo->query('SELECT * FROM order_status ORDER BY order_status_id ASC')->fetchAll(PDO::FETCH_ASSOC);

$log_stmt = $pdo->prepare(
    'SELECT l.*,
        s1.status_name AS old_status_name,
        s2.status_name AS new_status_name
    FROM order_status_logs l
    LEFT JOIN order_status s1 ON l.old_status_id = s1.order_status_id
    LEFT JOIN order_status s2 ON l.new_status_id = s2.order_status_id
    WHERE l.order_id = ?
    ORDER BY l.changed_at DESC'
);
$log_stmt->execute([$order_id]);
$logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

$oim_saved_images = order_image_list_saved($pdo, $order_id);
$oim_can_factory = admin_user_can('menu_yz');

$selStmt = $pdo->prepare('SELECT type_id, option_id FROM order_variation_details WHERE order_id = ?');
$selStmt->execute([$order_id]);
$selectedVariants = [];
foreach ($selStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $selectedVariants[(int) $row['type_id']] = (int) $row['option_id'];
}

$page_title = 'Sipariş #' . $order_id;
$statusBadgeClass = 'om-status--default';
$sn = mb_strtolower(trim((string) ($order['status_name'] ?? '')));
if (str_contains($sn, 'teslim')) {
    $statusBadgeClass = 'om-status--done';
} elseif (str_contains($sn, 'kargo') || str_contains($sn, 'onay')) {
    $statusBadgeClass = 'om-status--ship';
} elseif (str_contains($sn, 'iptal') || str_contains($sn, 'iade')) {
    $statusBadgeClass = 'om-status--bad';
} elseif (str_contains($sn, 'bekle') || str_contains($sn, 'ulaş')) {
    $statusBadgeClass = 'om-status--wait';
}

if ($om_popup) {
    header_remove('X-Frame-Options');
    header('Content-Security-Policy: frame-ancestors \'self\'', true);
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="om-body<?= $om_embed ? ' om-body--embed' : ' om-body--popup' ?>">
    <?php
} else {
    include __DIR__ . '/admin_header.php';
}
?>

<style>
.om-body {
    margin: 0;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f1f5f9;
}
.om-body--popup { padding: 16px; }
.om-body--embed { padding: 0; background: #f8fafc; }
.om-body--embed .om-hero { display: none !important; }
.om-body--embed .om-wrap { padding: 10px 12px 14px; max-width: 100%; }
.om-body--embed .om-save-bar {
    margin: 8px 0 0;
    position: sticky;
    bottom: 0;
    border-radius: 10px;
}
.om-body--embed .om-card { margin-bottom: 10px; }
.om-body--embed .om-card-h { padding: 8px 12px; font-size: 0.72rem; }
.om-body--embed .om-card-b { padding: 12px; }
.om-wrap {
    max-width: <?= $om_popup ? '100%' : '980px' ?>;
    margin: 0 auto;
    padding: <?= $om_embed ? '16px 18px 28px' : '0 8px 32px' ?>;
}
.om-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
    color: #fff;
    border-radius: <?= $om_embed ? '0' : '14px' ?>;
    padding: 16px 18px;
    margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}
.om-body--embed .om-hero {
    border-radius: 0;
    margin: -16px -18px 16px;
    padding: 14px 18px;
}
.om-hero__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.om-hero h1 {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0 0 6px;
    color: #fff;
}
.om-hero__meta {
    font-size: 0.82rem;
    color: #cbd5e1;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.om-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
}
.om-status--default { background: rgba(255,255,255,0.15); color: #e2e8f0; }
.om-status--wait { background: #fef3c7; color: #92400e; }
.om-status--ship { background: #dbeafe; color: #1e40af; }
.om-status--done { background: #dcfce7; color: #166534; }
.om-status--bad { background: #fee2e2; color: #991b1b; }
.om-hero__actions .btn {
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
}
.om-page-title { display: none; }
.om-muted { color: #64748b; font-size: 13px; }
.om-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 14px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.om-card-h {
    padding: 11px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-weight: 700;
    font-size: 0.78rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #475569;
    background: #f8fafc;
}
.om-card-h i { color: #2563eb; margin-right: 4px; }
.om-card-b { padding: 16px; }
.om-image-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}
.om-image-gallery__item {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #f8fafc;
}
.om-image-gallery__item img {
    display: block;
    width: 100%;
    height: 100px;
    object-fit: cover;
    background: #fff;
}
.om-image-gallery__meta {
    padding: 6px 8px;
    font-size: 0.68rem;
    color: #64748b;
    line-height: 1.35;
}
.om-image-gallery__actions {
    display: flex;
    gap: 4px;
    padding: 0 8px 8px;
}
.om-image-gallery__actions .btn { font-size: 0.68rem; padding: 2px 6px; }
.om-label { font-size: 0.72rem; font-weight: 700; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.03em; }
.form-select:focus, .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}
.om-save-bar {
    position: sticky;
    bottom: 0;
    z-index: 10;
    background: rgba(255,255,255,0.96);
    backdrop-filter: blur(8px);
    border-top: 1px solid #e2e8f0;
    padding: 12px 16px;
    margin: 0 -18px -28px;
    box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.06);
}
.om-body--embed .om-save-bar { margin: 8px -18px -28px; }
.log-row {
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
    font-size: 13px;
    background: #fafafa;
}
.badge-soft { background: #e0e7ff; color: #3730a3; font-weight: 600; }
.om-flash {
    border-radius: 10px;
    border: none;
    font-size: 0.88rem;
}
<?php if (!$om_popup): ?>
.om-page-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.om-page-title h1 {
    font-size: 1.35rem;
    font-weight: 600;
    margin: 0;
    color: #111827;
}
.om-hero { display: none; }
.om-wrap { max-width: 980px; padding: 0 8px 32px; }
.om-save-bar { position: static; margin: 0; box-shadow: none; background: transparent; border: none; padding: 0; }
<?php endif; ?>
</style>

<?php if ($om_popup && !$om_embed): ?>
<div class="bg-white border-bottom shadow-sm py-2 px-3 mb-3 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2" style="max-width:100%;margin-left:auto;margin-right:auto;">
    <span class="small fw-semibold text-secondary"><i class="fas fa-window-restore me-1"></i>Sipariş yönetimi</span>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="omTryClose();"><i class="fas fa-times"></i> Kapat</button>
</div>
<?php endif; ?>

<div class="om-wrap container-fluid px-2">
    <div class="om-hero">
        <div class="om-hero__top">
            <div>
                <h1><i class="fas fa-receipt me-1"></i> Sipariş #<?= (int) $order_id ?></h1>
                <div class="om-hero__meta">
                    <span class="om-status <?= htmlspecialchars($statusBadgeClass) ?>"><?= htmlspecialchars((string) ($order['status_name'] ?? '—')) ?></span>
                    <?php if (!empty($order['order_date'])): ?>
                        <span><i class="far fa-clock me-1"></i><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string) $order['order_date']))) ?></span>
                    <?php endif; ?>
                    <?php
                    $refDisp = trim((string) ($order['referrer'] ?? ''));
                    $rekDisp = trim((string) ($order['reklam'] ?? ''));
                    ?>
                    <?php if ($refDisp !== ''): ?>
                        <span class="badge bg-dark bg-opacity-25"><?= htmlspecialchars($refDisp) ?></span>
                    <?php endif; ?>
                    <?php if ($rekDisp !== ''): ?>
                        <span><?= htmlspecialchars($rekDisp) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="om-hero__actions d-flex flex-wrap gap-2">
                <a href="order_image_maker.php?order_id=<?= (int) $order_id ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="fas fa-palette me-1"></i>Stüdyo</a>
                <a href="order_image_maker.php?factory=1&amp;order_ids=<?= (int) $order_id ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="fas fa-industry me-1"></i>Fabrika</a>
                <?php if (!$om_embed): ?>
                    <?php if ($om_popup): ?>
                        <button type="button" class="btn btn-sm btn-outline-light" onclick="if(window.opener){window.opener.location.reload();}"><i class="fas fa-sync-alt"></i> Liste</button>
                    <?php else: ?>
                        <a href="orders.php" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> Liste</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="om-page-title">
        <div>
            <h1><i class="fas fa-receipt text-primary"></i> Sipariş #<?= (int) $order_id ?></h1>
            <div class="om-muted">Durum: <strong><?= htmlspecialchars((string) ($order['status_name'] ?? '—')) ?></strong></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 justify-content-end">
            <a href="order_image_maker.php?order_id=<?= (int) $order_id ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fas fa-palette me-1"></i>Stüdyo</a>
            <a href="order_image_maker.php?factory=1&amp;order_ids=<?= (int) $order_id ?>" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm"><i class="fas fa-industry me-1"></i>Fabrika</a>
            <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Sipariş listesi</a>
        </div>
    </div>

    <?php
    $hasUtm = trim((string) ($order['utm_campaign'] ?? '')) !== ''
        || trim((string) ($order['utm_source'] ?? '')) !== ''
        || trim((string) ($order['utm_medium'] ?? '')) !== ''
        || trim((string) ($order['attribution_click_json'] ?? '')) !== '';
    ?>
    <?php if ($hasUtm): ?>
    <div class="om-card mb-3">
        <div class="om-card-h"><i class="fas fa-bullhorn me-1"></i> Kampanya / UTM</div>
        <div class="om-card-b row g-2 small">
            <div class="col-md-4"><span class="om-label">utm_source</span> <?= htmlspecialchars((string) ($order['utm_source'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="col-md-4"><span class="om-label">utm_medium</span> <?= htmlspecialchars((string) ($order['utm_medium'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="col-md-4"><span class="om-label">utm_campaign</span> <?= htmlspecialchars((string) ($order['utm_campaign'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="col-md-6"><span class="om-label">utm_content</span> <?= htmlspecialchars((string) ($order['utm_content'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="col-md-6"><span class="om-label">utm_term</span> <?= htmlspecialchars((string) ($order['utm_term'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if (!empty($order['attribution_landing_url'])): ?>
            <div class="col-12"><span class="om-label">İlk giriş URL</span> <span class="text-break"><?= htmlspecialchars((string) $order['attribution_landing_url'], ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
            <?php if (!empty($order['attribution_click_json'])): ?>
            <div class="col-12"><span class="om-label">Tıklama kimlikleri</span> <code class="small text-break"><?= htmlspecialchars((string) $order['attribution_click_json'], ENT_QUOTES, 'UTF-8') ?></code></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-info py-2 om-flash"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if ($oim_can_factory): ?>
    <div class="om-card mb-3">
        <div class="om-card-h d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-images me-1"></i> Üretilen görseller</span>
            <a href="order_image_maker.php?factory=1&amp;order_ids=<?= (int) $order_id ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">
                <i class="fas fa-industry me-1"></i>Fabrikada üret
            </a>
        </div>
        <div class="om-card-b">
            <?php if ($oim_saved_images === []): ?>
                <p class="small text-muted mb-0">
                    Henüz kayıtlı görsel yok. Fabrikada üretirken teslimatı <strong>Sunucuya kaydet</strong> veya <strong>İkisi birden</strong> seçin; görseller burada listelenir.
                </p>
            <?php else: ?>
                <div class="om-image-gallery">
                    <?php foreach ($oim_saved_images as $img): ?>
                        <?php
                        $adminThumb = '../' . ltrim((string) $img['path'], '/');
                        $imgDate = !empty($img['mtime']) ? date('d.m.Y H:i', (int) $img['mtime']) : '—';
                        $imgKb = !empty($img['size']) ? number_format(((int) $img['size']) / 1024, 0, ',', '.') . ' KB' : '';
                        ?>
                        <div class="om-image-gallery__item">
                            <a href="<?= htmlspecialchars((string) $img['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                <img src="<?= htmlspecialchars($adminThumb, ENT_QUOTES, 'UTF-8') ?>" alt="Sipariş #<?= (int) $order_id ?> görseli" loading="lazy">
                            </a>
                            <div class="om-image-gallery__meta">
                                <?= htmlspecialchars($imgDate, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($imgKb !== ''): ?> · <?= htmlspecialchars($imgKb, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </div>
                            <div class="om-image-gallery__actions">
                                <a href="<?= htmlspecialchars((string) $img['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Aç</a>
                                <a href="<?= htmlspecialchars((string) $img['url'], ENT_QUOTES, 'UTF-8') ?>" download class="btn btn-outline-secondary btn-sm">İndir</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($multipleOrderItems): ?>
        <div class="alert alert-warning border-0 py-2 mb-3">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Bu siparişte birden fazla ürün satırı var. Bu ekranda yalnızca <strong>ilk satır</strong> düzenlenir.
        </div>
    <?php endif; ?>

    <form method="post" action="order_manage.php" class="needs-validation">
        <input type="hidden" name="order_id" value="<?= (int) $order_id ?>">
        <input type="hidden" name="order_item_id" value="<?= (int) $order['order_item_id'] ?>">
        <?php if ($om_popup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
        <?php if ($om_embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

        <div class="om-card">
            <div class="om-card-h"><i class="fas fa-user me-1"></i> Müşteri</div>
            <div class="om-card-b row g-3">
                <div class="col-md-6">
                    <div class="om-label">Ad soyad</div>
                    <input type="text" name="customer_name" class="form-control form-control-sm" required value="<?= htmlspecialchars((string) ($order['customer_name'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <div class="om-label">Telefon</div>
                    <input type="text" name="customer_phone" class="form-control form-control-sm" required value="<?= htmlspecialchars((string) ($order['customer_phone'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <div class="om-label">Adres</div>
                    <textarea name="customer_address" rows="3" class="form-control form-control-sm" required><?= htmlspecialchars((string) ($order['customer_address'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                    <div class="om-label">Şehir</div>
                    <select name="customer_city" id="customer_city" class="form-select form-select-sm" required>
                        <option value="">Seçin</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= (int) $city['city_id'] ?>" <?= (int) $order['customer_city'] === (int) $city['city_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $city['city_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="om-label">İlçe</div>
                    <select name="customer_district" id="customer_district" class="form-select form-select-sm" required>
                        <option value="">İlçe seç</option>
                        <?php foreach ($districts as $d): ?>
                            <?php if ((int) $d['city_id'] === (int) $order['customer_city']): ?>
                                <option value="<?= (int) $d['district_id'] ?>" <?= (int) $order['customer_district'] === (int) $d['district_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $d['district_name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Şehir seçince liste yenilenir.</small>
                </div>
            </div>
        </div>

        <div class="om-card">
            <div class="om-card-h"><i class="fas fa-building me-1"></i> Kurumsal fatura</div>
            <div class="om-card-b row g-3">
                <div class="col-md-6">
                    <div class="om-label">Vergi no (11 hane)</div>
                    <input type="text" name="invoice_vkn" id="invoice_vkn" maxlength="11" inputmode="numeric" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($order['invoice_vkn'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <div class="om-label">Vergi dairesi</div>
                    <input type="text" name="invoice_tax_office" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($order['invoice_tax_office'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <div class="om-label">Firma ünvanı</div>
                    <input type="text" name="invoice_company_name" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($order['invoice_company_name'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <div class="om-label">Fatura adresi</div>
                    <textarea name="invoice_address" rows="3" class="form-control form-control-sm"><?= htmlspecialchars((string) ($order['invoice_address'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="om-card">
            <div class="om-card-h"><i class="fas fa-shopping-basket me-1"></i> Ürün ve varyantlar</div>
            <div class="om-card-b row g-3">
                <div class="col-md-6">
                    <div class="om-label">Ödeme yöntemi</div>
                    <select name="payment_method_id" class="form-select form-select-sm" required>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?= (int) $method['payment_method_id'] ?>" <?= (int) $order['payment_method_id'] === (int) $method['payment_method_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $method['method_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="om-label">Ürün (ilk satır)</div>
                    <select name="product_id" id="product_id" class="form-select form-select-sm">
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['product_id'] ?>" data-price="<?= htmlspecialchars((string) $product['product_price']) ?>" <?= $order['product_id'] === (int) $product['product_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $product['product_name']) ?> — <?= htmlspecialchars((string) $product['product_price']) ?> TL</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1">Ürünü değiştirirseniz varyant alanlarının güncellenmesi için sayfayı yeniden açın (veya kaydettikten sonra tekrar girin).</small>
                </div>
                <div class="col-md-4">
                    <div class="om-label">Satır fiyatı (TL)</div>
                    <input type="text" name="product_price" id="product_price" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($order['order_item_price'] ?? '')) ?>">
                </div>

                <?php
                $types = [];
                try {
                    $vtStmt = $pdo->prepare(
                        'SELECT vt.type_id, vt.type_name, pva.is_required
                        FROM product_variation_types vt
                        JOIN product_variation_assignments pva ON vt.type_id = pva.type_id
                        WHERE pva.product_id = ? AND vt.is_active = 1
                        ORDER BY COALESCE(pva.display_order, vt.display_order), vt.type_name'
                    );
                    $vtStmt->execute([$order['product_id']]);
                    $types = $vtStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    $types = [];
                }
                ?>
                <?php if ($types !== []): ?>
                    <div class="col-12">
                        <div class="om-label mb-2">Varyantlar — kayıtlı seçimler seçili gelir.</div>
                        <div class="row g-2">
                            <?php foreach ($types as $t): ?>
                                <?php
                                $tid = (int) $t['type_id'];
                                $optStmt = $pdo->prepare(
                                    'SELECT option_id, option_name FROM product_variation_options WHERE type_id = ? AND is_active = 1 ORDER BY display_order, option_name'
                                );
                                $optStmt->execute([$tid]);
                                $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <div class="col-md-6">
                                    <div class="om-label"><?= htmlspecialchars(variant_type_display_name((string) ($t['type_name'] ?? ''))) ?><?= !empty($t['is_required']) ? ' <span class="text-danger">*</span>' : '' ?></div>
                                    <select name="variant_<?= $tid ?>" class="form-select form-select-sm" <?= !empty($t['is_required']) ? 'required' : '' ?>>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($options as $opt): ?>
                                            <option value="<?= (int) $opt['option_id'] ?>" <?= (isset($selectedVariants[$tid]) && $selectedVariants[$tid] === (int) $opt['option_id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $opt['option_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-12 text-muted small">Bu ürüne atanmış aktif varyant türü yok.</div>
                <?php endif; ?>

                <div class="col-12">
                    <div class="om-label">Müşteri notu</div>
                    <textarea name="customer_notes" rows="2" class="form-control form-control-sm"><?= htmlspecialchars((string) ($order['customer_notes'] ?? '')) ?></textarea>
                </div>
                <div class="col-12">
                    <div class="om-label">Sipariş notu (panel)</div>
                    <textarea name="order_notes" rows="2" class="form-control form-control-sm"><?= htmlspecialchars((string) ($order['order_notes'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="om-card om-save-bar">
            <div class="om-card-b row align-items-end g-3 mb-0 py-2">
                <div class="col-md-8">
                    <div class="om-label">Durum değişikliği (isteğe bağlı)</div>
                    <select name="order_status_id" class="form-select form-select-sm">
                        <option value="">— Dokunma —</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= (int) $status['order_status_id'] ?>" <?= (int) $order['order_status_id'] === (int) $status['order_status_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $status['status_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Durum değişirse log, SMS/Telegram akışları çalışır.</small>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="submit" name="save_order" value="1" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="om-card">
        <div class="om-card-h"><i class="fas fa-cloud me-1"></i> Paraşüt</div>
        <div class="om-card-b">
            <?php $paraId = trim((string) ($order['parasut_invoice_id'] ?? '')); ?>
            <?php if ($paraId !== ''): ?>
                <p class="small mb-2"><span class="badge badge-soft">Fatura ID</span> <code><?= htmlspecialchars($paraId) ?></code></p>
            <?php endif; ?>
            <form method="post" action="order_manage.php">
                <input type="hidden" name="order_id" value="<?= (int) $order_id ?>">
                <?php if ($om_popup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
        <?php if ($om_embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                <?php if ($paraId !== ''): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="parasut_force" id="pf" value="1">
                        <label class="form-check-label small" for="pf">Tekrar oluştur (zorla)</label>
                    </div>
                <?php endif; ?>
                <button type="submit" name="parasut_send" value="1" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-cloud-upload-alt"></i> <?= $paraId === '' ? 'Paraşüt satış faturası oluştur' : 'Paraşüt’e yeniden gönder' ?>
                </button>
            </form>
            <p class="text-muted small mt-2 mb-0">
                Önce <a href="parasut_settings.php">Paraşüt ayarları</a> doğrulansın.
            </p>
        </div>
    </div>

    <div class="om-card">
        <div class="om-card-h"><i class="fas fa-history me-1"></i> Durum günlüğü</div>
        <div class="om-card-b">
            <?php if ($logs !== []): ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-row">
                        <strong><?= htmlspecialchars((string) ($log['changed_by'] ?? '')) ?></strong>
                        <?= htmlspecialchars((string) ($log['old_status_name'] ?? '—')) ?>
                        →
                        <?= htmlspecialchars((string) ($log['new_status_name'] ?? '—')) ?>
                        <div class="text-muted small mt-1"><i class="far fa-clock"></i> <?= htmlspecialchars(date('d.m.Y H:i:s', strtotime((string) ($log['changed_at'] ?? 'now')))) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted small mb-0">Henüz durum kaydı yok.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const productSelect = document.getElementById('product_id');
    const priceInput = document.getElementById('product_price');

    function updateProductPriceFromSelect() {
        if (!productSelect || !priceInput) return;
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const price = selectedOption ? selectedOption.getAttribute('data-price') : '';
        if (price) priceInput.value = price;
    }

    productSelect?.addEventListener('change', updateProductPriceFromSelect);

    priceInput?.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9.,]/g, '');
    });

    const citySelect = document.getElementById('customer_city');
    const districtSelect = document.getElementById('customer_district');

    async function refillDistricts(keepDistrictId) {
        if (!citySelect || !districtSelect) return;
        const cityId = citySelect.value;
        if (!cityId) {
            districtSelect.innerHTML = '<option value="">Önce şehir seç</option>';
            return;
        }
        districtSelect.innerHTML = '<option>Yükleniyor…</option>';
        try {
            const url = '../get_districts.php?city_id=' + encodeURIComponent(cityId);
            const r = await fetch(url);
            const data = await r.json();
            districtSelect.innerHTML = '<option value="">İlçe seç</option>';
            data.forEach((d) => {
                const o = document.createElement('option');
                o.value = String(d.district_id);
                o.textContent = d.district_name;
                districtSelect.appendChild(o);
            });
            if (keepDistrictId) {
                districtSelect.value = String(keepDistrictId);
            }
        } catch (_) {
            districtSelect.innerHTML = '<option>Hata</option>';
        }
    }

    citySelect?.addEventListener('change', function () {
        refillDistricts('');
    });

    window.addEventListener('DOMContentLoaded', function () {
        const iv = document.getElementById('invoice_vkn');
        if (iv) {
            iv.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);
            });
        }
    });
})();
</script>

<?php if ($om_popup): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function omTryClose() {
    try {
        if (window.parent && window.parent !== window && typeof window.parent.closeOrderManage === 'function') {
            window.parent.closeOrderManage();
            return;
        }
    } catch (e) {}
    if (window.opener) {
        try { window.opener.focus(); } catch (e2) {}
        window.close();
        return;
    }
    window.location.href = 'orders.php';
}
</script>
<?php if ($om_embed && $flash !== ''): ?>
<script>
try {
    window.parent.postMessage({
        type: 'order-manage-saved',
        orderId: <?= (int) $order_id ?>,
        title: 'Sipariş #<?= (int) $order_id ?>'
    }, '*');
} catch (e) {}
</script>
<?php endif; ?>
</body>
</html>
<?php else: ?>
<?php include __DIR__ . '/admin_footer_common.php'; ?>
<?php endif; ?>
