<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/location_service.php';

location_ensure_schema($pdo);

$viewCountry = strtoupper((string) ($_GET['c'] ?? location_checkout_country($pdo)));
if (!in_array($viewCountry, location_pack_codes(), true)) {
    $viewCountry = 'TR';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'set_country') {
        $cc = strtoupper((string) ($_POST['checkout_country'] ?? ''));
        location_set_checkout_country($pdo, $cc);
        $_SESSION['message'] = 'Sipariş formu ülkesi kaydedildi.';
        header('Location: manage_locations.php?c=' . urlencode($cc));
        exit;
    }
    if ($action === 'import_pack') {
        $cc = strtoupper((string) ($_POST['pack'] ?? ''));
        $r = location_pack_import($pdo, $cc);
        $_SESSION['message'] = sprintf(
            '%s yüklendi: %d yeni il/eyalet, %d yeni ilçe/şehir (mevcut kayıtlar korundu).',
            location_pack_titles()[$cc] ?? $cc,
            $r['cities'],
            $r['districts']
        );
        header('Location: manage_locations.php?c=' . urlencode($cc));
        exit;
    }
    if ($action === 'add_city') {
        $n = trim((string) ($_POST['city_name'] ?? ''));
        $cc = strtoupper((string) ($_POST['country_code'] ?? $viewCountry));
        if ($n !== '' && in_array($cc, location_pack_codes(), true)) {
            $pdo->prepare('INSERT INTO cities (city_name, country_code) VALUES (?, ?)')->execute([$n, $cc]);
            $_SESSION['message'] = 'Kayıt eklendi.';
        }
        header('Location: manage_locations.php?c=' . urlencode($cc));
        exit;
    }
    if ($action === 'add_district') {
        $cid = (int) ($_POST['city_id'] ?? 0);
        $dn = trim((string) ($_POST['district_name'] ?? ''));
        $cc = strtoupper((string) ($_POST['country_code'] ?? $viewCountry));
        if ($cid > 0 && $dn !== '') {
            $pdo->prepare('INSERT INTO districts (city_id, district_name) VALUES (?,?)')->execute([$cid, $dn]);
            $_SESSION['message'] = 'Alt birim eklendi.';
        }
        header('Location: manage_locations.php?c=' . urlencode($cc));
        exit;
    }
}

