<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/site_content_urls.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: cms_pages.php');
    exit;
}

$st = $pdo->prepare('SELECT * FROM cms_pages WHERE slug = ? LIMIT 1');
$st->execute([$slug]);
$page = $st->fetch(PDO::FETCH_ASSOC);
if (!$page) {
    header('Location: cms_pages.php?error=notfound');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare(
        'UPDATE cms_pages SET title = ?, html_content = ?, meta_title = ?, meta_description = ?, hero_image = ?, head_extra = ?, prefer_cms = ?, is_active = ?, updated_at = NOW() WHERE id = ?'
    )->execute([
        trim((string) ($_POST['title'] ?? '')),
        $_POST['html_content'] ?? '',
        trim((string) ($_POST['meta_title'] ?? '')),
        trim((string) ($_POST['meta_description'] ?? '')),
        trim((string) ($_POST['hero_image'] ?? '')),
        $_POST['head_extra'] ?? '',
        isset($_POST['prefer_cms']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
        (int) $page['id'],
    ]);
    $_SESSION['message'] = 'Sayfa kaydedildi.';
    if (is_file(__DIR__ . '/../includes/cache/page_cache_service.php')) {
        require_once __DIR__ . '/../includes/cache/page_cache_service.php';
        PageCacheService::purgeAll();
    }
    header('Location: cms_edit.php?slug=' . urlencode($slug));
    exit;
}

$page_title = 'CMS — ' . $slug;
include 'admin_header.php';

$previewTargets = ['hakkimizda.php' => 'hakkimizda', 'iletisim.php' => 'iletisim', 'kargo_sureci.php' => 'kargo-sureci'];
$previewHref = null;
foreach ($previewTargets as $f => $s) {
    if ($slug === $s) {
        $previewHref = '../' . $f;
        break;
    }
}

$heroImageRaw = trim((string) ($page['hero_image'] ?? ''));
$heroImageUrl = $heroImageRaw !== '' ? site_media_public_src($heroImageRaw, $pdo) : '';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="cms_pages.php">CMS sayfaları</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($slug) ?></li>
        </ol>
    </nav>

    <div class="admin-page-intro mb-3">
        <h1><?= htmlspecialchars((string) $page['title']) ?></h1>
        <p class="lead">Slug: <code><?= htmlspecialchars($slug) ?></code></p>
    </div>

    <form method="post" class="admin-cms-edit-layout">
        <div class="admin-section-stack">
            <section class="admin-section-card">
                <div class="admin-section-card__head">
                    <h2 class="admin-section-card__title"><i class="fas fa-cog"></i> Yayın ayarları</h2>
                </div>
                <div class="admin-section-card__body">
                    <div class="admin-switch-row mb-2">
                        <label for="prefer"><strong>Vitrinde CMS içeriğini kullan</strong></label>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="prefer_cms" id="prefer" <?= !empty((int) $page['prefer_cms']) ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">Kapalıyken sayfa metinleri eski bloklardan gelir; <strong>hero görsel</strong> buradan yazdığınız yol ile yine de güncellenir.</p>
                    <div class="admin-switch-row">
                        <label for="act"><strong>Sayfa yayında</strong></label>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="act" <?= !empty((int) $page['is_active']) ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            </section>

            <section class="admin-section-card">
                <div class="admin-section-card__head">
                    <h2 class="admin-section-card__title"><i class="fas fa-heading"></i> Başlık ve SEO</h2>
                </div>
                <div class="admin-section-card__body admin-field-grid">
                    <div class="admin-field" style="grid-column:1/-1">
                        <label for="title">Sayfa başlığı</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars((string) $page['title']) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="meta_title">Meta başlık</label>
                        <input type="text" id="meta_title" name="meta_title" class="form-control" value="<?= htmlspecialchars((string) ($page['meta_title'] ?? '')) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="hero_image">Hero görsel URL</label>
                        <input type="text" id="hero_image" name="hero_image" class="form-control" placeholder="uploads/ornek.jpg" value="<?= htmlspecialchars($heroImageRaw) ?>">
                        <p class="form-text mb-0">Örnek: <code>uploads/development_team.png</code> — dosya sunucuda <code>uploads/</code> klasöründe olmalı.</p>
                        <?php if ($heroImageUrl !== ''): ?>
                            <div class="mt-2 p-2 border rounded bg-light">
                                <img id="hero_image_preview" src="<?= htmlspecialchars($heroImageUrl) ?>" alt="Hero önizleme" style="max-width:100%;max-height:160px;border-radius:8px;">
                            </div>
                        <?php else: ?>
                            <div class="mt-2 p-2 border rounded bg-light text-muted small">Görsel yolu girince önizleme burada görünür.</div>
                        <?php endif; ?>
                    </div>
                    <div class="admin-field" style="grid-column:1/-1">
                        <label for="meta_description">Meta açıklama</label>
                        <input type="text" id="meta_description" name="meta_description" class="form-control" value="<?= htmlspecialchars((string) ($page['meta_description'] ?? '')) ?>">
                    </div>
                </div>
            </section>

            <section class="admin-section-card">
                <div class="admin-section-card__head">
                    <h2 class="admin-section-card__title"><i class="fas fa-align-left"></i> HTML içerik</h2>
                </div>
                <div class="admin-section-card__body">
                    <textarea id="html_content" name="html_content" rows="20" class="form-control font-monospace small"><?= htmlspecialchars((string) ($page['html_content'] ?? '')) ?></textarea>
                    <p class="form-text mt-2">Güvenilir HTML. Paragraflar için <code>&lt;p&gt;</code>, kalın için <code>&lt;strong&gt;</code> kullanın.</p>
                </div>
            </section>

            <section class="admin-section-card">
                <div class="admin-section-card__head">
                    <h2 class="admin-section-card__title"><i class="fas fa-code"></i> Ek head kodu</h2>
                </div>
                <div class="admin-section-card__body">
                    <textarea name="head_extra" rows="5" class="form-control font-monospace small" placeholder="<script>…</script>"><?= htmlspecialchars((string) ($page['head_extra'] ?? '')) ?></textarea>
                </div>
            </section>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Kaydet</button>
                <a href="cms_pages.php" class="btn btn-outline-secondary">Geri</a>
                <?php if ($previewHref): ?>
                    <a href="<?= htmlspecialchars($previewHref) ?>" target="_blank" rel="noopener" class="btn btn-outline-info">Canlı önizle</a>
                <?php endif; ?>
            </div>
        </div>

        <aside class="admin-section-card">
            <div class="admin-section-card__head">
                <h2 class="admin-section-card__title"><i class="fas fa-eye"></i> Önizleme</h2>
            </div>
            <div class="admin-section-card__body">
                <div id="cms-live-preview" class="admin-cms-preview" style="max-height:70vh"><?= (string) ($page['html_content'] ?? '') ?></div>
                <p class="small text-muted mt-2 mb-0">Kaydetmeden önce içeriği kontrol edin. Stil vitrin CSS’ine bağlıdır.</p>
            </div>
        </aside>
    </form>
</div>

<script>
(function () {
    var ta = document.getElementById('html_content');
    var prev = document.getElementById('cms-live-preview');
    if (ta && prev) {
        ta.addEventListener('input', function () { prev.innerHTML = ta.value; });
    }

    var heroInput = document.getElementById('hero_image');
    var heroPreview = document.getElementById('hero_image_preview');
    if (!heroInput) return;

    heroInput.addEventListener('input', function () {
        var val = heroInput.value.trim();
        if (!val) return;
        var url = val;
        if (!/^https?:\/\//i.test(val)) {
            url = val.replace(/^\/+/, '');
            url = (url.indexOf('uploads/') === 0 ? '' : 'uploads/') + url;
            url = '../' + url;
        }
        if (heroPreview) {
            heroPreview.src = url;
        }
    });
})();
</script>

<?php include 'admin_footer_common.php'; ?>
