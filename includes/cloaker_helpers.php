<?php
declare(strict_types=1);

require_once __DIR__ . '/site_helpers.php';
require_once __DIR__ . '/app_url.php';

/** Vitrin taban URL: önce kayıtlı override, yoksa settings.site_url, yoksa istekten algı. */
function cloaker_resolve_public_base(PDO $pdo, array $cfg): string
{
    $override = site_url_normalize(trim((string) ($cfg['public_base_url'] ?? '')));
    if ($override !== '' && site_url_is_valid($override)) {
        return $override;
    }

    return site_public_url($pdo);
}

/** Kampanya vitrin linki (?ref= dahil). */
function cloaker_campaign_url(PDO $pdo, array $cfg): string
{
    $base = cloaker_resolve_public_base($pdo, $cfg);
    $ref = trim((string) ($cfg['ref_key'] ?? ''));
    if ($ref === '') {
        return $base . '/';
    }

    return app_url('', ['ref' => $ref], $pdo);
}

/** Geçit (gateway) URL — reklam hedefi olarak kullanılabilir. */
function cloaker_gateway_url(PDO $pdo, array $cfg): string
{
    $token = cloaker_ensure_gateway_token($pdo, $cfg);
    if ($token === '') {
        return '';
    }

    return app_url('go.php', ['k' => $token], $pdo);
}

function cloaker_ensure_gateway_token(PDO $pdo, array $cfg): string
{
    $t = trim((string) ($cfg['gateway_token'] ?? ''));
    if ($t !== '' && preg_match('/^[a-f0-9]{16}$/', $t)) {
        return $t;
    }
    try {
        $t = bin2hex(random_bytes(8));
        $pdo->prepare('UPDATE cloaker_settings SET gateway_token = ? WHERE id = 1')->execute([$t]);

        return $t;
    } catch (Throwable $e) {
        return '';
    }
}

/** Paravan (reklamda görünen) hazır site şablonları — kendi siteniz hariç. */
function cloaker_paravan_presets(): array
{
    return [
        'arabam' => [
            'label' => 'Arabam.com',
            'icon' => 'fa-car',
            'url' => 'https://www.arabam.com/ilan/vasita-otomobil',
            'hint' => 'İlan / vitrin sayfası URL’si. Reklam incelemesinde güvenilir görünür.',
        ],
        'hurriyet' => [
            'label' => 'Hürriyet',
            'icon' => 'fa-newspaper',
            'url' => 'https://www.hurriyet.com.tr/ekonomi/',
            'hint' => 'Haber kategorisi — ekonomi / gündem sayfaları.',
        ],
        'milliyet' => [
            'label' => 'Milliyet',
            'icon' => 'fa-newspaper',
            'url' => 'https://www.milliyet.com.tr/ekonomi/',
            'hint' => 'Haber sitesi paravan linki.',
        ],
        'sahibinden' => [
            'label' => 'Sahibinden',
            'icon' => 'fa-store',
            'url' => 'https://www.sahibinden.com/kategori/vasita',
            'hint' => 'Kategori veya ilan URL’si.',
        ],
        'n11' => [
            'label' => 'N11 / e-ticaret',
            'icon' => 'fa-shopping-bag',
            'url' => 'https://www.n11.com/kampanyalar',
            'hint' => 'Kampanya veya kategori sayfası.',
        ],
        'custom' => [
            'label' => 'Özel URL',
            'icon' => 'fa-link',
            'url' => '',
            'hint' => 'Kendi paravan adresinizi yazın (kendi vitrin domaininiz olmasın).',
        ],
    ];
}

function cloaker_normalize_external_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    return mb_substr($url, 0, 512);
}

/** Aktif hazır profil adını tahmin eder (UI rozeti için). */
function cloaker_detect_active_profile(array $cfg): ?string
{
    if (empty((int) ($cfg['advanced_cloaker'] ?? 0))) {
        return empty((int) ($cfg['cloaker_enabled'] ?? 1)) ? null : 'basic';
    }
    $mode = (string) ($cfg['campaign_mode'] ?? 'direct');
    if ($mode === 'paravan') {
        return 'paravan';
    }
    $reqRef = !empty((int) ($cfg['require_ref'] ?? 0));
    $dns = (string) ($cfg['dns_mode'] ?? 'esnek');
    $js = (string) ($cfg['js_mode'] ?? 'esnek');
    $geo = !empty((int) ($cfg['geoip_on'] ?? 0));
    if (!$reqRef && $dns === 'esnek' && $js === 'esnek' && !$geo) {
        return 'traffic';
    }
    if ($reqRef && $dns === 'esnek' && $js === 'esnek' && !$geo) {
        return 'ads';
    }
    if ($reqRef && $dns === 'agresif' && $js === 'agresif' && $geo) {
        return 'strict';
    }
    if ($reqRef) {
        return 'stable';
    }

    return 'custom';
}

/** Profil etiketleri. */
function cloaker_profile_labels(): array
{
    return [
        'basic' => 'Temel (gelişmiş kapalı)',
        'traffic' => 'Trafik koruma',
        'ads' => 'Reklam kampanyası',
        'stable' => 'Stabil',
        'strict' => 'Sıkı',
        'paravan' => 'Paravan link',
        'custom' => 'Özel ayar',
    ];
}
