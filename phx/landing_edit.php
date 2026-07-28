<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/landing_service.php';

if (empty($_SESSION['csrf_landing'])) {
    $_SESSION['csrf_landing'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['csrf_landing'];

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM landing_pages WHERE id = ? LIMIT 1');
$st->execute([$id]);
$page = $st->fetch(PDO::FETCH_ASSOC);
if (!$page) {
    header('Location: landing_pages.php');
    exit;
}

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $flash = 'Oturum güvenliği doğrulanamadı.';
        $flashType = 'danger';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = 'Açılış sayfası';
        }
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $slug = landing_slugify($slugInput !== '' ? $slugInput : $title);
        // benzersizlik
        $chk = $pdo->prepare('SELECT COUNT(*) FROM landing_pages WHERE slug = ? AND id <> ?');
        $chk->execute([$slug, $id]);
        if ((int) $chk->fetchColumn() > 0) {
            $slug .= '-' . $id;
        }

        $blocks = landing_sanitize_blocks($_POST['blocks'] ?? []);
        $blocksJson = json_encode(array_values($blocks), JSON_UNESCAPED_UNICODE);

        $theme = (string) ($_POST['theme'] ?? 'aurora');
        if (!isset(landing_theme_registry()[$theme])) {
            $theme = 'aurora';
        }
        $status = ((string) ($_POST['status'] ?? '')) === 'published' ? 'published' : 'draft';

        $pdo->prepare(
            'UPDATE landing_pages SET title = ?, slug = ?, status = ?, blocks_json = ?, theme = ?, meta_title = ?, meta_description = ?, og_image = ?, head_extra = ?, show_pixels = ?, updated_at = NOW() WHERE id = ?'
        )->execute([
            $title,
            $slug,
            $status,
            $blocksJson,
            $theme,
            trim((string) ($_POST['meta_title'] ?? '')),
            trim((string) ($_POST['meta_description'] ?? '')),
            trim((string) ($_POST['og_image'] ?? '')),
            (string) ($_POST['head_extra'] ?? ''),
            isset($_POST['show_pixels']) ? 1 : 0,
            $id,
        ]);

        if (is_file(__DIR__ . '/../includes/cache/page_cache_service.php')) {
            require_once __DIR__ . '/../includes/cache/page_cache_service.php';
            PageCacheService::purgeAll();
        }

        $_SESSION['landing_flash'] = 'Sayfa kaydedildi.';
        $_SESSION['landing_flash_type'] = 'success';
        header('Location: landing_edit.php?id=' . $id);
        exit;
    }
}

if (!empty($_SESSION['landing_flash'])) {
    $flash = (string) $_SESSION['landing_flash'];
    $flashType = (string) ($_SESSION['landing_flash_type'] ?? 'success');
    unset($_SESSION['landing_flash'], $_SESSION['landing_flash_type']);
}

$registry = landing_block_registry();
$themes = landing_theme_registry();
$blocks = landing_decode_blocks((string) ($page['blocks_json'] ?? ''));

