<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 2) . '/includes/admin_cc_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/admin_rbac.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (! admin_user_can('menu_destek')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Geçersiz kayıt'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($type === 'support') {
        $stmt = $pdo->prepare('SELECT * FROM support_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            throw new RuntimeException('Talep bulunamadı');
        }
        $open = in_array((string) ($row['status'] ?? ''), ['', 'Beklemede', 'Yeni'], true)
            || ($row['status'] ?? null) === null;
        echo json_encode([
            'ok' => true,
            'type' => 'support',
            'record' => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'phone' => (string) $row['phone'],
                'subject' => (string) $row['subject'],
                'note' => (string) $row['note'],
                'response' => (string) ($row['response'] ?? ''),
                'status' => (string) ($row['status'] ?? 'Beklemede'),
                'created_at' => (string) $row['created_at'],
                'is_open' => $open,
                'phone_html' => cc_phone_actions_html((string) $row['phone']),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'dealer') {
        $stmt = $pdo->prepare('SELECT * FROM dealer_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            throw new RuntimeException('Başvuru bulunamadı');
        }
        $approved = (string) ($row['status'] ?? '') === 'approved';
        echo json_encode([
            'ok' => true,
            'type' => 'dealer',
            'record' => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'phone' => (string) $row['phone'],
                'company_name' => (string) $row['company_name'],
                'address' => (string) $row['address'],
                'note' => (string) ($row['note'] ?? ''),
                'role' => (string) $row['role'],
                'status' => (string) ($row['status'] ?? 'pending'),
                'created_at' => (string) $row['created_at'],
                'is_approved' => $approved,
                'phone_html' => cc_phone_actions_html((string) $row['phone']),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new RuntimeException('Geçersiz tip');
} catch (Throwable $e) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
