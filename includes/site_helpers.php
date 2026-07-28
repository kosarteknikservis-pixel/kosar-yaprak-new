<?php
declare(strict_types=1);

if (defined('SITE_HELPERS_LOADED')) {
    return;
}
define('SITE_HELPERS_LOADED', true);

/** Site URL — kayıt ve kullanımda sondaki / zorunlu değil */function site_url_normalize(string $url): string
{
    $url = trim($url);

    return rtrim($url, '/');
}

function site_url_is_valid(string $url): bool
{
    $url = site_url_normalize($url);

    return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/** Tarayıcı / sunucu isteğinden vitrin taban URL (protokol + host + kurulum yolu). */
function site_url_detect_from_request(): string
{
    require_once __DIR__ . '/app_url.php';

    return site_url_normalize(app_site_url(null));
}

/**
 * Admin “Siteyi Gör” ve canonical için: önce settings.site_url, yoksa otomatik algı.
 */
function site_public_url(?PDO $pdo = null): string
{
    if ($pdo instanceof PDO) {
        try {
            $raw = $pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn();
            $u = site_url_normalize(is_string($raw) ? $raw : '');
            if ($u !== '' && site_url_is_valid($u)) {
                return $u;
            }
        } catch (Throwable $e) {
            /* fallback */
        }
    }

    return site_url_detect_from_request();
}

/** Vitrin ana sayfa linki (temiz URL uyumlu). */
function site_public_vitrin_href(?PDO $pdo = null): string
{
    require_once __DIR__ . '/app_url.php';

    return app_url('', [], $pdo);
}

/** settings.site_url boşsa otomatik algılanan adresi kaydeder; kaydedilen URL döner. */
function site_url_ensure_from_request(PDO $pdo): string
{
    $current = site_url_normalize((string) ($pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn() ?: ''));
    if ($current !== '' && site_url_is_valid($current)) {
        return $current;
    }
    $detected = site_url_detect_from_request();
    if ($detected !== '' && site_url_is_valid($detected)) {
        $pdo->prepare('UPDATE settings SET site_url = ? WHERE id = 1')->execute([$detected]);
    }

    return $detected;
}

/** Kurulum veya domain taşımada: algılanan host farklıysa settings.site_url güncelle. */
function site_url_sync_from_request(PDO $pdo): string
{
    $detected = site_url_detect_from_request();
    if ($detected === '' || ! site_url_is_valid($detected)) {
        return site_public_url($pdo);
    }

    try {
        $stored = site_url_normalize((string) ($pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn() ?: ''));
        $dHost = strtolower((string) parse_url($detected, PHP_URL_HOST));
        $sHost = strtolower((string) parse_url($stored, PHP_URL_HOST));
        $dPath = rtrim((string) parse_url($detected, PHP_URL_PATH), '/');
        $sPath = rtrim((string) parse_url($stored, PHP_URL_PATH), '/');
        if ($stored === '' || ($dHost !== '' && $dHost !== $sHost) || ($dPath !== '' && $dPath !== $sPath)) {
            $pdo->prepare('UPDATE settings SET site_url = ? WHERE id = 1')->execute([$detected]);

            return $detected;
        }

        return $stored !== '' ? $stored : $detected;
    } catch (Throwable $e) {
        return $detected;
    }
}

/** Kurulum tamamlanırken site URL’yi zorunlu güncelle. */
function install_apply_site_url(PDO $pdo): string
{
    $detected = site_url_detect_from_request();
    if ($detected === '' || ! site_url_is_valid($detected)) {
        throw new RuntimeException('Site URL otomatik algılanamadı. HTTP_HOST ve kurulum yolunu kontrol edin.');
    }
    $pdo->prepare('UPDATE settings SET site_url = ? WHERE id = 1')->execute([$detected]);

    return $detected;
}
