<?php

declare(strict_types=1);

define('INSTALL_GUARD_SKIP', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = null;
$dbConnectError = null;
try {
    require __DIR__ . '/db.php';
} catch (Throwable $e) {
    $dbConnectError = $e->getMessage();
}

require_once __DIR__ . '/includes/install_service.php';
require_once __DIR__ . '/includes/site_helpers.php';

date_default_timezone_set('Europe/Istanbul');

$panelConfigPath = __DIR__ . '/includes/laravel4_config.php';

if ($pdo instanceof PDO) {
    install_ensure_schema($pdo);
}

// Panel adresi erişilebilirlik testi (arayüzdeki "Bağlantıyı test et" butonu çağırır).
if (($_GET['action'] ?? '') === 'test_panel') {
    header('Content-Type: application/json; charset=utf-8');
    $testUrl = (string) ($_POST['panel_base_url'] ?? '');
    $testKey = (string) ($_POST['panel_order_api_key'] ?? '');
    echo json_encode(install_test_panel_connection($testUrl, $testKey), JSON_UNESCAPED_UNICODE);
    exit;
}

$installReqs = install_server_requirements($pdo, $dbConnectError, __DIR__);
$installReqsOk = install_requirements_met($installReqs);

if (isset($_GET['reinstall']) && $pdo instanceof PDO && install_is_complete($pdo)) {
    $pdo->exec('UPDATE site_install SET is_complete = 0, completed_at = NULL WHERE id = 1');
    header('Location: install.php?step=2');
    exit;
}

$step = max(1, min(2, (int) ($_GET['step'] ?? 1)));
$error = '';

if ($step === 2 && ! $installReqsOk) {
    $step = 1;
    $error = 'Zorunlu sunucu gereksinimleri karşılanmıyor. Eksik satırları giderip tekrar deneyin.';
}

if ($pdo instanceof PDO && install_is_complete($pdo) && !isset($_GET['reinstall'])) {
    $step = 0;
}

$panelCfg = install_read_laravel4_config($panelConfigPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_run'])) {
    if (! $installReqsOk || ! ($pdo instanceof PDO)) {
        $error = 'Kurulum tamamlanamadı: sunucu gereksinimleri veya veritabanı bağlantısı eksik.';
        $step = 1;
    } else {
        $baseTitle = trim((string) ($_POST['site_base_title'] ?? ''));
        $logoMain = trim((string) ($_POST['logo_main'] ?? ''));
        $logoSub = trim((string) ($_POST['logo_sub'] ?? ''));
        $hpMain = trim((string) ($_POST['hp_main'] ?? ''));
        $hpSub = trim((string) ($_POST['hp_sub'] ?? ''));
        $metaMode = (string) ($_POST['meta_mode'] ?? 'auto');

        if ($baseTitle === '') {
            $error = 'Site başlığı (SEO tabanı) zorunludur.';
            $step = 2;
        } else {
            try {
                $wipeOpts = [
                    'orders' => isset($_POST['wipe_orders']),
                    'yarim_kalanlar' => isset($_POST['wipe_yarim']),
                    'support_requests' => isset($_POST['wipe_support']),
                    'dealer_requests' => isset($_POST['wipe_dealer']),
                    'page_views' => isset($_POST['wipe_views']),
                    'carkifelek_log' => isset($_POST['wipe_cark']),
                    'social_clicks' => isset($_POST['wipe_social']),
                    'cloaker_traffic' => isset($_POST['wipe_cloaker_traffic']),
                    'cloaker_stats' => isset($_POST['wipe_cloaker_stats']),
                    'link_cloak' => isset($_POST['wipe_link_cloak']),
                    'login_logs' => isset($_POST['wipe_logs']),
                    'product_reviews' => isset($_POST['wipe_reviews']),
                ];
                if (isset($_POST['wipe_all'])) {
                    foreach ($wipeOpts as $k => $_) {
                        $wipeOpts[$k] = true;
                    }
                }
                install_wipe_operational($pdo, $wipeOpts);

                install_apply_branding($pdo, $logoMain, $logoSub, $hpMain, $hpSub);

                // Varsayılan dil & para birimi (i18n)
                $dlang = strtolower(preg_replace('/[^a-z]/i', '', (string) ($_POST['default_lang'] ?? '')));
                if ($dlang !== '') {
                    try {
                        $pdo->exec('UPDATE site_languages SET is_default = 0');
                        $pdo->prepare('UPDATE site_languages SET is_default = 1, is_active = 1 WHERE code = ?')->execute([$dlang]);
                    } catch (Throwable $e) { /* tablo yoksa yoksay */ }
                }
                $dcur = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['default_cur'] ?? '')));
                if ($dcur !== '') {
                    try {
                        $pdo->exec('UPDATE site_currencies SET is_default = 0');
                        $pdo->prepare('UPDATE site_currencies SET is_default = 1, is_active = 1 WHERE code = ?')->execute([$dcur]);
                    } catch (Throwable $e) { /* tablo yoksa yoksay */ }
                }

                $pageTitles = install_default_page_titles($baseTitle);
                $metaDesc = install_default_meta_descriptions($baseTitle);

                if ($metaMode === 'manual') {
                    foreach (array_keys($pageTitles) as $pg) {
                        $tKey = 'page_title_' . md5($pg);
                        $dKey = 'page_desc_' . md5($pg);
                        $customTitle = trim((string) ($_POST[$tKey] ?? ''));
                        $customDesc = trim((string) ($_POST[$dKey] ?? ''));
                        if ($customTitle !== '') {
                            $pageTitles[$pg] = $customTitle;
                        }
                        if ($customDesc !== '') {
                            $metaDesc[$pg] = $customDesc;
                        }
                    }
                }

                install_apply_page_meta($pdo, $pageTitles, $metaDesc);

                // Ortak panel (YQ Panel) bağlantısı — kutucuk kapalıysa API anahtarı boş yazılır (senkron pasif).
                $panelEnabled = isset($_POST['panel_enabled']);
                $panelData = [
                    'panel_base_url' => trim((string) ($_POST['panel_base_url'] ?? '')),
                    'order_api_key' => $panelEnabled ? trim((string) ($_POST['panel_order_api_key'] ?? '')) : '',
                    'order_source_key' => trim((string) ($_POST['panel_order_source_key'] ?? '')),
                    'form_source_key' => trim((string) ($_POST['panel_form_source_key'] ?? '')),
                    'webhook_secret' => trim((string) ($_POST['panel_webhook_secret'] ?? '')),
                ];
                if ($panelData['order_source_key'] === '') {
                    $panelData['order_source_key'] = install_suggest_source_key($baseTitle, 'web');
                }
                if ($panelData['form_source_key'] === '') {
                    $panelData['form_source_key'] = install_suggest_source_key($baseTitle, 'form');
                }
                if ($panelData['panel_base_url'] === '') {
                    $panelData['panel_base_url'] = 'https://qypanel.com';
                }
                install_write_laravel4_config($panelConfigPath, $panelData);

                install_mark_complete($pdo, $baseTitle);
                header('Location: phx/login.php?installed=1');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $step = 2;
            }
        }
    }
}

