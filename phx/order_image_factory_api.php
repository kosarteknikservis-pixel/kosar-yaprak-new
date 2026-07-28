<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/order_image_payload.php';

header('Content-Type: application/json; charset=utf-8');

if (!admin_user_can('menu_yz')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'orders') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $limit = max(1, min((int) ($_GET['limit'] ?? 50), 200));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    $q = trim((string) ($_GET['q'] ?? ''));

    $parts = [];
    $params = [];
    if ($status !== '') {
        $parts[] = 's.status_name = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $parts[] = '(o.customer_name LIKE ? OR o.customer_phone LIKE ? OR CAST(o.order_id AS CHAR) LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $where = $parts !== [] ? 'WHERE ' . implode(' AND ', $parts) : '';

    try {
        $countSql = "SELECT COUNT(*) FROM orders o JOIN order_status s ON o.order_status_id = s.order_status_id {$where}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT o.order_id, o.customer_name, o.customer_phone, o.order_date, s.status_name,
                       (SELECT COALESCE(SUM(oi.price * GREATEST(COALESCE(oi.quantity, 1), 1)), 0)
                        FROM order_items oi WHERE oi.order_id = o.order_id) AS order_total,
                       (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.order_id) AS item_count
                FROM orders o
                JOIN order_status s ON o.order_status_id = s.order_status_id
                {$where}
                ORDER BY o.order_id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $orders = [];
        foreach ($rows as $r) {
            $orders[] = [
                'order_id' => (int) $r['order_id'],
                'customer_name' => (string) ($r['customer_name'] ?? ''),
                'customer_phone' => (string) ($r['customer_phone'] ?? ''),
                'status_name' => (string) ($r['status_name'] ?? ''),
                'order_date' => (string) ($r['order_date'] ?? ''),
                'order_total' => (float) ($r['order_total'] ?? 0),
                'item_count' => (int) ($r['item_count'] ?? 0),
            ];
        }

        echo json_encode([
            'ok' => true,
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Sipariş listesi alınamadı'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'payload') {
    $idsRaw = trim((string) ($_GET['order_ids'] ?? $_GET['order_id'] ?? ''));
    if ($idsRaw === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'order_id gerekli'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ids = [];
    foreach (preg_split('/[\s,;]+/', $idsRaw) as $chunk) {
        $id = (int) $chunk;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if ($ids === []) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Geçersiz sipariş'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payloads = [];
    foreach ($ids as $oid) {
        $p = order_image_build_payload($pdo, $oid);
        if ($p !== null) {
            $payloads[] = $p;
        }
    }

    echo json_encode(['ok' => true, 'payloads' => $payloads], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw !== false ? $raw : '', true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'JSON gerekli'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orderId = max(0, (int) ($data['order_id'] ?? 0));
    $imageData = (string) ($data['image'] ?? '');
    if ($orderId <= 0 || $imageData === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'order_id ve image gerekli'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $imageData, $m)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Geçersiz görsel verisi'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fmt = strtolower($m[1]);
    if ($fmt === 'jpg') {
        $fmt = 'jpeg';
    }
    $ext = $fmt === 'jpeg' ? 'jpg' : $fmt;
    $bin = base64_decode(substr($imageData, strpos($imageData, ',') + 1), true);
    if ($bin === false || strlen($bin) < 32) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Görsel çözümlenemedi'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dir = dirname(__DIR__) . '/uploads/order_images';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Klasör oluşturulamadı'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filename = 'order_' . $orderId . '_' . date('Ymd_His') . '.' . $ext;
    $fullPath = $dir . '/' . $filename;
    if (file_put_contents($fullPath, $bin) === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Dosya yazılamadı'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $publicPath = 'uploads/order_images/' . $filename;
    $publicUrl = order_image_upload_url($pdo, $publicPath);

    echo json_encode([
        'ok' => true,
        'order_id' => $orderId,
        'path' => $publicPath,
        'url' => $publicUrl,
        'filename' => $filename,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'images') {
    $orderId = max(0, (int) ($_GET['order_id'] ?? 0));
    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'order_id gerekli'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $images = order_image_list_saved($pdo, $orderId);
    echo json_encode(['ok' => true, 'order_id' => $orderId, 'images' => $images], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Bilinmeyen action'], JSON_UNESCAPED_UNICODE);
