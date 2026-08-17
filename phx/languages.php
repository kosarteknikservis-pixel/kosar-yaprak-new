<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/currency_rates.php';

if (empty($_SESSION['csrf_i18n'])) {
    $_SESSION['csrf_i18n'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['csrf_i18n'];

/* ---------------------------------------------------------------------------
 * Dışa aktarma (başlıklardan önce, ham çıktı akışı)
 * ------------------------------------------------------------------------- */
$export = (string) ($_GET['export'] ?? '');
if ($export !== '') {
    $langs = $pdo->query('SELECT code FROM site_languages ORDER BY sort_order, code')->fetchAll(PDO::FETCH_COLUMN);
    $rows = $pdo->query('SELECT lang_code, t_key, t_value, t_group FROM site_translations ORDER BY t_group, t_key, lang_code')->fetchAll(PDO::FETCH_ASSOC);

    if ($export === 'json') {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['lang_code']][(string) $r['t_key']] = (string) $r['t_value'];
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="translations.json"');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($export === 'csv') {
        // key,group,<lang1>,<lang2>...
        $byKey = [];
        foreach ($rows as $r) {
            $k = (string) $r['t_key'];
            $byKey[$k]['group'] = (string) $r['t_group'];
            $byKey[$k][(string) $r['lang_code']] = (string) $r['t_value'];
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="translations.csv"');
        $fh = fopen('php://output', 'w');
        fprintf($fh, "\xEF\xBB\xBF"); // UTF-8 BOM (Excel)
        fputcsv($fh, array_merge(['key', 'group'], $langs));
        foreach ($byKey as $k => $data) {
            $line = [$k, (string) ($data['group'] ?? 'general')];
            foreach ($langs as $lc) {
                $line[] = (string) ($data[$lc] ?? '');
            }
            fputcsv($fh, $line);
        }
        fclose($fh);
        exit;
    }
}

/* ---------------------------------------------------------------------------
 * POST işlemleri
 * ------------------------------------------------------------------------- */
$flash = '';
$flashType = 'success';

function i18n_admin_upsert_translation(PDO $pdo, string $lang, string $key, string $value, string $group = 'general'): void
{
    $st = $pdo->prepare(
        'INSERT INTO site_translations (lang_code, t_key, t_value, t_group) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE t_value = VALUES(t_value), t_group = VALUES(t_group)'
    );
    $st->execute([$lang, $key, $value, $group]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $flash = 'Oturum güvenliği doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            switch ($action) {
                /* ---- Diller ---- */
                case 'lang_toggle': {
                    $code = preg_replace('/[^a-z]/i', '', (string) ($_POST['code'] ?? ''));
                    $pdo->prepare('UPDATE site_languages SET is_active = 1 - is_active WHERE code = ?')->execute([$code]);
                    // Varsayılan dil pasifleştirilemez
                    $pdo->exec("UPDATE site_languages SET is_active = 1 WHERE is_default = 1");
                    $flash = 'Dil durumu güncellendi.';
                    break;
                }
                case 'lang_default': {
                    $code = preg_replace('/[^a-z]/i', '', (string) ($_POST['code'] ?? ''));
                    $pdo->exec('UPDATE site_languages SET is_default = 0');
                    $pdo->prepare('UPDATE site_languages SET is_default = 1, is_active = 1 WHERE code = ?')->execute([$code]);
                    $flash = 'Varsayılan dil ayarlandı.';
                    break;
                }
                case 'lang_save': {
                    $code = preg_replace('/[^a-z]/i', '', (string) ($_POST['code'] ?? ''));
                    $pdo->prepare('UPDATE site_languages SET name = ?, native_name = ?, flag = ?, is_rtl = ?, sort_order = ? WHERE code = ?')
                        ->execute([
                            trim((string) ($_POST['name'] ?? '')),
                            trim((string) ($_POST['native_name'] ?? '')),
                            trim((string) ($_POST['flag'] ?? '')),
                            isset($_POST['is_rtl']) ? 1 : 0,
                            (int) ($_POST['sort_order'] ?? 0),
                            $code,
                        ]);
                    $flash = 'Dil kaydedildi.';
                    break;
                }
                case 'lang_add': {
                    $code = strtolower(preg_replace('/[^a-z]/i', '', (string) ($_POST['code'] ?? '')));
                    if ($code === '') {
                        throw new RuntimeException('Geçerli bir dil kodu girin (örn: de).');
                    }
                    $st = $pdo->prepare('INSERT IGNORE INTO site_languages (code, name, native_name, flag, is_rtl, is_active, is_default, sort_order) VALUES (?,?,?,?,?,1,0,?)');
                    $st->execute([
                        $code,
                        trim((string) ($_POST['name'] ?? $code)),
                        trim((string) ($_POST['native_name'] ?? $code)),
                        trim((string) ($_POST['flag'] ?? '')),
                        isset($_POST['is_rtl']) ? 1 : 0,
                        (int) ($_POST['sort_order'] ?? 99),
                    ]);
                    $flash = 'Dil eklendi.';
                    break;
                }
                case 'lang_delete': {
                    $code = preg_replace('/[^a-z]/i', '', (string) ($_POST['code'] ?? ''));
                    $isDef = (int) $pdo->query('SELECT is_default FROM site_languages WHERE code = ' . $pdo->quote($code))->fetchColumn();
                    if ($isDef === 1) {
                        throw new RuntimeException('Varsayılan dil silinemez.');
                    }
                    $pdo->prepare('DELETE FROM site_languages WHERE code = ?')->execute([$code]);
                    $pdo->prepare('DELETE FROM site_translations WHERE lang_code = ?')->execute([$code]);
                    $flash = 'Dil ve çevirileri silindi.';
                    break;
                }

                /* ---- Para birimleri ---- */
                case 'cur_toggle': {
                    $code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['code'] ?? '')));
                    $pdo->prepare('UPDATE site_currencies SET is_active = 1 - is_active WHERE code = ?')->execute([$code]);
                    $pdo->exec('UPDATE site_currencies SET is_active = 1 WHERE is_default = 1');
                    $flash = 'Para birimi durumu güncellendi.';
                    break;
                }
                case 'cur_default': {
                    $code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['code'] ?? '')));
                    $pdo->exec('UPDATE site_currencies SET is_default = 0');
                    $pdo->prepare('UPDATE site_currencies SET is_default = 1, is_active = 1 WHERE code = ?')->execute([$code]);
                    $flash = 'Varsayılan para birimi ayarlandı.';
                    break;
                }
                case 'cur_save': {
                    $code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['code'] ?? '')));
                    $pos = (string) ($_POST['symbol_position'] ?? 'after');
                    $pos = in_array($pos, ['before', 'after'], true) ? $pos : 'after';
                    $pdo->prepare('UPDATE site_currencies SET symbol = ?, name = ?, rate = ?, decimals = ?, symbol_position = ?, sort_order = ? WHERE code = ?')
                        ->execute([
                            trim((string) ($_POST['symbol'] ?? '')),
                            trim((string) ($_POST['name'] ?? '')),
                            (float) str_replace(',', '.', (string) ($_POST['rate'] ?? '1')),
                            max(0, min(4, (int) ($_POST['decimals'] ?? 2))),
                            $pos,
                            (int) ($_POST['sort_order'] ?? 0),
                            $code,
                        ]);
                    $flash = 'Para birimi kaydedildi.';
                    break;
                }
                case 'cur_add': {
                    $code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['code'] ?? '')));
                    if (strlen($code) < 2) {
                        throw new RuntimeException('Geçerli bir para birimi kodu girin (örn: JPY).');
                    }
                    $pos = (string) ($_POST['symbol_position'] ?? 'after');
                    $pos = in_array($pos, ['before', 'after'], true) ? $pos : 'after';
                    $st = $pdo->prepare('INSERT IGNORE INTO site_currencies (code, symbol, name, rate, is_active, is_default, decimals, symbol_position, sort_order) VALUES (?,?,?,?,1,0,?,?,?)');
                    $st->execute([
                        $code,
                        trim((string) ($_POST['symbol'] ?? $code)),
                        trim((string) ($_POST['name'] ?? $code)),
                        (float) str_replace(',', '.', (string) ($_POST['rate'] ?? '1')),
                        max(0, min(4, (int) ($_POST['decimals'] ?? 2))),
                        $pos,
                        (int) ($_POST['sort_order'] ?? 99),
                    ]);
                    $flash = 'Para birimi eklendi.';
                    break;
                }
                case 'cur_delete': {
                    $code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['code'] ?? '')));
                    $isDef = (int) $pdo->query('SELECT is_default FROM site_currencies WHERE code = ' . $pdo->quote($code))->fetchColumn();
                    if ($isDef === 1) {
                        throw new RuntimeException('Varsayılan para birimi silinemez.');
                    }
                    $pdo->prepare('DELETE FROM site_currencies WHERE code = ?')->execute([$code]);
                    $flash = 'Para birimi silindi.';
                    break;
                }
                case 'cur_fetch_rates': {
                    $msg = null;
                    $ok = currency_update_rates($pdo, $msg);
                    $flash = (string) $msg;
                    $flashType = $ok ? 'success' : 'warning';
                    break;
                }

                /* ---- Çeviriler ---- */
                case 'trans_save': {
                    $group = trim((string) ($_POST['group'] ?? 'general')) ?: 'general';
                    $vals = $_POST['val'] ?? [];
                    $groups = $_POST['grp'] ?? [];
                    $n = 0;
                    if (is_array($vals)) {
                        foreach ($vals as $lang => $pairs) {
                            $lang = preg_replace('/[^a-z]/i', '', (string) $lang);
                            if (!is_array($pairs)) {
                                continue;
                            }
                            foreach ($pairs as $key => $value) {
                                $key = (string) $key;
                                $g = (string) ($groups[$key] ?? $group);
                                i18n_admin_upsert_translation($pdo, $lang, $key, (string) $value, $g !== '' ? $g : 'general');
                                $n++;
                            }
                        }
                    }
                    $flash = $n . ' çeviri alanı kaydedildi.';
                    break;
                }
                case 'trans_add': {
                    $key = trim((string) ($_POST['t_key'] ?? ''));
                    if ($key === '') {
                        throw new RuntimeException('Anahtar boş olamaz.');
                    }
                    $group = trim((string) ($_POST['t_group'] ?? 'general')) ?: 'general';
                    $vals = $_POST['nval'] ?? [];
                    if (is_array($vals)) {
                        foreach ($vals as $lang => $value) {
                            $lang = preg_replace('/[^a-z]/i', '', (string) $lang);
                            i18n_admin_upsert_translation($pdo, $lang, $key, (string) $value, $group);
                        }
                    }
                    $flash = 'Anahtar eklendi: ' . $key;
                    break;
                }
                case 'trans_delete': {
                    $key = trim((string) ($_POST['t_key'] ?? ''));
                    $pdo->prepare('DELETE FROM site_translations WHERE t_key = ?')->execute([$key]);
                    $flash = 'Anahtar silindi: ' . $key;
                    break;
                }

                /* ---- İçe aktar ---- */
                case 'import': {
                    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Dosya yüklenemedi.');
                    }
                    $tmp = (string) $_FILES['file']['tmp_name'];
                    $name = strtolower((string) $_FILES['file']['name']);
                    $count = 0;
                    if (str_ends_with($name, '.json')) {
                        $data = json_decode((string) file_get_contents($tmp), true);
                        if (!is_array($data)) {
                            throw new RuntimeException('Geçersiz JSON.');
                        }
                        foreach ($data as $lang => $pairs) {
                            $lang = preg_replace('/[^a-z]/i', '', (string) $lang);
                            if (!is_array($pairs)) {
                                continue;
                            }
                            foreach ($pairs as $key => $value) {
                                i18n_admin_upsert_translation($pdo, $lang, (string) $key, (string) $value, 'general');
                                $count++;
                            }
                        }
                    } elseif (str_ends_with($name, '.csv')) {
                        $fh = fopen($tmp, 'r');
                        $header = fgetcsv($fh);
                        if (!$header) {
                            throw new RuntimeException('Boş CSV.');
                        }
                        // header: key,group,<lang codes...>
                        $header = array_map(static fn ($h) => trim((string) $h), $header);
                        $langCols = array_slice($header, 2);
                        while (($row = fgetcsv($fh)) !== false) {
                            $key = trim((string) ($row[0] ?? ''));
                            if ($key === '') {
                                continue;
                            }
                            $group = trim((string) ($row[1] ?? 'general')) ?: 'general';
                            foreach ($langCols as $i => $lc) {
                                $lc = preg_replace('/[^a-z]/i', '', (string) $lc);
                                $val = (string) ($row[$i + 2] ?? '');
                                if ($lc !== '') {
                                    i18n_admin_upsert_translation($pdo, $lc, $key, $val, $group);
                                    $count++;
                                }
                            }
                        }
                        fclose($fh);
                    } else {
                        throw new RuntimeException('Yalnızca .json veya .csv desteklenir.');
                    }
                    $flash = $count . ' çeviri içe aktarıldı.';
                    break;
                }
            }
        } catch (Throwable $e) {
            $flash = 'İşlem başarısız: ' . $e->getMessage();
            $flashType = 'danger';
        }
        if ($flashType !== 'danger' && is_file(__DIR__ . '/../includes/cache/page_cache_service.php')) {
            require_once __DIR__ . '/../includes/cache/page_cache_service.php';
            if (class_exists('PageCacheService')) {
                PageCacheService::purgeAll();
            }
        }
        $_SESSION['i18n_flash'] = $flash;
        $_SESSION['i18n_flash_type'] = $flashType;
        $qs = isset($_GET['tab']) ? ('?tab=' . urlencode((string) $_GET['tab'])) : '';
        header('Location: languages.php' . $qs);
        exit;
    }
}

