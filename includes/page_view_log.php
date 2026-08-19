<?php
declare(strict_types=1);

/** Vitrin sayfaları — dashboard ziyaretçi metrikleri yalnızca bunları sayar. */
function page_view_storefront_pages(): array
{
    return ['index.php', 'order.php', 'thankyou.php'];
}

function page_view_stats_sql_in(): string
{
    return "'" . implode("','", page_view_storefront_pages()) . "'";
}

function page_view_is_bot(): bool
{
    $ua = strtolower(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    if ($ua === '') {
        return true;
    }
    static $needles = [
        'bot', 'spider', 'crawl', 'slurp', 'facebookexternalhit', 'whatsapp',
        'telegrambot', 'bingpreview', 'yandex', 'baiduspider', 'duckduckbot',
        'googlebot', 'adsbot', 'petalbot', 'semrush', 'ahrefs', 'mj12bot',
        'dotbot', 'screaming frog', 'headlesschrome', 'lighthouse', 'pingdom',
        'uptimerobot', 'monitor', 'curl/', 'wget/', 'python-requests',
        'go-http-client', 'java/', 'libwww-perl',
    ];
    foreach ($needles as $n) {
        if (str_contains($ua, $n)) {
            return true;
        }
    }

    return false;
}

function page_view_should_log(string $pageName): bool
{
    if (! in_array($pageName, page_view_storefront_pages(), true)) {
        return false;
    }
    if (page_view_is_bot()) {
        return false;
    }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return false;
    }

    return true;
}

/** @param PDO $pdo */
function page_view_log(PDO $pdo, string $pageName): void
{
    if (! page_view_should_log($pageName)) {
        return;
    }

    if (! function_exists('app_client_ip')) {
        require_once __DIR__ . '/app_url.php';
    }

    $ip = app_client_ip();
    if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
        return;
    }

    try {
        $dedupe = $pdo->prepare(
            'SELECT 1 FROM page_views
             WHERE ip_address = ? AND page_name = ?
               AND visit_time >= NOW() - INTERVAL 10 MINUTE
             LIMIT 1'
        );
        $dedupe->execute([$ip, $pageName]);
        if ($dedupe->fetchColumn()) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO page_views (page_name, ip_address, visit_time) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$pageName, $ip]);
    } catch (Throwable $e) {
        /* sessiz */
    }
}
