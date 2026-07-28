<?php
declare(strict_types=1);

if (!defined('ADMIN_WEB_ROOT')) {
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*)/(admin|phx)(/|$)#', $sn, $m)) {
        define('ADMIN_WEB_ROOT', $m[1] . '/' . $m[2]);
    } else {
        define('ADMIN_WEB_ROOT', $sn !== '' ? rtrim(str_replace('\\', '/', dirname($sn)), '/') : '');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT : '';

        return ($base !== '' ? $base . '/' : '/') . $path;
    }
}

if (!function_exists('admin_href')) {
    function admin_href(string $path): string
    {
        return htmlspecialchars(admin_url($path), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Admin statik varlığı (css/js) için önbellek kırıcı URL üretir.
 * Dosyanın değişiklik zamanını ?v= parametresi olarak ekler; böylece
 * 30 günlük tarayıcı önbelleğine takılmadan yeni sürüm yüklenir.
 */
if (!function_exists('admin_asset')) {
    function admin_asset(string $path): string
    {
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        $physical = dirname(__DIR__) . '/phx/' . $rel;
        $ver = @filemtime($physical);
        $url = admin_url($rel);
        return $ver ? $url . '?v=' . $ver : $url;
    }
}

/** Hata mesajı ile admin sayfasına yönlendir (die yerine). */
if (!function_exists('admin_abort_redirect')) {
    function admin_abort_redirect(string $message, string $path = 'index.php', string $type = 'danger'): never
    {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
        header('Location: ' . admin_url($path));
        exit;
    }
}
