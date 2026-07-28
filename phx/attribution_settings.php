<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__.'/../includes/attribution_helpers.php';
require_once __DIR__.'/../includes/site_helpers.php';

$page_title = 'Kampanya / UTM ayarları';

$siteUrl = site_public_url($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_utm'])) {
            $en = isset($_POST['utm_capture_enabled']) ? 1 : 0;
            $pdo->prepare('UPDATE checkout_module_settings SET utm_capture_enabled = ? WHERE id = 1')->execute([$en]);
            $_SESSION['message'] = 'Kayıt tercihi kaydedildi.';
            $_SESSION['message_type'] = 'success';
        } elseif (isset($_POST['add_utm_link'])) {
            $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 128);
            $landing = mb_substr(trim((string) ($_POST['landing_path'] ?? '/')), 0, 512);
            $utmSource = mb_substr(trim((string) ($_POST['utm_source'] ?? '')), 0, 255);
            $utmMedium = mb_substr(trim((string) ($_POST['utm_medium'] ?? '')), 0, 255);
            $utmCampaign = mb_substr(trim((string) ($_POST['utm_campaign'] ?? '')), 0, 255);
            $utmContent = mb_substr(trim((string) ($_POST['utm_content'] ?? '')), 0, 255);
            $utmTerm = mb_substr(trim((string) ($_POST['utm_term'] ?? '')), 0, 255);

            if ($label === '' || $utmCampaign === '') {
                throw new RuntimeException('Etiket ve utm_campaign zorunludur.');
            }
            if ($landing === '') {
                $landing = '/';
            }
            if (! str_starts_with($landing, '/')) {
                $landing = '/'.$landing;
            }

            $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM utm_campaign_links')->fetchColumn();
            $pdo->prepare(
                'INSERT INTO utm_campaign_links (label, landing_path, utm_source, utm_medium, utm_campaign, utm_content, utm_term, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $label,
                $landing,
                $utmSource !== '' ? $utmSource : null,
                $utmMedium !== '' ? $utmMedium : null,
                $utmCampaign,
                $utmContent !== '' ? $utmContent : null,
                $utmTerm !== '' ? $utmTerm : null,
                $maxSort + 1,
            ]);
            $_SESSION['message'] = 'Reklam linki eklendi.';
            $_SESSION['message_type'] = 'success';
        } elseif (isset($_POST['delete_utm_link'])) {
            $id = (int) ($_POST['link_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM utm_campaign_links WHERE id = ?')->execute([$id]);
                $_SESSION['message'] = 'Link silindi.';
                $_SESSION['message_type'] = 'success';
            }
        } elseif (isset($_POST['toggle_utm_link'])) {
            $id = (int) ($_POST['link_id'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($id > 0) {
                $pdo->prepare('UPDATE utm_campaign_links SET is_active = ? WHERE id = ?')->execute([$active, $id]);
                $_SESSION['message'] = 'Link durumu güncellendi.';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Hata: '.$e->getMessage();
        $_SESSION['message_type'] = 'error';
    }

    header('Location: attribution_settings.php');
    exit;
}

$utm = 1;
try {
    $utm = (int) $pdo->query('SELECT COALESCE(utm_capture_enabled,1) FROM checkout_module_settings WHERE id = 1')->fetchColumn();
} catch (Throwable $e) {
    $utm = 1;
}

$utmLinks = [];
try {
    $utmLinks = $pdo->query(
        'SELECT * FROM utm_campaign_links ORDER BY sort_order ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $utmLinks = [];
}

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (! empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?> border-0 shadow-sm"><?= htmlspecialchars((string) $_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-bullhorn text-primary"></i> Kampanya / UTM</h1>
            <p class="text-muted small mb-0">Reklam linklerini buradan üretin; siparişe hangi kampanyadan gelindiği yazılır.</p>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#utmHelpModal">
            <i class="fas fa-question-circle me-1"></i> Nasıl kullanılır?
        </button>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <span class="fw-semibold text-secondary"><i class="fas fa-sliders-h me-2 text-primary"></i>Kayıt tercihi</span>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="save_utm" value="1">
                        <div class="p-3 rounded-3 bg-light border">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="utm_capture_enabled" id="utm_capture_enabled" value="1" <?= $utm === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label fw-medium" for="utm_capture_enabled">UTM ve tıklama kimliklerini siparişe kaydet</label>
                            </div>
                            <p class="small text-muted mb-0 mt-2">Kapalıyken sipariş satırına ve panele UTM yazılmaz.</p>
                        </div>
                        <button type="submit" class="btn btn-primary align-self-start"><i class="fas fa-save me-1"></i> Kaydet</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-bottom py-3">
                    <span class="fw-semibold text-secondary"><i class="fas fa-plus-circle me-2 text-success"></i>Yeni reklam linki</span>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <input type="hidden" name="add_utm_link" value="1">
                        <div class="col-12">
                            <label class="form-label small mb-0">Etiket (iç kullanım) *</label>
                            <input type="text" name="label" class="form-control form-control-sm" required maxlength="128" placeholder="Meta — Kreatif A">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-0">Hedef sayfa</label>
                            <input type="text" name="landing_path" class="form-control form-control-sm" value="/" placeholder="/ veya /order.php?product_id=1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0">utm_source</label>
                            <input type="text" name="utm_source" class="form-control form-control-sm" placeholder="facebook">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0">utm_medium</label>
                            <input type="text" name="utm_medium" class="form-control form-control-sm" placeholder="cpc">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-0">utm_campaign *</label>
                            <input type="text" name="utm_campaign" class="form-control form-control-sm" required maxlength="255" placeholder="yaz_kampanya_2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0">utm_content (kreatif)</label>
                            <input type="text" name="utm_content" class="form-control form-control-sm" maxlength="255" placeholder="video_v1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0">utm_term</label>
                            <input type="text" name="utm_term" class="form-control form-control-sm" maxlength="255">
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-link me-1"></i> Link ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold text-secondary"><i class="fas fa-list me-2 text-primary"></i>Reklam linkleri (<?= count($utmLinks) ?>)</span>
                    <a href="attribution_orders.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-table me-1"></i> Kampanya siparişleri</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($utmLinks === []): ?>
                        <p class="text-muted small p-3 mb-0">Henüz link yok. Sol taraftan istediğiniz kadar kreatif linki ekleyebilirsiniz.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Etiket</th>
                                        <th>utm_campaign</th>
                                        <th>utm_content</th>
                                        <th class="text-nowrap">Tam link</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utmLinks as $link): ?>
                                        <?php
                                        $fullUrl = attribution_build_tracking_url($siteUrl, $link);
                                        $active = (int) ($link['is_active'] ?? 1) === 1;
                                        ?>
                                        <tr class="<?= $active ? '' : 'table-secondary opacity-75' ?>">
                                            <td class="small fw-medium"><?= htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small font-monospace"><?= htmlspecialchars((string) ($link['utm_campaign'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small font-monospace"><?= htmlspecialchars((string) ($link['utm_content'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small" style="max-width:220px;">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control font-monospace utm-copy-src" readonly value="<?= htmlspecialchars($fullUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="button" class="btn btn-outline-primary utm-copy-btn" title="Kopyala"><i class="fas fa-copy"></i></button>
                                                </div>
                                            </td>
                                            <td class="text-nowrap">
                                                <form method="post" class="d-inline" onsubmit="return confirm('Silinsin mi?');">
                                                    <input type="hidden" name="delete_utm_link" value="1">
                                                    <input type="hidden" name="link_id" value="<?= (int) ($link['id'] ?? 0) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="utmHelpModal" tabindex="-1" aria-labelledby="utmHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="utmHelpModalLabel">UTM linkleri nasıl kullanılır?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body small">
                <p><strong>1.</strong> Sol taraftan bir reklam linki ekleyin. Her kreatif veya kampanya için ayrı satır açın — örneğin Meta video A, Meta görsel B.</p>
                <p><strong>2.</strong> Tabloda oluşan <strong>Tam link</strong> satırını kopyalayıp reklam panelindeki hedef URL alanına yapıştırın.</p>
                <p><strong>3.</strong> Müşteri o linkle siteye girince kaynak bilgisi tarayıcıda tutulur. Sipariş verdiğinde aynı bilgi sipariş kaydına geçer.</p>
                <p><strong>4.</strong> <a href="attribution_orders.php">Kampanya siparişleri</a> ekranından hangi kampanyadan kaç sipariş geldiğini görürsünüz.</p>
                <p class="mb-0 text-muted">Site adresi otomatik alınır: <code><?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.utm-copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = btn.closest('.input-group').querySelector('.utm-copy-src');
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(function () {
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
            setTimeout(function () {
                btn.classList.add('btn-outline-primary');
                btn.classList.remove('btn-success');
            }, 1200);
        });
    });
});
</script>

<?php include 'admin_footer_common.php'; ?>
