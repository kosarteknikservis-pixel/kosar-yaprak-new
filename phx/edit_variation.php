<?php
require '../db.php';
require 'auth.php';

$variation_id = isset($_GET['variation_id']) ? (int) $_GET['variation_id'] : 0;

if ($variation_id <= 0) {
    admin_abort_redirect('Varyasyon ID belirtilmedi.', 'variant_management.php');
}

$stmt = $pdo->prepare('SELECT * FROM product_variations WHERE variation_id = ?');
$stmt->execute([$variation_id]);
$variation = $stmt->fetch();

if (!$variation) {
    admin_abort_redirect('Varyasyon bulunamadı.', 'variant_management.php');
}

$stmt = $pdo->prepare('SELECT * FROM product_variants WHERE variation_id = ?');
$stmt->execute([$variation_id]);
$variants = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_name = $_POST['variant_name'];

    $stmt = $pdo->prepare("INSERT INTO product_variants (variation_id, variant_name) VALUES (?, ?)");
    $stmt->execute([$variation_id, $variant_name]);

    $_SESSION['message'] = "Varyant başarıyla eklendi!";
    header("Location: edit_variation.php?variation_id=$variation_id");
    exit();
}

$page_title = 'Varyant ekle — ' . htmlspecialchars($variation['variation_name']);
include 'admin_header.php';
?>

<div class="container-fluid px-0">
    <h1 class="h4 mb-3"><i class="fas fa-tags me-2 text-muted"></i>Varyant ekle — <?= htmlspecialchars($variation['variation_name']) ?></h1>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars($_SESSION['message']); ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="variant_name" class="form-label">Varyant adı</label>
            <input type="text" class="form-control" id="variant_name" name="variant_name" required>
        </div>
        <button type="submit" class="btn btn-primary">Varyantı ekle</button>
    </form>

    <h2 class="h6 text-uppercase text-muted mb-2">Mevcut varyantlar</h2>
    <?php if ($variants): ?>
        <ul class="list-group mb-4">
            <?php foreach ($variants as $variant): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <?= htmlspecialchars($variant['variant_name']) ?>
                    <span>
                        <a href="edit_variant.php?variant_id=<?= $variant['variant_id'] ?>" class="btn btn-outline-secondary btn-sm">Düzenle</a>
                        <a href="delete_variant.php?variant_id=<?= $variant['variant_id'] ?>&variation_id=<?= $variation_id ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu varyantı silmek istediğinize emin misiniz?');">Sil</a>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted mb-4">Henüz varyant eklenmedi.</p>
    <?php endif; ?>

    <a href="add_variation.php" class="btn btn-outline-secondary">Varyasyon listesine dön</a>
</div>

<?php include 'admin_footer_common.php'; ?>
