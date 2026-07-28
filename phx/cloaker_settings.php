<?php
declare(strict_types=1);

require '../db.php';
require_once '../includes/cloaker_helpers.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cloaker_reset_stats'])) {
        $pdo->exec('UPDATE cloaker_settings SET stat_total = 0, stat_passed = 0, stat_blocked = 0 WHERE id = 1');
        $_SESSION['message'] = 'Cloaker istatistikleri sıfırlandı.';
        $_SESSION['message_type'] = 'success';
        header('Location: cloaker_settings.php');
        exit;
    }

    if (isset($_POST['cloaker_regenerate_gateway'])) {
        $t = bin2hex(random_bytes(8));
        $pdo->prepare('UPDATE cloaker_settings SET gateway_token = ? WHERE id = 1')->execute([$t]);
        $_SESSION['message'] = 'Geçit anahtarı yenilendi. Reklam hedef URL’sini güncelleyin.';
        $_SESSION['message_type'] = 'success';
        header('Location: cloaker_settings.php');
        exit;
    }

    if (isset($_POST['cloaker_sync_site_url'])) {
        $synced = site_public_url($pdo);
        $pdo->prepare('UPDATE cloaker_settings SET public_base_url = ? WHERE id = 1')->execute(['']);
        $_SESSION['message'] = 'Vitrin tabanı site adresinden alınacak: ' . $synced;
        $_SESSION['message_type'] = 'success';
        header('Location: cloaker_settings.php');
        exit;
    }

    $prev = $pdo->query('SELECT * FROM cloaker_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $prevRow = $prev !== false && is_array($prev) ? $prev : [];

    if (isset($_POST['cloaker_profile_traffic']) || isset($_POST['cloaker_profile_ads'])
        || isset($_POST['cloaker_profile_stable']) || isset($_POST['cloaker_profile_strict'])
        || isset($_POST['cloaker_profile_paravan'])) {
        $profile = 'stable';
        if (isset($_POST['cloaker_profile_strict'])) {
            $profile = 'strict';
        } elseif (isset($_POST['cloaker_profile_traffic'])) {
            $profile = 'traffic';
        } elseif (isset($_POST['cloaker_profile_ads'])) {
            $profile = 'ads';
        } elseif (isset($_POST['cloaker_profile_paravan'])) {
            $profile = 'paravan';
        }
        $strict = ($profile === 'strict');
        $_POST['advanced_cloaker'] = '1';
        $_POST['cloak_method'] = '2';
        $_POST['threat_handling'] = 'safe_shadow';
        $_POST['block_known_bots'] = '1';
        $_POST['traffic_log_enabled'] = '1';
        $_POST['respect_benign_bots'] = '1';
        $_POST['block_empty_ua'] = '1';
        $_POST['block_scraper_ua'] = '1';
        $_POST['allowed_countries'] = 'TR';
        $_POST['campaign_mode'] = $profile === 'paravan' ? 'paravan' : 'direct';
        $_POST['gateway_enabled'] = $profile === 'paravan' ? '1' : '0';
        $rk = isset($_POST['ref_key_stable']) ? trim((string) $_POST['ref_key_stable']) : '';
        if ($rk !== '') {
            $_POST['ref_key'] = $rk;
        } elseif (trim((string) ($prevRow['ref_key'] ?? '')) !== '') {
            $_POST['ref_key'] = (string) $prevRow['ref_key'];
        } else {
            $_POST['ref_key'] = 'kampanya';
        }
        if ($profile === 'paravan') {
            $presetKey = trim((string) ($_POST['paravan_preset_key'] ?? 'arabam'));
            $presets = cloaker_paravan_presets();
            if (isset($presets[$presetKey]) && $presetKey !== 'custom') {
                $_POST['paravan_label'] = $presetKey;
                $_POST['paravan_url'] = (string) $presets[$presetKey]['url'];
            } else {
                $_POST['paravan_label'] = 'custom';
                $customPv = trim((string) ($_POST['paravan_url_custom'] ?? ''));
                if ($customPv !== '') {
                    $_POST['paravan_url'] = $customPv;
                } elseif (trim((string) ($prevRow['paravan_url'] ?? '')) !== '') {
                    $_POST['paravan_url'] = (string) $prevRow['paravan_url'];
                }
            }
            $_POST['require_ref'] = '1';
            $_POST['dns_mode'] = 'esnek';
            $_POST['js_mode'] = 'esnek';
            $_POST['geoip_on'] = '0';
            $_POST['blocking_enabled'] = '0';
        } elseif ($profile === 'traffic') {
            $_POST['require_ref'] = '0';
            $_POST['dns_mode'] = 'esnek';
            $_POST['js_mode'] = 'esnek';
            $_POST['geoip_on'] = '0';
            $_POST['blocking_enabled'] = '0';
        } elseif ($profile === 'ads') {
            $_POST['require_ref'] = '1';
            $_POST['dns_mode'] = 'esnek';
            $_POST['js_mode'] = 'esnek';
            $_POST['geoip_on'] = '0';
            $_POST['blocking_enabled'] = '0';
        } else {
            $_POST['require_ref'] = '1';
            $_POST['dns_mode'] = $strict ? 'agresif' : 'esnek';
            $_POST['js_mode'] = $strict ? 'agresif' : 'esnek';
            $_POST['geoip_on'] = $strict ? '1' : '0';
            $_POST['blocking_enabled'] = $strict ? '1' : '0';
        }
    }

    $advancedOn = isset($_POST['advanced_cloaker']);

    $allowedThreat = ['safe_shadow', 'safe_redirect', 'http_403', 'log_only'];
    $thMerged = trim((string) ($advancedOn ? ($_POST['threat_handling'] ?? '') : ($prevRow['threat_handling'] ?? '')));
    if ($thMerged === '') {
        $thMerged = 'safe_shadow';
    }
    $thValidated = in_array($thMerged, $allowedThreat, true) ? $thMerged : 'safe_shadow';

    $publicBaseSave = trim((string) ($_POST['public_base_url'] ?? ''));
    if ($publicBaseSave !== '' && !preg_match('#^https?://#i', $publicBaseSave)) {
        $publicBaseSave = 'https://' . ltrim($publicBaseSave, '/');
    }
    $publicBaseSave = mb_substr($publicBaseSave, 0, 512);

    $campaignMode = trim((string) ($_POST['campaign_mode'] ?? 'direct'));
    if (!in_array($campaignMode, ['direct', 'paravan'], true)) {
        $campaignMode = 'direct';
    }
    $paravanUrl = cloaker_normalize_external_url((string) ($_POST['paravan_url'] ?? ''));
    $paravanLabel = mb_substr(trim((string) ($_POST['paravan_label'] ?? '')), 0, 64);
    $gatewayOn = isset($_POST['gateway_enabled']) ? 1 : 0;

    $stmt = $pdo->prepare(
        'UPDATE cloaker_settings SET
            cloaker_enabled = ?,
            traffic_log_enabled = ?,
            blocking_enabled = ?,
            block_empty_ua = ?,
            block_scraper_ua = ?,
            respect_benign_bots = ?,
            extra_block_substrings = ?,
            advanced_cloaker = ?,
            require_ref = ?,
            ref_key = ?,
            safe_page_target = ?,
            cloak_method = ?,
            threat_handling = ?,
            dns_mode = ?,
            js_mode = ?,
            geoip_on = ?,
            allowed_countries = ?,
            geoip_mmdb_path = ?,
            ip_whitelist = ?,
            ip_blacklist = ?,
            block_known_bots = ?,
            cloaker_exempt_basenames = ?,
            public_base_url = ?,
            campaign_mode = ?,
            paravan_url = ?,
            paravan_label = ?,
            gateway_enabled = ?
         WHERE id = 1'
    );
    $stmt->execute([
        isset($_POST['cloaker_enabled']) ? 1 : 0,
        isset($_POST['traffic_log_enabled']) ? 1 : 0,
        isset($_POST['blocking_enabled']) ? 1 : 0,
        isset($_POST['block_empty_ua']) ? 1 : 0,
        isset($_POST['block_scraper_ua']) ? 1 : 0,
        isset($_POST['respect_benign_bots']) ? 1 : 0,
        (string) ($_POST['extra_block_substrings'] ?? ''),
        $advancedOn ? 1 : 0,
        $advancedOn
            ? (isset($_POST['require_ref']) ? 1 : 0)
            : (int) ($prevRow['require_ref'] ?? 0),
        $advancedOn
            ? trim((string) ($_POST['ref_key'] ?? ''))
            : trim((string) ($prevRow['ref_key'] ?? '')),
        $advancedOn
            ? trim((string) ($_POST['safe_page_target'] ?? 'safe-page.php'))
            : trim((string) ($prevRow['safe_page_target'] ?? 'safe-page.php')),
        $advancedOn
            ? max(1, min(2, (int) ($_POST['cloak_method'] ?? 2)))
            : max(1, min(2, (int) ($prevRow['cloak_method'] ?? 2))),
        $thValidated,
        (($tmp = trim((string) ($advancedOn ? ($_POST['dns_mode'] ?? 'esnek') : ($prevRow['dns_mode'] ?? 'esnek'))))
            && in_array($tmp, ['esnek', 'agresif'], true) ? $tmp : 'esnek'),
        (($tmpJs = trim((string) ($advancedOn ? ($_POST['js_mode'] ?? 'esnek') : ($prevRow['js_mode'] ?? 'esnek'))))
            && in_array($tmpJs, ['esnek', 'agresif'], true) ? $tmpJs : 'esnek'),
        $advancedOn
            ? (isset($_POST['geoip_on']) ? 1 : 0)
            : (int) ($prevRow['geoip_on'] ?? 0),
        $advancedOn
            ? trim((string) ($_POST['allowed_countries'] ?? 'TR'))
            : trim((string) ($prevRow['allowed_countries'] ?? 'TR')),
        $advancedOn
            ? trim((string) ($_POST['geoip_mmdb_path'] ?? ''))
            : trim((string) ($prevRow['geoip_mmdb_path'] ?? '')),
        $advancedOn
            ? (string) ($_POST['ip_whitelist'] ?? '')
            : (string) ($prevRow['ip_whitelist'] ?? ''),
        $advancedOn
            ? (string) ($_POST['ip_blacklist'] ?? '')
            : (string) ($prevRow['ip_blacklist'] ?? ''),
        $advancedOn
            ? (isset($_POST['block_known_bots']) ? 1 : 0)
            : (int) ($prevRow['block_known_bots'] ?? 1),
        $advancedOn
            ? (string) ($_POST['cloaker_exempt_basenames'] ?? '')
            : (string) ($prevRow['cloaker_exempt_basenames'] ?? ''),
        $publicBaseSave,
        $campaignMode,
        $paravanUrl,
        $paravanLabel,
        $gatewayOn,
    ]);

    $cfgAfter = $pdo->query('SELECT * FROM cloaker_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty((int) ($cfgAfter['gateway_enabled'] ?? 0))) {
        cloaker_ensure_gateway_token($pdo, is_array($cfgAfter) ? $cfgAfter : []);
    }

    $_SESSION['message'] = 'Cloaker ayarları güncellendi.';
    $_SESSION['message_type'] = 'success';
    header('Location: cloaker_settings.php');
    exit;
}

