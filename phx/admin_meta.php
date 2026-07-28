<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/site_helpers.php';
require_once dirname(__DIR__) . '/includes/page_seo.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$allPages = array_keys(page_seo_default_titles());
$allPages[] = 'dinamik_form.php';
$allPages = array_values(array_unique($allPages));

$pageLabels = [
    'index.php' => 'Ana Sayfa',
    'order.php' => 'Sipariş',
    'thankyou.php' => 'Teşekkür',
    'sorgula.php' => 'Sipariş Sorgula',
    'destek_talebi.php' => 'Destek Talebi',
    'error.php' => 'Hata',
    'hakkimizda.php' => 'Hakkımızda',
    'bayilik.php' => 'Bayilik',
    'kargo_sureci.php' => 'Kargo Süreci',
    'iletisim.php' => 'İletişim',
    'sss.php' => 'SSS (Müşteri)',
    'kvkk.php' => 'KVKK',
    'mesafeli_satis.php' => 'Mesafeli Satış',
    'iade_degisim.php' => 'İade & Değişim',
    'dinamik_form.php' => 'Dinamik Form',
];

$detectedUrl = site_url_detect_from_request();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save_page');

    if ($action === 'save_site_name') {
        $siteName = mb_substr(trim((string) ($_POST['site_name'] ?? '')), 0, 255);
        if ($siteName === '') {
            $_SESSION['message'] = 'Site adı boş olamaz.';
            $_SESSION['message_type'] = 'error';
        } else {
            try {
                $pdo->prepare('UPDATE settings SET site_name = ? WHERE id = 1')->execute([$siteName]);
                $_SESSION['message'] = 'Site adı (settings) güncellendi.';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Hata: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        }
        header('Location: admin_meta.php');
        exit;
    }

    if ($action === 'save_site_url' || $action === 'detect_site_url') {
        if ($action === 'detect_site_url') {
            $site_url = $detectedUrl;
        } else {
            $site_url = site_url_normalize(trim($_POST['site_url'] ?? ''));
        }
        if ($site_url === '' || !site_url_is_valid($site_url)) {
            $_SESSION['message'] = 'Geçerli bir site URL giriniz.';
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE settings SET site_url = ? WHERE id = 1');
            $stmt->execute([$site_url]);
            $_SESSION['message'] = $action === 'detect_site_url'
                ? 'Site URL otomatik algılandı ve kaydedildi.'
                : 'Site URL güncellendi.';
            $_SESSION['message_type'] = 'success';
        }
        header('Location: admin_meta.php');
        exit;
    }

    $page_name = trim((string) ($_POST['page_name'] ?? ''));
    if (!in_array($page_name, $allPages, true)) {
        $_SESSION['message'] = 'Geçersiz sayfa seçimi.';
        $_SESSION['message_type'] = 'error';
        header('Location: admin_meta.php');
        exit;
    }

    $page_title = trim($_POST['page_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');
    $head_content = trim($_POST['head_content'] ?? '');
    $body_content = trim($_POST['body_content'] ?? '');

    if ($page_title === '') {
        $_SESSION['message'] = 'Sayfa başlığı zorunludur.';
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO page_meta (page_name, page_title, meta_description, meta_keywords, head_content, body_content)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    page_title = VALUES(page_title),
                    meta_description = VALUES(meta_description),
                    meta_keywords = VALUES(meta_keywords),
                    head_content = VALUES(head_content),
                    body_content = VALUES(body_content)'
            );
            $stmt->execute([$page_name, $page_title, $meta_description, $meta_keywords, $head_content, $body_content]);
            $_SESSION['message'] = 'SEO ayarları kaydedildi: ' . ($pageLabels[$page_name] ?? $page_name);
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }

    header('Location: admin_meta.php?page=' . rawurlencode($page_name));
    exit;
}

$selectedPage = trim((string) ($_GET['page'] ?? 'index.php'));
if (!in_array($selectedPage, $allPages, true)) {
    $selectedPage = 'index.php';
}

$stmt = $pdo->prepare('SELECT * FROM page_meta WHERE page_name = ? LIMIT 1');
$stmt->execute([$selectedPage]);
$meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'page_title' => page_seo_default_titles()[$selectedPage] ?? '',
    'meta_description' => '',
    'meta_keywords' => '',
    'head_content' => '',
    'body_content' => '',
];

$site_url = site_url_normalize((string) ($pdo->query('SELECT site_url FROM settings WHERE id = 1')->fetchColumn() ?: ''));
$site_name = trim((string) ($pdo->query('SELECT site_name FROM settings WHERE id = 1')->fetchColumn() ?: ''));
$urlAutoSaved = false;
if ($site_url === '' && site_url_is_valid($detectedUrl)) {
    $site_url = site_url_ensure_from_request($pdo);
    $urlAutoSaved = true;
}

$vitrinPreview = site_public_vitrin_href($pdo);
$selectedLabel = $pageLabels[$selectedPage] ?? $selectedPage;
$descLen = mb_strlen(trim((string) ($meta['meta_description'] ?? '')));

$page_title = 'SEO & Meta Yönetimi';
include 'admin_header.php';
?>

