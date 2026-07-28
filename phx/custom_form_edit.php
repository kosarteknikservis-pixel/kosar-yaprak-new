<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM custom_forms WHERE id = ?');
$st->execute([$id]);
$form = $st->fetch(PDO::FETCH_ASSOC);
if (!$form) {
    $_SESSION['message'] = 'Form bulunamadı.';
    $_SESSION['message_type'] = 'danger';
    header('Location: custom_forms.php');
    exit;
}

$types = ['text', 'textarea', 'email', 'tel', 'number', 'select', 'checkbox'];

function valid_field_key(string $k): bool
{
    return (bool) preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $k);
}

function valid_slug(string $s): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9\-]{1,126}$/i', $s);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['save_meta'])) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $msg = trim((string) ($_POST['success_message'] ?? ''));
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $showMenu = !empty($_POST['show_in_menu']) ? 1 : 0;
        $menuSort = (int) ($_POST['menu_sort'] ?? 50);
        $menuLabel = trim((string) ($_POST['menu_label'] ?? ''));
        if ($title === '' || !valid_slug($slug)) {
            $_SESSION['message'] = 'Başlık ve geçerli bir slug gerekli (küçük harf, rakam, tire).';
            $_SESSION['message_type'] = 'danger';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE custom_forms SET title = ?, slug = ?, success_message = ?, is_active = ?, show_in_menu = ?, menu_sort = ?, menu_label = ? WHERE id = ?'
                )->execute([
                    mb_substr($title, 0, 255),
                    mb_substr($slug, 0, 128),
                    mb_substr($msg, 0, 512),
                    $active,
                    $showMenu,
                    $menuSort,
                    $menuLabel !== '' ? mb_substr($menuLabel, 0, 255) : null,
                    $id,
                ]);
                $_SESSION['message'] = 'Form kaydedildi.';
                $_SESSION['message_type'] = 'success';
            } catch (Throwable $e) {
                $_SESSION['message'] = 'Slug başka bir formda kullanılıyor olabilir.';
                $_SESSION['message_type'] = 'danger';
            }
        }
        header('Location: custom_form_edit.php?id=' . $id);
        exit;
    }

    if (!empty($_POST['add_field'])) {
        $fk = trim((string) ($_POST['field_key'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $ft = strtolower(trim((string) ($_POST['field_type'] ?? 'text')));
        $opt = (string) ($_POST['options_text'] ?? '');
        $req = !empty($_POST['is_required']) ? 1 : 0;
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if (!valid_field_key($fk) || $label === '') {
            $_SESSION['message'] = 'Alan anahtarı (ör. ad_soyad) ve etiket zorunlu.';
            $_SESSION['message_type'] = 'danger';
        } elseif (!in_array($ft, ['text', 'textarea', 'email', 'tel', 'number', 'select', 'checkbox'], true)) {
            $_SESSION['message'] = 'Geçersiz alan tipi.';
            $_SESSION['message_type'] = 'danger';
        } else {
            $pdo->prepare(
                'INSERT INTO custom_form_fields (form_id, field_key, label, field_type, options_text, is_required, sort_order)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$id, mb_substr($fk, 0, 64), mb_substr($label, 0, 255), $ft, $opt !== '' ? $opt : null, $req, $sort]);
            $_SESSION['message'] = 'Alan eklendi.';
            $_SESSION['message_type'] = 'success';
        }
        header('Location: custom_form_edit.php?id=' . $id);
        exit;
    }

    if (!empty($_POST['delete_field'])) {
        $fid = (int) $_POST['delete_field'];
        if ($fid > 0) {
            $pdo->prepare('DELETE FROM custom_form_fields WHERE id = ? AND form_id = ?')->execute([$fid, $id]);
            $_SESSION['message'] = 'Alan silindi.';
            $_SESSION['message_type'] = 'success';
        }
        header('Location: custom_form_edit.php?id=' . $id);
        exit;
    }
}

$st = $pdo->prepare(
    'SELECT * FROM custom_form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC'
);
$st->execute([$id]);
$fields = $st->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Form düzenle';
include 'admin_header.php';
$url = '../dinamik_form.php?f=' . rawurlencode((string) ($form['slug'] ?? ''));
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $isErr = in_array($mtp, ['error', 'danger'], true); ?>
        <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <nav class="small mb-2"><a href="custom_forms.php">← Başvuru formları</a></nav>
    <h1 class="h5 mb-3"><?= htmlspecialchars((string) ($form['title'] ?? '')) ?></h1>

    <div class="mb-4 p-3 bg-light rounded small">
        <strong>Vitrin linki:</strong>
        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($url) ?></a>
    </div>

    <form method="post" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6">Form ayarları</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Başlık</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars((string) ($form['title'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug (URL)</label>
                    <input type="text" name="slug" class="form-control font-monospace" pattern="[a-z0-9\-]+" value="<?= htmlspecialchars((string) ($form['slug'] ?? '')) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Başarı mesajı</label>
                    <input type="text" name="success_message" class="form-control" value="<?= htmlspecialchars((string) ($form['success_message'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="act" <?= !empty((int) ($form['is_active'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="act">Form aktif</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_in_menu" id="show_menu" <?= !empty((int) ($form['show_in_menu'] ?? 0)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_menu">Site menüsünde göster (üst / yan menü)</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Menü sırası</label>
                    <input type="number" name="menu_sort" class="form-control" value="<?= (int) ($form['menu_sort'] ?? 50) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Menü etiketi (boşsa form başlığı)</label>
                    <input type="text" name="menu_label" class="form-control" value="<?= htmlspecialchars((string) ($form['menu_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="255" placeholder="Örn: Bayilik başvurusu">
                </div>
                <div class="col-12">
                    <button type="submit" name="save_meta" value="1" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </form>

    <h2 class="h6 mb-3">Alanlar</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm">
            <thead><tr><th>Sıra</th><th>Anahtar</th><th>Etiket</th><th>Tip</th><th>Zor.</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($fields as $ff): ?>
                <tr>
                    <td><?= (int) $ff['sort_order'] ?></td>
                    <td><code><?= htmlspecialchars((string) $ff['field_key']) ?></code></td>
                    <td><?= htmlspecialchars((string) $ff['label']) ?></td>
                    <td><?= htmlspecialchars((string) $ff['field_type']) ?></td>
                    <td><?= !empty((int) $ff['is_required']) ? 'evet' : 'hayır' ?></td>
                    <td>
                        <form method="post" class="d-inline" onsubmit="return confirm('Bu alanı silmek istiyor musunuz?');">
                            <input type="hidden" name="delete_field" value="<?= (int) $ff['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                        </form>
                    </td>
                </tr>
                <?php if (!empty((string) ($ff['options_text'] ?? ''))): ?>
                    <tr><td colspan="6" class="small text-muted"><?= nl2br(htmlspecialchars((string) $ff['options_text'])) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h6">Yeni alan ekle</h2>
            <form method="post" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="field_key" class="form-control form-control-sm" placeholder="Alan anahtarı (ad_soyad)" required pattern="[A-Za-z][A-Za-z0-9_]*">
                </div>
                <div class="col-md-5">
                    <input type="text" name="label" class="form-control form-control-sm" placeholder="Görünen etiket" required>
                </div>
                <div class="col-md-3">
                    <select name="field_type" class="form-select form-select-sm">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <textarea name="options_text" class="form-control form-control-sm" rows="2" placeholder="select için: her satırda bir seçenek"></textarea>
                </div>
                <div class="col-md-2">
                    <input type="number" name="sort_order" class="form-control form-control-sm" value="0">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="is_required" id="rq">
                        <label class="form-check-label small" for="rq">Zorunlu</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" name="add_field" value="1" class="btn btn-success btn-sm">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
