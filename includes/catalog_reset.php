<?php
declare(strict_types=1);

/** Tablo var mı? */
function catalog_table_exists(PDO $pdo, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    try {
        $q = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

        return $q && $q->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function catalog_truncate_or_delete(PDO $pdo, string $table): void
{
    if (! catalog_table_exists($pdo, $table)) {
        return;
    }
    try {
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    } catch (Throwable $e) {
        $pdo->exec('DELETE FROM `' . $table . '`');
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
        } catch (Throwable $e2) {
            /* kolon yoksa veya izin yoksa atla */
        }
    }
}

/** @return list<string> */
function catalog_collect_product_image_paths(PDO $pdo): array
{
    $paths = [];
    if (catalog_table_exists($pdo, 'product_images')) {
        foreach ($pdo->query('SELECT image_path FROM product_images') as $row) {
            $p = trim((string) ($row['image_path'] ?? ''));
            if ($p !== '') {
                $paths[] = $p;
            }
        }
    }
    if (catalog_table_exists($pdo, 'products')) {
        foreach ($pdo->query("SELECT product_image FROM products WHERE product_image IS NOT NULL AND TRIM(product_image) != ''") as $row) {
            $p = trim((string) ($row['product_image'] ?? ''));
            if ($p !== '') {
                $paths[] = $p;
            }
        }
    }

    return array_values(array_unique($paths));
}

/** @param list<string> $relativePaths */
function catalog_delete_upload_files(array $relativePaths, string $projectRoot): int
{
    $deleted = 0;
    $uploadsRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsRoot === false) {
        return 0;
    }

    foreach ($relativePaths as $rel) {
        $rel = str_replace('\\', '/', trim($rel));
        $rel = preg_replace('#^(\.\./)+#', '', $rel) ?? $rel;
        $rel = ltrim($rel, '/');
        if ($rel === '' || str_contains($rel, '..')) {
            continue;
        }
        $full = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        if ($full === false || ! str_starts_with($full, $uploadsRoot)) {
            continue;
        }
        if (is_file($full) && @unlink($full)) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Tüm ürün + varyant verisini sıfırla (sipariş tablolarına dokunmaz).
 *
 * @return array{tables:list<string>,files_deleted:int}
 */
function catalog_reset_all(PDO $pdo): array
{
    $projectRoot = dirname(__DIR__);
    $imagePaths = catalog_collect_product_image_paths($pdo);

    if (is_file(__DIR__ . '/media_guard.php')) {
        require_once __DIR__ . '/media_guard.php';
        media_guard_log('catalog_reset_wipe', ['paths' => count($imagePaths)]);
    }

    $tables = [
        'product_review_images',
        'product_reviews',
        'product_variation_assignments',
        'product_images',
        'products',
        'product_variation_options',
        'product_variation_types',
        'product_variants',
        'product_variants_2',
        'product_variations',
        'product_variations_2',
    ];

    $cleared = [];
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            if (! catalog_table_exists($pdo, $table)) {
                continue;
            }
            catalog_truncate_or_delete($pdo, $table);
            $cleared[] = $table;
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $filesDeleted = catalog_delete_upload_files($imagePaths, $projectRoot);

    return ['tables' => $cleared, 'files_deleted' => $filesDeleted];
}

/** POST sıfırlama isteğini doğrula */
function catalog_reset_request_valid(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['catalog_reset_all'])
        && (string) ($_POST['catalog_reset_confirm'] ?? '') === 'SIFIRLA';
}
