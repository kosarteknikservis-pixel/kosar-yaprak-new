<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$page_title = 'Sipariş sorgulama ayarı';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lookup'])) {
    $src = (string) ($_POST['order_lookup_source'] ?? 'local');
    if (! in_array($src, ['local', 'panel'], true)) {
        $src = 'local';
    }
    try {
        $pdo->prepare('UPDATE checkout_module_settings SET order_lookup_source = ? WHERE id = 1')->execute([$src]);
        $_SESSION['message'] = 'Kaydedildi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Hata: '.$e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: order_lookup_settings.php');
    exit;
}

$lookupSource = 'local';
try {
    $lookupSource = (string) $pdo->query("SELECT COALESCE(order_lookup_source, 'local') FROM checkout_module_settings WHERE id = 1")->fetchColumn();
} catch (Throwable $e) {
    $lookupSource = 'local';
}

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (! empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?> border-0 shadow-sm"><?= htmlspecialchars((string) $_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-3"><i class="fas fa-search text-primary"></i> Sipariş sorgulama</h1>

    <div class="card border-0 shadow-sm" style="max-width: 520px;">
        <div class="card-body">
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="save_lookup" value="1">

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="order_lookup_source" id="lookup_local" value="local" <?= $lookupSource === 'local' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="lookup_local">Bu site</label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="order_lookup_source" id="lookup_panel" value="panel" <?= $lookupSource === 'panel' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="lookup_panel">Ortak panel <span class="text-muted fw-normal">(admin ile görüşün)</span></label>
                </div>

                <button type="submit" class="btn btn-primary align-self-start"><i class="fas fa-save me-1"></i> Kaydet</button>
            </form>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
