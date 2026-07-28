<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/gateways/PaytrGateway.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['paytr_test'])) {
        $cfg = $pdo->query('SELECT * FROM paytr_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $mid = trim((string) ($_POST['merchant_id'] ?? $cfg['merchant_id'] ?? ''));
        $mkey = (string) ($_POST['merchant_key'] ?? $cfg['merchant_key'] ?? '');
        $msalt = (string) ($_POST['merchant_salt'] ?? $cfg['merchant_salt'] ?? '');
        $ok = $mid !== '' && $mkey !== '' && $msalt !== '';
        $_SESSION['message'] = $ok
            ? 'PayTR kimlik bilgileri formda mevcut. Canlı test için mağaza panelinde Bildirim URL: ' . htmlspecialchars(dirname(__DIR__) . '/payment/paytr_callback.php')
            : 'PayTR test: merchant_id, key ve salt zorunlu.';
        $_SESSION['message_type'] = $ok ? 'success' : 'danger';
        header('Location: paytr_settings.php');
        exit;
    }

    if (!empty($_POST['save_paytr'])) {
        $pdo->prepare(
            'UPDATE paytr_settings SET is_enabled = ?, merchant_id = ?, merchant_key = ?, merchant_salt = ?,
             test_mode = ?, no_installment = ?, max_installment = ?, debug_on = ?, timeout_limit = ? WHERE id = 1'
        )->execute([
            !empty($_POST['is_enabled']) ? 1 : 0,
            mb_substr(trim((string) ($_POST['merchant_id'] ?? '')), 0, 32),
            mb_substr(trim((string) ($_POST['merchant_key'] ?? '')), 0, 255),
            mb_substr(trim((string) ($_POST['merchant_salt'] ?? '')), 0, 255),
            !empty($_POST['test_mode']) ? 1 : 0,
            !empty($_POST['no_installment']) ? 1 : 0,
            max(0, min(12, (int) ($_POST['max_installment'] ?? 0))),
            !empty($_POST['debug_on']) ? 1 : 0,
            max(5, min(120, (int) ($_POST['timeout_limit'] ?? 30))),
        ]);
        $_SESSION['message'] = 'PayTR ayarları kaydedildi.';
        $_SESSION['message_type'] = 'success';
        header('Location: paytr_settings.php');
        exit;
    }
}

$r = $pdo->query('SELECT * FROM paytr_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
require_once dirname(__DIR__) . '/includes/app_url.php';
$callbackUrl = app_url('payment/paytr_callback', [], $pdo);

$page_title = 'PayTR Ayarları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $flashMsg = (string) $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <div class="alert alert-<?= $mtp === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2"><i class="fas fa-credit-card text-primary"></i> PayTR (kredi kartı)</h1>
    <p class="text-muted small">
        <a href="https://dev.paytr.com/iframe-api/iframe-api-1-adim" target="_blank" rel="noopener">PayTR iFrame API</a> —
        Mağaza panelinde <strong>Bildirim URL</strong>: <code><?= htmlspecialchars($callbackUrl) ?></code>
    </p>

    <form method="post" class="card border-0 shadow-sm p-3">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_enabled" id="en" value="1" <?= !empty($r['is_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="en">PayTR ödeme açık</label>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Mağaza no (merchant_id)</label>
                <input type="text" name="merchant_id" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['merchant_id'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Merchant key</label>
                <input type="password" name="merchant_key" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['merchant_key'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-4">
                <label class="form-label">Merchant salt</label>
                <input type="password" name="merchant_salt" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['merchant_salt'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">Test modu</label>
                <select name="test_mode" class="form-select">
                    <option value="0"<?= empty($r['test_mode']) ? ' selected' : '' ?>>Kapalı</option>
                    <option value="1"<?= !empty($r['test_mode']) ? ' selected' : '' ?>>Açık</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Taksit gösterme</label>
                <select name="no_installment" class="form-select">
                    <option value="0"<?= empty($r['no_installment']) ? ' selected' : '' ?>>Taksit var</option>
                    <option value="1"<?= !empty($r['no_installment']) ? ' selected' : '' ?>>Tek çekim</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Max taksit (0=panel limiti)</label>
                <input type="number" name="max_installment" class="form-control" min="0" max="12" value="<?= (int) ($r['max_installment'] ?? 0) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Zaman aşımı (dk)</label>
                <input type="number" name="timeout_limit" class="form-control" min="5" max="120" value="<?= (int) ($r['timeout_limit'] ?? 30) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Debug (testte 1)</label>
                <select name="debug_on" class="form-select">
                    <option value="0"<?= empty($r['debug_on']) ? ' selected' : '' ?>>Kapalı</option>
                    <option value="1"<?= !empty($r['debug_on']) ? ' selected' : '' ?>>Açık</option>
                </select>
            </div>
        </div>
        <p class="text-muted small mt-3 mb-2">Ödeme yöntemlerinde <strong>Kredi Kartı (PayTR)</strong> satırını aktif edin (<a href="manage_payment_methods.php">Ödeme yöntemleri</a>).</p>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" name="save_paytr" value="1" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
            <button type="submit" name="paytr_test" value="1" class="btn btn-outline-secondary"><i class="fas fa-plug"></i> Bilgileri kontrol et</button>
        </div>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
