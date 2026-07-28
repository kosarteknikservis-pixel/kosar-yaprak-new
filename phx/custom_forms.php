<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/admin_cc_helpers.php';
require_once __DIR__ . '/../includes/app_url.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['create_blank'])) {
        $slug = 'form-' . bin2hex(random_bytes(6));
        $pdo->prepare('INSERT INTO custom_forms (title, slug) VALUES (?, ?)')
            ->execute(['Yeni başvuru formu', $slug]);
        $newId = (int) $pdo->lastInsertId();
        $_SESSION['message'] = 'Form oluşturuldu. Slug ve alanları düzenleyin.';
        $_SESSION['message_type'] = 'success';
        header('Location: custom_form_edit.php?id=' . $newId);

        exit;
    }
    if (!empty($_POST['delete_form_id'])) {
        $fid = (int) $_POST['delete_form_id'];
        if ($fid > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM custom_form_entries WHERE form_id = ?')->execute([$fid]);
                $pdo->prepare('DELETE FROM custom_form_fields WHERE form_id = ?')->execute([$fid]);
                $pdo->prepare('DELETE FROM custom_forms WHERE id = ?')->execute([$fid]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            $_SESSION['message'] = 'Form silindi.';
            $_SESSION['message_type'] = 'success';
        }
        header('Location: custom_forms.php');
        exit;
    }
}

$forms = $pdo->query(
    'SELECT f.id, f.title, f.slug, f.is_active,
     (SELECT COUNT(*) FROM custom_form_entries e WHERE e.form_id = f.id) AS cnt,
     (SELECT COUNT(*) FROM custom_form_entries e WHERE e.form_id = f.id AND DATE(e.created_at) = CURDATE()) AS cnt_today
     FROM custom_forms f ORDER BY f.id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$recentEntries = [];
try {
    $recentEntries = $pdo->query(
        'SELECT e.id, e.created_at, e.payload_json, e.ip, f.id AS form_id, f.title AS form_title
         FROM custom_form_entries e
         JOIN custom_forms f ON e.form_id = f.id
         ORDER BY e.id DESC LIMIT 15'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentEntries = [];
}

$totalEntriesToday = (int) $pdo->query(
    'SELECT COUNT(*) FROM custom_form_entries WHERE DATE(created_at) = CURDATE()'
)->fetchColumn();

$page_title = 'Başvuru formları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $isErr = in_array($mtp, ['error', 'danger'], true); ?>
        <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-wpforms text-primary"></i> Dinamik başvuru formları</h1>
            <p class="text-muted small mb-0">Vitrin adresi: <code>dinamik_form?f=<em>slug</em></code> — bugün <strong><?= $totalEntriesToday ?></strong> yeni başvuru</p>
        </div>
        <form method="post" class="m-0">
            <button type="submit" name="create_blank" value="1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Yeni form</button>
        </form>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-book-open text-primary me-1"></i> Nasıl kullanılır?</div>
        <div class="card-body small text-muted">
            <ol class="mb-3 ps-3">
                <li class="mb-2"><strong>Yeni form</strong> butonuna tıklayın; otomatik bir slug oluşur (ör. <code>form-a1b2c3</code>).</li>
                <li class="mb-2"><strong>Alanlar</strong> sayfasından soruları ekleyin: metin, telefon, e-posta, onay kutusu vb. Zorunlu alanları işaretleyin.</li>
                <li class="mb-2">Formu <strong>Aktif</strong> yapın. İsterseniz <strong>Menüde göster</strong> ile vitrin menüsüne ekleyin (menü etiketi ve sıra ayarlanır).</li>
                <li class="mb-2">Müşteri vitrinde formu doldurur; kayıtlar <strong>Gönderilenler</strong> ekranında listelenir (telefon, IP, tarih).</li>
                <li class="mb-0">Örnek: <code><?= htmlspecialchars(app_url('dinamik_form', ['f' => 'slug'], $pdo)) ?></code></li>
            </ol>
        </div>
    </div>

    <?php if ($recentEntries !== []): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-inbox text-primary me-1"></i> Son başvurular (tüm formlar)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 cc-table-compact">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Form</th>
                            <th>Telefon</th>
                            <th>Özet</th>
                            <th>Tarih</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEntries as $re): ?>
                            <?php
                            $payload = json_decode((string) ($re['payload_json'] ?? '{}'), true);
                            $payload = is_array($payload) ? $payload : [];
                            $phone = cc_extract_phone_from_payload($payload);
                            $summary = '';
                            foreach ($payload as $k => $v) {
                                if (!is_scalar($v) || $k === 'telefon' || str_contains(mb_strtolower((string) $k, 'UTF-8'), 'tel')) {
                                    continue;
                                }
                                $summary = (string) $v;
                                break;
                            }
                            ?>
                            <tr>
                                <td><?= (int) $re['id'] ?></td>
                                <td class="small"><?= htmlspecialchars((string) $re['form_title']) ?></td>
                                <td><?= cc_phone_actions_html($phone, true) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars(mb_substr($summary, 0, 60)) ?></td>
                                <td class="text-nowrap small"><?= htmlspecialchars((string) $re['created_at']) ?></td>
                                <td>
                                    <a href="custom_form_entries.php?id=<?= (int) $re['form_id'] ?>" class="btn btn-sm btn-outline-secondary">Aç</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-sm align-middle cc-table-compact">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Slug</th>
                    <th>Aktif</th>
                    <th>Bugün</th>
                    <th>Toplam</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($forms as $f): ?>
                <tr>
                    <td><?= (int) $f['id'] ?></td>
                    <td><strong><?= htmlspecialchars((string) $f['title']) ?></strong></td>
                    <td><code><?= htmlspecialchars((string) $f['slug']) ?></code></td>
                    <td><?= !empty((int) $f['is_active']) ? '<span class="cc-badge cc-badge--ok">Aktif</span>' : '<span class="cc-badge cc-badge--muted">Kapalı</span>' ?></td>
                    <td><?= (int) ($f['cnt_today'] ?? 0) ?></td>
                    <td><?= (int) ($f['cnt'] ?? 0) ?></td>
                    <td class="text-nowrap">
                        <a href="custom_form_edit.php?id=<?= (int) $f['id'] ?>" class="btn btn-sm btn-outline-primary">Alanlar</a>
                        <a href="custom_form_entries.php?id=<?= (int) $f['id'] ?>" class="btn btn-sm btn-outline-secondary">Gönderilenler</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Form ve tüm alanları / başvuruları silinsin mi?');">
                            <input type="hidden" name="delete_form_id" value="<?= (int) $f['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