$postedTitle = trim((string) ($_POST['site_base_title'] ?? ''));
$previewTitles = install_default_page_titles($postedTitle !== '' ? $postedTitle : 'Marka Adı');
$detectedSiteUrl = site_url_detect_from_request();
$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$installLangs = [];
$installCurs = [];
if ($pdo instanceof PDO) {
    try {
        $installLangs = $pdo->query('SELECT code, native_name, name, flag, is_default FROM site_languages WHERE is_active = 1 ORDER BY sort_order, code')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $installLangs = [];
    }
    try {
        $installCurs = $pdo->query('SELECT code, symbol, name, is_default FROM site_currencies WHERE is_active = 1 ORDER BY sort_order, code')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $installCurs = [];
    }
}
$postedLang = strtolower(preg_replace('/[^a-z]/i', '', (string) ($_POST['default_lang'] ?? '')));
$postedCur = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($_POST['default_cur'] ?? '')));

// Alanların ön-dolumu: gönderilmiş değer → mevcut config → varsayılan.
$pv = static function (string $postKey, string $cfgVal = '', string $default = '') use ($esc): string {
    $val = (string) ($_POST[$postKey] ?? '');
    if ($val === '') {
        $val = $cfgVal !== '' ? $cfgVal : $default;
    }
    return $esc($val);
};

