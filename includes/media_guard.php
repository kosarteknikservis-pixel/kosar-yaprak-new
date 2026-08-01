<?php

declare(strict_types=1);

/**
 * Ürün / slider görselleri — yedek arşiv + eksik dosya onarımı.
 * Arşiv uploads/ DIŞINDA tutulur (tema geri yükleme uploads'ı silse bile kalır).
 */

function media_guard_project_root(): string
{
    return dirname(__DIR__);
}

function media_guard_uploads_root(): string
{
    return media_guard_project_root() . DIRECTORY_SEPARATOR . 'uploads';
}

function media_guard_archive_root(): string
{
    return media_guard_project_root() . DIRECTORY_SEPARATOR . 'uploads_media_archive';
}

function media_guard_log(string $action, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] [media_guard] ' . $action;
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $line .= ' ' . $json;
        }
    }
    $line .= "\n";
    @file_put_contents(media_guard_project_root() . '/hata_loglari.log', $line, FILE_APPEND | LOCK_EX);
}

function media_guard_normalize_rel(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#^(\.\./)+#', '', $path) ?? $path;
    $path = ltrim($path, '/');
    if ($path === '') {
        return '';
    }
    if (! str_starts_with($path, 'uploads/')) {
        $path = 'uploads/' . ltrim($path, '/');
    }

    return $path;
}

function media_guard_full_path(string $rel): ?string
{
    $rel = media_guard_normalize_rel($rel);
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }

    $full = media_guard_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $uploadsRoot = realpath(media_guard_uploads_root());
    $real = realpath($full);
    if ($real === false) {
        $real = $full;
    }
    if ($uploadsRoot !== false && ! str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $uploadsRoot))) {
        return null;
    }

    return $full;
}

function media_guard_archive_path_for_rel(string $rel): string
{
    $rel = media_guard_normalize_rel($rel);
    $basename = basename($rel);
    if ($basename === '' || $basename === '.' || $basename === '..') {
        return '';
    }

    return media_guard_archive_root() . DIRECTORY_SEPARATOR . $basename;
}

/**
 * Yüklenen / güncellenen dosyayı arşive kopyala.
 */
function media_guard_archive_file(string $storedPath): bool
{
    $full = media_guard_full_path($storedPath);
    if ($full === null || ! is_file($full)) {
        return false;
    }

    $archiveRoot = media_guard_archive_root();
    if (! is_dir($archiveRoot)) {
        @mkdir($archiveRoot, 0755, true);
    }

    $dest = media_guard_archive_path_for_rel($storedPath);
    if ($dest === '') {
        return false;
    }

    @mkdir(dirname($dest), 0755, true);
    $ok = @copy($full, $dest);

    if ($ok) {
        media_guard_log('archived', ['file' => media_guard_normalize_rel($storedPath)]);
    }

    return $ok;
}

/**
 * @return list<string>
 */
