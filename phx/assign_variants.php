<?php
require '../db.php';
require 'auth.php';

$product_id = (int) ($_GET['product_id'] ?? 0);
$message = '';
$message_type = '';

if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

// Ürün bilgilerini çek
$stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

// Varyant atama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_variants'])) {
    try {
        // Mevcut atamaları sil
        $stmt = $pdo->prepare('DELETE FROM product_variation_assignments WHERE product_id = ?');
        $stmt->execute([$product_id]);

        // Yeni atamaları ekle
        if (isset($_POST['variant_types'])) {
            foreach ($_POST['variant_types'] as $type_id => $data) {
                // Sadece enabled olanları kaydet
                if (isset($data['enabled'])) {
                    $is_required = isset($data['required']) ? 1 : 0;
                    $display_order = (int)$data['order'];

                    $stmt = $pdo->prepare('INSERT INTO product_variation_assignments (product_id, type_id, is_required, display_order) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$product_id, $type_id, $is_required, $display_order]);
                }
            }
        }

        $message = 'Varyantlar başarıyla atandı!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Mevcut varyant türlerini çek
$stmt = $pdo->query('SELECT * FROM product_variation_types WHERE is_active = 1 ORDER BY display_order, type_name');
$variation_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bu ürüne atanmış varyantları çek
$stmt = $pdo->prepare('SELECT * FROM product_variation_assignments WHERE product_id = ?');
$stmt->execute([$product_id]);
$assigned_variants = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
    $assigned_variants[$assignment['type_id']] = $assignment;
}
$page_title = 'Varyant Ata — ' . htmlspecialchars($product['product_name']);
include 'admin_header.php';
?>

<div class="container-fluid px-0">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-tags"></i> Ürüne Varyant Ata</h1>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Ürün: <?= htmlspecialchars($product['product_name']) ?></h5>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-layer-group"></i> Varyant Türlerini Seçin</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($variation_types)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Henüz varyant türü eklenmemiş.
                                    <a href="variant_management.php" class="alert-link">Varyant Yönetimi</a> sayfasından varyant türleri ekleyin.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($variation_types as $type): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input type="checkbox"
                                                               class="form-check-input variant-checkbox"
                                                               id="type_<?= $type['type_id'] ?>"
                                                               name="variant_types[<?= $type['type_id'] ?>][enabled]"
                                                               <?= isset($assigned_variants[$type['type_id']]) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="type_<?= $type['type_id'] ?>">
                                                            <strong><?= htmlspecialchars($type['type_name']) ?></strong>
                                                            <?php if ($type['type_description']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($type['type_description']) ?></small>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>

                                                    <div class="variant-options mt-2" style="display: <?= isset($assigned_variants[$type['type_id']]) ? 'block' : 'none' ?>;">
                                                        <div class="form-check">
                                                            <input type="checkbox"
                                                                   class="form-check-input"
                                                                   id="required_<?= $type['type_id'] ?>"
                                                                   name="variant_types[<?= $type['type_id'] ?>][required]"
                                                                   <?= isset($assigned_variants[$type['type_id']]) && $assigned_variants[$type['type_id']]['is_required'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="required_<?= $type['type_id'] ?>">
                                                                Zorunlu varyant
                                                            </label>
                                                        </div>

                                                        <div class="form-group mt-2">
                                                            <label for="order_<?= $type['type_id'] ?>">Görüntüleme Sırası</label>
                                                            <input type="number"
                                                                   class="form-control form-control-sm"
                                                                   id="order_<?= $type['type_id'] ?>"
                                                                   name="variant_types[<?= $type['type_id'] ?>][order]"
                                                                   value="<?= isset($assigned_variants[$type['type_id']]) ? $assigned_variants[$type['type_id']]['display_order'] : $type['display_order'] ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="assign_variants" class="btn btn-primary">
                                <i class="fas fa-save"></i> Varyantları Ata
                            </button>
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Geri Dön
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.variant-checkbox').change(function() {
                var typeId = $(this).attr('id').replace('type_', '');
                var optionsDiv = $(this).closest('.card-body').find('.variant-options');

                if ($(this).is(':checked')) {
                    optionsDiv.show();
                } else {
                    optionsDiv.hide();
                    // Seçili değilse form verilerini temizle
                    optionsDiv.find('input[type="checkbox"]').prop('checked', false);
                }
            });
        });
    </script>
<?php include 'admin_footer_common.php'; ?>
