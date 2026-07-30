<?php
declare(strict_types=1);
require dirname(__DIR__) . '/db.php';
$root = dirname(__DIR__);
echo "SLIDER\n";
foreach ($pdo->query('SELECT id, image_path FROM slider_images ORDER BY display_order') as $r) {
    $p = (string) $r['image_path'];
    $full = $root . '/' . ltrim(str_replace('../', '', str_replace('\\', '/', $p)), '/');
    $ok = is_file($full) ? 'OK' : 'MISSING';
    echo $r['id'] . '|' . $ok . '|' . $p . PHP_EOL;
}
echo "PRODUCT_IMAGES\n";
foreach ($pdo->query('SELECT product_id, image_path FROM product_images LIMIT 20') as $r) {
    $p = (string) $r['image_path'];
    $full = $root . '/' . ltrim(str_replace('../', '', str_replace('\\', '/', $p)), '/');
    $ok = is_file($full) ? 'OK' : 'MISSING';
    echo $r['product_id'] . '|' . $ok . '|' . $p . PHP_EOL;
}
