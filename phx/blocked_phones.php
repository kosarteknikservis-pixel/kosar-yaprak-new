<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
    $lenFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
    $subFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    if ($lenFn($raw) < 10) {
        $_SESSION['message'] = 'En az 10 haneli telefon numarası girin.';
    } else {
        $last = $subFn($raw, -10);
        try {
            $pdo->prepare('INSERT INTO blocked_phones (phone_digits, reason) VALUES (?,?)')->execute([
                $last,
                substr(trim((string)($_POST['reason'] ?? '')), 0, 250),
            ]);
            $_SESSION['message'] = 'Telefon kaydedildi.';
        } catch (Throwable $e) {
            $_SESSION['message'] = 'Bu numara zaten kayıtlı veya kayıt yapılamadı.';
        }
    }
    header('Location: blocked_phones.php');
    exit;
}
if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $pdo->prepare('DELETE FROM blocked_phones WHERE id = ?')->execute([(int)$_GET['del']]);
    $_SESSION['message'] = 'Silindi.';
    header('Location: blocked_phones.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM blocked_phones ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Engellenen telefon';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <h1 class="h4 mb-3">Telefon engelleme</h1>
    <p class="text-muted small">Son 10 haneye göre eşleşir.</p>
    <form method="post" class="row g-2 mb-4 align-items-end">
        <div class="col-md-4"><input type="text" name="phone" class="form-control" placeholder="05xx veya 5xx" required></div>
        <div class="col-md-5"><input type="text" name="reason" class="form-control" placeholder="Sebep"></div>
        <div class="col-md-2"><button class="btn btn-danger">Ekle</button></div>
    </form>
    <table class="table table-sm card border-0 shadow-sm">
        <thead><tr><th>10 hane</th><th>Sebep</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$r['phone_digits']) ?></td>
                    <td><?= htmlspecialchars((string)($r['reason'] ?? '')) ?></td>
                    <td><a href="blocked_phones.php?del=<?= (int)$r['id'] ?>">Sil</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'admin_footer_common.php'; ?>
