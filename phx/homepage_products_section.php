<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/i18n.php';

$page_title = 'Ana sayfa ürün bölümü';

/** @disregard yalın hex veya güvenli font stack */
function hp_normalize_color(string $raw, string $fallback): string
{
    $t = trim($raw);

    return preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $t) ? $t : $fallback;
}

/** @disregard font-family değeri; tehlikeli karakterleri sök */
function hp_font_stack(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return null;
    }

    $t = str_replace(["\r", "\n", '<', '>', ';'], '', $t);

    return mb_substr($t, 0, 420);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare(
            'UPDATE homepage_product_section SET
                section_enabled = ?,
                show_heading = ?,
                show_heading_main = ?,
                show_heading_sub = ?,
                heading_main = ?,
                heading_sub = ?,
                heading_main_color = ?,
                heading_sub_color = ?,
                heading_main_font = ?,
                heading_sub_font = ?,
                card_name_color = ?,
                card_description_color = ?,
                card_original_price_color = ?,
                card_sale_price_color = ?,
                cta_bg_color = ?
            WHERE id = 1'
        );

        $stmt->execute([
            isset($_POST['section_enabled']) ? 1 : 0,
            isset($_POST['show_heading']) ? 1 : 0,
            isset($_POST['show_heading_main']) ? 1 : 0,
            isset($_POST['show_heading_sub']) ? 1 : 0,
            trim($_POST['heading_main'] ?? ''),
            trim($_POST['heading_sub'] ?? ''),
            hp_normalize_color(trim($_POST['heading_main_color'] ?? ''), '#f97316'),
            hp_normalize_color(trim($_POST['heading_sub_color'] ?? ''), '#283458'),
            hp_font_stack(trim($_POST['heading_main_font'] ?? '')),
            hp_font_stack(trim($_POST['heading_sub_font'] ?? '')),
            hp_normalize_color(trim($_POST['card_name_color'] ?? ''), '#15803d'),
            hp_normalize_color(trim($_POST['card_description_color'] ?? ''), '#374151'),
            hp_normalize_color(trim($_POST['card_original_price_color'] ?? ''), '#6b7280'),
            hp_normalize_color(trim($_POST['card_sale_price_color'] ?? ''), '#15803d'),
            hp_normalize_color(trim($_POST['cta_bg_color'] ?? ''), '#5fbd0f'),
        ]);

        foreach (['en', 'ar'] as $langCode) {
            content_t_save($pdo, 'homepage_section', 1, 'heading_main', $langCode, (string) ($_POST['heading_main_' . $langCode] ?? ''));
            content_t_save($pdo, 'homepage_section', 1, 'heading_sub', $langCode, (string) ($_POST['heading_sub_' . $langCode] ?? ''));
        }

        $_SESSION['message'] = 'Ana sayfa ürün bölümü kaydedildi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Kayıt hatası: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }

    header('Location: homepage_products_section.php');
    exit;
}

