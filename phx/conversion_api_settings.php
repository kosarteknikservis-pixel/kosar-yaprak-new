<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare(
            'UPDATE conversion_api_settings SET
                meta_pixel_id = ?, meta_capi_access_token = ?, meta_capi_test_code = ?, meta_events_enabled = ?,
                ga4_measurement_id = ?, ga4_api_secret = ?, ga4_mp_enabled = ?,
                tiktok_pixel_id = ?, tiktok_events_api_token = ?, tiktok_events_enabled = ?,
                google_gtag_measurement_id = ?, google_ads_conversion_id = ?, google_ads_conversion_label = ?, google_ads_conversion_enabled = ?,
                head_snippet = ?, body_snippet = ?,
                yandex_metrica_counter_id = ?, microsoft_clarity_project_id = ?
            WHERE id = 1'
        );
        $stmt->execute([
            trim($_POST['meta_pixel_id'] ?? ''),
            $_POST['meta_capi_access_token'] ?? '',
            trim($_POST['meta_capi_test_code'] ?? ''),
            isset($_POST['meta_events_enabled']) ? 1 : 0,
            trim($_POST['ga4_measurement_id'] ?? ''),
            trim($_POST['ga4_api_secret'] ?? ''),
            isset($_POST['ga4_mp_enabled']) ? 1 : 0,
            trim($_POST['tiktok_pixel_id'] ?? ''),
            $_POST['tiktok_events_api_token'] ?? '',
            isset($_POST['tiktok_events_enabled']) ? 1 : 0,
            trim($_POST['google_gtag_measurement_id'] ?? ''),
            preg_replace('/^AW-/i', '', trim($_POST['google_ads_conversion_id'] ?? '')),
            trim($_POST['google_ads_conversion_label'] ?? ''),
            isset($_POST['google_ads_conversion_enabled']) ? 1 : 0,
            $_POST['head_snippet'] ?? '',
            $_POST['body_snippet'] ?? '',
            preg_replace('/\D+/', '', trim($_POST['yandex_metrica_counter_id'] ?? '')),
            preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['microsoft_clarity_project_id'] ?? '')),
        ]);
        $_SESSION['message'] = 'Dönüşüm ayarları kaydedildi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Kayıt sırasında hata: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: conversion_api_settings.php');
    exit;
}