<div class="container-fluid py-3 admin-meta-page">
    <div class="admin-page-intro d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-search text-primary"></i> SEO &amp; Meta Yönetimi</h1>
            <p class="lead mb-0">Sayfa bazlı title, açıklama ve anahtar kelimeler. Canonical adres site URL ayarından gelir.</p>
        </div>
        <a href="<?= htmlspecialchars($vitrinPreview, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-external-link-alt"></i> Siteyi Gör
        </a>
    </div>

    <?php if ($urlAutoSaved): ?>
        <div class="alert alert-success py-2 small mb-3">
            <i class="fas fa-magic me-1"></i> Site URL boştu; otomatik algılandı: <strong><?= htmlspecialchars($site_url) ?></strong>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?> py-2 mb-3">
            <?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="admin-meta-layout">
        <aside class="admin-meta-sidebar">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-tag text-primary me-1"></i> Site adı</div>
                <div class="card-body">
                    <form method="POST" class="admin-meta-url-form">
                        <input type="hidden" name="action" value="save_site_name">
                        <label for="site_name" class="form-label small fw-semibold mb-1">Marka / site adı</label>
                        <input type="text" class="form-control form-control-sm mb-2" id="site_name" name="site_name"
                               value="<?= htmlspecialchars($site_name) ?>"
                               placeholder="Poloyaka Tişörtler" required maxlength="255">
                        <div class="form-text mb-2"><code>settings.site_name</code> — bildirimler, og:site_name ve kurulum başlığı.</div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Kaydet</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-globe text-primary me-1"></i> Site URL</div>
                <div class="card-body">
                    <form method="POST" class="admin-meta-url-form">
                        <input type="hidden" name="action" value="save_site_url">
                        <label for="site_url" class="form-label small fw-semibold mb-1">Canonical taban URL</label>
                        <input type="url" class="form-control form-control-sm mb-2" id="site_url" name="site_url"
                               value="<?= htmlspecialchars($site_url) ?>"
                               placeholder="<?= htmlspecialchars($detectedUrl) ?>" required>
                        <div class="form-text mb-2">SEO, sipariş kaynağı ve “Siteyi Gör” linki bu adrese göre çalışır.</div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Kaydet</button>
                            <button type="submit" name="action" value="detect_site_url" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sync"></i> Adresten al
                            </button>
                        </div>
                    </form>
                    <div class="small text-muted mt-3 pt-2 border-top">
                        Algılanan: <code class="user-select-all"><?= htmlspecialchars($detectedUrl) ?></code>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-file-alt text-primary me-1"></i> Sayfa seç</div>
                <nav class="list-group list-group-flush admin-meta-page-nav">
                    <?php foreach ($allPages as $pg): ?>
                        <?php $isActive = ($pg === $selectedPage); ?>
                        <a href="admin_meta.php?page=<?= rawurlencode($pg) ?>"
                           class="list-group-item list-group-item-action<?= $isActive ? ' active' : '' ?>">
                            <span class="d-block fw-semibold"><?= htmlspecialchars($pageLabels[$pg] ?? $pg) ?></span>
                            <span class="admin-meta-page-slug"><?= htmlspecialchars($pg) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </aside>

        <main class="admin-meta-main">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold"><i class="fas fa-edit text-primary me-1"></i> <?= htmlspecialchars($selectedLabel) ?></span>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($selectedPage) ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" class="admin-meta-seo-form">
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="page_name" value="<?= htmlspecialchars($selectedPage) ?>">

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="page_title" class="form-label">Sayfa başlığı (Title)</label>
                                <input type="text" class="form-control" id="page_title" name="page_title"
                                       value="<?= htmlspecialchars((string) ($meta['page_title'] ?? '')) ?>" required maxlength="255">
                            </div>
                            <div class="col-12">
                                <label for="meta_description" class="form-label">Meta description</label>
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="512"><?= htmlspecialchars((string) ($meta['meta_description'] ?? '')) ?></textarea>
                                <div class="form-text"><span id="metaDescCount"><?= (int) $descLen ?></span> / 512 karakter (önerilen: 120–160)</div>
                            </div>
                            <div class="col-12">
                                <label for="meta_keywords" class="form-label">Meta keywords</label>
                                <input type="text" class="form-control" id="meta_keywords" name="meta_keywords"
                                       value="<?= htmlspecialchars((string) ($meta['meta_keywords'] ?? '')) ?>" maxlength="512"
                                       placeholder="online alışveriş, kapıda ödeme, hızlı kargo">
                            </div>
                            <div class="col-12">
                                <label for="head_content" class="form-label">Ek head kodu</label>
                                <textarea class="form-control font-monospace small" id="head_content" name="head_content" rows="4"><?= htmlspecialchars((string) ($meta['head_content'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label for="body_content" class="form-label">Ek body kodu</label>
                                <textarea class="form-control font-monospace small" id="body_content" name="body_content" rows="3"><?= htmlspecialchars((string) ($meta['body_content'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> SEO kaydet</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info small mt-3 mb-0">
                KVKK, mesafeli satış, iade ve SSS metinleri genel şablondan gelir; buradan yalnızca SEO alanları özelleştirilir.
            </div>
        </main>
    </div>
</div>

<script>
(function () {
    var ta = document.getElementById('meta_description');
    var cnt = document.getElementById('metaDescCount');
    if (!ta || !cnt) return;
    ta.addEventListener('input', function () { cnt.textContent = String(ta.value.length); });
})();
</script>

<?php include 'admin_footer_common.php'; ?>
