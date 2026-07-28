<?php
declare(strict_types=1);

require_once __DIR__ . '/site_helpers.php';

/**
 * Göreli uploads yolu veya eski tam URL → geçerli site tabanlı adres.
 */
function site_media_public_src(string $src, ?PDO $pdo = null): string
{
    $src = trim($src);
    if ($src === '') {
        return '';
    }

    require_once __DIR__ . '/app_url.php';

    if (preg_match('#^https?://#i', $src)) {
        if (preg_match('#/(uploads/.+)$#i', $src, $m)) {
            return app_url($m[1], [], $pdo);
        }
        $host = parse_url($src, PHP_URL_HOST);
        $baseHost = parse_url(site_public_url($pdo), PHP_URL_HOST);
        if (is_string($host) && is_string($baseHost) && strcasecmp($host, $baseHost) !== 0) {
            $path = parse_url($src, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return app_url(ltrim($path, '/'), [], $pdo);
            }
        }

        return $src;
    }

    $rel = ltrim(str_replace('\\', '/', $src), '/');
    if (str_starts_with($rel, '../')) {
        $rel = ltrim(substr($rel, 3), '/');
    }

    return app_url($rel, [], $pdo);
}

/** @return list<string> */
function site_content_url_bases(PDO $pdo): array
{
    $bases = [];
    try {
        $siteUrl = site_url_normalize((string) ($pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn() ?: ''));
        if ($siteUrl !== '') {
            $bases[] = $siteUrl;
        }
    } catch (Throwable $e) {
    }

    foreach (['http://localhost/3dhesap', 'http://localhost/3dhesap/', 'http://3dhesap.test', 'https://3dhesap.test'] as $guess) {
        $bases[] = rtrim($guess, '/');
    }

    $detected = site_url_detect_from_request();
    if ($detected !== '') {
        $bases[] = $detected;
    }

    return array_values(array_unique(array_filter($bases)));
}

function site_content_replace_base(string $value, string $oldBase, string $newBase): string
{
    $oldBase = rtrim($oldBase, '/');
    $newBase = rtrim($newBase, '/');
    if ($oldBase === '' || $newBase === '' || $oldBase === $newBase) {
        return $value;
    }

    return str_replace(
        [$oldBase . '/', $oldBase],
        [$newBase . '/', $newBase],
        $value
    );
}

/**
 * about_us / iletisim / kargo görselleri ve benzeri tam URL’leri günceller.
 */
function site_content_rewrite_stored_urls(PDO $pdo, ?string $newBase = null): int
{
    $newBase = site_url_normalize($newBase ?? site_url_detect_from_request());
    if ($newBase === '' || ! site_url_is_valid($newBase)) {
        return 0;
    }

    $updated = 0;
    $textTables = [
        ['about_us', 'image'],
        ['contact_info', 'image'],
        ['shipping_process', 'image'],
        ['slider_images', 'image_path'],
        ['product_images', 'image_path'],
        ['products', 'product_image'],
        ['footer_images', 'image_path'],
        ['footer_images', 'logo_image'],
    ];

    foreach ($textTables as [$table, $column]) {
        try {
            $q = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
            if (! $q || ! $q->fetch()) {
                continue;
            }
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $pk = array_key_first($row);
                if ($pk === null) {
                    continue;
                }
                $raw = (string) ($row[$column] ?? '');
                if ($raw === '' || ! str_contains($raw, 'http')) {
                    continue;
                }
                $next = $raw;
                foreach (site_content_url_bases($pdo) as $oldBase) {
                    $next = site_content_replace_base($next, $oldBase, $newBase);
                }
                if ($next !== $raw) {
                    $pdo->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE `{$pk}` = ?")
                        ->execute([$next, $row[$pk]]);
                    $updated++;
                }
            }
        } catch (Throwable $e) {
            /* tablo yoksa atla */
        }
    }

    return $updated;
}
