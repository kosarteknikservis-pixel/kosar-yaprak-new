<?php
declare(strict_types=1);

/**
 * Uygulama kök yolu ve temiz URL üretimi (MVC-lite).
 */

function app_install_subpath(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(dirname(__DIR__)) ?: '';
    if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));

        return $cached = rtrim($rel, '/');
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    if (str_ends_with($dir, '/payment') || str_ends_with($dir, '/admin') || str_ends_with($dir, '/phx') || str_ends_with($dir, '/ajax')) {
        $dir = dirname($dir);
    }

    return $cached = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}

function app_site_url(?PDO $pdo = null): string
{
    if ($pdo instanceof PDO) {
        try {
            $u = $pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn();
            if (is_string($u) && $u !== '') {
                require_once __DIR__ . '/site_helpers.php';

                return site_url_normalize($u);
            }
        } catch (Throwable $e) {
            /* fallback */
        }
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = app_install_subpath();

    return ($https ? 'https' : 'http') . '://' . $host . $base;
}

/** @param array<string, scalar> $query */
function app_url(string $path = '', array $query = [], ?PDO $pdo = null): string
{
    $path = ltrim($path, '/');
    if ($path === '' || $path === 'index' || $path === 'index.php') {
        $path = '';
    } elseif (str_ends_with($path, '.php')) {
        $path = substr($path, 0, -4);
    }

    $base = app_site_url($pdo);
    $url = $path === '' ? $base . '/' : $base . '/' . $path;

    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    return $url;
}

function app_client_ip(): string
{
    if (! empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return (string) $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);

        return trim($parts[0]);
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

function app_paytr_local_ip_override(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === 'localhost' || str_starts_with($host, '127.0.0.1') || str_contains($host, '.test') || str_contains($host, '.local')) {
        $ext = getenv('PAYTR_DEV_IP');
        if (is_string($ext) && $ext !== '') {
            return $ext;
        }
    }

    return app_client_ip();
}
