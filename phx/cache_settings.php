<?php
declare(strict_types=1);

require '../db.php';
require_once '../includes/cache/page_cache_service.php';
require 'auth.php';

$page_title = 'Site hızlandırma';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['purge_cache'])) {
        $n = PageCacheService::purgeAll();
        $_SESSION['message'] = $n > 0 ? $n . ' önbellek dosyası silindi.' : 'Önbellek zaten boştu.';
        $_SESSION['message_type'] = 'success';
        header('Location: cache_settings.php');
        exit;
    }
    $enabled = isset($_POST['page_cache_enabled']) ? 1 : 0;
    $ttl = max(60, min(86400, (int) ($_POST['page_cache_ttl'] ?? 3600)));
    $pdo->prepare('UPDATE checkout_module_settings SET page_cache_enabled = ?, page_cache_ttl = ? WHERE id = 1')
        ->execute([$enabled, $ttl]);
    if ($enabled === 0) {
        PageCacheService::purgeAll();
    }
    $_SESSION['message'] = 'Kaydedildi.';
    $_SESSION['message_type'] = 'success';
    header('Location: cache_settings.php');
    exit;
}

$row = $pdo->query('SELECT page_cache_enabled, page_cache_ttl FROM checkout_module_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$enabled = ! empty((int) ($row['page_cache_enabled'] ?? 0));
$ttl = (int) ($row['page_cache_ttl'] ?? 3600);
$cached = PageCacheService::listCached();
$totalSize = array_sum(array_column($cached, 'size'));

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (! empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-bolt text-warning"></i> Site hızlandırma</h1>
            <p class="text-muted small mb-0">Vitrin sayfalarının HTML çıktısını önbelleğe alır — tekrarlayan ziyaretler daha hızlı açılır.</p>
        </div>
        <span class="badge fs-6 <?= $enabled ? 'bg-success' : 'bg-secondary' ?>"><?= $enabled ? 'Aktif' : 'Kapalı' ?></span>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <form method="post" class="card border-0 shadow-sm">
                <div class="card-body vstack gap-3">
                    <div class="form-check form-switch p-3 rounded border <?= $enabled ? 'border-success bg-success-subtle' : '' ?>">
                        <input class="form-check-input" type="checkbox" name="page_cache_enabled" id="pc1" <?= $enabled ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="pc1">Sayfa önbelleği</label>
                        <p class="small text-muted mb-0 mt-1">Ana sayfa, hakkımızda, iletişim, kargo vb. — parametresiz GET istekleri.</p>
                    </div>
                    <div>
                        <label class="form-label small" for="ttl">Süre (saniye)</label>
                        <input type="number" name="page_cache_ttl" id="ttl" class="form-control" min="60" max="86400" value="<?= (int) $ttl ?>">
                        <div class="form-text">Varsayılan 3600 (1 saat). Ürün güncelledikten sonra önbelleği temizleyin.</div>
                    </div>
                    <button type="submit" class="btn btn-primary align-self-start"><i class="fas fa-save"></i> Kaydet</button>
                </div>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Önbellek durumu</span>
                    <span class="small text-muted"><?= count($cached) ?> dosya · <?= number_format($totalSize / 1024, 1) ?> KB</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Cloaker açıkken bot filtrelemesi önbellekten önce çalışır. Sepet, UTM veya çark çerezi olan ziyaretçiler önbellekten yararlanmaz.</p>
                    <form method="post" onsubmit="return confirm('Tüm önbellek silinsin mi?');">
                        <button type="submit" name="purge_cache" value="1" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Önbelleği temizle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