if (!empty($_SESSION['i18n_flash'])) {
    $flash = (string) $_SESSION['i18n_flash'];
    $flashType = (string) ($_SESSION['i18n_flash_type'] ?? 'success');
    unset($_SESSION['i18n_flash'], $_SESSION['i18n_flash_type']);
}

/* ---------------------------------------------------------------------------
 * Veri
 * ------------------------------------------------------------------------- */
$languages = $pdo->query('SELECT * FROM site_languages ORDER BY sort_order, code')->fetchAll(PDO::FETCH_ASSOC);
$currencies = $pdo->query('SELECT * FROM site_currencies ORDER BY sort_order, code')->fetchAll(PDO::FETCH_ASSOC);

$groups = $pdo->query('SELECT DISTINCT t_group FROM site_translations ORDER BY t_group')->fetchAll(PDO::FETCH_COLUMN);
$activeTab = (string) ($_GET['tab'] ?? 'languages');
$curGroup = (string) ($_GET['tg'] ?? ($groups[0] ?? 'menu'));

// Seçili grup çevirileri: key => [group, lang=>value]
$transRows = [];
$stmt = $pdo->prepare('SELECT t_key, t_group, lang_code, t_value FROM site_translations WHERE t_group = ? ORDER BY t_key');
$stmt->execute([$curGroup]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = (string) $r['t_key'];
    $transRows[$k]['group'] = (string) $r['t_group'];
    $transRows[$k][(string) $r['lang_code']] = (string) $r['t_value'];
}
ksort($transRows);