$r = $pdo->query('SELECT * FROM conversion_api_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'Dönüşüm API';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="admin-page-intro">
        <h1><i class="fas fa-bullseye text-danger"></i> Dönüşüm API ve piksel ayarları</h1>
        <p class="lead">Türkiye vitrini için Meta, Google ve TikTok dönüşümleri. Tutarlar <strong>₺ (TRY)</strong> olarak teşekkür sayfasında gönderilir.</p>
    </div>

    <div class="admin-locale-note">
        <i class="fas fa-lira-sign"></i>
        <span>Sunucu tarafı satın alma olayı: <code>thankyou</code> sayfası · Para birimi: <strong>TRY</strong> · Ondalık ayırıcı: virgül (Türkiye)</span>
    </div>

    <form method="post" class="admin-section-stack">
        <section class="admin-section-card">
            <div class="admin-section-card__head">
                <h2 class="admin-section-card__title"><i class="fab fa-facebook"></i> Meta (Facebook / Instagram)</h2>
                <span class="admin-badge-tr">CAPI + Piksel</span>
            </div>
            <div class="admin-section-card__body">
                <div class="admin-field-grid">
                    <div class="admin-field">
                        <label for="meta_pixel_id">Piksel kimliği</label>
                        <input type="text" id="meta_pixel_id" name="meta_pixel_id" class="form-control" value="<?= htmlspecialchars((string) ($r['meta_pixel_id'] ?? '')) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="meta_capi_test_code">Test olay kodu</label>
                        <input type="text" id="meta_capi_test_code" name="meta_capi_test_code" class="form-control" value="<?= htmlspecialchars((string) ($r['meta_capi_test_code'] ?? '')) ?>">
                    </div>
                    <div class="admin-field" style="grid-column:1/-1">
                        <label for="meta_capi_access_token">CAPI erişim jetonu</label>
                        <textarea id="meta_capi_access_token" name="meta_capi_access_token" class="form-control font-monospace" rows="2"><?= htmlspecialchars((string) ($r['meta_capi_access_token'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="admin-switch-row mt-3">
                    <div>
                        <strong><?= admin_tr_conversion_label('purchase') ?></strong>
                        <div class="small text-muted">Sunucudan doğrulanmış satın alma gönderimi</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="meta_events_enabled" id="m" <?= !empty((int) $r['meta_events_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="m">Açık</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-section-card">
            <div class="admin-section-card__head">
                <h2 class="admin-section-card__title"><i class="fab fa-google"></i> Google Analytics 4</h2>
                <p class="admin-section-card__hint">Measurement Protocol — ölçüm kimliği G- ile başlar</p>
            </div>
            <div class="admin-section-card__body">
                <div class="admin-field-grid">
                    <div class="admin-field">
                        <label for="ga4_measurement_id">Ölçüm kimliği (G-…)</label>
                        <input type="text" id="ga4_measurement_id" name="ga4_measurement_id" class="form-control" value="<?= htmlspecialchars((string) ($r['ga4_measurement_id'] ?? '')) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="ga4_api_secret">API gizli anahtarı</label>
                        <input type="text" id="ga4_api_secret" name="ga4_api_secret" class="form-control font-monospace" value="<?= htmlspecialchars((string) ($r['ga4_api_secret'] ?? '')) ?>">
                    </div>
                </div>
                <div class="admin-switch-row mt-3">
                    <strong>Satın alma (purchase) — sunucu</strong>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ga4_mp_enabled" id="ga" <?= !empty((int) $r['ga4_mp_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ga">Açık</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-section-card">
            <div class="admin-section-card__head">
                <h2 class="admin-section-card__title"><i class="fab fa-google"></i> Google Ads dönüşümü</h2>
            </div>
            <div class="admin-section-card__body">
                <div class="admin-field-grid">
                    <div class="admin-field">
                        <label for="google_gtag_measurement_id">gtag ölçüm kimliği</label>
                        <input type="text" id="google_gtag_measurement_id" name="google_gtag_measurement_id" class="form-control" value="<?= htmlspecialchars((string) ($r['google_gtag_measurement_id'] ?? '')) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="google_ads_conversion_id">Dönüşüm kimliği (AW-…)</label>
                        <input type="text" id="google_ads_conversion_id" name="google_ads_conversion_id" class="form-control" placeholder="AW- veya rakamlar" value="<?= htmlspecialchars((string) ($r['google_ads_conversion_id'] ?? '')) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="google_ads_conversion_label">Dönüşüm etiketi</label>
                        <input type="text" id="google_ads_conversion_label" name="google_ads_conversion_label" class="form-control" value="<?= htmlspecialchars((string) ($r['google_ads_conversion_label'] ?? '')) ?>">
                    </div>
                </div>
                <div class="admin-switch-row mt-3">
                    <strong>Teşekkür sayfasında dönüşüm etiketi</strong>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="google_ads_conversion_enabled" id="gas" <?= !empty((int) $r['google_ads_conversion_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gas">Açık</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-section-card">
            <div class="admin-section-card__head">
                <h2 class="admin-section-card__title"><i class="fab fa-tiktok"></i> TikTok</h2>
            </div>
            <div class="admin-section-card__body">
                <div class="admin-field-grid">
                    <div class="admin-field">
                        <label for="tiktok_pixel_id">Piksel kimliği</label>
                        <input type="text" id="tiktok_pixel_id" name="tiktok_pixel_id" class="form-control" value="<?= htmlspecialchars((string) ($r['tiktok_pixel_id'] ?? '')) ?>">
                    </div>
                    <div class="admin-field" style="grid-column:1/-1">
                        <label for="tiktok_events_api_token">Events API jetonu</label>
                        <textarea id="tiktok_events_api_token" name="tiktok_events_api_token" class="form-control font-monospace" rows="2"><?= htmlspecialchars((string) ($r['tiktok_events_api_token'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="admin-switch-row mt-3">
                    <strong><?= admin_tr_conversion_label('complete_payment') ?></strong>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="tiktok_events_enabled" id="tt" <?= !empty((int) $r['tiktok_events_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tt">Açık</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-section-card">
            <div class="admin-section-card__head">
                <h2 class="admin-section-card__title"><i class="fas fa-code"></i> Ek izleme kodları</h2>
                <p class="admin-section-card__hint">İsteğe bağlı — Yandex / Clarity Türkiye dışı trafik için</p>
            </div>
            <div class="admin-section-card__body">
                <div class="admin-field-grid">
                    <div class="admin-field">
                        <label for="yandex_metrica_counter_id">Yandex.Metrica sayaç no</label>
                        <input type="text" id="yandex_metrica_counter_id" name="yandex_metrica_counter_id" class="form-control" maxlength="24" value="<?= htmlspecialchars((string) ($r['yandex_metrica_counter_id'] ?? '')) ?>">
                    </div>
                    <div class="admin-field">
                        <label for="microsoft_clarity_project_id">Microsoft Clarity proje kodu</label>
                        <input type="text" id="microsoft_clarity_project_id" name="microsoft_clarity_project_id" class="form-control" maxlength="64" value="<?= htmlspecialchars((string) ($r['microsoft_clarity_project_id'] ?? '')) ?>">
                    </div>
                </div>
                <hr>
                <p class="text-muted small">Piksel kimlikleri doluysa aynı kodu snippet içinde tekrarlamayın. Analitik panel kodunu <strong>head snippet</strong> alanına yapıştırabilirsiniz.</p>
                <div class="admin-field mt-2">
                    <label for="head_snippet">&lt;head&gt; ek kodları</label>
                    <textarea id="head_snippet" name="head_snippet" class="form-control font-monospace small" rows="4"><?= htmlspecialchars((string) ($r['head_snippet'] ?? '')) ?></textarea>
                </div>
                <div class="admin-field mt-3">
                    <label for="body_snippet">&lt;body&gt; bitiş öncesi kod</label>
                    <textarea id="body_snippet" name="body_snippet" class="form-control font-monospace small" rows="3"><?= htmlspecialchars((string) ($r['body_snippet'] ?? '')) ?></textarea>
                </div>
            </div>
        </section>

        <div>
            <button class="btn btn-primary btn-lg" type="submit"><i class="fas fa-save"></i> Tüm ayarları kaydet</button>
        </div>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
