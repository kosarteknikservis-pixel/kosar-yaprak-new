<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nm = trim((string)($_POST['status_name'] ?? ''));
    if ($nm !== '') {
        try {
            $pdo->prepare('INSERT INTO order_status (status_name) VALUES (?)')->execute([$nm]);
            $_SESSION['message'] = 'Durum eklendi.';
            header('Location: manage_order_status.php');
            exit;
        } catch (Throwable $e) {
            $msg = 'Eklenemedi: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $id = (int)$_GET['del'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_status_id = ?');
    $c->execute([$id]);
    if ((int)$c->fetchColumn() > 0) {
        $_SESSION['message'] = 'Bu durum siparişlerde kullanılıyor; silinemedi.';
    } else {
        $pdo->prepare('DELETE FROM order_status WHERE order_status_id = ?')->execute([$id]);
        $_SESSION['message'] = 'Durum silindi.';
    }
    header('Location: manage_order_status.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM order_status ORDER BY order_status_id')->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Sipariş durumları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <h1 class="h4 mb-3">Sipariş durumları</h1>

    <form method="post" class="row g-2 mb-4 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Yeni durum adı</label>
            <input type="text" name="status_name" class="form-control" required maxlength="128">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" type="submit">Ekle</button>
        </div>
    </form>

    <div class="table-responsive card border-0 shadow-sm">
        <table class="table mb-0">
            <thead><tr><th>ID</th><th>Ad</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['order_status_id'] ?></td>
                        <td><?= htmlspecialchars((string)$r['status_name']) ?></td>
                        <td><a href="manage_order_status.php?del=<?= (int)$r['order_status_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silinsin mi?')">Sil</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
