<?php
declare(strict_types=1);

require 'db.php';
require_once 'tracking.php';
require_once __DIR__ . '/includes/landing_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Europe/Istanbul');

$slug = strtolower(trim((string) ($_GET['slug'] ?? $_GET['s'] ?? '')));
if ($slug !== '' && !preg_match('/^[a-z0-9][a-z0-9\-]{0,158}$/', $slug)) {
    http_response_code(400);
    $slug = '';
}

$isAdminPreview = isset($_SESSION['user_id']) && !empty($_GET['preview']);

$page = null;
if ($slug !== '') {
    try {
        $st = $pdo->prepare('SELECT * FROM landing_pages WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $page = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $page = null;
    }
}

$published = $page && (string) ($page['status'] ?? '') === 'published';
if (!$page || (!$published && !$isAdminPreview)) {
    http_response_code(404);
    $page = null;
}

if ($page && !$isAdminPreview) {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    try {
        $pdo->prepare('INSERT INTO page_views (page_name, ip_address, visit_time) VALUES (?, ?, ?)')
            ->execute(['landing:' . $slug, $ip, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
    }
    try {
        $pdo->prepare('UPDATE landing_pages SET views = views + 1 WHERE id = ?')->execute([(int) $page['id']]);
    } catch (Throwable $e) {
    }
}

$themes = landing_theme_registry();
$themeKey = $page ? (string) ($page['theme'] ?? 'aurora') : 'aurora';
$theme = $themes[$themeKey] ?? $themes['aurora'];
$themeVars = '';
foreach ($theme['vars'] as $k => $v) {
    $themeVars .= $k . ':' . $v . ';';
}

$metaTitle = $page ? (trim((string) ($page['meta_title'] ?? '')) !== '' ? (string) $page['meta_title'] : (string) $page['title']) : 'Sayfa bulunamadı';
$metaDesc = $page ? trim((string) ($page['meta_description'] ?? '')) : '';
$ogImage = $page ? trim((string) ($page['og_image'] ?? '')) : '';
$ogImageUrl = $ogImage !== '' ? site_media_public_src($ogImage, $pdo) : '';
$showPixels = $page ? (int) ($page['show_pixels'] ?? 1) === 1 : false;

$blocks = $page ? landing_decode_blocks((string) ($page['blocks_json'] ?? '')) : [];
$blocksHtml = $page ? landing_render_blocks($pdo, $blocks) : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= landing_e($metaTitle) ?></title>
    <?php if ($metaDesc !== ''): ?>
        <meta name="description" content="<?= landing_e($metaDesc) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= landing_e($metaTitle) ?>">
    <?php if ($metaDesc !== ''): ?><meta property="og:description" content="<?= landing_e($metaDesc) ?>"><?php endif; ?>
    <?php if ($ogImageUrl !== ''): ?><meta property="og:image" content="<?= landing_e($ogImageUrl) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= landing_e(app_url('css/landing.css')) ?>" rel="stylesheet">
    <?php if ($showPixels): require __DIR__ . '/includes/site_tracking_head.php'; endif; ?>
    <?php if ($page && trim((string) ($page['head_extra'] ?? '')) !== ''): ?>
        <?= $page['head_extra'] ?>
    <?php endif; ?>
</head>
<body class="lp-body lp-theme--<?= landing_e($themeKey) ?>" style="<?= $themeVars ?>">
<?php if ($isAdminPreview): ?>
    <div class="lp-preview-bar">Önizleme modu — bu sayfa yayında olmasa bile size görünür. <a href="<?= landing_e(app_url('phx/landing_edit.php?id=' . (int) $page['id'])) ?>">Düzenle</a></div>
<?php endif; ?>

<?php if (!$page): ?>
    <div class="lp-notfound">
        <h1>404</h1>
        <p>Aradığınız açılış sayfası bulunamadı ya da yayında değil.</p>
        <a class="lp-btn lp-btn--primary" href="<?= landing_e(app_url('index.php')) ?>">Ana sayfaya dön</a>
    </div>
<?php else: ?>
    <main class="lp-main">
        <?= $blocksHtml ?>
    </main>
    <footer class="lp-footer">
        <div class="lp-wrap">
            <p>© <?= date('Y') ?> · Tüm hakları saklıdır.</p>
        </div>
    </footer>
<?php endif; ?>
</body>
</html>