if (isset($_GET['del_city']) && ctype_digit($_GET['del_city'])) {
    $id = (int) $_GET['del_city'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM districts WHERE city_id = ?');
    $c->execute([$id]);
    if ((int) $c->fetchColumn() > 0) {
        $_SESSION['message'] = 'Bağlı alt birimler var — önce onları silin.';
    } else {
        $cc = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_city = ?');
        $cc->execute([$id]);
        if ((int) $cc->fetchColumn() > 0) {
            $_SESSION['message'] = 'Bu kayıt siparişlerde kullanılıyor; silinemedi.';
        } else {
            $pdo->prepare('DELETE FROM cities WHERE city_id = ?')->execute([$id]);
            $_SESSION['message'] = 'Silindi.';
        }
    }
    header('Location: manage_locations.php?c=' . urlencode($viewCountry));
    exit;
}

if (isset($_GET['del_district']) && ctype_digit($_GET['del_district'])) {
    $id = (int) $_GET['del_district'];
    $cc = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_district = ?');
    $cc->execute([$id]);
    if ((int) $cc->fetchColumn() > 0) {
        $_SESSION['message'] = 'Siparişlerde kullanılıyor; silinemedi.';
    } else {
        $pdo->prepare('DELETE FROM districts WHERE district_id = ?')->execute([$id]);
        $_SESSION['message'] = 'Silindi.';
    }
    header('Location: manage_locations.php?c=' . urlencode($viewCountry));
    exit;
}

$activeCountry = location_checkout_country($pdo);
$cities = location_cities($pdo, $viewCountry);
$cityIds = array_map(static fn ($r) => (int) $r['city_id'], $cities);
$districts = [];
if ($cityIds !== []) {
    $ph = implode(',', array_fill(0, count($cityIds), '?'));
    $st = $pdo->prepare(
        "SELECT d.*, c.city_name FROM districts d JOIN cities c ON c.city_id = d.city_id
         WHERE d.city_id IN ($ph) ORDER BY c.city_name, d.district_name"
    );
    $st->execute($cityIds);
    $districts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$page_title = 'İl / ilçe — teslimat bölgeleri';
include 'admin_header.php';
$titles = location_pack_titles();
?>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2">Teslimat bölgeleri (il / ilçe)</h1>
    <p class="text-muted small mb-4">
        Türkiye gibi iki kademeli liste: önce üst birim (il, eyalet, bölge), sonra alt birim (ilçe, şehir).
        Sipariş formunda yalnızca seçili ülkenin listesi görünür. Mevcut sipariş kayıtları silinmez.
    </p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="set_country">
                <div class="col-md-6">
                    <label class="form-label">Sipariş formunda gösterilecek ülke</label>
                    <select name="checkout_country" class="form-select">
                        <?php foreach ($titles as $code => $title): ?>
                            <option value="<?= htmlspecialchars($code) ?>"<?= $activeCountry === $code ? ' selected' : '' ?>><?= htmlspecialchars($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary" type="submit">Kaydet</button>
                </div>
            </form>
            <p class="small text-muted mt-2 mb-0">Para birimi AUD ise Avustralya, SAR ise Suudi, AED ise BAE önerilir. Türkiye için TRY.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6">Hazır liste yükle</h2>
            <p class="small text-muted">Eksik kayıtları ekler; aynı isimler atlanır. Türkiye zaten yüklüyse tekrar basmak zarar vermez.</p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($titles as $code => $title): ?>
                    <?php $cnt = location_country_counts($pdo, $code); ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="import_pack">
                        <input type="hidden" name="pack" value="<?= htmlspecialchars($code) ?>">
                        <button class="btn btn-outline-primary btn-sm" type="submit">
                            <?= htmlspecialchars($title) ?>
                            <span class="badge bg-light text-dark"><?= (int) $cnt['cities'] ?> / <?= (int) $cnt['districts'] ?></span>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <?php foreach ($titles as $code => $title): ?>
            <li class="nav-item">
                <a class="nav-link<?= $viewCountry === $code ? ' active' : '' ?>" href="manage_locations.php?c=<?= urlencode($code) ?>"><?= htmlspecialchars($code) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">Yeni üst birim (<?= htmlspecialchars($viewCountry) ?>)</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_city">
                        <input type="hidden" name="country_code" value="<?= htmlspecialchars($viewCountry) ?>">
                        <div class="mb-2">
                            <input type="text" name="city_name" class="form-control" placeholder="Ad" required>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Ekle</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">Yeni alt birim</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_district">
                        <input type="hidden" name="country_code" value="<?= htmlspecialchars($viewCountry) ?>">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <select name="city_id" class="form-select" required>
                                    <option value="">Üst birim</option>
                                    <?php foreach ($cities as $c): ?>
                                        <option value="<?= (int) $c['city_id'] ?>"><?= htmlspecialchars((string) $c['city_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="district_name" class="form-control" placeholder="Ad" required>
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
            <h2 class="h6">Üst birimler (<?= count($cities) ?>)</h2>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <?php foreach ($cities as $c): ?>
                            <tr>
                                <td><?= (int) $c['city_id'] ?></td>
                                <td><?= htmlspecialchars((string) $c['city_name']) ?></td>
                                <td><a href="manage_locations.php?c=<?= urlencode($viewCountry) ?>&amp;del_city=<?= (int) $c['city_id'] ?>" class="text-danger" onclick="return confirm('Silinsin mi?')">Sil</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-7">
            <h2 class="h6">Alt birimler (<?= count($districts) ?>)</h2>
            <div class="table-responsive" style="max-height:420px;overflow:auto;">
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Üst</th><th>Alt</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($districts as $d): ?>
                            <tr>
                                <td><?= (int) $d['district_id'] ?></td>
                                <td><?= htmlspecialchars((string) $d['city_name']) ?></td>
                                <td><?= htmlspecialchars((string) $d['district_name']) ?></td>
                                <td><a href="manage_locations.php?c=<?= urlencode($viewCountry) ?>&amp;del_district=<?= (int) $d['district_id'] ?>" class="text-danger" onclick="return confirm('Silinsin mi?')">Sil</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
