<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$rows = $pdo->query('SELECT slug, title, prefer_cms, is_active, updated_at, LEFT(html_content, 200) AS excerpt FROM cms_pages ORDER BY slug ASC')->fetchAll(PDO::FETCH_ASSOC);

$slugToFile = [
    'hakkimizda' => 'hakkimizda.php',
    'iletisim' => 'iletisim.php',
    'kargo-sureci' => 'kargo_sureci.php',
    'sss' => 'sss.php',
];

$page_title = 'CMS — Sayfa yönetimi';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <div class="admin-page-intro d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1><i class="fas fa-copy text-primary"></i> İçerik yönetimi (CMS)</h1>
            <p class="lead">Hakkımızda, iletişim, kargo ve SSS sayfalarını buradan düzenleyin. «CMS kullan» açıkken vitrin bu içeriği önceler.</p>
        </div>
        <span class="admin-badge-tr"><i class="fas fa-flag"></i> Türkiye · Türkçe</span>
    </div>

    <?php if ($rows === []): ?>
        <div class="alert alert-warning">Henüz CMS sayfası yok. Veritabanı şemasını güncelleyin veya destek alın.</div>
    <?php else: ?>
        <div class="admin-cms-grid">
            <?php foreach ($rows as $r): ?>
                <?php
                $slug = (string) $r['slug'];
                $previewFile = $slugToFile[$slug] ?? null;
                $excerpt = trim(strip_tags((string) ($r['excerpt'] ?? '')));
                ?>
                <article class="admin-cms-card">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h3><?= htmlspecialchars((string) $r['title']) ?></h3>
                        <?php if (!empty((int) $r['is_active'])): ?>
                            <span class="badge bg-success">Yayında</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Kapalı</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-cms-card__slug">/<?= htmlspecialchars($slug) ?> · <code><?= htmlspecialchars($slug) ?></code></div>
                    <div class="small text-muted">
                        <?php if (!empty((int) $r['prefer_cms'])): ?>
                            <span class="text-success fw-semibold">CMS aktif</span> — vitrin bu içeriği kullanır
                        <?php else: ?>
                            <span class="text-warning">Eski blok</span> — CMS’i açmak için düzenle
                        <?php endif; ?>
                    </div>
                    <?php if ($excerpt !== ''): ?>
                        <div class="admin-cms-preview"><?= htmlspecialchars(mb_substr($excerpt, 0, 160)) ?>…</div>
                    <?php endif; ?>
                    <div class="small text-muted">Güncelleme: <?= admin_tr_datetime((string) ($r['updated_at'] ?? '')) ?></div>
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <a class="btn btn-primary btn-sm" href="cms_edit.php?slug=<?= urlencode($slug) ?>"><i class="fas fa-pen"></i> Düzenle</a>
                        <?php if ($previewFile): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="../<?= htmlspecialchars($previewFile) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Önizle</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info mt-4 mb-0 small">
        <strong>İpucu:</strong> Eski “Hakkımızda / İletişim / Kargo” blok editörleri kaldırıldı; tüm sayfa metinleri CMS üzerinden yönetilir.
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
