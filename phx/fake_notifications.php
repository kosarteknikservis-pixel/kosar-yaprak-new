<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/fake_notification_defaults.php';

$page_title = 'Sahte bildirimler';
$list = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_defaults'])) {
    $n = fake_notifications_seed_defaults($pdo, true);
    $_SESSION['message'] = $n . ' örnek bildirim yüklendi.';
    header('Location: fake_notifications.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['seed_defaults'])) {
    $pdo->prepare(
        'INSERT INTO fake_notifications (customer_name, city_name, time_label, weight, sort_order, is_active)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        trim($_POST['customer_name'] ?? ''),
        trim($_POST['city_name'] ?? ''),
        trim($_POST['time_label'] ?? 'biraz önce'),
        max(1, (int) ($_POST['weight'] ?? 10)),
        (int) ($_POST['sort_order'] ?? 0),
        isset($_POST['is_active']) ? 1 : 0,
    ]);
    $_SESSION['message'] = 'Eklendi.';
    header('Location: fake_notifications.php');
    exit;
}

if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $pdo->prepare('DELETE FROM fake_notifications WHERE id = ?')->execute([(int) $_GET['del']]);
    $_SESSION['message'] = 'Silindi.';
    header('Location: fake_notifications.php');
    exit;
}

try {
    $list = $pdo->query('SELECT * FROM fake_notifications ORDER BY sort_order DESC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $list = [];
}

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-comments-dollar text-secondary"></i> Sahte bildirimler</h1>
            <p class="text-muted small mb-0">Ana sayfada dönüşümlü “canlı sipariş” bildirimleri — yalnızca <strong>Türkiye isim ve illeri</strong> (<?= count($list) ?> kayıt).</p>
        </div>
        <form method="post" class="m-0" onsubmit="return confirm('Mevcut tüm kayıtlar silinip 40 gerçekçi örnek (Doğu ağırlıklı) yüklensin mi?');">
            <button type="submit" name="seed_defaults" value="1" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-magic"></i> 40 örnek yükle
            </button>
        </form>
    </div>

    <form method="post" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3">
            <div class="col-md-4"><input type="text" name="customer_name" class="form-control" placeholder="Örn: Ahmet Y." required></div>
            <div class="col-md-3"><input type="text" name="city_name" class="form-control" placeholder="İl" required></div>
            <div class="col-md-3"><input type="text" name="time_label" class="form-control" value="biraz önce" placeholder="Süre metni"></div>
            <div class="col-md-2"><input type="number" name="weight" class="form-control" value="10" min="1" title="Önem / sıklık"></div>
            <div class="col-md-2"><input type="number" name="sort_order" class="form-control" value="0"></div>
            <div class="col-md-12 form-check ms-3">
                <input class="form-check-input" type="checkbox" name="is_active" checked id="fa">
                <label class="form-check-label" for="fa">Aktif</label>
            </div>
            <div class="col-12"><button class="btn btn-primary">Ekle</button></div>
        </div>
    </form>

    <div class="table-responsive card border-0 shadow-sm">
        <table class="table mb-0 table-hover">
            <thead class="table-light"><tr><th>#</th><th>Ad</th><th>Şehir</th><th>Süre</th><th>Ağırlık</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($list as $r): ?>
                    <tr class="<?= empty($r['is_active']) ? 'table-secondary' : '' ?>">
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= htmlspecialchars((string) $r['customer_name']) ?></td>
                        <td><?= htmlspecialchars((string) $r['city_name']) ?></td>
                        <td><?= htmlspecialchars((string) $r['time_label']) ?></td>
                        <td><?= (int) ($r['weight'] ?? 0) ?></td>
                        <td><a href="fake_notifications.php?del=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sil?')">Sil</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
