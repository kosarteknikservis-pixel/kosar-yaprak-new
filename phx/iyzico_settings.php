<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_iyzico'])) {
    $pdo->prepare(
        'UPDATE iyzico_settings SET is_enabled = ?, api_key = ?, secret_key = ?, sandbox = ?, enabled_installments = ? WHERE id = 1'
    )->execute([
        !empty($_POST['is_enabled']) ? 1 : 0,
        mb_substr(trim((string) ($_POST['api_key'] ?? '')), 0, 128),
        mb_substr(trim((string) ($_POST['secret_key'] ?? '')), 0, 255),
        !empty($_POST['sandbox']) ? 1 : 0,
        mb_substr(trim((string) ($_POST['enabled_installments'] ?? '2,3,6,9')), 0, 64),
    ]);
    $_SESSION['message'] = 'iyzico ayarları kaydedildi.';
    $_SESSION['message_type'] = 'success';
    header('Location: iyzico_settings.php');
    exit;
}

$r = $pdo->query('SELECT * FROM iyzico_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
require_once dirname(__DIR__) . '/includes/app_url.php';
$callbackUrl = app_url('payment/iyzico_callback', [], $pdo);

$page_title = 'iyzico Ayarları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $flashMsg = (string) $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <div class="alert alert-<?= $mtp === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2"><i class="fas fa-credit-card text-success"></i> iyzico (kredi kartı)</h1>
    <p class="text-muted small">
        <a href="https://docs.iyzico.com/" target="_blank" rel="noopener">iyzico Checkout Form</a> —
        Callback URL: <code><?= htmlspecialchars($callbackUrl) ?></code>
    </p>

    <form method="post" class="card border-0 shadow-sm p-3">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_enabled" id="en" value="1" <?= !empty($r['is_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="en">iyzico ödeme açık</label>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">API Key</label>
                <input type="text" name="api_key" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['api_key'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label">Secret Key</label>
                <input type="password" name="secret_key" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['secret_key'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ortam</label>
                <select name="sandbox" class="form-select">
                    <option value="1"<?= !isset($r['sandbox']) || !empty($r['sandbox']) ? ' selected' : '' ?>>Sandbox (test)</option>
                    <option value="0"<?= isset($r['sandbox']) && empty($r['sandbox']) ? ' selected' : '' ?>>Canlı</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Taksit seçenekleri (virgülle)</label>
                <input type="text" name="enabled_installments" class="form-control" value="<?= htmlspecialchars((string) ($r['enabled_installments'] ?? '2,3,6,9')) ?>">
            </div>
        </div>
        <p class="text-muted small mt-3 mb-2">Ödeme yöntemlerinde <strong>Kredi Kartı (iyzico)</strong> satırını aktif edin.</p>
        <button type="submit" name="save_iyzico" value="1" class="btn btn-primary mt-2"><i class="fas fa-save"></i> Kaydet</button>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
