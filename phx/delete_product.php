<?php
require '../db.php';
require 'auth.php';

/** @param 'warning'|'danger' $tone */
function admin_delete_product_feedback(string $title, string $bodyHtml, string $tone): void {
    $page_title = $title;
    include __DIR__ . '/admin_header.php';
    $alertClass = $tone === 'danger' ? 'alert-danger' : 'alert-warning';
    ?>
    <div class="container py-5" style="max-width: 520px;">
        <div class="alert <?= $alertClass ?> text-center mb-0"><?= $bodyHtml ?></div>
        <div class="text-center mt-3">
            <a href="products.php" class="btn btn-outline-secondary">Ürün listesi</a>
        </div>
    </div>
    <?php
    include __DIR__ . '/admin_footer_common.php';
    exit;
}

$product_id = $_GET['product_id'] ?? null;

if (!$product_id || !is_numeric($product_id)) {
    ob_start();
    ?>
    <p class="mb-0">Geçersiz ürün ID.</p>
    <?php
    $body = ob_get_clean();
    admin_delete_product_feedback('Geçersiz istek', $body, 'danger');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
$stmt->execute([$product_id]);
$order_count = (int) $stmt->fetchColumn();

if ($order_count > 0) {
    $pid = (int) $product_id;
    ob_start();
    ?>
    <h4 class="alert-heading mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Ürün silinemez</h4>
    <p class="mb-0">Bu ürün <strong><?= (int) $order_count ?> adet siparişte</strong> kullanılıyor. Ürünü silmek yerine <strong>gizleyebilirsiniz</strong>.</p>
    <hr>
    <a href="edit_product.php?product_id=<?= $pid ?>" class="btn btn-warning mt-2 me-2">
        <i class="fas fa-edit"></i> Ürünü düzenle
    </a>
    <a href="products.php" class="btn btn-secondary mt-2">
        <i class="fas fa-arrow-left"></i> Geri dön
    </a>
    <?php
    $body = ob_get_clean();
    admin_delete_product_feedback('Ürün silinemez', $body, 'warning');
}

try {
    $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
    $stmt->execute([$product_id]);

    header('Location: products.php?deleted=1');
    exit();
} catch (PDOException $e) {
    ob_start();
    ?>
    <h4 class="alert-heading mb-3"><i class="fas fa-exclamation-circle me-2"></i>Hata</h4>
    <p class="mb-0"><?= htmlspecialchars($e->getMessage()) ?></p>
    <?php
    $body = ob_get_clean();
    admin_delete_product_feedback('Hata', $body, 'danger');
}