function media_guard_collect_referenced_paths(PDO $pdo): array
{
    $paths = [];

    try {
        if ($pdo->query("SHOW TABLES LIKE 'product_images'")->fetch()) {
            foreach ($pdo->query('SELECT image_path FROM product_images') as $row) {
                $p = trim((string) ($row['image_path'] ?? ''));
                if ($p !== '') {
                    $paths[] = $p;
                }
            }
        }
        if ($pdo->query("SHOW TABLES LIKE 'products'")->fetch()) {
            foreach ($pdo->query("SELECT product_image FROM products WHERE product_image IS NOT NULL AND TRIM(product_image) != ''") as $row) {
                $p = trim((string) ($row['product_image'] ?? ''));
                if ($p !== '') {
                    $paths[] = $p;
                }
            }
        }
        if ($pdo->query("SHOW TABLES LIKE 'slider_images'")->fetch()) {
            foreach ($pdo->query('SELECT image_path FROM slider_images') as $row) {
                $p = trim((string) ($row['image_path'] ?? ''));
                if ($p !== '') {
                    $paths[] = $p;
                }
            }
        }
        if ($pdo->query("SHOW TABLES LIKE 'footer_images'")->fetch()) {
            foreach ($pdo->query('SELECT image_path, logo_image FROM footer_images') as $row) {
                foreach (['image_path', 'logo_image'] as $col) {
                    $p = trim((string) ($row[$col] ?? ''));
                    if ($p !== '') {
                        $paths[] = $p;
                    }
                }
            }
        }
        foreach (['about_us', 'contact_info', 'shipping_process'] as $tbl) {
            if ($pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetch()) {
                foreach ($pdo->query("SELECT image FROM `{$tbl}`") as $row) {
                    $p = trim((string) ($row['image'] ?? ''));
                    if ($p !== '') {
                        $paths[] = $p;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        media_guard_log('collect_error', ['error' => $e->getMessage()]);
    }

    $normalized = [];
    foreach ($paths as $p) {
        $n = media_guard_normalize_rel($p);
        if ($n !== '') {
            $normalized[$n] = true;
        }
    }

    return array_keys($normalized);
}

/**
 * DB'de kayıtlı ama diskte olmayan görselleri arşivden geri koy.
 *
 * @return array{checked:int, missing:int, restored:int, still_missing:list<string>}
 */
function media_guard_verify_and_restore(PDO $pdo): array
{
    $checked = 0;
    $missing = 0;
    $restored = 0;
    $stillMissing = [];

    foreach (media_guard_collect_referenced_paths($pdo) as $rel) {
        $checked++;
        $full = media_guard_full_path($rel);
        if ($full !== null && is_file($full)) {
            continue;
        }

        $missing++;
        $archive = media_guard_archive_path_for_rel($rel);
        if ($archive !== '' && is_file($archive) && $full !== null) {
            @mkdir(dirname($full), 0755, true);
            if (@copy($archive, $full)) {
                $restored++;
                media_guard_log('restored', ['file' => $rel, 'from' => 'archive']);
                continue;
            }
        }

        $stillMissing[] = $rel;
    }

    if ($restored > 0 || $stillMissing !== []) {
        media_guard_log('verify', [
            'checked' => $checked,
            'missing' => $missing,
            'restored' => $restored,
            'still_missing' => $stillMissing,
        ]);
    }

    return [
        'checked' => $checked,
        'missing' => $missing,
        'restored' => $restored,
        'still_missing' => $stillMissing,
    ];
}

/**
 * Panelden kasıtlı silme — log tutar, arşivde kopya kalır.
 */
function media_guard_safe_unlink(string $storedPath, string $reason = 'admin_delete'): bool
{
    $full = media_guard_full_path($storedPath);
    if ($full === null || ! is_file($full)) {
        return false;
    }

    media_guard_archive_file($storedPath);
    $ok = @unlink($full);
    if ($ok) {
        media_guard_log('unlink', ['file' => media_guard_normalize_rel($storedPath), 'reason' => $reason]);
    }

    return $ok;
}

/**
 * Mevcut uploads içeriğini arşive senkronize et (ilk kurulum / deploy sonrası).
 */
function media_guard_sync_uploads_to_archive(): int
{
    $uploads = media_guard_uploads_root();
    if (! is_dir($uploads)) {
        return 0;
    }

    $count = 0;
    $archiveRoot = media_guard_archive_root();
    if (! is_dir($archiveRoot)) {
        @mkdir($archiveRoot, 0755, true);
    }

    foreach (scandir($uploads) ?: [] as $name) {
        if ($name === '.' || $name === '..' || $name === '_media_archive') {
            continue;
        }
        $src = $uploads . DIRECTORY_SEPARATOR . $name;
        if (! is_file($src)) {
            continue;
        }
        $dest = $archiveRoot . DIRECTORY_SEPARATOR . $name;
        if (! is_file($dest) || filesize($src) !== filesize($dest)) {
            if (@copy($src, $dest)) {
                $count++;
            }
        }
    }

    if ($count > 0) {
        media_guard_log('archive_sync', ['files' => $count]);
    }

    return $count;
}
