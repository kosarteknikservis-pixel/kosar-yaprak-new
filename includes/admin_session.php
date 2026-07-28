<?php
declare(strict_types=1);

/**
 * Admin oturumu — Laragon'da Laravel ile PHPSESSID çakışmasını önler.
 */

function admin_request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
}

function admin_session_cookie_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.+)/(?:admin|phx)/#', $script, $m)) {
        return rtrim($m[1], '/') . '/';
    }

    return '/';
}

function admin_client_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    $behindProxy = $remote === ''
        || $remote === '127.0.0.1'
        || $remote === '::1'
        || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|fc|fd)/i', $remote) === 1;

    if ($behindProxy) {
        $candidates = [
            trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '')),
            trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? '')),
        ];
        $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            $candidates[] = trim(explode(',', $xff)[0]);
        }
        foreach ($candidates as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $remote !== '' ? $remote : '0.0.0.0';
}

function admin_ips_equivalent(string $a, string $b): bool
{
    $a = trim($a);
    $b = trim($b);
    if ($a === $b) {
        return true;
    }

    $localhost = ['127.0.0.1', '::1', '0.0.0.0'];
    if (in_array($a, $localhost, true) && in_array($b, $localhost, true)) {
        return true;
    }

    return false;
}

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_name('DH3ADMIN');

    $secure = admin_request_is_https();
    $path = admin_session_cookie_path();

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $path, '', $secure, true);
    }

    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_httponly', '1');

    session_start();
}