$langCodes = array_map(static fn ($l) => (string) $l['code'], $languages);

$page_title = 'Diller & Para Birimleri';
include 'admin_header.php';
?>
<div class="container-fluid py-3">
    <?php if ($flash !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="admin-page-intro mb-3">
        <h1><i class="fas fa-globe"></i> Diller &amp; Para Birimleri</h1>
        <p class="lead mb-2">Vitrin dili sitede seçilmez. Panelde <strong>varsayılan dil</strong> hangisiyse müşteri onu görür (yıldız). Çeviriler yine Çeviriler / ürün / ödeme ekranlarından yazılır.</p>
        <ol class="mb-0 small text-muted">
            <li><strong>Diller</strong> — İngilizce veya Arapça’yı varsayılan yapın; tüm sipariş arayüzü o dile geçer.</li>
            <li><strong>Çeviriler</strong> — sipariş metinleri (grup: <code>order</code>, <code>shop</code>, <code>thankyou</code>).</li>
            <li>Ürün adı/açıklama: <a href="products.php">Ürünler</a> → düzenle → “Yurtdışı dil”.</li>
            <li>Ödeme yöntemi adları: <a href="manage_payment_methods.php">Ödeme yöntemleri</a> EN/AR sütunları.</li>
        </ol>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php
        $tabs = [
            'languages' => ['fa-language', 'Diller'],
            'currencies' => ['fa-coins', 'Para Birimleri'],
            'translations' => ['fa-list', 'Çeviriler'],
            'io' => ['fa-file-import', 'İçe / Dışa Aktar'],
        ];
        foreach ($tabs as $tk => $ti):
            $act = $activeTab === $tk;
        ?>
        <li class="nav-item">
            <a class="nav-link <?= $act ? 'active' : '' ?>" href="?tab=<?= $tk ?>"><i class="fas <?= $ti[0] ?>"></i> <?= htmlspecialchars($ti[1]) ?></a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php /* ===================== DİLLER ===================== */ ?>
    <?php if ($activeTab === 'languages'): ?>
    <section class="admin-section-card mb-3">
        <div class="admin-section-card__head"><h2><i class="fas fa-language"></i> Aktif diller</h2></div>
        <div class="admin-section-card__body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th>Kod</th><th>Bayrak</th><th>Ad</th><th>Yerel ad</th><th>RTL</th><th>Sıra</th><th>Durum</th><th class="text-end">İşlem</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($languages as $l): $isDef = (int) $l['is_default'] === 1; $fid = 'lf_' . preg_replace('/[^a-z0-9]/i', '', (string) $l['code']); ?>
                        <tr>
                            <td><code><?= htmlspecialchars((string) $l['code']) ?></code><?= $isDef ? ' <span class="badge bg-primary">varsayılan</span>' : '' ?></td>
                            <td style="width:70px"><input form="<?= $fid ?>" class="form-control form-control-sm" name="flag" value="<?= htmlspecialchars((string) $l['flag']) ?>"></td>
                            <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $l['name']) ?>"></td>
                            <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="native_name" value="<?= htmlspecialchars((string) $l['native_name']) ?>"></td>
                            <td class="text-center"><input form="<?= $fid ?>" type="checkbox" class="form-check-input" name="is_rtl" <?= (int) $l['is_rtl'] === 1 ? 'checked' : '' ?>></td>
                            <td style="width:80px"><input form="<?= $fid ?>" type="number" class="form-control form-control-sm" name="sort_order" value="<?= (int) $l['sort_order'] ?>"></td>
                            <td>
                                <span class="badge <?= (int) $l['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) $l['is_active'] === 1 ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <form id="<?= $fid ?>" method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="lang_save"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $l['code']) ?>"><button class="btn btn-outline-primary" title="Kaydet"><i class="fas fa-save"></i></button></form>
                                    <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="lang_default"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $l['code']) ?>"><button class="btn btn-outline-secondary" title="Varsayılan yap" <?= $isDef ? 'disabled' : '' ?>><i class="fas fa-star"></i></button></form>
                                    <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="lang_toggle"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $l['code']) ?>"><button class="btn <?= (int) $l['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="Aktif/Pasif" <?= $isDef ? 'disabled' : '' ?>><i class="fas fa-power-off"></i></button></form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Dil ve tüm çevirileri silinsin mi?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="lang_delete"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $l['code']) ?>"><button class="btn btn-outline-danger" title="Sil" <?= $isDef ? 'disabled' : '' ?>><i class="fas fa-trash"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="admin-section-card">
        <div class="admin-section-card__head"><h2><i class="fas fa-plus"></i> Dil ekle</h2></div>
        <div class="admin-section-card__body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="lang_add">
                <div class="col-auto"><label class="form-label">Kod</label><input class="form-control" name="code" placeholder="de" required style="width:90px"></div>
                <div class="col-auto"><label class="form-label">Bayrak</label><input class="form-control" name="flag" placeholder="🇩🇪" style="width:90px"></div>
                <div class="col-auto"><label class="form-label">Ad</label><input class="form-control" name="name" placeholder="German"></div>
                <div class="col-auto"><label class="form-label">Yerel ad</label><input class="form-control" name="native_name" placeholder="Deutsch"></div>
                <div class="col-auto"><div class="form-check mt-4"><input type="checkbox" class="form-check-input" name="is_rtl" id="addLangRtl"><label class="form-check-label" for="addLangRtl">RTL</label></div></div>
                <div class="col-auto"><button class="btn btn-primary"><i class="fas fa-plus"></i> Ekle</button></div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php /* ===================== PARA BİRİMLERİ ===================== */ ?>
    <?php if ($activeTab === 'currencies'): ?>
    <section class="admin-section-card mb-3">
        <div class="admin-section-card__head d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-coins"></i> Para birimleri</h2>
            <form method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="cur_fetch_rates">
                <button class="btn btn-sm btn-primary"><i class="fas fa-sync"></i> Kurları güncelle (canlı)</button>
            </form>
        </div>
        <div class="admin-section-card__body">
            <p class="text-muted small">Fiyatlar veritabanında <strong>TRY</strong> (baz) tutulur. <em>Kur</em> = 1 TRY karşılığı. Kurları elle düzenleyebilir veya “Kurları güncelle” ile canlı çekebilirsiniz.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Kod</th><th>Sembol</th><th>Ad</th><th>Kur (1 TRY =)</th><th>Ondalık</th><th>Sembol yeri</th><th>Sıra</th><th>Durum</th><th class="text-end">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($currencies as $c): $isDef = (int) $c['is_default'] === 1; $fid = 'cf_' . preg_replace('/[^A-Z0-9]/i', '', (string) $c['code']); ?>
                        <tr>
                            <td><code><?= htmlspecialchars((string) $c['code']) ?></code><?= $isDef ? ' <span class="badge bg-primary">varsayılan</span>' : '' ?></td>
                            <td style="width:70px"><input form="<?= $fid ?>" class="form-control form-control-sm" name="symbol" value="<?= htmlspecialchars((string) $c['symbol']) ?>"></td>
                            <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $c['name']) ?>"></td>
                            <td style="width:130px"><input form="<?= $fid ?>" class="form-control form-control-sm" name="rate" value="<?= htmlspecialchars(rtrim(rtrim(number_format((float) $c['rate'], 8, '.', ''), '0'), '.')) ?>" <?= (string) $c['code'] === 'TRY' ? 'readonly' : '' ?>></td>
                            <td style="width:80px"><input form="<?= $fid ?>" type="number" min="0" max="4" class="form-control form-control-sm" name="decimals" value="<?= (int) $c['decimals'] ?>"></td>
                            <td style="width:110px">
                                <select form="<?= $fid ?>" class="form-select form-select-sm" name="symbol_position">
                                    <option value="before" <?= (string) $c['symbol_position'] === 'before' ? 'selected' : '' ?>>Önce ($1)</option>
                                    <option value="after" <?= (string) $c['symbol_position'] === 'after' ? 'selected' : '' ?>>Sonra (1 ₺)</option>
                                </select>
                            </td>
                            <td style="width:80px"><input form="<?= $fid ?>" type="number" class="form-control form-control-sm" name="sort_order" value="<?= (int) $c['sort_order'] ?>"></td>
                            <td><span class="badge <?= (int) $c['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) $c['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <form id="<?= $fid ?>" method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="cur_save"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $c['code']) ?>"><button class="btn btn-outline-primary" title="Kaydet"><i class="fas fa-save"></i></button></form>
                                    <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="cur_default"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $c['code']) ?>"><button class="btn btn-outline-secondary" title="Varsayılan yap" <?= $isDef ? 'disabled' : '' ?>><i class="fas fa-star"></i></button></form>
                                    <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="cur_toggle"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $c['code']) ?>"><button class="btn <?= (int) $c['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="Aktif/Pasif" <?= $isDef ? 'disabled' : '' ?>><i class="fas fa-power-off"></i></button></form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Para birimi silinsin mi?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="cur_delete"><input type="hidden" name="code" value="<?= htmlspecialchars((string) $c['code']) ?>"><button class="btn btn-outline-danger" title="Sil" <?= $isDef ? 'disabled' : '' ?>><i class="fas fa-trash"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $lastUpd = $pdo->query('SELECT MAX(rate_updated_at) FROM site_currencies')->fetchColumn(); ?>
            <?php if ($lastUpd): ?><p class="text-muted small mt-2 mb-0">Son kur güncellemesi: <?= htmlspecialchars((string) $lastUpd) ?></p><?php endif; ?>
        </div>
    </section>

    <section class="admin-section-card">
        <div class="admin-section-card__head"><h2><i class="fas fa-plus"></i> Para birimi ekle</h2></div>
        <div class="admin-section-card__body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="cur_add">
                <div class="col-auto"><label class="form-label">Kod</label><input class="form-control" name="code" placeholder="JPY" style="width:90px" required></div>
                <div class="col-auto"><label class="form-label">Sembol</label><input class="form-control" name="symbol" placeholder="¥" style="width:80px"></div>
                <div class="col-auto"><label class="form-label">Ad</label><input class="form-control" name="name" placeholder="Japon Yeni"></div>
                <div class="col-auto"><label class="form-label">Kur (1 TRY =)</label><input class="form-control" name="rate" placeholder="4.5" style="width:120px"></div>
                <div class="col-auto"><label class="form-label">Ondalık</label><input type="number" min="0" max="4" class="form-control" name="decimals" value="2" style="width:90px"></div>
                <div class="col-auto"><label class="form-label">Sembol yeri</label><select class="form-select" name="symbol_position"><option value="before">Önce</option><option value="after" selected>Sonra</option></select></div>
                <div class="col-auto"><button class="btn btn-primary"><i class="fas fa-plus"></i> Ekle</button></div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php /* ===================== ÇEVİRİLER ===================== */ ?>
    <?php if ($activeTab === 'translations'): ?>
    <section class="admin-section-card mb-3">
        <div class="admin-section-card__head d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="mb-0"><i class="fas fa-list"></i> Arayüz çevirileri</h2>
            <form method="get" class="d-flex align-items-center gap-2 m-0">
                <input type="hidden" name="tab" value="translations">
                <label class="form-label m-0 small">Grup:</label>
                <select class="form-select form-select-sm" name="tg" onchange="this.form.submit()" style="width:auto">
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= htmlspecialchars((string) $g) ?>" <?= $curGroup === (string) $g ? 'selected' : '' ?>><?= htmlspecialchars((string) $g) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="admin-section-card__body">
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="trans_save">
                <input type="hidden" name="group" value="<?= htmlspecialchars($curGroup) ?>">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr>
                            <th style="min-width:180px">Anahtar</th>
                            <?php foreach ($languages as $l): ?>
                                <th><?= htmlspecialchars((string) ($l['flag'] ?: '')) ?> <?= htmlspecialchars((string) $l['code']) ?><?= (int) $l['is_default'] === 1 ? ' (kaynak)' : '' ?></th>
                            <?php endforeach; ?>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($transRows as $key => $data): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars((string) $key) ?></code>
                                    <input type="hidden" name="grp[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string) ($data['group'] ?? $curGroup)) ?>">
                                </td>
                                <?php foreach ($languages as $l): $lc = (string) $l['code']; ?>
                                    <td><input class="form-control form-control-sm" name="val[<?= htmlspecialchars($lc) ?>][<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string) ($data[$lc] ?? '')) ?>"<?= (int) $l['is_rtl'] === 1 ? ' dir="rtl"' : '' ?>></td>
                                <?php endforeach; ?>
                                <td class="text-end"></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$transRows): ?>
                            <tr><td colspan="<?= count($languages) + 2 ?>" class="text-center text-muted py-3">Bu grupta çeviri yok.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3"><button class="btn btn-primary"><i class="fas fa-save"></i> Bu grubu kaydet</button></div>
            </form>
        </div>
    </section>

    <section class="admin-section-card">
        <div class="admin-section-card__head"><h2><i class="fas fa-plus"></i> Yeni anahtar ekle</h2></div>
        <div class="admin-section-card__body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="trans_add">
                <div class="col-md-3"><label class="form-label">Anahtar</label><input class="form-control" name="t_key" placeholder="menu.new_key" required></div>
                <div class="col-md-2"><label class="form-label">Grup</label><input class="form-control" name="t_group" value="<?= htmlspecialchars($curGroup) ?>"></div>
                <?php foreach ($languages as $l): $lc = (string) $l['code']; ?>
                    <div class="col-md-2"><label class="form-label"><?= htmlspecialchars($lc) ?></label><input class="form-control" name="nval[<?= htmlspecialchars($lc) ?>]"<?= (int) $l['is_rtl'] === 1 ? ' dir="rtl"' : '' ?>></div>
                <?php endforeach; ?>
                <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-plus"></i> Ekle</button></div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php /* ===================== İÇE / DIŞA AKTAR ===================== */ ?>
    <?php if ($activeTab === 'io'): ?>
    <div class="row g-3">
        <div class="col-md-6">
            <section class="admin-section-card h-100">
                <div class="admin-section-card__head"><h2><i class="fas fa-file-export"></i> Dışa aktar</h2></div>
                <div class="admin-section-card__body">
                    <p class="text-muted">Tüm çevirileri indirin. JSON uygulama içi yedek için, CSV Excel’de düzenleme için idealdir.</p>
                    <a class="btn btn-outline-primary me-2" href="?export=json"><i class="fas fa-file-code"></i> JSON indir</a>
                    <a class="btn btn-outline-success" href="?export=csv"><i class="fas fa-file-csv"></i> CSV indir</a>
                </div>
            </section>
        </div>
        <div class="col-md-6">
            <section class="admin-section-card h-100">
                <div class="admin-section-card__head"><h2><i class="fas fa-file-import"></i> İçe aktar</h2></div>
                <div class="admin-section-card__body">
                    <p class="text-muted">JSON <code>{"en":{"menu.home":"Home"}}</code> veya CSV <code>key,group,tr,en,ar</code> yükleyin. Var olan anahtarlar güncellenir.</p>
                    <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="import">
                        <input type="file" class="form-control" name="file" accept=".json,.csv" required>
                        <button class="btn btn-primary"><i class="fas fa-upload"></i> Yükle</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
