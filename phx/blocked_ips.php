<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = trim((string)($_POST['ip'] ?? ''));
    if ($ip === '') {
        $_SESSION['message'] = 'IP adresi girin.';
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $_SESSION['message'] = 'Geçerli bir IPv4 veya IPv6 adresi girin.';
    } else {
        try {
            $pdo->prepare('INSERT INTO blocked_ips (ip, reason) VALUES (?,?)')->execute([
                $ip,
                substr(trim((string)($_POST['reason'] ?? '')), 0, 250),
            ]);
            $_SESSION['message'] = 'IP kaydedildi.';
        } catch (Throwable $e) {
            $_SESSION['message'] = 'Bu IP zaten kayıtlı veya kayıt yapılamadı.';
        }
    }
    header('Location: blocked_ips.php');
    exit;
}
if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $pdo->prepare('DELETE FROM blocked_ips WHERE id = ?')->execute([(int)$_GET['del']]);
    $_SESSION['message'] = 'Silindi.';
    header('Location: blocked_ips.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM blocked_ips ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Engellenen IP';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <h1 class="h4 mb-3">IP engelleme</h1>
    <form method="post" class="row g-2 mb-4 align-items-end">
        <div class="col-md-4"><input type="text" name="ip" class="form-control" placeholder="IPv4/IPv6" required></div>
        <div class="col-md-5"><input type="text" name="reason" class="form-control" placeholder="Sebep"></div>
        <div class="col-md-2"><button class="btn btn-danger">Ekle</button></div>
    </form>
    <table class="table table-sm card border-0 shadow-sm">
        <thead><tr><th>IP</th><th>Sebep</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$r['ip']) ?></td>
                    <td><?= htmlspecialchars((string)($r['reason'] ?? '')) ?></td>
                    <td><a href="blocked_ips.php?del=<?= (int)$r['id'] ?>">Sil</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'admin_footer_common.php'; ?>
