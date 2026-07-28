<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/landing_service.php';

if (empty($_SESSION['csrf_landing'])) {
    $_SESSION['csrf_landing'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['csrf_landing'];

$flash = '';
$flashType = 'success';

/** Slug'ı benzersiz yap. */
function landing_unique_slug(PDO $pdo, string $base, int $ignoreId = 0): string
{
    $slug = landing_slugify($base);
    $candidate = $slug;
    $i = 2;
    while (true) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM landing_pages WHERE slug = ? AND id <> ?');
        $st->execute([$candidate, $ignoreId]);
        if ((int) $st->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $slug . '-' . $i;
        $i++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $flash = 'Oturum güvenliği doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title === '') {
                    $title = 'Yeni açılış sayfası';
                }
                $slug = landing_unique_slug($pdo, trim((string) ($_POST['slug'] ?? '')) !== '' ? (string) $_POST['slug'] : $title);
                $blocks = json_encode(landing_starter_blocks(), JSON_UNESCAPED_UNICODE);
                $st = $pdo->prepare('INSERT INTO landing_pages (slug, title, status, blocks_json, theme, meta_title) VALUES (?, ?, ?, ?, ?, ?)');
                $st->execute([$slug, $title, 'draft', $blocks, 'aurora', $title]);
                $newId = (int) $pdo->lastInsertId();
                header('Location: landing_edit.php?id=' . $newId);
                exit;
            }
            if ($action === 'delete') {
                $pdo->prepare('DELETE FROM landing_pages WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
                $flash = 'Sayfa silindi.';
            }
            if ($action === 'toggle') {
                $id = (int) ($_POST['id'] ?? 0);
                $cur = $pdo->prepare('SELECT status FROM landing_pages WHERE id = ?');
                $cur->execute([$id]);
                $status = (string) $cur->fetchColumn();
                $next = $status === 'published' ? 'draft' : 'published';
                $pdo->prepare('UPDATE landing_pages SET status = ? WHERE id = ?')->execute([$next, $id]);
                $flash = $next === 'published' ? 'Sayfa yayınlandı.' : 'Sayfa taslağa alındı.';
            }
            if ($action === 'duplicate') {
                $id = (int) ($_POST['id'] ?? 0);
                $src = $pdo->prepare('SELECT * FROM landing_pages WHERE id = ?');
                $src->execute([$id]);
                $row = $src->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $slug = landing_unique_slug($pdo, (string) $row['slug'] . '-kopya');
                    $st = $pdo->prepare('INSERT INTO landing_pages (slug, title, status, blocks_json, theme, meta_title, meta_description, og_image, head_extra, show_pixels) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    $st->execute([
                        $slug,
                        (string) $row['title'] . ' (kopya)',
                        'draft',
                        (string) ($row['blocks_json'] ?? ''),
                        (string) ($row['theme'] ?? 'aurora'),
                        (string) ($row['meta_title'] ?? ''),
                        (string) ($row['meta_description'] ?? ''),
                        (string) ($row['og_image'] ?? ''),
                        (string) ($row['head_extra'] ?? ''),
                        (int) ($row['show_pixels'] ?? 1),
                    ]);
                    $flash = 'Sayfa kopyalandı.';
                }
            }
        } catch (Throwable $e) {
            $flash = 'İşlem başarısız: ' . $e->getMessage();
            $flashType = 'danger';
        }
        if ($flash !== '') {
            $_SESSION['landing_flash'] = $flash;
            $_SESSION['landing_flash_type'] = $flashType;
        }
        header('Location: landing_pages.php');
        exit;
    }
}

if (!empty($_SESSION['landing_flash'])) {
    $flash = (string) $_SESSION['landing_flash'];
    $flashType = (string) ($_SESSION['landing_flash_type'] ?? 'success');
    unset($_SESSION['landing_flash'], $_SESSION['landing_flash_type']);
}

$rows = $pdo->query('SELECT * FROM landing_pages ORDER BY updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Landing sayfalar';
include 'admin_header.php';
?>
<div class="container-fluid py-3">
    <?php if ($flash !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <div class="admin-page-intro mb-3 d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div>
            <h1><i class="fas fa-rocket"></i> Landing sayfalar</h1>
            <p class="lead mb-0">Kampanya ve reklam için blok tabanlı açılış sayfaları oluşturun, yayınlayın.</p>
        </div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fas fa-plus"></i> Yeni sayfa
        </button>
    </div>

    <section class="admin-section-card">
        <div class="admin-section-card__body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Başlık</th>
                            <th>Slug</th>
                            <th>Durum</th>
                            <th class="text-end">Görüntülenme</th>
                            <th>Güncellendi</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Henüz açılış sayfası yok. “Yeni sayfa” ile başlayın.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $isPub = (string) $r['status'] === 'published';
                            $viewUrl = '../landing.php?slug=' . urlencode((string) $r['slug']);
                            ?>
                            <tr>
                                <td>
                                    <a href="landing_edit.php?id=<?= (int) $r['id'] ?>" class="fw-semibold text-decoration-none">
                                        <?= htmlspecialchars((string) $r['title']) ?>
                                    </a>
                                </td>
                                <td><code><?= htmlspecialchars((string) $r['slug']) ?></code></td>
                                <td>
                                    <span class="badge <?= $isPub ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $isPub ? 'Yayında' : 'Taslak' ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= number_format((int) $r['views'], 0, ',', '.') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars((string) $r['updated_at']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a class="btn btn-outline-secondary" href="landing_edit.php?id=<?= (int) $r['id'] ?>" title="Düzenle"><i class="fas fa-pen"></i></a>
                                        <a class="btn btn-outline-info" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener" title="Görüntüle"><i class="fas fa-eye"></i></a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn <?= $isPub ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $isPub ? 'Taslağa al' : 'Yayınla' ?>">
                                                <i class="fas <?= $isPub ? 'fa-eye-slash' : 'fa-cloud-arrow-up' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="action" value="duplicate">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-outline-secondary" title="Kopyala"><i class="fas fa-copy"></i></button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu sayfa kalıcı olarak silinsin mi?');">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-outline-danger" title="Sil"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-rocket"></i> Yeni açılış sayfası</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="lp_title">Başlık</label>
                    <input type="text" class="form-control" id="lp_title" name="title" placeholder="Örn: Yaz Kampanyası" required>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="lp_slug">Slug (opsiyonel)</label>
                    <input type="text" class="form-control" id="lp_slug" name="slug" placeholder="yaz-kampanyasi">
                    <div class="form-text">Boş bırakılırsa başlıktan otomatik üretilir. Adres: <code>landing.php?slug=...</code></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Oluştur ve düzenle</button>
            </div>
        </form>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
