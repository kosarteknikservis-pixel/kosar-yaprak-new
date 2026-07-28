<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$page_title = 'Yarım kalan yakalama ayarı';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_abandoned'])) {
    $enabled = isset($_POST['abandoned_capture_enabled']) ? 1 : 0;
    $autoTrigger = (string) ($_POST['abandoned_auto_trigger'] ?? 'scroll');
    if (! in_array($autoTrigger, ['scroll', 'product'], true)) {
        $autoTrigger = 'scroll';
    }
    $productOnly = isset($_POST['abandoned_product_only']) ? 1 : 0;
    $abandonedSmsEnabled = isset($_POST['abandoned_sms_enabled']) ? 1 : 0;
    $abandonedWhatsapp = preg_replace('/\D+/', '', (string) ($_POST['abandoned_whatsapp_number'] ?? '05527391073'));
    if ($abandonedWhatsapp === '') {
        $abandonedWhatsapp = '05527391073';
    }
    if (strlen($abandonedWhatsapp) === 10) {
        $abandonedWhatsapp = '0' . $abandonedWhatsapp;
    }
    $scrollPctRaw = $_POST['abandoned_scroll_pct'] ?? null;
    if ($scrollPctRaw === null || $scrollPctRaw === '') {
        try {
            $scrollPct = (int) $pdo->query('SELECT COALESCE(abandoned_scroll_pct, 50) FROM checkout_module_settings WHERE id = 1')->fetchColumn();
        } catch (Throwable $e) {
            $scrollPct = 50;
        }
    } else {
        $scrollPct = (int) $scrollPctRaw;
    }
    if ($scrollPct < 10) {
        $scrollPct = 10;
    } elseif ($scrollPct > 95) {
        $scrollPct = 95;
    }

    try {
        $pdo->prepare(
            'UPDATE checkout_module_settings SET
                abandoned_capture_enabled = ?,
                abandoned_auto_trigger = ?,
                abandoned_scroll_pct = ?,
                abandoned_product_only = ?,
                abandoned_trigger_form = 1,
                abandoned_trigger_scroll = ?,
                abandoned_trigger_products = ?,
                abandoned_sms_enabled = ?,
                abandoned_whatsapp_number = ?
             WHERE id = 1'
        )->execute([
            $enabled,
            $autoTrigger,
            $scrollPct,
            $productOnly,
            $enabled && $autoTrigger === 'scroll' ? 1 : 0,
            $enabled && $autoTrigger === 'product' ? 1 : 0,
            $abandonedSmsEnabled,
            mb_substr($abandonedWhatsapp, 0, 20),
        ]);
        $_SESSION['message'] = 'Kaydedildi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Hata: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: abandoned_settings.php');
    exit;
}