$panelBaseVal = $pv('panel_base_url', (string) ($panelCfg['panel_base_url'] ?? ''), 'https://qypanel.com');
$panelKeyVal = $pv('panel_order_api_key', (string) ($panelCfg['order_api_key'] ?? ''));
$panelOrderSrcVal = $pv('panel_order_source_key', (string) ($panelCfg['order_source_key'] ?? ''));
$panelFormSrcVal = $pv('panel_form_source_key', (string) ($panelCfg['form_source_key'] ?? ''));
$panelSecretVal = $pv('panel_webhook_secret', (string) ($panelCfg['webhook_secret'] ?? ''));
$panelEnabledChecked = isset($_POST['install_run'])
    ? isset($_POST['panel_enabled'])
    : trim((string) ($panelCfg['order_api_key'] ?? '')) !== '';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurulum Sihirbazı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --ins: #6366f1; --ins2: #0891b2; }
        body { background: linear-gradient(180deg, #e8edf5 0%, #f4f7fb 40%, #f8fafc 100%); min-height: 100vh; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .install-wrap { max-width: 900px; margin: 0 auto; padding: 32px 16px 64px; }
        .install-card { background: #fff; border-radius: 18px; box-shadow: 0 18px 48px rgba(40,52,88,.14); padding: 30px 32px; }
        .install-head h1 { font-weight: 800; letter-spacing: -.02em; }
        .brand-badge { width: 54px; height: 54px; border-radius: 15px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; background: linear-gradient(135deg, var(--ins) 0%, var(--ins2) 100%); box-shadow: 0 10px 24px rgba(99,102,241,.4); }
        .step-pill { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
        .meta-preview { max-height: 220px; overflow: auto; font-size: 13px; }
        .install-req-list { list-style: none; padding: 0; margin: 0 0 1.25rem; }
        .install-req-list li { display: flex; align-items: flex-start; gap: 10px; padding: 11px 13px; border-radius: 11px; margin-bottom: 8px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .install-req-list li.is-ok { border-color: #bbf7d0; background: #f0fdf4; }
        .install-req-list li.is-fail { border-color: #fecaca; background: #fef2f2; }
        .install-req-list li.is-warn { border-color: #fde68a; background: #fffbeb; }
        .install-req-icon { font-size: 1.15rem; line-height: 1.2; flex-shrink: 0; width: 1.35rem; text-align: center; }
        .install-req-body { flex: 1; min-width: 0; }
        .install-req-label { font-weight: 600; font-size: 0.9rem; color: #0f172a; }
        .install-req-detail { font-size: 0.78rem; color: #64748b; margin-top: 2px; word-break: break-word; }
        .install-req-badge { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; }
        /* Sihirbaz adım göstergesi */
        .wiz-stepper { display: flex; gap: 10px; margin-bottom: 26px; }
        .wiz-step { flex: 1; text-align: center; position: relative; }
        .wiz-step .dot { width: 38px; height: 38px; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; background: #e2e8f0; color: #64748b; transition: .25s; }
        .wiz-step .lbl { font-size: .74rem; font-weight: 600; color: #94a3b8; }
        .wiz-step::before { content: ""; position: absolute; top: 19px; left: -50%; width: 100%; height: 3px; background: #e2e8f0; z-index: 0; }
        .wiz-step:first-child::before { display: none; }
        .wiz-step .dot { position: relative; z-index: 1; }
        .wiz-step.is-active .dot { background: linear-gradient(135deg, var(--ins) 0%, #4f46e5 100%); color: #fff; box-shadow: 0 6px 16px rgba(79,70,229,.35); }
        .wiz-step.is-active .lbl { color: var(--ins); }
        .wiz-step.is-done .dot { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); color: #fff; }
        .wiz-step.is-done .lbl { color: #059669; }
        .wiz-step.is-done::before, .wiz-step.is-active::before { background: linear-gradient(90deg, #34d399, var(--ins)); }
        .wiz-pane { display: none; animation: wizIn .28s ease; }
        .wiz-pane.is-active { display: block; }
        @keyframes wizIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .sec-title { font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: .5rem; margin-bottom: .35rem; }
        .sec-title i { color: var(--ins); }
        .panel-toggle { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #f8fafc; }
        .form-control:focus { border-color: rgba(99,102,241,.5); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
        .test-result { font-size: .82rem; margin-top: 8px; min-height: 1.1rem; }
        .field-note { font-size: .76rem; color: #64748b; }
        .btn-grad { background: linear-gradient(135deg, var(--ins) 0%, #4f46e5 100%); border: none; color: #fff; font-weight: 600; }
        .btn-grad:hover { filter: brightness(1.07); color: #fff; }

        /* Mobil uyum */
        @media (max-width: 640px) {
            .install-wrap { padding: 18px 12px 48px; }
            .install-card { padding: 20px 16px; border-radius: 14px; }
            .install-head h1 { font-size: 1.35rem; }
            .wiz-stepper { gap: 4px; margin-bottom: 20px; }
            .wiz-step .dot { width: 30px; height: 30px; font-size: .8rem; margin-bottom: 5px; }
            .wiz-step .lbl { font-size: .6rem; line-height: 1.15; }
            .wiz-step::before { top: 15px; height: 2px; }
            .wiz-nav { flex-direction: column-reverse; gap: 10px; }
            .wiz-nav .btn { width: 100%; }
            .install-card .row > [class*="col-"] { margin-bottom: .35rem; }
            .btn-lg { padding: .6rem 1rem; font-size: 1rem; }
        }
        @media (max-width: 400px) {
            .wiz-step .lbl { display: none; }
        }
    </style>
</head>
<body>
<div class="install-wrap">
    <div class="text-center install-head mb-4">
        <div class="brand-badge mb-3"><i class="fas fa-rocket"></i></div>
        <h1 class="h3 text-dark mb-1">Kurulum Sihirbazı</h1>
        <p class="text-muted mb-0">SQL içe aktarıldı, <code>db.php</code> bağlandı → adımları tamamlayıp siteyi yayına alın.</p>
    </div>

    <?php if ($step === 0): ?>
        <div class="install-card text-center">
            <div class="mb-3"><i class="fas fa-circle-check text-success" style="font-size:2.4rem"></i></div>
            <p class="mb-3 fw-semibold">Kurulum zaten tamamlanmış.</p>
            <a href="index.php" class="btn btn-success me-2"><i class="fas fa-globe me-1"></i>Siteye git</a>
            <a href="phx/login.php" class="btn btn-outline-primary"><i class="fas fa-lock me-1"></i>Panele git</a>
            <p class="mt-4 small text-muted"><a href="install.php?reinstall=1">Kurulumu yeniden çalıştır</a> (dikkatli kullanın)</p>
        </div>
    <?php else: ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-1"></i><?= $esc($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <div class="install-card">
            <p class="step-pill mb-3">Adım 1 / 4 — Sunucu kontrolü</p>
            <h2 class="h5 fw-bold mb-2">Sunucu gereksinimleri</h2>
            <p class="text-muted small mb-3">Kuruluma başlamadan önce sunucu ve PHP ortamı denetlenir. Kırmızı (✗) zorunlu maddeler giderilmeden devam edilemez.</p>

            <ul class="install-req-list">
                <?php foreach ($installReqs as $req): ?>
                    <?php
                    $ok = ! empty($req['ok']);
                    $required = ! empty($req['required']);
                    $rowClass = $ok ? 'is-ok' : ($required ? 'is-fail' : 'is-warn');
                    $icon = $ok
                        ? '<i class="fas fa-check install-req-icon text-success"></i>'
                        : '<i class="fas fa-times install-req-icon text-danger"></i>';
                    if (! $ok && ! $required) {
                        $icon = '<i class="fas fa-exclamation install-req-icon text-warning"></i>';
                    }
                    ?>
                    <li class="<?= $rowClass ?>">
                        <?= $icon ?>
                        <div class="install-req-body">
                            <div class="install-req-label">
                                <?= $esc((string) $req['label']) ?>
                                <?php if (! $required): ?><span class="install-req-badge ms-1">önerilen</span><?php endif; ?>
                            </div>
                            <div class="install-req-detail"><?= $esc((string) $req['detail']) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($installReqsOk): ?>
                <div class="alert alert-success py-2 small mb-3"><i class="fas fa-check-circle me-1"></i> Zorunlu gereksinimler tamam — kuruluma devam edebilirsiniz.</div>
                <a href="install.php?step=2" class="btn btn-grad btn-lg">Devam et <i class="fas fa-arrow-right ms-1"></i></a>
            <?php else: ?>
                <div class="alert alert-danger py-2 small mb-3"><i class="fas fa-times-circle me-1"></i> Eksik zorunlu gereksinim var. Düzelttikten sonra yeniden kontrol edin.</div>
                <a href="install.php?step=1" class="btn btn-outline-secondary"><i class="fas fa-rotate me-1"></i>Yeniden kontrol et</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

    <form method="post" class="install-card" id="wizForm">
        <input type="hidden" name="install_run" value="1">

        <div class="wiz-stepper">
            <div class="wiz-step is-done"><div class="dot"><i class="fas fa-check"></i></div><div class="lbl">Sunucu</div></div>
            <div class="wiz-step is-active" data-goto="0"><div class="dot">2</div><div class="lbl">Site kimliği</div></div>
            <div class="wiz-step" data-goto="1"><div class="dot">3</div><div class="lbl">Ortak panel</div></div>
            <div class="wiz-step" data-goto="2"><div class="dot">4</div><div class="lbl">Veri & bitiş</div></div>
        </div>

        <!-- PANE 1: Site kimliği -->
        <div class="wiz-pane is-active" data-pane="0">
            <h2 class="h5 fw-bold mb-3">Site kimliği & SEO</h2>

            <div class="alert alert-light border mb-4">
                <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-link text-success mt-1"></i>
                    <div>
                        <strong>Algılanan site adresi</strong>
                        <div class="font-monospace small text-primary"><?= $esc($detectedSiteUrl) ?></div>
                        <div class="field-note mb-0">Kurulum bitince <code>settings.site_url</code> / <code>settings.site_name</code> güncellenir; sayfa görsellerindeki eski localhost adresleri de düzeltilir.</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Site SEO taban başlığı *</label>
                    <input type="text" name="site_base_title" id="site_base_title" class="form-control" required
                           placeholder="Örn: NovaShop — Online Mağaza"
                           value="<?= $esc($postedTitle) ?>">
                    <div class="field-note mt-1">Tüm sayfa başlıkları buna göre üretilir. Panel kaynak anahtarları da bundan önerilir.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo ana metin (header)</label>
                    <input type="text" name="logo_main" class="form-control" placeholder="Nova" value="<?= $esc((string) ($_POST['logo_main'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo alt metin</label>
                    <input type="text" name="logo_sub" class="form-control" placeholder="Shop" value="<?= $esc((string) ($_POST['logo_sub'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ana sayfa ürün başlığı (ana)</label>
                    <input type="text" name="hp_main" class="form-control" placeholder="Öne çıkan ürünler" value="<?= $esc((string) ($_POST['hp_main'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ana sayfa ürün başlığı (alt)</label>
                    <input type="text" name="hp_sub" class="form-control" placeholder="Kampanyalı fiyatlar" value="<?= $esc((string) ($_POST['hp_sub'] ?? '')) ?>">
                </div>
            </div>

            <?php if ($installLangs || $installCurs): ?>
            <hr class="my-4">
            <div class="sec-title"><i class="fas fa-globe"></i> Dil &amp; para birimi</div>
            <p class="field-note mb-3">Ön yüzün varsayılan dilini ve para birimini seçin. Ziyaretçiler menüden değiştirebilir; hepsini kurulumdan sonra <code>phx/languages.php</code> üzerinden yönetebilirsiniz.</p>
            <div class="row g-3">
                <?php if ($installLangs): ?>
                <div class="col-md-6">
                    <label class="form-label">Varsayılan dil</label>
                    <select name="default_lang" class="form-select">
                        <?php foreach ($installLangs as $il): $code = (string) $il['code'];
                            $sel = $postedLang !== '' ? ($postedLang === $code) : ((int) $il['is_default'] === 1); ?>
                            <option value="<?= $esc($code) ?>" <?= $sel ? 'selected' : '' ?>>
                                <?= $esc((string) ($il['flag'] ?? '')) ?> <?= $esc((string) ($il['native_name'] ?: ($il['name'] ?: $code))) ?> (<?= $esc($code) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if ($installCurs): ?>
                <div class="col-md-6">
                    <label class="form-label">Varsayılan para birimi</label>
                    <select name="default_cur" class="form-select">
                        <?php foreach ($installCurs as $ic): $code = (string) $ic['code'];
                            $sel = $postedCur !== '' ? ($postedCur === $code) : ((int) $ic['is_default'] === 1); ?>
                            <option value="<?= $esc($code) ?>" <?= $sel ? 'selected' : '' ?>>
                                <?= $esc((string) ($ic['symbol'] ?? '')) ?> <?= $esc((string) ($ic['name'] ?: $code)) ?> (<?= $esc($code) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mt-4 wiz-nav">
                <a href="install.php?step=1" class="btn btn-link text-muted"><i class="fas fa-arrow-left me-1"></i>Sunucu kontrolü</a>
                <button type="button" class="btn btn-grad wiz-next">İleri <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </div>

        <!-- PANE 2: Ortak panel -->
        <div class="wiz-pane" data-pane="1">
            <h2 class="h5 fw-bold mb-1">Ortak panel (YQ Panel) bağlantısı</h2>
            <p class="text-muted small mb-3">Sipariş, form ve terk edilen sepet verileri bu panele akar. Kapatırsanız site tek başına çalışır, senkron yapılmaz.</p>

            <div class="panel-toggle mb-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="panel_enabled" id="panel_enabled" <?= $panelEnabledChecked ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="panel_enabled">Bu siteyi ortak panele bağla (sipariş & form senkronu)</label>
                </div>
            </div>

            <div id="panelFields">
                <div class="mb-3">
                    <label class="form-label">Panel adresi</label>
                    <input type="url" name="panel_base_url" id="panel_base_url" class="form-control" value="<?= $panelBaseVal ?>" placeholder="https://qypanel.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sipariş API anahtarı <span class="field-note">(panel .env → EXTERNAL_SYNC_API_KEY ile birebir aynı)</span></label>
                    <div class="input-group">
                        <input type="text" name="panel_order_api_key" id="panel_order_api_key" class="form-control font-monospace" value="<?= $panelKeyVal ?>" placeholder="YqExt_...">
                        <button type="button" class="btn btn-outline-secondary" id="togKey" title="Göster/Gizle"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Sipariş kaynak anahtarı <span class="field-note">(site başına farklı)</span></label>
                        <input type="text" name="panel_order_source_key" id="panel_order_source_key" class="form-control" value="<?= $panelOrderSrcVal ?>" placeholder="novashop_web">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Form kaynak anahtarı</label>
                        <input type="text" name="panel_form_source_key" id="panel_form_source_key" class="form-control" value="<?= $panelFormSrcVal ?>" placeholder="novashop_form">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Webhook imza anahtarı <span class="field-note">(form güvenliği — opsiyonel)</span></label>
                        <div class="input-group">
                            <input type="text" name="panel_webhook_secret" id="panel_webhook_secret" class="form-control font-monospace" value="<?= $panelSecretVal ?>" placeholder="(boş bırakılabilir)">
                            <button type="button" class="btn btn-outline-secondary" id="genSecret" title="Rastgele üret"><i class="fas fa-wand-magic-sparkles"></i></button>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="testPanel"><i class="fas fa-plug me-1"></i>Bağlantıyı test et</button>
                    <div class="test-result text-muted" id="testResult"></div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4 wiz-nav">
                <button type="button" class="btn btn-outline-secondary wiz-back"><i class="fas fa-arrow-left me-1"></i>Geri</button>
                <button type="button" class="btn btn-grad wiz-next">İleri <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </div>

        <!-- PANE 3: Veri temizliği & bitiş -->
        <div class="wiz-pane" data-pane="2">
            <h2 class="h5 fw-bold mb-1">Operasyonel veri temizliği</h2>
            <p class="small text-muted mb-2"><strong>Ürünler, slider ve ayar tabloları korunur.</strong> Temiz / sıfır kurulum için tümünü seçili bırakın.</p>

            <div class="form-check mb-3 p-2 border rounded bg-light">
                <input class="form-check-input" type="checkbox" name="wipe_all" id="wipe_all">
                <label class="form-check-label fw-semibold" for="wipe_all">Tümünü seç / kaldır</label>
            </div>
            <div class="row g-2 mb-4" id="wipe-options">
                <?php
                $wipes = [
                    'wipe_orders' => 'Siparişler',
                    'wipe_yarim' => 'Yarım kalanlar',
                    'wipe_support' => 'Destek talepleri',
                    'wipe_dealer' => 'Bayilik başvuruları',
                    'wipe_views' => 'Site hit / ziyaret sayaçları',
                    'wipe_social' => 'Sosyal buton tıklamaları',
                    'wipe_cark' => 'Şans çarkı logları',
                    'wipe_cloaker_traffic' => 'Cloaker trafik logları',
                    'wipe_cloaker_stats' => 'Cloaker istatistik sıfırlama',
                    'wipe_link_cloak' => 'Geçit Merkezi (kampanya + trafik)',
                    'wipe_logs' => 'Giriş / admin logları',
                    'wipe_reviews' => 'Ürün yorumları',
                ];
                foreach ($wipes as $key => $label):
                ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input wipe-opt" type="checkbox" name="<?= $esc($key) ?>" id="<?= $esc($key) ?>" checked>
                        <label class="form-check-label" for="<?= $esc($key) ?>"><?= $esc($label) ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 class="h6 fw-bold mb-2">Sayfa meta (SEO)</h3>
            <div class="mb-3">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="meta_mode" id="meta_auto" value="auto" checked>
                    <label class="form-check-label" for="meta_auto">Otomatik (site başlığından)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="meta_mode" id="meta_manual" value="manual">
                    <label class="form-check-label" for="meta_manual">Manuel düzenle</label>
                </div>
            </div>
            <div class="meta-preview border rounded p-2 mb-3 bg-light">
                <?php foreach ($previewTitles as $pg => $pt): ?>
                    <div class="mb-1"><code><?= $esc($pg) ?></code> → <?= $esc($pt) ?></div>
                <?php endforeach; ?>
            </div>
            <details class="mb-4">
                <summary class="fw-semibold mb-2" style="cursor:pointer">Manuel sayfa başlığı / açıklama (isteğe bağlı)</summary>
                <?php foreach ($previewTitles as $pg => $pt): ?>
                    <?php $th = md5($pg); ?>
                    <div class="border rounded p-2 mb-2">
                        <div class="small fw-bold mb-1"><?= $esc($pg) ?></div>
                        <input type="text" class="form-control form-control-sm mb-1" name="page_title_<?= $esc($th) ?>" placeholder="<?= $esc($pt) ?>">
                        <input type="text" class="form-control form-control-sm" name="page_desc_<?= $esc($th) ?>" placeholder="Meta açıklama">
                    </div>
                <?php endforeach; ?>
            </details>

            <div class="d-flex justify-content-between mt-4 wiz-nav">
                <button type="button" class="btn btn-outline-secondary wiz-back"><i class="fas fa-arrow-left me-1"></i>Geri</button>
                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-check me-1"></i> Kurulumu tamamla</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var form = document.getElementById('wizForm');
    if (!form) return;

    var panes = Array.prototype.slice.call(form.querySelectorAll('.wiz-pane'));
    var steps = Array.prototype.slice.call(document.querySelectorAll('.wiz-stepper .wiz-step'));
    var current = 0;

    function show(idx) {
        if (idx < 0 || idx >= panes.length) return;
        // Site kimliğinden ilerlemek için başlık zorunlu.
        if (idx > 0 && current === 0) {
            var title = document.getElementById('site_base_title');
            if (title && title.value.trim() === '') {
                title.classList.add('is-invalid');
                title.focus();
                return;
            }
        }
        current = idx;
        panes.forEach(function (p, i) { p.classList.toggle('is-active', i === idx); });
        steps.forEach(function (s) {
            var g = s.getAttribute('data-goto');
            if (g === null) return; // Sunucu adımı hep tamamlanmış
            var gi = parseInt(g, 10);
            s.classList.toggle('is-active', gi === idx);
            s.classList.toggle('is-done', gi < idx);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.querySelectorAll('.wiz-next').forEach(function (b) { b.addEventListener('click', function () { show(current + 1); }); });
    form.querySelectorAll('.wiz-back').forEach(function (b) { b.addEventListener('click', function () { show(current - 1); }); });
    steps.forEach(function (s) {
        var g = s.getAttribute('data-goto');
        if (g === null) return;
        s.style.cursor = 'pointer';
        s.addEventListener('click', function () { show(parseInt(g, 10)); });
    });

    // Başlıktan panel kaynak anahtarı önerisi.
    function slug(v) {
        var map = { 'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','Ç':'c','Ğ':'g','İ':'i','Ö':'o','Ş':'s','Ü':'u' };
        v = (v || '').replace(/[çğıöşüÇĞİÖŞÜ]/g, function (m) { return map[m] || m; });
        v = v.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        return v.split('_')[0] || 'site';
    }
    var titleEl = document.getElementById('site_base_title');
    var orderSrc = document.getElementById('panel_order_source_key');
    var formSrc = document.getElementById('panel_form_source_key');
    if (titleEl) {
        titleEl.addEventListener('input', function () {
            titleEl.classList.remove('is-invalid');
            var base = slug(titleEl.value);
            if (orderSrc && orderSrc.dataset.touched !== '1') orderSrc.value = base + '_web';
            if (formSrc && formSrc.dataset.touched !== '1') formSrc.value = base + '_form';
        });
    }
    [orderSrc, formSrc].forEach(function (el) {
        if (el) el.addEventListener('input', function () { el.dataset.touched = '1'; });
    });

    // Panel aç/kapa → alanları etkinleştir.
    var panelEnabled = document.getElementById('panel_enabled');
    var panelFields = document.getElementById('panelFields');
    function syncPanel() {
        if (!panelEnabled || !panelFields) return;
        var on = panelEnabled.checked;
        panelFields.style.opacity = on ? '1' : '.5';
        panelFields.querySelectorAll('input,button').forEach(function (el) {
            if (el.id === 'panel_enabled') return;
            el.disabled = !on;
        });
    }
    if (panelEnabled) { panelEnabled.addEventListener('change', syncPanel); syncPanel(); }

    // API anahtarı göster/gizle.
    var togKey = document.getElementById('togKey');
    var keyEl = document.getElementById('panel_order_api_key');
    if (togKey && keyEl) {
        togKey.addEventListener('click', function () {
            var hidden = keyEl.type === 'password';
            keyEl.type = hidden ? 'text' : 'password';
            togKey.innerHTML = hidden ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });
    }

    // Rastgele webhook imza anahtarı.
    var genSecret = document.getElementById('genSecret');
    var secretEl = document.getElementById('panel_webhook_secret');
    if (genSecret && secretEl) {
        genSecret.addEventListener('click', function () {
            var a = new Uint8Array(24), s = '';
            (window.crypto || {}).getRandomValues ? window.crypto.getRandomValues(a) : a.forEach(function (_, i) { a[i] = Math.floor(Math.random() * 256); });
            for (var i = 0; i < a.length; i++) s += ('0' + a[i].toString(16)).slice(-2);
            secretEl.value = s;
        });
    }

    // Bağlantı testi.
    var testBtn = document.getElementById('testPanel');
    var testOut = document.getElementById('testResult');
    if (testBtn && testOut) {
        testBtn.addEventListener('click', function () {
            var url = document.getElementById('panel_base_url').value;
            var key = keyEl ? keyEl.value : '';
            testBtn.disabled = true;
            testOut.className = 'test-result text-muted';
            testOut.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Test ediliyor…';
            var body = new URLSearchParams();
            body.append('panel_base_url', url);
            body.append('panel_order_api_key', key);
            fetch('install.php?action=test_panel', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    testBtn.disabled = false;
                    testOut.className = 'test-result ' + (d.ok ? 'text-success' : 'text-danger');
                    testOut.innerHTML = '<i class="fas ' + (d.ok ? 'fa-circle-check' : 'fa-circle-xmark') + ' me-1"></i>' + (d.message || '');
                })
                .catch(function () {
                    testBtn.disabled = false;
                    testOut.className = 'test-result text-danger';
                    testOut.innerHTML = '<i class="fas fa-circle-xmark me-1"></i>Test isteği başarısız oldu.';
                });
        });
    }

    // "Tümünü seç" senkronu.
    var master = document.getElementById('wipe_all');
    var items = document.querySelectorAll('.wipe-opt');
    function syncMaster() {
        var allOn = true;
        items.forEach(function (cb) { if (!cb.checked) allOn = false; });
        if (master) master.checked = allOn;
    }
    if (master) {
        master.addEventListener('change', function () {
            items.forEach(function (cb) { cb.checked = master.checked; });
        });
        items.forEach(function (cb) { cb.addEventListener('change', syncMaster); });
        syncMaster();
    }
})();
</script>
</body>
</html>
