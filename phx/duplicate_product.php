<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$pid = (int) ($_GET['product_id'] ?? 0);

if ($pid <= 0) {
    $_SESSION['message'] = 'Geçersiz ürün.';
    $_SESSION['message_type'] = 'danger';
    header('Location: products.php#products-list');
    exit;
}

$st = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
$st->execute([$pid]);
$src = $st->fetch(PDO::FETCH_ASSOC);

if (!$src) {
    $_SESSION['message'] = 'Ürün bulunamadı.';
    $_SESSION['message_type'] = 'danger';
    header('Location: products.php#products-list');
    exit;
}

$projectRoot = dirname(__DIR__);
$uploadDirAbs = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

if (!is_dir($uploadDirAbs)) {
    mkdir($uploadDirAbs, 0777, true);
}

/**
 * @return string|false
 */
function resolve_upload_abs(string $stored, string $projectRoot)
{
    $stored = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($stored));
    if ($stored === '') {
        return false;
    }

    if (str_starts_with($stored, '..' . DIRECTORY_SEPARATOR)) {
        $rp = realpath($projectRoot . DIRECTORY_SEPARATOR . $stored);

        return ($rp !== false && is_file($rp)) ? $rp : false;
    }

    if (str_starts_with($stored, 'uploads' . DIRECTORY_SEPARATOR)) {
        $rp = realpath($projectRoot . DIRECTORY_SEPARATOR . $stored);

        return ($rp !== false && is_file($rp)) ? $rp : false;
    }

    $base = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $rp = realpath($base . basename($stored));

    return ($rp !== false && is_file($rp)) ? $rp : false;
}

try {
    $pdo->beginTransaction();

    $name = trim((string) ($src['product_name'] ?? '')) . ' (Kopya)';

    $ins = $pdo->prepare(
        'INSERT INTO products (product_name, product_description, product_price, original_price, show_description, show_price, show_name_heading, status, display_order, product_image, sku)
         VALUES (?,?,?,?,?,?,?,?,?,?, NULL)'
    );

    $ins->execute([
        mb_substr($name, 0, 512),
        $src['product_description'] ?? '',
        $src['product_price'] ?? 0,
        $src['original_price'] ?? 0,
        (int) ($src['show_description'] ?? 0),
        (int) ($src['show_price'] ?? 0),
        (int) ($src['show_name_heading'] ?? 0),
        $src['status'] ?? 'visible',
        (int) ($src['display_order'] ?? 0) + 1,
        $src['product_image'] ?? null,
    ]);

    $newId = (int) $pdo->lastInsertId();

    $imgStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY image_id ASC');
    $imgStmt->execute([$pid]);
    $paths = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    $idx = 0;
    foreach ($paths as $oldPath) {
        $srcFile = resolve_upload_abs((string) $oldPath, $projectRoot);
        if ($srcFile === false || !is_readable($srcFile)) {
            continue;
        }

        $ext = pathinfo($srcFile, PATHINFO_EXTENSION) ?: 'jpg';
        $newName = 'product_' . $newId . '_' . time() . '_' . $idx . '.' . strtolower($ext);
        $destAbs = $uploadDirAbs . $newName;
        if (!@copy($srcFile, $destAbs)) {
            continue;
        }

        $dbPath = '../uploads/' . $newName;
        $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?,?)')->execute([$newId, $dbPath]);

        if ($idx === 0) {
            $pdo->prepare('UPDATE products SET product_image = ? WHERE product_id = ?')->execute([$dbPath, $newId]);
        }
        ++$idx;
    }

    $as = $pdo->prepare('SELECT type_id, is_required FROM product_variation_assignments WHERE product_id = ?');
    $as->execute([$pid]);
    foreach ($as->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pdo->prepare('INSERT INTO product_variation_assignments (product_id, type_id, is_required) VALUES (?,?,?)')->execute([
            $newId,
            $row['type_id'],
            $row['is_required'],
        ]);
    }

    $pdo->commit();

    try {
        $copySku = 'PRD-COPY-' . str_pad((string) $newId, 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE products SET sku = ? WHERE product_id = ?')->execute([$copySku, $newId]);
    } catch (Throwable $e) {
        // SKU kolonu yoksa veya çakışma — kopya yine de oluştu
    }

    $_SESSION['message'] = 'Ürün kopyalandı (#' . $newId . '). Listede bulup ad ve SKU’yu düzenleyebilirsiniz.';
    $_SESSION['message_type'] = 'success';
    header('Location: products.php#products-list');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['message'] = 'Kopyalanamadı: ' . htmlspecialchars($e->getMessage());
    $_SESSION['message_type'] = 'danger';
    header('Location: products.php#products-list');
    exit;
}