$products = [];
try {
    $products = $pdo->query('SELECT product_id, product_name FROM products ORDER BY product_name ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
$forms = [];
try {
    $forms = $pdo->query('SELECT id, title FROM custom_forms WHERE is_active = 1 ORDER BY title ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

/**
 * Tek bir blok alanı için form kontrolü üretir.
 * @param array<string,mixed> $field
 */
function lp_render_field(array $field, string $idxToken, string $value, array $products, array $forms): string
{
    $key = (string) $field['key'];
    $type = (string) ($field['type'] ?? 'text');
    $name = 'blocks[' . $idxToken . '][' . $key . ']';
    $label = htmlspecialchars((string) ($field['label'] ?? $key), ENT_QUOTES, 'UTF-8');
    $ph = htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hint = trim((string) ($field['hint'] ?? ''));
    $val = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $hintHtml = $hint !== '' ? '<div class="form-text small">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</div>' : '';

    $control = '';
    switch ($type) {
        case 'textarea':
            $control = '<textarea class="form-control" name="' . $name . '" rows="3" placeholder="' . $ph . '">' . $val . '</textarea>';
            break;
        case 'lines':
            $control = '<textarea class="form-control font-monospace small" name="' . $name . '" rows="4" placeholder="' . $ph . '">' . $val . '</textarea>';
            break;
        case 'html':
            $control = '<textarea class="form-control font-monospace small" name="' . $name . '" rows="6" placeholder="' . $ph . '">' . $val . '</textarea>';
            break;
        case 'color':
            $cv = $value !== '' ? $val : htmlspecialchars((string) ($field['default'] ?? '#6d28d9'), ENT_QUOTES, 'UTF-8');
            $control = '<input type="color" class="form-control form-control-color" name="' . $name . '" value="' . $cv . '">';
            break;
        case 'number':
            $control = '<input type="number" class="form-control" name="' . $name . '" value="' . $val . '" placeholder="' . $ph . '">';
            break;
        case 'url':
            $control = '<input type="text" class="form-control" name="' . $name . '" value="' . $val . '" placeholder="' . $ph . '">';
            break;
        case 'bool':
            $checked = ($value === '1') ? 'checked' : (($value === '' && ($field['default'] ?? '') === '1') ? 'checked' : '');
            $control = '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" value="1" name="' . $name . '" ' . $checked . '></div>';
            break;
        case 'select':
            $opts = '';
            foreach ((array) ($field['options'] ?? []) as $ov => $ol) {
                $sel = ((string) $ov === $value) ? 'selected' : '';
                $opts .= '<option value="' . htmlspecialchars((string) $ov, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>' . htmlspecialchars((string) $ol, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $control = '<select class="form-select" name="' . $name . '">' . $opts . '</select>';
            break;
        case 'product':
            $opts = '<option value="">— Ürün seçin —</option>';
            foreach ($products as $p) {
                $sel = ((string) $p['product_id'] === $value) ? 'selected' : '';
                $opts .= '<option value="' . (int) $p['product_id'] . '" ' . $sel . '>' . htmlspecialchars((string) $p['product_name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $control = '<select class="form-select" name="' . $name . '">' . $opts . '</select>';
            break;
        case 'form':
            $opts = '<option value="">— Form seçin —</option>';
            foreach ($forms as $f) {
                $sel = ((string) $f['id'] === $value) ? 'selected' : '';
                $opts .= '<option value="' . (int) $f['id'] . '" ' . $sel . '>' . htmlspecialchars((string) $f['title'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $control = '<select class="form-select" name="' . $name . '">' . $opts . '</select>';
            break;
        case 'image':
            $control = '<div class="lp-img-field">'
                . '<div class="input-group input-group-sm">'
                . '<input type="text" class="form-control lp-img-input" name="' . $name . '" value="' . $val . '" placeholder="uploads/ornek.jpg">'
                . '<button type="button" class="btn btn-outline-secondary lp-upload-btn"><i class="fas fa-upload"></i> Yükle</button>'
                . '</div>'
                . '<div class="lp-img-preview mt-2">' . ($value !== '' ? '<img src="../' . htmlspecialchars(ltrim($value, "/"), ENT_QUOTES, "UTF-8") . '" alt="">' : '') . '</div>'
                . '</div>';
            break;
        default:
            $control = '<input type="text" class="form-control" name="' . $name . '" value="' . $val . '" placeholder="' . $ph . '">';
    }

    $isFull = in_array($type, ['textarea', 'lines', 'html', 'image'], true);
    $cls = $isFull ? 'lp-field lp-field--full' : 'lp-field';
    return '<div class="' . $cls . '"><label class="form-label small fw-semibold">' . $label . '</label>' . $control . $hintHtml . '</div>';
}

/**
 * Tek bir blok kartı (mevcut veya şablon) üretir.
 * @param array<string,string> $data
 */
function lp_render_block_card(string $type, array $data, string $idxToken, array $registry, array $products, array $forms): string
{
    $def = $registry[$type] ?? null;
    if (!$def) {
        return '';
    }
    $fields = '';
    foreach ($def['fields'] as $field) {
        $fields .= lp_render_field($field, $idxToken, (string) ($data[$field['key']] ?? ''), $products, $forms);
    }
    $icon = htmlspecialchars((string) $def['icon'], ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars((string) $def['label'], ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars((string) $def['desc'], ENT_QUOTES, 'UTF-8');
    return '<div class="lp-block" data-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="blocks[' . $idxToken . '][type]" value="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">'
        . '<div class="lp-block__head">'
        . '<div class="lp-block__title"><span class="lp-block__ic"><i class="fas ' . $icon . '"></i></span>'
        . '<div><strong>' . $label . '</strong><div class="lp-block__desc">' . $desc . '</div></div></div>'
        . '<div class="lp-block__tools">'
        . '<button type="button" class="btn btn-sm btn-light lp-move-up" title="Yukarı"><i class="fas fa-arrow-up"></i></button>'
        . '<button type="button" class="btn btn-sm btn-light lp-move-down" title="Aşağı"><i class="fas fa-arrow-down"></i></button>'
        . '<button type="button" class="btn btn-sm btn-outline-danger lp-remove" title="Sil"><i class="fas fa-trash"></i></button>'
        . '</div></div>'
        . '<div class="lp-block__body">' . $fields . '</div>'
        . '</div>';
}

$page_title = 'Landing — ' . (string) $page['title'];
include 'admin_header.php';
?>
<div class="container-fluid py-3 landing-editor">
    <?php if ($flash !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="landing_pages.php">Landing sayfalar</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars((string) $page['title']) ?></li>
        </ol>
    </nav>

    <form method="post" id="landingForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="admin-page-intro mb-0">
                <h1 class="h4 mb-1"><i class="fas fa-rocket"></i> <?= htmlspecialchars((string) $page['title']) ?></h1>
                <p class="small text-muted mb-0">Adres: <code>landing.php?slug=<?= htmlspecialchars((string) $page['slug']) ?></code></p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-info" href="../landing.php?slug=<?= urlencode((string) $page['slug']) ?>&preview=1" target="_blank" rel="noopener"><i class="fas fa-eye"></i> Önizle</a>
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Kaydet</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="admin-section-card mb-3">
                    <div class="admin-section-card__head d-flex justify-content-between align-items-center">
                        <h2 class="admin-section-card__title mb-0"><i class="fas fa-layer-group"></i> Bloklar</h2>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-plus"></i> Blok ekle
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($registry as $bt => $def): ?>
                                    <li>
                                        <button class="dropdown-item lp-add-block" type="button" data-type="<?= htmlspecialchars($bt) ?>">
                                            <i class="fas <?= htmlspecialchars((string) $def['icon']) ?> fa-fw"></i> <?= htmlspecialchars((string) $def['label']) ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="admin-section-card__body">
                        <div id="lpBlocks">
                            <?php $i = 0; foreach ($blocks as $b): ?>
                                <?= lp_render_block_card((string) $b['type'], $b['data'], (string) $i, $registry, $products, $forms) ?>
                                <?php $i++; endforeach; ?>
                        </div>
                        <div id="lpEmpty" class="text-center text-muted py-4 <?= $blocks ? 'd-none' : '' ?>">
                            Henüz blok yok. Yukarıdaki <strong>“Blok ekle”</strong> ile başlayın.
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="admin-section-card mb-3">
                    <div class="admin-section-card__head"><h2 class="admin-section-card__title"><i class="fas fa-sliders"></i> Yayın</h2></div>
                    <div class="admin-section-card__body">
                        <div class="admin-field mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" value="<?= htmlspecialchars((string) $page['title']) ?>">
                        </div>
                        <div class="admin-field mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" name="slug" value="<?= htmlspecialchars((string) $page['slug']) ?>">
                        </div>
                        <div class="admin-field mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <option value="draft" <?= (string) $page['status'] !== 'published' ? 'selected' : '' ?>>Taslak</option>
                                <option value="published" <?= (string) $page['status'] === 'published' ? 'selected' : '' ?>>Yayında</option>
                            </select>
                        </div>
                        <div class="admin-field mb-3">
                            <label class="form-label">Tema</label>
                            <select class="form-select" name="theme">
                                <?php foreach ($themes as $tk => $tv): ?>
                                    <option value="<?= htmlspecialchars($tk) ?>" <?= (string) $page['theme'] === $tk ? 'selected' : '' ?>><?= htmlspecialchars((string) $tv['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="show_pixels" id="show_pixels" <?= (int) ($page['show_pixels'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_pixels">Dönüşüm pikselleri / analitik kodları</label>
                        </div>
                    </div>
                </section>

                <section class="admin-section-card mb-3">
                    <div class="admin-section-card__head"><h2 class="admin-section-card__title"><i class="fas fa-magnifying-glass"></i> SEO & paylaşım</h2></div>
                    <div class="admin-section-card__body">
                        <div class="admin-field mb-3">
                            <label class="form-label">Meta başlık</label>
                            <input type="text" class="form-control" name="meta_title" value="<?= htmlspecialchars((string) ($page['meta_title'] ?? '')) ?>">
                        </div>
                        <div class="admin-field mb-3">
                            <label class="form-label">Meta açıklama</label>
                            <textarea class="form-control" name="meta_description" rows="2"><?= htmlspecialchars((string) ($page['meta_description'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label class="form-label">OG görsel (paylaşım)</label>
                            <div class="lp-img-field">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control lp-img-input" name="og_image" value="<?= htmlspecialchars((string) ($page['og_image'] ?? '')) ?>" placeholder="uploads/og.jpg">
                                    <button type="button" class="btn btn-outline-secondary lp-upload-btn"><i class="fas fa-upload"></i></button>
                                </div>
                                <div class="lp-img-preview mt-2"><?php $og = trim((string) ($page['og_image'] ?? '')); if ($og !== ''): ?><img src="../<?= htmlspecialchars(ltrim($og, '/')) ?>" alt=""><?php endif; ?></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-section-card">
                    <div class="admin-section-card__head"><h2 class="admin-section-card__title"><i class="fas fa-code"></i> Ek head kodu</h2></div>
                    <div class="admin-section-card__body">
                        <textarea class="form-control font-monospace small" name="head_extra" rows="4" placeholder="<script>…</script>"><?= htmlspecialchars((string) ($page['head_extra'] ?? '')) ?></textarea>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>

<?php foreach ($registry as $bt => $def): ?>
    <template id="lp-tpl-<?= htmlspecialchars($bt) ?>">
        <?= lp_render_block_card($bt, [], '__IDX__', $registry, $products, $forms) ?>
    </template>
<?php endforeach; ?>

<input type="file" id="lpFileInput" accept="image/*" style="display:none">
<script>
(function () {
    var container = document.getElementById('lpBlocks');
    var empty = document.getElementById('lpEmpty');
    var counter = <?= (int) count($blocks) ?>;
    var CSRF = <?= json_encode($csrf) ?>;

    function refreshEmpty() {
        if (!empty) return;
        empty.classList.toggle('d-none', container.children.length > 0);
    }

    document.querySelectorAll('.lp-add-block').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-type');
            var tpl = document.getElementById('lp-tpl-' + type);
            if (!tpl) return;
            var html = tpl.innerHTML.replace(/__IDX__/g, 'n' + (counter++));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var node = wrap.firstElementChild;
            container.appendChild(node);
            refreshEmpty();
            node.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    container.addEventListener('click', function (e) {
        var block = e.target.closest('.lp-block');
        if (!block) return;
        if (e.target.closest('.lp-remove')) {
            if (confirm('Bu blok silinsin mi?')) { block.remove(); refreshEmpty(); }
        } else if (e.target.closest('.lp-move-up')) {
            var prev = block.previousElementSibling;
            if (prev) container.insertBefore(block, prev);
        } else if (e.target.closest('.lp-move-down')) {
            var next = block.nextElementSibling;
            if (next) container.insertBefore(next, block);
        }
    });

    // Görsel yükleme
    var fileInput = document.getElementById('lpFileInput');
    var activeField = null;
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.lp-upload-btn');
        if (!btn) return;
        activeField = btn.closest('.lp-img-field');
        fileInput.value = '';
        fileInput.click();
    });
    fileInput.addEventListener('change', function () {
        if (!fileInput.files || !fileInput.files[0] || !activeField) return;
        var fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('image', fileInput.files[0]);
        var input = activeField.querySelector('.lp-img-input');
        var prev = activeField.querySelector('.lp-img-preview');
        if (prev) prev.innerHTML = '<span class="text-muted small">Yükleniyor…</span>';
        fetch('landing_upload.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.ok && res.path) {
                    if (input) input.value = res.path;
                    if (prev) prev.innerHTML = '<img src="../' + res.path.replace(/^\/+/, '') + '" alt="">';
                } else {
                    if (prev) prev.innerHTML = '<span class="text-danger small">' + ((res && res.error) || 'Yükleme başarısız') + '</span>';
                }
            })
            .catch(function () { if (prev) prev.innerHTML = '<span class="text-danger small">Ağ hatası</span>'; });
    });

    // Görsel yolu yazıldığında önizleme
    document.addEventListener('input', function (e) {
        if (!e.target.classList.contains('lp-img-input')) return;
        var field = e.target.closest('.lp-img-field');
        var prev = field && field.querySelector('.lp-img-preview');
        if (!prev) return;
        var v = e.target.value.trim();
        prev.innerHTML = v ? '<img src="../' + v.replace(/^\/+/, '') + '" alt="">' : '';
    });

    refreshEmpty();
})();
</script>

<?php include 'admin_footer_common.php'; ?>
