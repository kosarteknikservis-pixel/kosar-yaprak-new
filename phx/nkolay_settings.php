<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_nkolay'])) {
    $pdo->prepare(
        'UPDATE nkolay_settings
         SET is_enabled = ?, sx_token = ?, merchant_secret_key = ?, merchant_customer_no = ?, sandbox = ?, use_3d = ?
         WHERE id = 1'
    )->execute([
        !empty($_POST['is_enabled']) ? 1 : 0,
        mb_substr(trim((string) ($_POST['sx_token'] ?? '')), 0, 255),
        mb_substr(trim((string) ($_POST['merchant_secret_key'] ?? '')), 0, 255),
        mb_substr(trim((string) ($_POST['merchant_customer_no'] ?? '')), 0, 64),
        !empty($_POST['sandbox']) ? 1 : 0,
        !empty($_POST['use_3d']) ? 1 : 0,
    ]);
    $_SESSION['message'] = 'N Kolay ayarları kaydedildi.';
    $_SESSION['message_type'] = 'success';
    require_once dirname(__DIR__) . '/includes/payment_methods_sync.php';
    payment_methods_sync_online_gateways($pdo);
    header('Location: nkolay_settings.php');
    exit;
}

$r = $pdo->query('SELECT * FROM nkolay_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
require_once dirname(__DIR__) . '/includes/app_url.php';
$callbackUrl = app_url('payment/nkolay_callback', [], $pdo);

$page_title = 'N Kolay Ayarları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $flashMsg = (string) $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <div class="alert alert-<?= $mtp === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2"><i class="fas fa-credit-card text-info"></i> N Kolay (Ortak Ödeme Sayfası)</h1>
    <p class="text-muted small">
        Dokümantasyon:
        <a href="https://paynkolay.com.tr/entegrasyon/01-payment-integration-services.php" target="_blank" rel="noopener">Pay N Kolay Entegrasyon</a>
        — Sonuç URL: <code><?= htmlspecialchars($callbackUrl) ?></code>
    </p>

    <form method="post" class="card border-0 shadow-sm p-3">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_enabled" id="en" value="1" <?= !empty($r['is_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="en">N Kolay ödeme açık</label>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Token (sx)</label>
                <input type="text" name="sx_token" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['sx_token'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label">Merchant Secret Key</label>
                <input type="password" name="merchant_secret_key" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['merchant_secret_key'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label">MerchantCustomerNo (opsiyonel)</label>
                <input type="text" name="merchant_customer_no" class="form-control" value="<?= htmlspecialchars((string) ($r['merchant_customer_no'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">Ortam</label>
                <select name="sandbox" class="form-select">
                    <option value="1"<?= !isset($r['sandbox']) || !empty($r['sandbox']) ? ' selected' : '' ?>>Test</option>
                    <option value="0"<?= isset($r['sandbox']) && empty($r['sandbox']) ? ' selected' : '' ?>>Canlı</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="use_3d" id="use3d" value="1" <?= !isset($r['use_3d']) || !empty($r['use_3d']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="use3d">3D Secure zorunlu</label>
                </div>
            </div>
        </div>

        <p class="text-muted small mt-3 mb-2">Ödeme yöntemlerinde <strong>Kredi / Banka Kartı (Online)</strong> satırını aktif edin.</p>
        <button type="submit" name="save_nkolay" value="1" class="btn btn-primary mt-2"><i class="fas fa-save"></i> Kaydet</button>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
