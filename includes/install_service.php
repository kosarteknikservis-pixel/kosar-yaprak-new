<?php

declare(strict_types=1);

require_once __DIR__ . '/page_seo.php';

function install_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_install (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        is_complete TINYINT(1) NOT NULL DEFAULT 0,
        site_base_title VARCHAR(255) NULL DEFAULT NULL,
        completed_at DATETIME NULL DEFAULT NULL,
        installer_version INT NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec('INSERT IGNORE INTO site_install (id, is_complete) VALUES (1, 0)');
}

function install_is_complete(PDO $pdo): bool
{
    try {
        install_ensure_schema($pdo);
        $v = $pdo->query('SELECT is_complete FROM site_install WHERE id = 1')->fetchColumn();
        return (int) $v === 1;
    } catch (Throwable $e) {
        return false;
    }
}

function install_url_from_request(): string
{
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($sn));
    if (str_ends_with($dir, '/admin') || str_ends_with($dir, '/phx')) {
        $dir = dirname($dir);
    }
    if ($dir === '.' || $dir === '/') {
        return '/install.php';
    }
    return rtrim($dir, '/') . '/install.php';
}

function install_guard_web(PDO $pdo): void
{
    if (install_is_complete($pdo)) {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (preg_match('#/(install\\.php|css/|js/|uploads/|robots\\.txt)(\\?|$)#i', $uri)) {
        return;
    }

    header('Location: ' . install_url_from_request(), true, 302);
    exit;
}

/** @return array<string, string> */
function install_default_page_titles(string $baseTitle): array
{
    $baseTitle = trim($baseTitle);
    if ($baseTitle === '') {
        $baseTitle = 'Online Alışveriş';
    }

    $suffixes = page_seo_default_titles();
    $out = [];
    foreach ($suffixes as $page => $suffix) {
        if ($page === 'index.php') {
            $out[$page] = $baseTitle;
        } else {
            $out[$page] = $baseTitle . ' — ' . $suffix;
        }
    }
    return $out;
}

/** @return array<string, string> */
function install_default_meta_descriptions(string $baseTitle): array
{
    $baseTitle = trim($baseTitle);
    $tpl = $baseTitle . ' — güvenli online sipariş, destek ve kargo takibi.';
    $pages = array_keys(page_seo_default_titles());
    $out = [];
    foreach ($pages as $page) {
        $out[$page] = $tpl;
    }
    return $out;
}

function install_wipe_operational(PDO $pdo, array $options): array
{
    $opts = array_merge([
        'orders' => true,
        'yarim_kalanlar' => true,
        'support_requests' => true,
        'dealer_requests' => true,
        'page_views' => true,
        'carkifelek_log' => true,
        'social_clicks' => true,
        'cloaker_traffic' => true,
        'cloaker_stats' => true,
        'link_cloak' => true,
        'login_logs' => true,
        'product_reviews' => false,
    ], $options);

    $done = [];
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {
        if (!empty($opts['orders'])) {
            $pdo->exec('DELETE FROM order_variation_details');
            $pdo->exec('DELETE FROM order_status_logs');
            $pdo->exec('DELETE FROM order_items');
            $pdo->exec('DELETE FROM orders');
            $pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 1');
            $done[] = 'orders';
        }
        if (!empty($opts['yarim_kalanlar'])) {
            $pdo->exec('DELETE FROM yarim_kalanlar');
            $pdo->exec('ALTER TABLE yarim_kalanlar AUTO_INCREMENT = 1');
            $done[] = 'yarim_kalanlar';
        }
        if (!empty($opts['support_requests'])) {
            $pdo->exec('DELETE FROM support_requests');
            $done[] = 'support_requests';
        }
        if (!empty($opts['dealer_requests'])) {
            $pdo->exec('DELETE FROM dealer_requests');
            $done[] = 'dealer_requests';
        }
        if (!empty($opts['page_views'])) {
            $pdo->exec('DELETE FROM page_views');
            $done[] = 'page_views';
        }
        if (!empty($opts['carkifelek_log'])) {
            $pdo->exec('DELETE FROM carkifelek_log');
            $done[] = 'carkifelek_log';
        }
        if (!empty($opts['social_clicks'])) {
            $pdo->exec('DELETE FROM social_button_clicks');
            $done[] = 'social_button_clicks';
        }
        if (!empty($opts['cloaker_traffic'])) {
            try {
                $pdo->exec('DELETE FROM cloaker_traffic');
                $done[] = 'cloaker_traffic';
            } catch (Throwable $e) {
            }
        }
        if (!empty($opts['cloaker_stats'])) {
            try {
                $pdo->exec('UPDATE cloaker_settings SET stat_total = 0, stat_passed = 0, stat_blocked = 0 WHERE id = 1');
                $done[] = 'cloaker_stats';
            } catch (Throwable $e) {
            }
        }
        if (!empty($opts['link_cloak'])) {
            try {
                $pdo->exec('DELETE FROM link_cloak_traffic');
                $pdo->exec('DELETE FROM link_cloak_campaigns');
                $pdo->exec('ALTER TABLE link_cloak_campaigns AUTO_INCREMENT = 1');
                $done[] = 'link_cloak';
            } catch (Throwable $e) {
            }
        }
        if (!empty($opts['login_logs'])) {
            $pdo->exec('DELETE FROM login_logs');
            try {
                $pdo->exec('DELETE FROM failed_logins');
            } catch (Throwable $e) {
            }
            $done[] = 'login_logs';
        }
        if (!empty($opts['product_reviews'])) {
            $pdo->exec('DELETE FROM product_review_images');
            $pdo->exec('DELETE FROM product_reviews');
            $done[] = 'product_reviews';
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    return $done;
}

/**
 * @param array<string, string> $pageTitles
 * @param array<string, string> $metaDescriptions
 */
function install_apply_page_meta(PDO $pdo, array $pageTitles, array $metaDescriptions): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO page_meta (page_name, page_title, meta_description)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE page_title = VALUES(page_title), meta_description = VALUES(meta_description)'
    );
    foreach ($pageTitles as $page => $title) {
        $desc = trim((string) ($metaDescriptions[$page] ?? ''));
        $stmt->execute([$page, trim($title), $desc !== '' ? $desc : null]);
    }
}

function install_apply_branding(PDO $pdo, string $logoMain, string $logoSub, string $hpMain, string $hpSub): void
{
    $logoMain = trim($logoMain);
    $logoSub = trim($logoSub);
    if ($logoMain !== '' || $logoSub !== '') {
        $pdo->prepare(
            'UPDATE footer_images SET logo_main_text = ?, logo_sub_text = ?, logo_text = ? WHERE id = 6'
        )->execute([
            $logoMain !== '' ? $logoMain : 'Marka',
            $logoSub,
            $logoMain !== '' ? $logoMain : 'Marka',
        ]);
    }

    if ($hpMain !== '' || $hpSub !== '') {
        try {
            $pdo->prepare(
                'UPDATE homepage_product_section SET heading_main = ?, heading_sub = ? WHERE id = 1'
            )->execute([$hpMain, $hpSub]);
        } catch (Throwable $e) {
        }
    }
}

/** settings.site_name — admin ayarları ve bildirimlerde görünen marka adı */
function install_apply_site_identity(PDO $pdo, string $baseTitle): void
{
    $baseTitle = trim($baseTitle);
    if ($baseTitle === '') {
        return;
    }

    try {
        $pdo->prepare('UPDATE settings SET site_name = ? WHERE id = 1')->execute([$baseTitle]);
    } catch (Throwable $e) {
        error_log('install_apply_site_identity site_name: ' . $e->getMessage());
    }

    try {
        $pdo->prepare(
            'UPDATE site_install SET site_base_title = ? WHERE id = 1'
        )->execute([$baseTitle]);
    } catch (Throwable $e) {
    }
}

/** @return array<string, mixed> */
function install_read_laravel4_config(string $path): array
{
    if (!is_file($path)) {
        return [
            'panel_base_url' => '',
            'order_api_key' => '',
            'site_origin' => '',
            'order_source_key' => '',
            'form_source_key' => '',
            'webhook_secret' => '',
        ];
    }
    $cfg = require $path;
    return is_array($cfg) ? $cfg : [];
}

/** @param array<string, mixed> $cfg */
function install_write_laravel4_config(string $path, array $cfg): bool
{
    $export = var_export([
        'panel_base_url' => trim((string) ($cfg['panel_base_url'] ?? '')),
        'order_api_key' => trim((string) ($cfg['order_api_key'] ?? '')),
        'site_origin' => trim((string) ($cfg['site_origin'] ?? '')),
        'order_source_key' => trim((string) ($cfg['order_source_key'] ?? '')),
        'form_source_key' => trim((string) ($cfg['form_source_key'] ?? '')),
        'webhook_secret' => trim((string) ($cfg['webhook_secret'] ?? '')),
    ], true);

    $content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Ortak Panel sipariş senkronu — install.php ile oluşturuldu/güncellendi.\n */\nreturn {$export};\n";

    return file_put_contents($path, $content) !== false;
}

function install_mark_complete(PDO $pdo, string $baseTitle): void
{
    install_ensure_schema($pdo);
    require_once __DIR__ . '/site_helpers.php';
    install_apply_site_url($pdo);
    install_apply_site_identity($pdo, $baseTitle);
    require_once __DIR__ . '/site_content_urls.php';
    site_content_rewrite_stored_urls($pdo);
    $pdo->prepare(
        'UPDATE site_install SET is_complete = 1, site_base_title = ?, completed_at = NOW() WHERE id = 1'
    )->execute([trim($baseTitle)]);
}

/** Mevcut canlı site — ürün varsa kurulum zorunluluğunu atla (güncelleme geçişi). */
function install_legacy_autocomplete(PDO $pdo): void
{
    install_ensure_schema($pdo);
    try {
        $row = $pdo->query('SELECT is_complete FROM site_install WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if ((int) ($row['is_complete'] ?? 0) === 1) {
            return;
        }
        $products = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        if ($products > 0) {
            require_once __DIR__ . '/site_helpers.php';
            site_url_sync_from_request($pdo);
            $pdo->exec(
                "UPDATE site_install SET is_complete = 1, site_base_title = COALESCE(NULLIF(site_base_title,''), 'Mevcut kurulum'), completed_at = NOW() WHERE id = 1"
            );
        }
    } catch (Throwable $e) {
    }
}

/**
 * Kurulum öncesi sunucu / PHP gereksinimleri.
 *
 * @return list<array{key:string,label:string,ok:bool,required:bool,detail:string}>
 */
function install_server_requirements(?PDO $pdo, ?string $dbError = null, string $projectRoot = ''): array
{
    if ($projectRoot === '') {
        $projectRoot = dirname(__DIR__);
    }

    $reqs = [];

    $phpOk = PHP_VERSION_ID >= 80000;
    $reqs[] = [
        'key' => 'php',
        'label' => 'PHP sürümü',
        'ok' => $phpOk,
        'required' => true,
        'detail' => 'PHP ' . PHP_VERSION . ' (gerekli: 8.0+)',
    ];

    $extChecks = [
        'pdo' => ['PDO', true],
        'pdo_mysql' => ['PDO MySQL', true],
        'curl' => ['cURL (API / bildirim)', true],
        'json' => ['JSON', true],
        'mbstring' => ['mbstring (Türkçe metin)', true],
        'openssl' => ['OpenSSL (HTTPS)', true],
        'session' => ['Session', true],
        'gd' => ['GD (görsel işleme)', false],
        'zip' => ['Zip (tema yedeği)', false],
        'fileinfo' => ['Fileinfo (dosya yükleme)', false],
    ];

    foreach ($extChecks as $ext => [$label, $required]) {
        $reqs[] = [
            'key' => 'ext_' . $ext,
            'label' => $label,
            'ok' => extension_loaded($ext),
            'required' => $required,
            'detail' => extension_loaded($ext) ? 'Yüklü' : 'Eksik — php.ini içinde etkinleştirin',
        ];
    }

    $dbOk = $pdo instanceof PDO && $dbError === null;
    $dbDetail = 'Bağlantı başarılı';
    if (! $dbOk) {
        $dbDetail = $dbError !== null && $dbError !== ''
            ? mb_substr($dbError, 0, 200)
            : 'db.php ayarlarını ve MySQL servisini kontrol edin';
    }
    $reqs[] = [
        'key' => 'database',
        'label' => 'Veritabanı (MySQL)',
        'ok' => $dbOk,
        'required' => true,
        'detail' => $dbDetail,
    ];

    $tablesOk = false;
    $tablesDetail = 'settings / site_install tabloları bulunamadı — SQL import edin';
    if ($dbOk) {
        try {
            $hasSettings = (bool) $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
            $hasInstall = (bool) $pdo->query("SHOW TABLES LIKE 'site_install'")->fetchColumn();
            $tablesOk = $hasSettings && $hasInstall;
            if ($tablesOk) {
                $tablesDetail = 'Temel tablolar mevcut';
            } elseif ($hasSettings || $hasInstall) {
                $tablesDetail = 'Eksik tablo — phpMyAdmin\'den tam SQL dump import edin';
            }
        } catch (Throwable $e) {
            $tablesDetail = $e->getMessage();
        }
    } else {
        $tablesDetail = 'Önce veritabanı bağlantısı gerekli';
    }
    $reqs[] = [
        'key' => 'tables',
        'label' => 'Veritabanı tabloları',
        'ok' => $tablesOk,
        'required' => true,
        'detail' => $tablesDetail,
    ];

    $dirChecks = [
        'uploads' => ['uploads/', true],
        'includes' => ['includes/', true],
        'cache' => ['cache/', false],
    ];
    foreach ($dirChecks as $key => [$rel, $required]) {
        $path = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        if (! $exists && $required) {
            @mkdir($path, 0755, true);
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
        }
        $reqs[] = [
            'key' => 'dir_' . $key,
            'label' => 'Yazılabilir: ' . $rel,
            'ok' => $writable,
            'required' => $required,
            'detail' => $writable ? 'OK' : ($exists ? 'Yazma izni yok (chmod 755/775)' : 'Klasör yok'),
        ];
    }

    $configPath = $projectRoot . '/includes/laravel4_config.php';
    $configOk = is_file($configPath) ? is_writable($configPath) : is_writable(dirname($configPath));
    $reqs[] = [
        'key' => 'config',
        'label' => 'includes/ klasörü (yazılabilir)',
        'ok' => $configOk,
        'required' => true,
        'detail' => $configOk ? 'Yazılabilir' : 'includes/ klasörüne yazma izni verin',
    ];

    $rewriteOk = null;
    $rewriteDetail = 'Apache dışı veya algılanamadı — temiz URL için rewrite gerekli';
    if (function_exists('apache_get_modules')) {
        $rewriteOk = in_array('mod_rewrite', apache_get_modules(), true);
        $rewriteDetail = $rewriteOk ? 'mod_rewrite aktif' : 'mod_rewrite kapalı — .htaccess çalışmaz';
    } elseif (str_contains(strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? '')), 'apache')) {
        $rewriteOk = false;
        $rewriteDetail = 'mod_rewrite durumu okunamadı';
    }
    if ($rewriteOk !== null) {
        $reqs[] = [
            'key' => 'rewrite',
            'label' => 'Apache mod_rewrite',
            'ok' => $rewriteOk,
            'required' => false,
            'detail' => $rewriteDetail,
        ];
    }

    $memLimit = ini_get('memory_limit');
    $memBytes = install_parse_ini_bytes(is_string($memLimit) ? $memLimit : '128M');
    $memOk = $memBytes >= 128 * 1024 * 1024 || $memBytes === -1;
    $reqs[] = [
        'key' => 'memory',
        'label' => 'memory_limit',
        'ok' => $memOk,
        'required' => false,
        'detail' => (is_string($memLimit) ? $memLimit : '?') . ' (önerilen: 128M+)',
    ];

    return $reqs;
}

