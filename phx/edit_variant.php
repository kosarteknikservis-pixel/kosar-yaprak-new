<?php
require '../db.php';
require 'auth.php';

$variant_id = isset($_GET['variant_id']) ? (int) $_GET['variant_id'] : 0;

if ($variant_id <= 0) {
    admin_abort_redirect('Varyant ID belirtilmedi.', 'variant_management.php');
}

$stmt = $pdo->prepare('SELECT * FROM product_variants WHERE variant_id = ?');
$stmt->execute([$variant_id]);
$variant = $stmt->fetch();

if (!$variant) {
    admin_abort_redirect('Varyant bulunamadı.', 'variant_management.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_name = $_POST['variant_name'];

    $stmt = $pdo->prepare('UPDATE product_variants SET variant_name = ? WHERE variant_id = ?');
    $stmt->execute([$variant_name, $variant_id]);

    $message = 'Varyant başarıyla güncellendi!';
    $_SESSION['success_message'] = $message;
    header("Location: edit_variant.php?variant_id=$variant_id");
    exit();
}

$page_title = 'Varyant düzenle';
include 'admin_header.php';
?>

<div class="container-fluid px-0" style="max-width: 640px;">
    <h1 class="h4 mb-3">Varyant düzenle — <?= htmlspecialchars($variant['variant_name']) ?></h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" id="success-alert" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="variant_name" class="form-label">Varyant adı</label>
            <input type="text" class="form-control" id="variant_name" name="variant_name" value="<?= htmlspecialchars($variant['variant_name']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-2">Güncelle</button>
    </form>
    <a href="edit_variation.php?variation_id=<?= (int)$variant['variation_id'] ?>" class="btn btn-outline-secondary w-100">Geri dön</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('success-alert');
    if (el) setTimeout(function() { el.style.display = 'none'; }, 3500);
});
</script>

<?php include 'admin_footer_common.php'; ?>