$r = $pdo->query('SELECT * FROM homepage_product_section WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$hpTrEn = content_t_load_lang($pdo, 'homepage_section', 1, 'en');
$hpTrAr = content_t_load_lang($pdo, 'homepage_section', 1, 'ar');

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2"><i class="fas fa-store text-primary"></i> Ana sayfa — ürün listesi &amp; başlık</h1>
    <p class="text-muted small mb-4">Başlık metinleri, renkler ve kart üzerindeki yazı renkleri buradan yönetilir. Metinler boşsa <code>footer_images</code> (id=8) eski alanlarına düşülür (yalnızca ön yüz).</p>

    <form method="post" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="section_enabled" id="section_enabled" <?= !empty((int) ($r['section_enabled'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="section_enabled">Ürün bölümünü göster</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="show_heading" id="show_heading" <?= !empty((int) ($r['show_heading'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_heading">Başlık alanını göster</label>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="show_heading_main" id="show_heading_main" <?= !empty((int) ($r['show_heading_main'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_heading_main">1. satır</label>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="show_heading_sub" id="show_heading_sub" <?= !empty((int) ($r['show_heading_sub'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_heading_sub">2. satır</label>
                    </div>
                </div>
            </div>

            <h2 class="h6 border-bottom pb-2">Başlık metinleri</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small">Üst satır (vurgulu)</label>
                    <input type="text" name="heading_main" class="form-control" value="<?= htmlspecialchars((string) ($r['heading_main'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Alt satır</label>
                    <input type="text" name="heading_sub" class="form-control" value="<?= htmlspecialchars((string) ($r['heading_sub'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Üst satır rengi</label>
                    <input type="text" name="heading_main_color" class="form-control" value="<?= htmlspecialchars((string) ($r['heading_main_color'] ?? '#f97316')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Alt satır rengi</label>
                    <input type="text" name="heading_sub_color" class="form-control" value="<?= htmlspecialchars((string) ($r['heading_sub_color'] ?? '#283458')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Üst satır font (isteğe bağlı, örn: <code>system-ui,sans-serif</code>)</label>
                    <input type="text" name="heading_main_font" class="form-control" value="<?= htmlspecialchars((string) ($r['heading_main_font'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Alt satır font (isteğe bağlı)</label>
                    <input type="text" name="heading_sub_font" class="form-control" value="<?= htmlspecialchars((string) ($r['heading_sub_font'] ?? '')) ?>">
                </div>
            </div>

            <h2 class="h6 border-bottom pb-2 mt-4">Yurtdışı dil (müşteri vitrini)</h2>
            <p class="text-muted small mb-3">İngilizce veya Arapça varsayılan dil seçildiğinde bu başlıklar kullanılır. Boş bırakılırsa Türkçe metin gösterilir. <a href="languages.php">Diller &amp; Para</a></p>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small">Üst satır (EN)</label>
                    <input type="text" name="heading_main_en" class="form-control" value="<?= htmlspecialchars((string) ($hpTrEn['heading_main'] ?? '')) ?>" placeholder="Featured Products">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Alt satır (EN)</label>
                    <input type="text" name="heading_sub_en" class="form-control" value="<?= htmlspecialchars((string) ($hpTrEn['heading_sub'] ?? '')) ?>" placeholder="Discounted Prices">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Üst satır (AR)</label>
                    <input type="text" name="heading_main_ar" class="form-control" value="<?= htmlspecialchars((string) ($hpTrAr['heading_main'] ?? '')) ?>" dir="rtl">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Alt satır (AR)</label>
                    <input type="text" name="heading_sub_ar" class="form-control" value="<?= htmlspecialchars((string) ($hpTrAr['heading_sub'] ?? '')) ?>" dir="rtl">
                </div>
            </div>

            <h2 class="h6 border-bottom pb-2">Ürün kartı renkleri</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">Ürün adı</label>
                    <input type="text" name="card_name_color" class="form-control" value="<?= htmlspecialchars((string) ($r['card_name_color'] ?? '#15803d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Açıklama</label>
                    <input type="text" name="card_description_color" class="form-control" value="<?= htmlspecialchars((string) ($r['card_description_color'] ?? '#374151')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">&quot;Hemen sipariş&quot; buton arka planı</label>
                    <input type="text" name="cta_bg_color" class="form-control" value="<?= htmlspecialchars((string) ($r['cta_bg_color'] ?? '#5fbd0f')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Üstü çizili fiyat</label>
                    <input type="text" name="card_original_price_color" class="form-control" value="<?= htmlspecialchars((string) ($r['card_original_price_color'] ?? '#6b7280')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">İndirimli fiyat</label>
                    <input type="text" name="card_sale_price_color" class="form-control" value="<?= htmlspecialchars((string) ($r['card_sale_price_color'] ?? '#15803d')) ?>">
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Kaydet</button>
            </div>
        </div>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
