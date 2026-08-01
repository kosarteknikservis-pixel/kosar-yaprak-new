<?php
declare(strict_types=1);
define('INSTALL_GUARD_SKIP', true);
require dirname(__DIR__) . '/db.php';

$root = dirname(__DIR__);
$missing = [];
$ok = [];

$products = $pdo->query(
    'SELECT p.product_id, p.product_name, p.product_image, p.status
     FROM products p
     WHERE p.status = \'visible\'
     ORDER BY p.display_order, p.product_id'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {
    $pid = (int) $p['product_id'];
    $st = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY image_id ASC');
    $st->execute([$pid]);
    $paths = $st->fetchAll(PDO::FETCH_COLUMN);

    if ($paths === []) {
        $pi = trim((string) ($p['product_image'] ?? ''));
        if ($pi !== '') {
            $paths = [$pi];
        }
    }

    $found = false;
    $checked = [];
    foreach ($paths as $path) {
        $path = str_replace('\\', '/', trim((string) $path));
        if ($path === '') {
            continue;
        }
        $checked[] = $path;
        $candidates = [];
        if (preg_match('#^https?://#i', $path)) {
            if (preg_match('#/(uploads/.+)$#i', $path, $m)) {
                $candidates[] = $root . '/' . $m[1];
            }
        } elseif (str_starts_with($path, 'uploads/')) {
            $candidates[] = $root . '/' . $path;
        } elseif (str_starts_with($path, '../uploads/')) {
            $candidates[] = $root . '/' . substr($path, 3);
        } else {
            $candidates[] = $root . '/uploads/' . ltrim($path, '/');
            $candidates[] = $root . '/' . ltrim($path, '/');
        }
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $found = true;
                break 2;
            }
        }
    }

    if ($found) {
        $ok[] = ['id' => $pid, 'name' => $p['product_name'], 'paths' => $checked];
    } else {
        $missing[] = ['id' => $pid, 'name' => $p['product_name'], 'paths' => $checked, 'product_image' => $p['product_image']];
    }
}

echo "MISSING (" . count($missing) . "):\n";
echo json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo "OK count: " . count($ok) . "\n";