$settings = [
    'abandoned_capture_enabled' => 0,
    'abandoned_auto_trigger' => 'scroll',
    'abandoned_scroll_pct' => 50,
    'abandoned_product_only' => 1,
    'abandoned_sms_enabled' => 1,
    'abandoned_whatsapp_number' => '05527391073',
];
try {
    $row = $pdo->query(
        'SELECT
            COALESCE(abandoned_capture_enabled, 0) AS abandoned_capture_enabled,
            COALESCE(abandoned_auto_trigger, \'scroll\') AS abandoned_auto_trigger,
            COALESCE(abandoned_scroll_pct, 50) AS abandoned_scroll_pct,
            COALESCE(abandoned_product_only, 1) AS abandoned_product_only,
            COALESCE(abandoned_sms_enabled, 1) AS abandoned_sms_enabled,
            COALESCE(abandoned_whatsapp_number, \'05527391073\') AS abandoned_whatsapp_number
         FROM checkout_module_settings WHERE id = 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $settings = $row;
    }
} catch (Throwable $e) {
    /* defaults */
}

$enhancedOn = (int) $settings['abandoned_capture_enabled'] === 1;

include 'admin_header.php';
?>

<div class="container-fluid py-3 ab-settings-page">
    <?php if (! empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?> border-0 shadow-sm"><?= htmlspecialchars((string) $_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2"><i class="fas fa-hourglass-half text-warning"></i> Yarım kalan yakalama</h1>
    <p class="text-muted small mb-4">
        Kayıtlar <a href="abandoned_orders.php">Yarım kalan satışlar</a> listesinde görünür.
        <strong>Sipariş sayfasında</strong> PHP ile daha önceki kayıt (IP/çerez) forma yazılır; tarayıcı kayıtlı autofill algılanıp kaydedilir — bu her zaman açıktır.
        Aşağıdaki gelişmiş mod ise ana sayfa kaydırma / ürün etkileşimi içindir.
    </p>

    <div class="card border-0 shadow-sm" style="max-width: 720px;">
        <div class="card-body">
            <form method="post" class="vstack gap-3" id="abSettingsForm">
                <input type="hidden" name="save_abandoned" value="1">

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="ab_enabled" name="abandoned_capture_enabled" value="1" <?= $enhancedOn ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="ab_enabled">Gelişmiş otomatik yakalama</label>
                    <div class="small text-muted ms-0 mt-1">
                        <strong>Kapalı:</strong> sipariş sayfasında autofill + manuel form kaydı devam eder; ana sayfada otomatik tetik yok.
                        <strong>Açık:</strong> ek olarak ana sayfada kaydırma veya ürün etkileşimi tetikleyicisi çalışır.
                    </div>
                </div>

                <div id="abEnhancedBlock" class="ab-enhanced-block<?= $enhancedOn ? '' : ' is-hidden' ?>">
                    <hr class="my-3">
                    <p class="small fw-medium mb-2">Otomatik tetikleyici (tek seçim)</p>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="abandoned_auto_trigger" id="ab_auto_scroll" value="scroll" <?= ($settings['abandoned_auto_trigger'] ?? 'scroll') === 'scroll' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ab_auto_scroll">Sayfa kaydırma — belirlenen yüzdeye ulaşınca</label>
                    </div>
                    <div class="row g-2 align-items-center ms-4 mb-3 ps-2 border-start border-2" id="abScrollPctRow">
                        <div class="col-auto">
                            <label class="col-form-label col-form-label-sm" for="ab_pct">Kaydırma yüzdesi</label>
                        </div>
                        <div class="col-auto">
                            <input type="number" class="form-control form-control-sm" style="width:5rem;" id="ab_pct" name="abandoned_scroll_pct" min="10" max="95" value="<?= (int) $settings['abandoned_scroll_pct'] ?>">
                        </div>
                        <div class="col-auto"><span class="small text-muted">% (ör. 30 veya 50)</span></div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="abandoned_auto_trigger" id="ab_auto_product" value="product" <?= ($settings['abandoned_auto_trigger'] ?? '') === 'product' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ab_auto_product">Ürün etkileşimi — ürünler bölümü, sipariş sayfası veya ürüne tıklama</label>
                    </div>

                    <hr class="my-3">

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ab_product_only" name="abandoned_product_only" value="1" <?= (int) $settings['abandoned_product_only'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ab_product_only">İletişim bilgisi olmadan ürün kaydı</label>
                        <div class="small text-muted ms-4 mt-1">Otomatik tetiklenince ad/telefon yoksa yine de ürün bilgisi kaydedilir.</div>
                    </div>
                </div>

                <hr class="my-3">
                <h2 class="h6 mb-2"><i class="fas fa-sms text-success me-1"></i> Otomatik SMS geri kazanım</h2>
                <p class="small text-muted mb-3">
                    Telefonu yakalanan yarım kalan kayıtlara bir kez SMS gider. Mesajda WhatsApp linki vardır; müşteri tıklayınca size yazabilir.
                    Mesajda <code>YK-{id}</code> referans kodu ile dönüş takip edilir.
                </p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="ab_sms_enabled" name="abandoned_sms_enabled" value="1" <?= (int) ($settings['abandoned_sms_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="ab_sms_enabled">Telefon yakalandığında otomatik SMS gönder</label>
                </div>
                <div class="mb-3">
                    <label for="ab_whatsapp" class="form-label">WhatsApp numarası (SMS’teki link)</label>
                    <input type="text" class="form-control" id="ab_whatsapp" name="abandoned_whatsapp_number" value="<?= htmlspecialchars((string) ($settings['abandoned_whatsapp_number'] ?? '05527391073'), ENT_QUOTES, 'UTF-8') ?>" placeholder="05527391073">
                    <div class="form-text">Örnek SMS: «Merhaba {ad}, … WhatsApp'tan yazın: wa.me/…»</div>
                </div>

                <button type="submit" class="btn btn-primary mt-2"><i class="fas fa-save me-1"></i> Kaydet</button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var sw = document.getElementById('ab_enabled');
    var block = document.getElementById('abEnhancedBlock');
    var scrollRadio = document.getElementById('ab_auto_scroll');
    var productRadio = document.getElementById('ab_auto_product');
    var scrollRow = document.getElementById('abScrollPctRow');
    if (!sw || !block) return;

    function syncScrollRow() {
        if (!scrollRow || !scrollRadio) return;
        var active = scrollRadio.checked;
        scrollRow.style.opacity = active ? '1' : '0.45';
        scrollRow.querySelectorAll('input').forEach(function (el) {
            el.readOnly = !active;
        });
    }

    sw.addEventListener('change', function () {
        block.classList.toggle('is-hidden', !sw.checked);
    });

    if (scrollRadio) scrollRadio.addEventListener('change', syncScrollRow);
    if (productRadio) productRadio.addEventListener('change', syncScrollRow);
    syncScrollRow();
})();
</script>

<style>
.ab-enhanced-block.is-hidden { display: none; }
.ab-settings-page .card { max-width: 720px; }
.ab-settings-page .form-check-label { line-height: 1.45; }
</style>

<?php include 'admin_footer_common.php'; ?>