function install_parse_ini_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '-1') {
        return -1;
    }
    $n = (int) $val;
    $u = strtolower(substr($val, -1));
    if ($u === 'g') {
        return $n * 1024 * 1024 * 1024;
    }
    if ($u === 'm') {
        return $n * 1024 * 1024;
    }
    if ($u === 'k') {
        return $n * 1024;
    }

    return $n;
}

/** @param list<array{ok:bool,required:bool}> $reqs */
function install_requirements_met(array $reqs): bool
{
    foreach ($reqs as $r) {
        if (! empty($r['required']) && empty($r['ok'])) {
            return false;
        }
    }

    return true;
}

/** Metinden panel kaynak anahtarı üretir (ör. "NovaShop" → "novashop_web"). */
function install_suggest_source_key(string $title, string $suffix = 'web'): string
{
    $map = [
        'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
    ];
    $slug = strtr($title, $map);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'site';
    }
    $parts = explode('_', $slug);
    $slug = $parts[0];
    if ($slug === '') {
        $slug = 'site';
    }

    return $suffix !== '' ? $slug . '_' . $suffix : $slug;
}

/** Rastgele webhook imza anahtarı üretir. */
function install_generate_secret(int $bytes = 24): string
{
    try {
        return bin2hex(random_bytes(max(8, $bytes)));
    } catch (Throwable $e) {
        return substr(str_shuffle(str_repeat('abcdef0123456789', 8)), 0, $bytes * 2);
    }
}

/**
 * Ortak panel adresinin erişilebilirliğini kontrol eder.
 *
 * @return array{ok:bool,status:int,message:string}
 */
function install_test_panel_connection(string $baseUrl, string $apiKey = ''): array
{
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '' || ! preg_match('#^https?://#i', $baseUrl)) {
        return ['ok' => false, 'status' => 0, 'message' => 'Geçerli bir panel adresi girin (http:// veya https:// ile).'];
    }

    if (! function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'message' => 'cURL eklentisi yüklü değil.'];
    }

    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'SiteInstaller/1.0',
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($status === 0) {
        return [
            'ok' => false,
            'status' => 0,
            'message' => 'Panele ulaşılamadı: ' . ($err !== '' ? $err : 'bağlantı zaman aşımı / DNS'),
        ];
    }

    if ($status >= 500) {
        return ['ok' => false, 'status' => $status, 'message' => "Panel yanıt verdi ancak sunucu hatası (HTTP {$status})."];
    }

    $note = $apiKey === '' ? ' (API anahtarı boş — senkron kapalı kalır)' : '';

    return [
        'ok' => true,
        'status' => $status,
        'message' => "Panele ulaşıldı (HTTP {$status}){$note}.",
    ];
}
