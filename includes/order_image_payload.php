<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';

/**
 * Ürün görsel yolunu tam site URL'sine çevirir (html2canvas CORS için).
 */
function order_image_upload_url(?PDO $pdo, string $rawPath): string
{
    $path = trim($rawPath);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    $base = rtrim(app_site_url($pdo), '/');
    if (str_starts_with($path, '/uploads/')) {
        return $base . $path;
    }
    if (str_starts_with($path, 'uploads/')) {
        return $base . '/' . $path;
    }

    return $base . '/uploads/' . ltrim(basename($path), '/');
}

/**
 * Sipariş görseli stüdyosu / fabrikası için JSON özet.
 *
 * @return array<string, mixed>|null
 */
function order_image_build_payload(PDO $pdo, int $orderId): ?array
{
    $orderId = max(1, $orderId);
    try {
        $stmt = $pdo->prepare(
            'SELECT oi.quantity, oi.price, oi.product_id, p.product_name, p.product_image
             FROM order_items oi
             INNER JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.order_item_id ASC'
        );
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return null;
        }

        $names = [];
        $total = 0.0;
        $imageUrls = [];
        foreach ($rows as $r) {
            $qty = isset($r['quantity']) ? (float) $r['quantity'] : 1.0;
            $price = isset($r['price']) ? (float) $r['price'] : 0.0;
            $total += $price * max(1.0, $qty);
            $names[] = (string) ($r['product_name'] ?? 'Ürün');

            $imgPath = (string) ($r['product_image'] ?? '');
            if ($imgPath === '') {
                $pi = $pdo->prepare(
                    'SELECT image_path FROM product_images WHERE product_id = ? LIMIT 1'
                );
                $pi->execute([(int) ($r['product_id'] ?? 0)]);
                $imgPath = (string) ($pi->fetchColumn() ?: '');
            }
            $url = order_image_upload_url($pdo, $imgPath);
            if ($url !== '' && !in_array($url, $imageUrls, true)) {
                $imageUrls[] = $url;
            }
        }

        $line = implode(' · ', array_slice($names, 0, 3));
        if (count($names) > 3) {
            $line .= '…';
        }

        return [
            'order_id' => $orderId,
            'productLine' => $line,
            'subtitle' => implode(' + ', array_slice($names, 0, 2)),
            'titleMain' => $names[0] ?? 'Ürün',
            'priceTotal' => $total,
            'priceLine' => number_format($total, 2, ',', '.') . ' TL · '
                . (count($rows) > 1 ? (count($rows) . ' kalem') : 'tek kalem'),
            'productImages' => $imageUrls,
            'primaryImage' => $imageUrls[0] ?? '',
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function order_image_storage_dir(): string
{
    return dirname(__DIR__) . '/uploads/order_images';
}

/**
 * Fabrikada sunucuya kaydedilen sipariş görselleri (en yeni önce).
 *
 * @return list<array{filename:string,path:string,url:string,mtime:int,size:int}>
 */
function order_image_list_saved(?PDO $pdo, int $orderId): array
{
    $orderId = max(1, $orderId);
    $dir = order_image_storage_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $prefix = 'order_' . $orderId . '_';
    $files = glob($dir . '/' . $prefix . '*');
    if ($files === false || $files === []) {
        return [];
    }

    $out = [];
    foreach ($files as $full) {
        if (!is_file($full)) {
            continue;
        }
        $basename = basename($full);
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            continue;
        }
        $rel = 'uploads/order_images/' . $basename;
        $out[] = [
            'filename' => $basename,
            'path' => $rel,
            'url' => order_image_upload_url($pdo, $rel),
            'mtime' => (int) filemtime($full),
            'size' => (int) filesize($full),
        ];
    }

    usort($out, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

    return $out;
}
