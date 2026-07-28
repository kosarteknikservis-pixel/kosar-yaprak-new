<?php
require '../db.php';
require 'auth.php';

$variant_id = isset($_GET['variant_id']) ? (int) $_GET['variant_id'] : 0;

if ($variant_id <= 0) {
    admin_abort_redirect('Varyant ID belirtilmedi.', 'variant_management.php');
}

$stmt = $pdo->prepare('SELECT * FROM product_variants_2 WHERE variant_id = ?');
$stmt->execute([$variant_id]);
$variant = $stmt->fetch();

if (!$variant) {
    admin_abort_redirect('Varyant bulunamadı.', 'variant_management.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_name = $_POST['variant_name'];

    $stmt = $pdo->prepare('UPDATE product_variants_2 SET variant_name = ? WHERE variant_id = ?');
    $stmt->execute([$variant_name, $variant_id]);

    $_SESSION['success_message'] = 'Varyant başarıyla güncellendi!';
    header('Location: edit_variant_2.php?variant_id=' . rawurlencode((string)$variant_id));
    exit();
}

$page_title = 'Varyant düzenle (2) — ' . htmlspecialchars($variant['variant_name']);
include 'admin_header.php';
?>

<div class="container-fluid px-0">
    <h1 class="h4 mb-3"><i class="fas fa-edit me-2 text-muted"></i>Varyant düzenle — <?= htmlspecialchars($variant['variant_name']) ?></h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" id="success-alert" role="alert"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="variant_name" class="form-label">Varyant adı</label>
            <input type="text" class="form-control" id="variant_name" name="variant_name" value="<?= htmlspecialchars($variant['variant_name']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Güncelle</button>
    </form>

    <a href="edit_variation_2.php?variation_id=<?= (int)$variant['variation_id'] ?>" class="btn btn-outline-secondary">Geri dön</a>
</div>

<script>
(function () {
    var el = document.getElementById('success-alert');
    if (!el) return;
    setTimeout(function () {
        el.classList.add('fade');
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 400);
    }, 2000);
})();
</script>

<?php include 'admin_footer_common.php'; ?>
