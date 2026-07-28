<?php
declare(strict_types=1);

require '../db.php';
require_once '../includes/safe_page_service.php';
require_once __DIR__ . '/../includes/app_url.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_safe_page'])) {
    $brand = mb_substr(trim((string) ($_POST['brand_name'] ?? '')), 0, 128);
    $metaTitle = mb_substr(trim((string) ($_POST['meta_title'] ?? '')), 0, 255);
    $metaDesc = mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 512);
    $heroTitle = mb_substr(trim((string) ($_POST['hero_title'] ?? '')), 0, 255);
    $heroSub = mb_substr(trim((string) ($_POST['hero_subtitle'] ?? '')), 0, 512);

    $posts = [];
    $titles = $_POST['post_title'] ?? [];
    $cats = $_POST['post_category'] ?? [];
    $dates = $_POST['post_date'] ?? [];
    $excerpts = $_POST['post_excerpt'] ?? [];
    if (is_array($titles)) {
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $posts[] = [
                'title' => mb_substr($title, 0, 200),
                'category' => mb_substr(trim((string) ($cats[$i] ?? 'Genel')), 0, 64),
                'date' => mb_substr(trim((string) ($dates[$i] ?? '')), 0, 16),
                'excerpt' => mb_substr(trim((string) ($excerpts[$i] ?? '')), 0, 600),
            ];
        }
    }
    if ($posts === []) {
        $posts = SafePageService::defaultPosts();
    }

    $pdo->prepare(
        'UPDATE cloaker_settings SET
            safe_page_brand_name = ?,
            safe_page_meta_title = ?,
            safe_page_meta_description = ?,
            safe_page_hero_title = ?,
            safe_page_hero_subtitle = ?,
            safe_page_posts_json = ?
         WHERE id = 1'
    )->execute([
        $brand,
        $metaTitle,
        $metaDesc,
        $heroTitle,
        $heroSub,
        json_encode($posts, JSON_UNESCAPED_UNICODE),
    ]);

    $_SESSION['message'] = 'Güvenli sayfa kaydedildi.';
    $_SESSION['message_type'] = 'success';
    header('Location: safe_page_settings.php');
    exit;
}

$page_title = 'Güvenli sayfa içeriği';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_defaults'])) {
    $d = SafePageService::defaultConfig($pdo);
    $pdo->prepare(
        'UPDATE cloaker_settings SET
            safe_page_brand_name = ?,
            safe_page_meta_title = ?,
            safe_page_meta_description = ?,
            safe_page_hero_title = ?,
            safe_page_hero_subtitle = ?,
            safe_page_posts_json = ?
         WHERE id = 1'
    )->execute([
        $d['brand_name'],
        $d['meta_title'],
        $d['meta_description'],
        $d['hero_title'],
        $d['hero_subtitle'],
        $d['posts_json'],
    ]);
    $_SESSION['message'] = 'Varsayılan blog içeriği yüklendi.';
    $_SESSION['message_type'] = 'success';
    header('Location: safe_page_settings.php');
    exit;
}

$cfg = SafePageService::load($pdo);
$posts = SafePageService::postsFromConfig($cfg);
$previewUrl = app_url('safe-page.php', [], $pdo);

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (! empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-newspaper text-teal"></i> Güvenli sayfa</h1>
            <p class="text-muted small mb-0">Cloaker şüpheli trafiğe gösterilen blog tarzı landing. Meta / Open Graph etiketleri dahil.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt"></i> Önizle</a>
            <a href="cloaker_settings.php" class="btn btn-sm btn-outline-secondary">Cloaker ayarları</a>
        </div>
    </div>

    <form method="post" class="row g-4">
        <input type="hidden" name="save_safe_page" value="1">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Site &amp; meta</div>
                <div class="card-body vstack gap-2">
                    <div>
                        <label class="form-label small">Marka / site adı</label>
                        <input type="text" name="brand_name" class="form-control form-control-sm" maxlength="128"
                            value="<?= htmlspecialchars((string) $cfg['brand_name']) ?>">
                    </div>
                    <div>
                        <label class="form-label small">Meta title</label>
                        <input type="text" name="meta_title" class="form-control form-control-sm" maxlength="255"
                            value="<?= htmlspecialchars((string) $cfg['meta_title']) ?>">
                    </div>
                    <div>
                        <label class="form-label small">Meta description</label>
                        <textarea name="meta_description" class="form-control form-control-sm" rows="2" maxlength="512"><?= htmlspecialchars((string) $cfg['meta_description']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Hero</div>
                <div class="card-body vstack gap-2">
                    <div>
                        <label class="form-label small">Başlık</label>
                        <input type="text" name="hero_title" class="form-control form-control-sm" maxlength="255"
                            value="<?= htmlspecialchars((string) $cfg['hero_title']) ?>">
                    </div>
                    <div>
                        <label class="form-label small">Alt metin</label>
                        <textarea name="hero_subtitle" class="form-control form-control-sm" rows="2" maxlength="512"><?= htmlspecialchars((string) $cfg['hero_subtitle']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Yazı kartları</span>
                    <button type="button" class="btn btn-sm btn-outline-success" id="add-post-row"><i class="fas fa-plus"></i> Satır</button>
                </div>
                <div class="card-body" id="posts-wrap">
                    <?php foreach ($posts as $idx => $post): ?>
                    <div class="border rounded p-3 mb-3 post-row">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label small">Başlık</label>
                                <input type="text" name="post_title[]" class="form-control form-control-sm" maxlength="200"
                                    value="<?= htmlspecialchars($post['title']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Kategori</label>
                                <input type="text" name="post_category[]" class="form-control form-control-sm" maxlength="64"
                                    value="<?= htmlspecialchars($post['category']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Tarih</label>
                                <input type="date" name="post_date[]" class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($post['date']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Özet</label>
                                <textarea name="post_excerpt[]" class="form-control form-control-sm" rows="2" maxlength="600"><?= htmlspecialchars($post['excerpt']) ?></textarea>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger px-0 mt-1 remove-post-row">Kaldır</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
            <button type="submit" name="reset_defaults" value="1" class="btn btn-outline-secondary" onclick="return confirm('Varsayılan içerik yüklensin mi?');">Varsayılana dön</button>
        </div>
    </form>
</div>

<template id="post-row-tpl">
    <div class="border rounded p-3 mb-3 post-row">
        <div class="row g-2">
            <div class="col-md-8">
                <label class="form-label small">Başlık</label>
                <input type="text" name="post_title[]" class="form-control form-control-sm" maxlength="200">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Kategori</label>
                <input type="text" name="post_category[]" class="form-control form-control-sm" maxlength="64" value="Genel">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tarih</label>
                <input type="date" name="post_date[]" class="form-control form-control-sm">
            </div>
            <div class="col-12">
                <label class="form-label small">Özet</label>
                <textarea name="post_excerpt[]" class="form-control form-control-sm" rows="2" maxlength="600"></textarea>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-link text-danger px-0 mt-1 remove-post-row">Kaldır</button>
    </div>
</template>

<script>
(function () {
    var wrap = document.getElementById('posts-wrap');
    var tpl = document.getElementById('post-row-tpl');
    document.getElementById('add-post-row').addEventListener('click', function () {
        if (!tpl || !wrap) return;
        wrap.appendChild(tpl.content.cloneNode(true));
    });
    wrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-post-row')) {
            var row = e.target.closest('.post-row');
            if (row && wrap.querySelectorAll('.post-row').length > 1) row.remove();
        }
    });
})();
</script>

<?php include 'admin_footer_common.php'; ?>
