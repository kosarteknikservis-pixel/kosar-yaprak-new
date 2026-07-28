<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 2) . '/includes/admin_rbac.php';

use PHPMailer\PHPMailer\PHPMailer;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (! admin_user_can('menu_destek')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST gerekli'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = (string) ($_POST['type'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Geçersiz kayıt'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($type === 'support' && $action === 'respond') {
        $response = trim((string) ($_POST['response'] ?? ''));
        if ($response === '') {
            throw new RuntimeException('Cevap metni zorunlu');
        }

        $stmt = $pdo->prepare('SELECT id, name, email, status FROM support_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            throw new RuntimeException('Talep bulunamadı');
        }

        $smtp = $pdo->query('SELECT * FROM smtp_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $emailSent = false;
        $emailError = '';

        if (! empty($smtp['is_enabled']) && ! empty($smtp['host']) && ! empty($smtp['username'])) {
            $mail = null;
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['username'];
                $mail->Password = $smtp['password'];
                $mail->SMTPSecure = $smtp['encryption'];
                $mail->Port = (int) $smtp['port'];
                $mail->CharSet = $smtp['charset'] ?? 'UTF-8';
                $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
                $mail->addAddress((string) $row['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Destek Talebiniz Cevaplandı';
                $name = htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8');
                $body = htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
                $mail->Body = 'Merhaba ' . $name . ',<br><br>' . nl2br($body) . '<br><br>Sevgiler..';
                $mail->AltBody = 'Merhaba ' . (string) $row['name'] . "\n\n" . $response . "\n\nSevgiler..";
                $mail->send();
                $emailSent = true;
            } catch (Throwable $e) {
                $emailError = $mail instanceof PHPMailer && $mail->ErrorInfo ? $mail->ErrorInfo : $e->getMessage();
            }
        }

        $pdo->prepare('UPDATE support_requests SET response = ?, status = ? WHERE id = ?')
            ->execute([$response, 'Cevaplandı', $id]);

        $msg = $emailSent
            ? 'Cevap kaydedildi ve e-posta gönderildi.'
            : ($emailError !== ''
                ? 'Cevap kaydedildi; e-posta gönderilemedi: ' . $emailError
                : 'Cevap kaydedildi (SMTP kapalı — yalnızca panel).');

        echo json_encode(['ok' => true, 'message' => $msg, 'status' => 'Cevaplandı'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'support' && $action === 'close') {
        $pdo->prepare('UPDATE support_requests SET status = ? WHERE id = ?')->execute(['Kapandı', $id]);
        echo json_encode(['ok' => true, 'message' => 'Talep kapatıldı.', 'status' => 'Kapandı'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'dealer' && $action === 'approve') {
        $pdo->prepare("UPDATE dealer_requests SET status = 'approved' WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'message' => 'Arandı olarak işaretlendi.', 'status' => 'approved'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'dealer' && $action === 'reopen') {
        $pdo->prepare("UPDATE dealer_requests SET status = 'pending' WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'message' => 'Beklemede olarak işaretlendi.', 'status' => 'pending'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new RuntimeException('Geçersiz işlem');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