$r = $pdo->query('SELECT * FROM cloaker_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$siteUrlAuto = site_public_url($pdo);
$campaignUrlBase = cloaker_resolve_public_base($pdo, $r);
$campaignFull = cloaker_campaign_url($pdo, $r);
$gatewayUrl = !empty((int) ($r['gateway_enabled'] ?? 0)) ? cloaker_gateway_url($pdo, $r) : '';
$activeProfile = cloaker_detect_active_profile($r);
$profileLabels = cloaker_profile_labels();
$paravanPresets = cloaker_paravan_presets();
$storedBaseOverride = trim((string) ($r['public_base_url'] ?? ''));

$cloakerOn = !isset($r['cloaker_enabled']) || (int) ($r['cloaker_enabled'] ?? 1) === 1;
$advancedOn = !empty((int) ($r['advanced_cloaker'] ?? 0));
$paravanMode = (string) ($r['campaign_mode'] ?? 'direct') === 'paravan';
$gatewayOn = !empty((int) ($r['gateway_enabled'] ?? 0));

$page_title = 'Cloaker & trafik';
include 'admin_header.php';
?>

<div class="container-fluid py-3 cloaker-admin-page">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $isErr = in_array($mtp, ['error', 'danger'], true); ?>
        <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="cloaker-page-head">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-user-shield text-primary"></i> Cloaker &amp; trafik</h1>
            <p class="text-muted small mb-0">Bot ve reklam inceleme trafiğini ayırın; gerçek ziyaretçiler vitrine ulaşsın.</p>
        </div>
        <div class="cloaker-page-head__actions">
            <a href="safe_page_settings.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-newspaper"></i> Güvenli sayfa</a>
            <a href="cloaker_traffic.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-line"></i> Trafik günlüğü</a>
        </div>
    </div>

    <div class="cloaker-status-banner <?= $cloakerOn ? 'is-on' : 'is-off' ?>">
        <div class="cloaker-status-banner__main">
            <span class="cloaker-status-dot"></span>
            <div>
                <strong><?= $cloakerOn ? 'Cloaker aktif' : 'Cloaker kapalı' ?></strong>
                <span class="cloaker-status-banner__sub">
                    <?php if (!$cloakerOn): ?>
                        Vitrinde filtreleme yapılmıyor.
                    <?php elseif ($advancedOn): ?>
                        Gelişmiş mod<?= $activeProfile && isset($profileLabels[$activeProfile]) ? ' · ' . htmlspecialchars($profileLabels[$activeProfile]) : '' ?>
                    <?php else: ?>
                        Temel UA kuralları
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="cloaker-status-banner__chips">
            <span class="cloaker-chip <?= $advancedOn ? 'cloaker-chip--on' : '' ?>"><i class="fas fa-cogs"></i> Gelişmiş</span>
            <span class="cloaker-chip <?= $paravanMode ? 'cloaker-chip--on' : '' ?>"><i class="fas fa-mask"></i> Paravan</span>
            <span class="cloaker-chip <?= $gatewayOn ? 'cloaker-chip--on' : '' ?>"><i class="fas fa-door-open"></i> Geçit</span>
            <span class="cloaker-chip <?= !empty((int) ($r['traffic_log_enabled'] ?? 1)) ? 'cloaker-chip--on' : '' ?>"><i class="fas fa-list"></i> Log</span>
        </div>
    </div>

    <div class="cloaker-link-board mb-4">
        <div class="cloaker-link-card cloaker-link-card--real">
            <div class="cloaker-link-card__head">
                <span class="cloaker-link-card__badge cloaker-link-card__badge--real">Gerçek vitrin</span>
                <span class="cloaker-link-card__tag">settings.site_url</span>
            </div>
            <code class="cloaker-link-card__url" id="link-real"><?= htmlspecialchars($campaignFull) ?></code>
            <p class="cloaker-link-card__hint">Gerçek kullanıcıların ulaştığı kampanya adresi. Ref anahtarı otomatik eklenir.</p>
            <button type="button" class="btn btn-sm btn-outline-success cloaker-copy-btn" data-copy-target="link-real"><i class="fas fa-copy"></i> Kopyala</button>
        </div>
        <?php if ($paravanMode && trim((string) ($r['paravan_url'] ?? '')) !== ''): ?>
        <div class="cloaker-link-card cloaker-link-card--decoy">
            <div class="cloaker-link-card__head">
                <span class="cloaker-link-card__badge cloaker-link-card__badge--decoy">Paravan (reklamda görünen)</span>
                <?php $pl = trim((string) ($r['paravan_label'] ?? '')); if ($pl !== '' && isset($paravanPresets[$pl])): ?>
                <span class="cloaker-link-card__tag"><?= htmlspecialchars($paravanPresets[$pl]['label']) ?></span>
                <?php endif; ?>
            </div>
            <code class="cloaker-link-card__url" id="link-paravan"><?= htmlspecialchars((string) $r['paravan_url']) ?></code>
            <p class="cloaker-link-card__hint">Reklam panelinde görünen / display URL olarak kullanılır. Kendi siteniz olmamalı.</p>
            <button type="button" class="btn btn-sm btn-outline-warning cloaker-copy-btn" data-copy-target="link-paravan"><i class="fas fa-copy"></i> Kopyala</button>
        </div>
        <?php endif; ?>
        <?php if ($gatewayOn && $gatewayUrl !== ''): ?>
        <div class="cloaker-link-card cloaker-link-card--gate">
            <div class="cloaker-link-card__head">
                <span class="cloaker-link-card__badge cloaker-link-card__badge--gate">Reklam hedefi (geçit)</span>
                <span class="cloaker-link-card__tag">go.php</span>
            </div>
            <code class="cloaker-link-card__url" id="link-gateway"><?= htmlspecialchars($gatewayUrl) ?></code>
            <p class="cloaker-link-card__hint">Reklam tıklama URL’si olarak bunu verin. Bot → güvenli sayfa; insan → gerçek vitrin.</p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary cloaker-copy-btn" data-copy-target="link-gateway"><i class="fas fa-copy"></i> Kopyala</button>
                <form method="post" class="m-0" onsubmit="return confirm('Geçit anahtarı değişir; reklam linklerini güncellemeniz gerekir.');">
                    <button type="submit" name="cloaker_regenerate_gateway" value="1" class="btn btn-sm btn-outline-secondary">Anahtarı yenile</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="cloaker-campaign-bar mb-4">
        <span class="cloaker-campaign-bar__label">Vitrin taban URL</span>
        <code class="cloaker-campaign-bar__url"><?= htmlspecialchars($campaignUrlBase) ?></code>
        <?php if ($storedBaseOverride === ''): ?>
            <span class="badge bg-success">site_url otomatik</span>
        <?php else: ?>
            <span class="badge bg-info text-dark">manuel override</span>
        <?php endif; ?>
        <form method="post" class="m-0 ms-auto">
            <button type="submit" name="cloaker_sync_site_url" value="1" class="btn btn-sm btn-outline-light">Site URL’den sıfırla</button>
        </form>
    </div>

    <?php
    $statTotal = (int) ($r['stat_total'] ?? 0);
    $statPass = (int) ($r['stat_passed'] ?? 0);
    $statBlk = (int) ($r['stat_blocked'] ?? 0);
    $passRate = $statTotal > 0 ? round(($statPass / $statTotal) * 100, 1) : 0;
    ?>
    <?php if ($advancedOn): ?>
    <div class="cloaker-stat-grid mb-3">
        <div class="cloaker-stat-card cloaker-stat-card--total"><span>Toplam</span><strong><?= $statTotal ?></strong></div>
        <div class="cloaker-stat-card cloaker-stat-card--pass"><span>Geçiş</span><strong><?= $statPass ?></strong><em>%<?= $passRate ?></em></div>
        <div class="cloaker-stat-card cloaker-stat-card--block"><span>Gölge / şüpheli</span><strong><?= $statBlk ?></strong></div>
    </div>
    <form method="post" class="d-inline mb-4" onsubmit="return confirm('İstatistikler sıfırlansın mı?');">
        <button type="submit" name="cloaker_reset_stats" value="1" class="btn btn-outline-danger btn-sm">İstatistikleri sıfırla</button>
    </form>
    <?php endif; ?>

    <h2 class="h6 text-secondary mb-2">Hazır profiller</h2>
    <p class="small text-muted mb-3">Bir profile tıklayınca ilgili ayarlar uygulanır ve kaydedilir. Aktif profil yeşil kenarlıkla işaretlenir.</p>
    <div class="cloaker-preset-grid mb-4">
        <?php
        $presetsUi = [
            'traffic' => [
                'icon' => 'fa-bolt', 'title' => 'Trafik koruma', 'featured' => true,
                'desc' => 'Ref zorunlu değil · esnek filtre · günlük kullanım.',
                'tags' => ['Ref: kapalı', 'DNS/JS: esnek', 'Hit kaybı düşük'],
                'btn' => 'cloaker_profile_traffic', 'btnClass' => 'btn-success',
            ],
            'paravan' => [
                'icon' => 'fa-mask', 'title' => 'Paravan link', 'featured' => false, 'accent' => 'paravan',
                'desc' => 'Sahte görünen URL + gerçek vitrin + otomatik geçit.',
                'tags' => ['Paravan URL', 'Geçit: açık', 'Ref: zorunlu'],
                'btn' => 'cloaker_profile_paravan', 'btnClass' => 'btn-warning',
            ],
            'ads' => [
                'icon' => 'fa-bullhorn', 'title' => 'Reklam kampanyası',
                'desc' => '?ref= zorunlu · Meta/Google linkleri.',
                'tags' => ['Ref: açık', 'DNS/JS: esnek'],
                'btn' => 'cloaker_profile_ads', 'btnClass' => 'btn-primary',
            ],
            'stable' => [
                'icon' => 'fa-balance-scale', 'title' => 'Stabil',
                'desc' => 'Ref zorunlu · dengeli filtre.',
                'tags' => ['Ref: açık', 'Klasik profil'],
                'btn' => 'cloaker_profile_stable', 'btnClass' => 'btn-outline-primary',
            ],
            'strict' => [
                'icon' => 'fa-lock', 'title' => 'Sıkı', 'danger' => true,
                'desc' => 'Agresif DNS/JS · GeoIP TR · yanlış pozitif riski.',
                'tags' => ['GeoIP TR', 'Agresif'],
                'btn' => 'cloaker_profile_strict', 'btnClass' => 'btn-outline-danger',
            ],
        ];
        foreach ($presetsUi as $pKey => $p):
            $isActive = ($activeProfile === $pKey);
            $cardClass = 'cloaker-preset-card';
            if (!empty($p['featured'])) {
                $cardClass .= ' cloaker-preset-card--featured';
            }
            if (!empty($p['danger'])) {
                $cardClass .= ' cloaker-preset-card--danger';
            }
            if (!empty($p['accent']) && $p['accent'] === 'paravan') {
                $cardClass .= ' cloaker-preset-card--paravan';
            }
            if ($isActive) {
                $cardClass .= ' cloaker-preset-card--active';
            }
        ?>
        <div class="<?= $cardClass ?>">
            <?php if ($isActive): ?><span class="cloaker-preset-card__active-badge"><i class="fas fa-check-circle"></i> Aktif</span><?php endif; ?>
            <div class="cloaker-preset-card__icon"><i class="fas <?= htmlspecialchars($p['icon']) ?>"></i></div>
            <h3 class="h6 mb-1"><?= htmlspecialchars($p['title']) ?></h3>
            <p class="small text-muted mb-2"><?= htmlspecialchars($p['desc']) ?></p>
            <ul class="cloaker-preset-card__tags">
                <?php foreach ($p['tags'] as $tag): ?>
                <li><?= htmlspecialchars($tag) ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" class="m-0 mt-auto">
                <input type="hidden" name="ref_key_stable" value="<?= htmlspecialchars(trim((string) ($r['ref_key'] ?? '')) ?: 'kampanya') ?>">
                <?php if ($pKey === 'paravan'): ?>
                <select name="paravan_preset_key" class="form-select form-select-sm mb-2">
                    <?php foreach ($paravanPresets as $pk => $pv): if ($pk === 'custom') continue; ?>
                    <option value="<?= htmlspecialchars($pk) ?>" <?= ($r['paravan_label'] ?? '') === $pk ? 'selected' : '' ?>><?= htmlspecialchars($pv['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if ($pKey === 'ads'): ?>
                <input type="text" name="ref_key_stable" class="form-control form-control-sm mb-2 font-monospace" maxlength="128" placeholder="ref: kampanya" value="<?= htmlspecialchars(trim((string) ($r['ref_key'] ?? '')) ?: 'kampanya') ?>">
                <?php endif; ?>
                <button type="submit" name="<?= htmlspecialchars($p['btn']) ?>" value="1" class="btn btn-sm w-100 <?= htmlspecialchars($p['btnClass']) ?>"
                    <?= $pKey === 'strict' ? 'onclick="return confirm(\'Sıkı profil bazı gerçek kullanıcıları gölgeye düşürebilir. Devam?\');"' : '' ?>>
                    <?= $isActive ? 'Yeniden uygula' : 'Uygula' ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <form method="post" class="card border-0 shadow-sm cloaker-settings-form">
        <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span><i class="fas fa-sliders-h text-primary me-1"></i> Manuel ayarlar</span>
            <span class="badge <?= $cloakerOn ? 'bg-success' : 'bg-secondary' ?>"><?= $cloakerOn ? 'Açık' : 'Kapalı' ?></span>
        </div>
        <div class="card-body">
            <div class="mb-3 p-3 rounded border cloaker-url-sync-block" id="cloaker-public-url-block">
                <label class="form-label fw-semibold mb-1" for="puburl">Vitrin (kampanya) taban URL’si</label>
                <div class="input-group mb-2">
                    <input type="text" name="public_base_url" id="puburl" class="form-control font-monospace" maxlength="512"
                        value="<?= htmlspecialchars($storedBaseOverride) ?>"
                        placeholder="<?= htmlspecialchars($siteUrlAuto) ?> (otomatik — site_url)"
                        inputmode="url" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary" id="btn-fill-site-url" title="Site URL’yi yaz"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="form-text mb-0">
                    Boş bırakırsanız <strong><?= htmlspecialchars($siteUrlAuto) ?></strong> kullanılır (paneldeki site adresi).
                    Manuel yazarsanız sadece cloaker yönlendirmelerinde override olur.
                </div>
            </div>

            <div class="cloaker-section mb-4">
                <h3 class="cloaker-section__title"><i class="fas fa-power-off"></i> Ana anahtarlar</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="cloaker-toggle-box <?= $cloakerOn ? 'is-on' : '' ?>">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="cloaker_enabled" id="ce0"
                                    <?= $cloakerOn ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="ce0">Cloaker aktif</label>
                            </div>
                            <p class="cloaker-toggle-box__hint">Kapalıysa vitrinde filtreleme durur.</p>
                        </div>
                    </div>
                    <div class="col-md-6" id="cloaker-detail-wrap">
                        <div class="cloaker-toggle-box <?= $advancedOn ? 'is-on' : '' ?>">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="advanced_cloaker" id="adv1"
                                    <?= $advancedOn ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="adv1">Gelişmiş cloaker</label>
                            </div>
                            <p class="cloaker-toggle-box__hint">Ref, bot listesi, güvenli sayfa, GeoIP.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="advanced-wrap">
            <div class="cloaker-section mb-4">
                <h3 class="cloaker-section__title"><i class="fas fa-mask"></i> Paravan / gerçek link</h3>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Kampanya modu</label>
                        <select name="campaign_mode" class="form-select" id="campaign-mode">
                            <option value="direct" <?= !$paravanMode ? 'selected' : '' ?>>Doğrudan vitrin</option>
                            <option value="paravan" <?= $paravanMode ? 'selected' : '' ?>>Paravan + gerçek link</option>
                        </select>
                    </div>
                    <div class="col-md-8" id="paravan-fields" style="<?= $paravanMode ? '' : 'display:none' ?>">
                        <label class="form-label small">Paravan URL (reklamda görünen)</label>
                        <div class="input-group mb-2">
                            <select class="form-select" id="paravan-preset-select" style="max-width:11rem">
                                <?php foreach ($paravanPresets as $pk => $pv): ?>
                                <option value="<?= htmlspecialchars($pk) ?>" data-url="<?= htmlspecialchars($pv['url']) ?>"
                                    <?= ($r['paravan_label'] ?? '') === $pk ? 'selected' : '' ?>><?= htmlspecialchars($pv['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="paravan_label" id="paravan-label" value="<?= htmlspecialchars((string) ($r['paravan_label'] ?? '')) ?>">
                            <input type="text" name="paravan_url" id="paravan-url" class="form-control font-monospace"
                                maxlength="512" value="<?= htmlspecialchars((string) ($r['paravan_url'] ?? '')) ?>"
                                placeholder="https://www.arabam.com/...">
                        </div>
                        <p class="form-text small mb-0" id="paravan-hint"></p>
                    </div>
                    <div class="col-12">
                        <div class="cloaker-toggle-box <?= $gatewayOn ? 'is-on' : '' ?>">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="gateway_enabled" id="gw1"
                                    <?= $gatewayOn ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="gw1">Reklam geçidi (go.php)</label>
                            </div>
                            <p class="cloaker-toggle-box__hint">Açıkken reklam hedef URL’si olarak geçit linkini kullanın; filtre otomatik çalışır.</p>
                        </div>
                    </div>
                </div>
            </div>

            <fieldset class="cloaker-section mb-4">
                <legend class="cloaker-section__title"><i class="fas fa-shield-alt"></i> Kampanya &amp; güvenli sayfa</legend>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="require_ref" id="reqref"
                        <?= !empty((int) ($r['require_ref'] ?? 0)) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="reqref"><code>?ref=</code> anahtarını zorunlu tut</label>
                </div>
                <label class="form-label small">Ref anahtarı</label>
                <input type="text" name="ref_key" class="form-control font-monospace mb-2" maxlength="128"
                    value="<?= htmlspecialchars((string) ($r['ref_key'] ?? '')) ?>" placeholder="örn: kampanya">

                <label class="form-label small">Şüpheli trafikte davranış</label>
                <select name="threat_handling" class="form-select mb-2">
                    <?php
                    $th = (string) ($r['threat_handling'] ?? 'safe_shadow');
                    foreach ([
                        'safe_shadow' => 'Güvenli sayfa — gölge (URL sabit)',
                        'safe_redirect' => 'Güvenli sayfa — 301 yönlendirme',
                        'http_403' => 'HTTP 403 düz metin',
                        'log_only' => 'Sadece günlük (sayfayı olduğu gibi göster)',
                    ] as $val => $label):
                    ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $th === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label small">Güvenli sayfa dosyası veya tam URL</label>
                <input type="text" name="safe_page_target" class="form-control mb-1" maxlength="512"
                    value="<?= htmlspecialchars((string) ($r['safe_page_target'] ?? 'safe-page.php')) ?>"
                    placeholder="safe-page.php veya https://...">
                <div class="form-text mb-0"><a href="safe_page_settings.php">Güvenli sayfa içeriği</a> panelden düzenlenir (blog landing + meta).</div>
                <label class="form-label small">Gölge yöntemi (dosya hedefinde)</label>
                <select name="cloak_method" class="form-select mb-0">
                    <?php $cm = (int) ($r['cloak_method'] ?? 2); ?>
                    <option value="2" <?= $cm === 2 ? 'selected' : '' ?>>Shadow: dosyayı include et (önerilen)</option>
                    <option value="1" <?= $cm === 1 ? 'selected' : '' ?>>Yönlendirme: Location header</option>
                </select>
            </fieldset>

            <fieldset class="cloaker-section mb-4">
                <legend class="cloaker-section__title"><i class="fas fa-filter"></i> Filtreler</legend>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="block_known_bots" id="bkb"
                        <?= !isset($r['block_known_bots']) || (int) ($r['block_known_bots'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="bkb">Bilinen önizleme / bot UA listesi</label>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">DNS / PTR modu</label>
                        <select name="dns_mode" class="form-select">
                            <?php $dm = (string) ($r['dns_mode'] ?? 'esnek'); ?>
                            <option value="esnek" <?= $dm === 'esnek' ? 'selected' : '' ?>>Esnek</option>
                            <option value="agresif" <?= $dm === 'agresif' ? 'selected' : '' ?>>Agresif</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">JavaScript token</label>
                        <select name="js_mode" class="form-select">
                            <?php $jm = (string) ($r['js_mode'] ?? 'esnek'); ?>
                            <option value="esnek" <?= $jm === 'esnek' ? 'selected' : '' ?>>Esnek</option>
                            <option value="agresif" <?= $jm === 'agresif' ? 'selected' : '' ?>>Agresif</option>
                        </select>
                    </div>
                </div>
                <hr class="my-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="geoip_on" id="gio"
                        <?= !empty((int) ($r['geoip_on'] ?? 0)) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="gio">GeoIP ülke kilidi</label>
                </div>
                <label class="form-label small">İzinli ülkeler (ISO, virgüllü)</label>
                <input type="text" name="allowed_countries" class="form-control mb-2" maxlength="256"
                    value="<?= htmlspecialchars((string) ($r['allowed_countries'] ?? 'TR')) ?>">
                <label class="form-label small">GeoLite2 City (.mmdb) yolu</label>
                <input type="text" name="geoip_mmdb_path" class="form-control font-monospace mb-2" maxlength="512"
                    value="<?= htmlspecialchars((string) ($r['geoip_mmdb_path'] ?? '')) ?>"
                    placeholder="includes/cloaker/GeoLite2-City.mmdb">
                <label class="form-label small">IP beyaz listesi</label>
                <textarea name="ip_whitelist" rows="2" class="form-control font-monospace small"><?= htmlspecialchars((string) ($r['ip_whitelist'] ?? '')) ?></textarea>
                <label class="form-label small mt-2">IP kara listesi</label>
                <textarea name="ip_blacklist" rows="2" class="form-control font-monospace small"><?= htmlspecialchars((string) ($r['ip_blacklist'] ?? '')) ?></textarea>
                <label class="form-label small mt-2">Muaf PHP dosyaları (satır başına)</label>
                <textarea name="cloaker_exempt_basenames" rows="2" class="form-control font-monospace small" placeholder="get_districts.php&#10;safe-page.php&#10;go.php"><?= htmlspecialchars((string) ($r['cloaker_exempt_basenames'] ?? '')) ?></textarea>
            </fieldset>
            </div>

            <div id="cloaker-sub-settings" class="cloaker-section <?= !$cloakerOn ? 'cloaker-section--disabled' : '' ?>">
                <h3 class="cloaker-section__title"><i class="fas fa-robot"></i> UA kuralları</h3>
                <?php if (!$cloakerOn): ?>
                <p class="small text-warning mb-2"><i class="fas fa-info-circle"></i> Cloaker kapalıyken bu kurallar vitrinde uygulanmaz; yalnızca kayıtlı tercihlerdir.</p>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="traffic_log_enabled" id="t1" <?= !empty((int) ($r['traffic_log_enabled'] ?? 1)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="t1">Trafik günlüğü</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="blocking_enabled" id="b1" <?= !empty((int) ($r['blocking_enabled'] ?? 0)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="b1">UA kurallarını tehdit olarak işle</label>
                        </div>
                        <div class="form-check mb-2 ms-3">
                            <input class="form-check-input" type="checkbox" name="block_empty_ua" id="b2" <?= !isset($r['block_empty_ua']) || (int) $r['block_empty_ua'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="b2">Boş / kısa User-Agent</label>
                        </div>
                        <div class="form-check mb-2 ms-3">
                            <input class="form-check-input" type="checkbox" name="block_scraper_ua" id="b3" <?= !isset($r['block_scraper_ua']) || (int) $r['block_scraper_ua'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="b3">Script istemci desenleri</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="respect_benign_bots" id="b4" <?= !isset($r['respect_benign_bots']) || (int) $r['respect_benign_bots'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="b4">Temel önizleme botlarını yumuşat</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Ek engel listesi (satır başına UA parçası)</label>
                        <textarea name="extra_block_substrings" class="form-control font-monospace small" rows="4"><?= htmlspecialchars((string) ($r['extra_block_substrings'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save"></i> Kaydet</button>
        </div>
    </form>
</div>
<script>
(function () {
    var siteUrlAuto = <?= json_encode($siteUrlAuto, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var paravanPresets = <?= json_encode($paravanPresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    document.querySelectorAll('.cloaker-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-copy-target');
            var el = id ? document.getElementById(id) : null;
            if (!el) return;
            var t = el.textContent || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(t).then(function () {
                    btn.innerHTML = '<i class="fas fa-check"></i> Kopyalandı';
                    setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> Kopyala'; }, 1500);
                });
            }
        });
    });

    var fillBtn = document.getElementById('btn-fill-site-url');
    var pubInput = document.getElementById('puburl');
    if (fillBtn && pubInput) {
        fillBtn.addEventListener('click', function () {
            pubInput.value = siteUrlAuto;
        });
    }

    var master = document.getElementById('ce0');
    var wrap = document.getElementById('cloaker-detail-wrap');
    var subSettings = document.getElementById('cloaker-sub-settings');
    var adv = document.getElementById('adv1');
    var advWrap = document.getElementById('advanced-wrap');
    var form = document.querySelector('form.cloaker-settings-form');

    function setDisabled(container, off) {
        if (!container) return;
        container.querySelectorAll('input, textarea, select, button').forEach(function (el) {
            if (el.id === 'ce0') return;
            el.disabled = off;
        });
    }

    function applyMaster() {
        var masterOn = master && master.checked;
        if (wrap) {
            wrap.style.opacity = masterOn ? '1' : '0.45';
        }
        if (subSettings) {
            subSettings.classList.toggle('cloaker-section--disabled', !masterOn);
            setDisabled(subSettings, !masterOn);
        }
        setDisabled(wrap, !masterOn);
        if (advWrap && adv) {
            var advOn = adv.checked && masterOn;
            advWrap.style.display = advOn ? 'block' : 'none';
            setDisabled(advWrap, !masterOn || !adv.checked);
        }
        if (adv) adv.disabled = !masterOn;
    }
    if (master) master.addEventListener('change', applyMaster);
    if (adv) adv.addEventListener('change', applyMaster);
    applyMaster();

    if (form) {
        form.addEventListener('submit', function () {
            setDisabled(subSettings, false);
            setDisabled(wrap, false);
            if (advWrap) setDisabled(advWrap, false);
        });
    }

    var modeSel = document.getElementById('campaign-mode');
    var paravanFields = document.getElementById('paravan-fields');
    var presetSel = document.getElementById('paravan-preset-select');
    var paravanUrl = document.getElementById('paravan-url');
    var paravanLabel = document.getElementById('paravan-label');
    var paravanHint = document.getElementById('paravan-hint');

    function syncParavanUi() {
        if (modeSel && paravanFields) {
            paravanFields.style.display = modeSel.value === 'paravan' ? '' : 'none';
        }
    }
    function syncParavanPreset() {
        if (!presetSel || !paravanUrl) return;
        var key = presetSel.value;
        if (paravanLabel) paravanLabel.value = key;
        var opt = presetSel.options[presetSel.selectedIndex];
        var url = opt ? opt.getAttribute('data-url') : '';
        if (key !== 'custom' && url) {
            paravanUrl.value = url;
        }
        if (paravanHint && paravanPresets[key]) {
            paravanHint.textContent = paravanPresets[key].hint || '';
        }
    }
    if (modeSel) modeSel.addEventListener('change', syncParavanUi);
    if (presetSel) presetSel.addEventListener('change', syncParavanPreset);
    syncParavanUi();
    syncParavanPreset();
})();
</script>

<?php include 'admin_footer_common.php'; ?>
