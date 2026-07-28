<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$colorPresets = [
    'temu' => ['label' => 'Turuncu / altın (Temu tarzı)', 'c1' => '#ff6b35', 'c2' => '#f7931e', 'a' => '#ffd700'],
    'sans' => ['label' => 'Yeşil / pembe', 'c1' => '#22c55e', 'c2' => '#db2777', 'a' => '#fbbf24'],
    'gece' => ['label' => 'Mor / lacivert', 'c1' => '#6366f1', 'c2' => '#4f46e5', 'a' => '#f472b6'],
    'klasik' => ['label' => 'Kırmızı / altın', 'c1' => '#ef4444', 'c2' => '#b91c1c', 'a' => '#fbbf24'],
];

$stmt = $pdo->query('SELECT * FROM carkifelek_settings WHERE id = 1');
$row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$row) {
    $pdo->exec('INSERT IGNORE INTO carkifelek_settings (id) VALUES (1)');
    $stmt = $pdo->query('SELECT * FROM carkifelek_settings WHERE id = 1');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
}

$row = array_merge([
    'is_active' => 0,
    'fab_enabled' => 1,
    'title' => 'Şansını Dene!',
    'subtitle' => 'Günde bir kez çevir, indirim veya kargo fırsatı yakala.',
    'prizes_json' => '[]',
    'color1' => '#ff6b35',
    'color2' => '#f7931e',
    'accent' => '#ffd700',
    'auto_popup' => 1,
    'auto_delay_seconds' => 5,
    'force_free_shipping' => 0,
    'limit_message' => '',
], $row ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $fab_enabled = isset($_POST['fab_enabled']) ? 1 : 0;
    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $limit_message = trim((string) ($_POST['limit_message'] ?? ''));
    $color1 = trim((string) ($_POST['color1'] ?? '#ff6b35'));
    $color2 = trim((string) ($_POST['color2'] ?? '#f7931e'));
    $accent = trim((string) ($_POST['accent'] ?? '#ffd700'));
    $auto_popup = isset($_POST['auto_popup']) ? 1 : 0;
    $auto_delay_seconds = max(0, min(600, (int) ($_POST['auto_delay_seconds'] ?? 5)));
    $force_free_shipping = isset($_POST['force_free_shipping']) ? 1 : 0;

    $prizes_raw = trim((string) ($_POST['prizes_text'] ?? ''));
    $lines = preg_split('/\r\n|\r|\n/', $prizes_raw) ?: [];
    $prize_list = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $prize_list[] = $line;
        }
    }

    if ($title === '') {
        $title = 'Şansını Dene!';
    }

    if (count($prize_list) < 4) {
        $_SESSION['message'] = 'En az 4 ödül satırı girin.';
        $_SESSION['message_type'] = 'error';
    } else {
        $prizes_json = json_encode($prize_list, JSON_UNESCAPED_UNICODE);
        try {
            $up = $pdo->prepare(
                'UPDATE carkifelek_settings SET is_active = ?, fab_enabled = ?, title = ?, subtitle = ?, prizes_json = ?, color1 = ?, color2 = ?, accent = ?, auto_popup = ?, auto_delay_seconds = ?, force_free_shipping = ?, limit_message = ? WHERE id = 1'
            );
            $up->execute([
                $is_active, $fab_enabled, $title, $subtitle, $prizes_json, $color1, $color2, $accent,
                $auto_popup, $auto_delay_seconds, $force_free_shipping, $limit_message,
            ]);
            $_SESSION['message'] = 'Ayarlar kaydedildi.';
            $_SESSION['message_type'] = 'success';
        } catch (Throwable $e) {
            $_SESSION['message'] = 'Kayıt hatası: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }
    header('Location: carkifelek_settings.php');
    exit;
}

$prizes_array = json_decode((string) $row['prizes_json'], true);
if (!is_array($prizes_array) || $prizes_array === []) {
    $prizes_display = implode("\n", ['%10 İndirim', '%15 İndirim', '%20 İndirim', '%25 İndirim', 'Ücretsiz Kargo', 'Tekrar Dene']);
} else {
    $prizes_display = implode("\n", array_map('strval', $prizes_array));
}

$logToday = 0;
$logTotal = 0;
try {
    $logToday = (int) $pdo->query('SELECT COUNT(*) FROM carkifelek_log WHERE tarih >= ' . (int) strtotime('today'))->fetchColumn();
    $logTotal = (int) $pdo->query('SELECT COUNT(*) FROM carkifelek_log')->fetchColumn();
} catch (Throwable $e) {
}

$page_title = 'Şans Çarkı';
include 'admin_header.php';
$c1 = htmlspecialchars((string) $row['color1'], ENT_QUOTES, 'UTF-8');
$c2 = htmlspecialchars((string) $row['color2'], ENT_QUOTES, 'UTF-8');
$ca = htmlspecialchars((string) $row['accent'], ENT_QUOTES, 'UTF-8');
?>

<link rel="stylesheet" href="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'css/record-drawer.css', ENT_QUOTES, 'UTF-8') ?>">

<div class="container-fluid px-3 px-lg-4 pb-4" style="max-width:1100px;">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= ($_SESSION['message_type'] ?? '') === 'error' ? 'danger' : 'success' ?>">
            <?= htmlspecialchars((string)$_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-dharmachakra text-warning me-2"></i>Şans çarkı</h1>
            <p class="text-muted small mb-0">Ön yüzde indirim çarkı — Temu tarzı görünüm, sağ-alt kısayol ayrı açılıp kapatılabilir.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="carkifelek_logs.php" class="btn btn-outline-secondary btn-sm">Loglar <span class="badge bg-secondary"><?= $logTotal ?></span></a>
            <span class="btn btn-light btn-sm disabled">Bugün <?= $logToday ?></span>
        </div>
    </div>

    <form method="post" class="row g-3">
        <div class="col-lg-7">
            <div class="admin-section-card">
                <div class="admin-section-card__head">
                    <h2 class="admin-section-card__title"><i class="fas fa-sliders-h"></i> Genel</h2>
                </div>
                <div class="admin-section-card__body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= !empty($row['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active"><strong>Modül aktif</strong> — kapalıyken sitede hiç görünmez</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="fab_enabled" id="fab_enabled" <?= !empty($row['fab_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fab_enabled"><strong>Sağ-alt kısayol</strong> — 🎁 butonu ile aç/kapa</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="auto_popup" id="auto_popup" <?= !empty($row['auto_popup']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="auto_popup">Sayfa açılışında otomatik göster (limit uygunsa)</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="auto_delay_seconds">Otomatik gecikme (sn)</label>
                            <input type="number" class="form-control" name="auto_delay_seconds" id="auto_delay_seconds" min="0" max="600" value="<?= (int) ($row['auto_delay_seconds'] ?? 5) ?>">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="force_free_shipping" id="force_free_shipping" <?= !empty($row['force_free_shipping']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="force_free_shipping">Ücretsiz kargo satırını önceliklendir</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="title">Başlık</label>
                            <input type="text" class="form-control" name="title" id="title" value="<?= htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="subtitle">Alt metin</label>
                            <textarea class="form-control" name="subtitle" id="subtitle" rows="2"><?= htmlspecialchars((string)$row['subtitle'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="limit_message">Limit mesajı (günde 1 kez)</label>
                            <textarea class="form-control" name="limit_message" id="limit_message" rows="2"><?= htmlspecialchars((string)$row['limit_message'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="prizes_text">Ödüller (satır başına bir)</label>
                            <textarea class="form-control font-monospace" name="prizes_text" id="prizes_text" rows="8" required><?= htmlspecialchars($prizes_display, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-section-card mb-3">
                <div class="admin-section-card__head">
                    <h2 class="admin-section-card__title"><i class="fas fa-palette"></i> Renkler</h2>
                </div>
                <div class="admin-section-card__body">
                    <p class="small text-muted">Hazır tema seçin veya renkleri elle ayarlayın.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($colorPresets as $key => $preset): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary cark-preset" data-c1="<?= htmlspecialchars($preset['c1'], ENT_QUOTES, 'UTF-8') ?>" data-c2="<?= htmlspecialchars($preset['c2'], ENT_QUOTES, 'UTF-8') ?>" data-a="<?= htmlspecialchars($preset['a'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($preset['label'], ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-2">
                        <div class="col-4"><label class="form-label small">Dilim 1</label><input type="color" class="form-control form-control-color w-100" name="color1" id="color1" value="<?= $c1 ?>"></div>
                        <div class="col-4"><label class="form-label small">Dilim 2</label><input type="color" class="form-control form-control-color w-100" name="color2" id="color2" value="<?= $c2 ?>"></div>
                        <div class="col-4"><label class="form-label small">Vurgu</label><input type="color" class="form-control form-control-color w-100" name="accent" id="accent" value="<?= $ca ?>"></div>
                    </div>
                    <div class="cark-preview mt-3 rounded-3 p-3 text-center text-white" id="carkPreview" style="background:linear-gradient(135deg, <?= $c1 ?>, <?= $c2 ?>); min-height:120px;">
                        <div style="font-size:2rem;">🎁</div>
                        <strong>Önizleme</strong>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> Kaydet</button>
            <p class="small text-muted mt-2 mb-0">Günlük limit IP ile <code>carkifelek_log</code> tablosunda tutulur. Test: <code>?debug_wheel=1</code></p>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.cark-preset').forEach(function(btn) {
    btn.addEventListener('click', function () {
        document.getElementById('color1').value = btn.dataset.c1;
        document.getElementById('color2').value = btn.dataset.c2;
        document.getElementById('accent').value = btn.dataset.a;
        updateCarkPreview();
    });
});
function updateCarkPreview() {
    var p = document.getElementById('carkPreview');
    if (!p) return;
    var c1 = document.getElementById('color1').value;
    var c2 = document.getElementById('color2').value;
    p.style.background = 'linear-gradient(135deg, ' + c1 + ', ' + c2 + ')';
}
['color1','color2','accent'].forEach(function(id) {
    document.getElementById(id).addEventListener('input', updateCarkPreview);
});
</script>

<?php include 'admin_footer_common.php';
