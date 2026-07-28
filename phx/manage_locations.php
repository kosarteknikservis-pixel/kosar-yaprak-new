<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_city') {
        $n = trim((string)($_POST['city_name'] ?? ''));
        if ($n !== '') {
            $pdo->prepare('INSERT INTO cities (city_name) VALUES (?)')->execute([$n]);
            $_SESSION['message'] = 'İl eklendi.';
        }
    } elseif ($action === 'add_district') {
        $cid = (int)($_POST['city_id'] ?? 0);
        $dn = trim((string)($_POST['district_name'] ?? ''));
        if ($cid > 0 && $dn !== '') {
            $pdo->prepare('INSERT INTO districts (city_id, district_name) VALUES (?,?)')->execute([$cid, $dn]);
            $_SESSION['message'] = 'İlçe eklendi.';
        }
    }
    header('Location: manage_locations.php');
    exit;
}

if (isset($_GET['del_city']) && ctype_digit($_GET['del_city'])) {
    $id = (int)$_GET['del_city'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM districts WHERE city_id = ?');
    $c->execute([$id]);
    if ((int)$c->fetchColumn() > 0) {
        $_SESSION['message'] = 'Bu ile bağlı ilçeler var — önce ilçeleri silin.';
    } else {
        $cc = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_city = ?');
        $cc->execute([$id]);
        if ((int)$cc->fetchColumn() > 0) {
            $_SESSION['message'] = 'Bu il siparişlerde kullanılıyor; silinemedi.';
        } else {
            $pdo->prepare('DELETE FROM cities WHERE city_id = ?')->execute([$id]);
            $_SESSION['message'] = 'İl silindi.';
        }
    }
    header('Location: manage_locations.php');
    exit;
}

if (isset($_GET['del_district']) && ctype_digit($_GET['del_district'])) {
    $id = (int)$_GET['del_district'];
    $cc = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_district = ?');
    $cc->execute([$id]);
    if ((int)$cc->fetchColumn() > 0) {
        $_SESSION['message'] = 'İlçe siparişlerde kullanılıyor; silinemedi.';
    } else {
        $pdo->prepare('DELETE FROM districts WHERE district_id = ?')->execute([$id]);
        $_SESSION['message'] = 'İlçe silindi.';
    }
    header('Location: manage_locations.php');
    exit;
}

$cities = $pdo->query('SELECT * FROM cities ORDER BY city_name')->fetchAll(PDO::FETCH_ASSOC);
$districts = $pdo->query('SELECT d.*, c.city_name FROM districts d JOIN cities c ON c.city_id = d.city_id ORDER BY c.city_name, d.district_name')->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'İl / ilçe yönetimi';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-4">İl / ilçe yönetimi</h1>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">Yeni il</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_city">
                        <div class="mb-2">
                            <input type="text" name="city_name" class="form-control" placeholder="İl adı" required>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Ekle</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">Yeni ilçe</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_district">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <select name="city_id" class="form-select" required>
                                    <option value="">İl seçin</option>
                                    <?php foreach ($cities as $c): ?>
                                        <option value="<?= (int)$c['city_id'] ?>"><?= htmlspecialchars($c['city_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="district_name" class="form-control" placeholder="İlçe adı" required>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit">Ekle</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-5">
            <h2 class="h6">İller</h2>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <?php foreach ($cities as $c): ?>
                            <tr>
                                <td><?= (int)$c['city_id'] ?></td>
                                <td><?= htmlspecialchars($c['city_name']) ?></td>
                                <td><a href="manage_locations.php?del_city=<?= (int)$c['city_id'] ?>" class="text-danger" onclick="return confirm('Silinsin mi?')">Sil</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-7">
            <h2 class="h6">İlçeler</h2>
            <div class="table-responsive" style="max-height:420px;overflow:auto;">
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>İl</th><th>İlçe</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($districts as $d): ?>
                            <tr>
                                <td><?= (int)$d['district_id'] ?></td>
                                <td><?= htmlspecialchars((string)$d['city_name']) ?></td>
                                <td><?= htmlspecialchars((string)$d['district_name']) ?></td>
                                <td><a href="manage_locations.php?del_district=<?= (int)$d['district_id'] ?>" class="text-danger" onclick="return confirm('Silinsin mi?')">Sil</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
