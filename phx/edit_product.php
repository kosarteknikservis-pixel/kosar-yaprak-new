<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/media_guard.php';

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    header('Location: products.php?error=missing_id');
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php?error=notfound');
    exit;
}

$imageStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ?');
$imageStmt->execute([$product_id]);
$images = $imageStmt->fetchAll();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'];
    $product_description = $_POST['product_description'];
    $product_price = $_POST['product_price'];
    $original_price = $_POST['original_price'];
    $status = $_POST['status'];
    $sku = !empty($_POST['sku']) ? trim($_POST['sku']) : null;
    $display_order = max(0, (int) ($_POST['display_order'] ?? 0));
    $show_description = isset($_POST['show_description']) ? 1 : 0;
    $show_price = isset($_POST['show_price']) ? 1 : 0;
    $show_name_heading = isset($_POST['show_name_heading']) ? 1 : 0;

    if ($product_price > $original_price) {
        $message = '❌ Hata: Satış fiyatı normal fiyattan yüksek olamaz!';
    } else {
        $stmt = $pdo->prepare('UPDATE products SET product_name = ?, product_description = ?, product_price = ?, original_price = ?, show_description = ?, show_price = ?, show_name_heading = ?, status = ?, display_order = ?, sku = ? WHERE product_id = ?');
        $stmt->execute([$product_name, $product_description, $product_price, $original_price, $show_description, $show_price, $show_name_heading, $status, $display_order, $sku, $product_id]);

        $uploaded_count = 0;
        $error_messages = [];

        if (isset($_POST['delete_existing_images'])) {
            $stmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
            $stmt->execute([$product_id]);
            $existing_images = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($existing_images as $image_path) {
                if ($image_path) {
                    media_guard_safe_unlink((string) $image_path, 'edit_product_replace');
                }
            }

            $deleteStmt = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
            $deleteStmt->execute([$product_id]);

            $stmt = $pdo->prepare('UPDATE products SET product_image = NULL WHERE product_id = ?');
            $stmt->execute([$product_id]);

            $message .= ' Görseller kaldırıldı.';
        }

        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $files = $_FILES['product_images'];

            $upload_dir = "../uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($files['name'] as $key => $filename) {
                if ($files['error'][$key] === UPLOAD_ERR_OK) {
                    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');

                    if (in_array($file_extension, $allowed_types)) {
                        $new_filename = $product_id . '_' . time() . '_' . $key . '.' . $file_extension;
                        $target_file = $upload_dir . $new_filename;

                        if (move_uploaded_file($files["tmp_name"][$key], $target_file)) {
                            media_guard_archive_file($target_file);
                            $stmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)');
                            $stmt->execute([$product_id, $target_file]);
                            $uploaded_count++;

                            if ($key === 0) {
                                $stmt = $pdo->prepare('UPDATE products SET product_image = ? WHERE product_id = ?');
                                $stmt->execute([$target_file, $product_id]);
                            }
                        } else {
                            $error_messages[] = 'Dosya taşınamadı: ' . $filename;
                        }
                    } else {
                        $error_messages[] = 'Desteklenmeyen format: ' . $filename;
                    }
                } elseif ($filename !== '') {
                    $error_messages[] = 'Yükleme hatası: ' . $filename;
                }
            }

            if ($uploaded_count > 0) {
                $message .= ' ✅ ' . $uploaded_count . ' görsel eklendi.';
            }
            if (!empty($error_messages)) {
                $message .= ' ❌ ' . implode(', ', $error_messages);
            }
        }

        if ($message === '' || strpos($message, '❌') === false && strpos($message, 'yüksek olamaz') === false) {
            $message = '✅ Ürün güncellendi.' . $message;
        }

        $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        $imageStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ?');
        $imageStmt->execute([$product_id]);
        $images = $imageStmt->fetchAll();
    }
}

$page_title = 'Ürün Düzenle';
include 'admin_header.php';
?>

<style>
.pe-wrap { max-width: 980px; margin: 0 auto 32px; padding: 0 8px; }
.pe-head { margin-bottom: 20px; }
.pe-head h1 { font-size: 1.35rem; font-weight: 600; color: #111827; margin: 0; display: flex; align-items: center; gap: 10px; }
.pe-head h1 i { color: #3b82f6; }
.pe-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); overflow: hidden; }
.pe-card-h { padding: 12px 16px; font-weight: 600; font-size: 14px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; color: #374151; }
.pe-card-b { padding: 18px; }
.pe-label { display: block; font-size: 12px; font-weight: 500; color: #6b7280; margin-bottom: 6px; }
.pe-inp { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 14px; }
.pe-inp:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
.pe-check { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; display: flex; align-items: center; gap: 10px; height: 100%; }
.pe-imgs { display: flex; flex-wrap: wrap; gap: 12px; }
.pe-thumb-wrap { position: relative; }
.pe-thumb { width: 88px; height: 88px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
.pe-drop { border: 2px dashed #d1d5db; border-radius: 8px; padding: 24px; text-align: center; background: #fafafa; }
.pe-drop:hover { border-color: #3b82f6; background: #eff6ff; }
.pe-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }
.btn-pe-primary { background: #3b82f6; color: #fff; border: none; padding: 10px 22px; border-radius: 6px; font-weight: 500; }
.btn-pe-primary:hover { background: #2563eb; color: #fff; }
.btn-pe-sec { background: #fff; border: 1px solid #d1d5db; color: #374151; padding: 10px 22px; border-radius: 6px; font-weight: 500; }
.btn-pe-sec:hover { background: #f9fafb; color: #111; }
.danger-zone { background: #fff5f5; border: 1px solid #fecaca; border-radius: 6px; padding: 12px; }
</style>

<div class="pe-wrap">
    <div class="pe-head d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h1><i class="fas fa-edit"></i>Ürün düzenle</h1>
        <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Ürün listesi</a>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= strpos($message, '❌') !== false ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show border-0 shadow-sm">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="pe-card">
            <div class="pe-card-h">Temel bilgiler</div>
            <div class="pe-card-b">
                <div class="row g-3">
                    <div class="col-md-3">
                        <span class="pe-label">Ürün adı *</span>
                        <input type="text" name="product_name" class="pe-inp" required value="<?= htmlspecialchars((string) $product['product_name']) ?>">
                    </div>
                    <div class="col-md-3">
                        <span class="pe-label">SKU</span>
                        <input type="text" name="sku" class="pe-inp" value="<?= htmlspecialchars((string) ($product['sku'] ?? '')) ?>" placeholder="Benzersiz kod">
                    </div>
                    <div class="col-md-3">
                        <span class="pe-label">Durum</span>
                        <select name="status" class="pe-inp">
                            <option value="visible" <?= $product['status'] == 'visible' ? 'selected' : '' ?>>Görünür</option>
                            <option value="hidden" <?= $product['status'] == 'hidden' ? 'selected' : '' ?>>Gizli</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <span class="pe-label">Vitrin sırası</span>
                        <input type="number" name="display_order" class="pe-inp" min="0" step="1" value="<?= (int) ($product['display_order'] ?? 0) ?>" title="Küçük sayı vitrinde üstte">
                    </div>
                    <div class="col-12">
                        <span class="pe-label">Açıklama *</span>
                        <textarea name="product_description" class="pe-inp" rows="4" required><?= htmlspecialchars((string) $product['product_description']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <span class="pe-label">Liste fiyatı (TL) *</span>
                        <input type="number" name="original_price" class="pe-inp" step="0.01" required value="<?= htmlspecialchars((string) $product['original_price']) ?>">
                    </div>
                    <div class="col-md-6">
                        <span class="pe-label">Satış fiyatı (TL) *</span>
                        <input type="number" name="product_price" class="pe-inp" step="0.01" required value="<?= htmlspecialchars((string) $product['product_price']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="pe-card">
            <div class="pe-card-h">Görünürlük</div>
            <div class="pe-card-b">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="pe-check">
                            <input type="checkbox" name="show_description" id="sd" class="form-check-input" <?= $product['show_description'] ? 'checked' : '' ?>>
                            <label class="mb-0" for="sd">Açıklama</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pe-check">
                            <input type="checkbox" name="show_price" id="sp" class="form-check-input" <?= $product['show_price'] ? 'checked' : '' ?>>
                            <label class="mb-0" for="sp">Fiyat</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pe-check">
                            <input type="checkbox" name="show_name_heading" id="sn" class="form-check-input" <?= $product['show_name_heading'] ? 'checked' : '' ?>>
                            <label class="mb-0" for="sn">Ürün adı başlık</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pe-card">
            <div class="pe-card-h">Görseller</div>
            <div class="pe-card-b">
                <?php if (!empty($images)): ?>
                    <div class="pe-imgs mb-3">
                        <?php foreach ($images as $image): ?>
                            <div class="pe-thumb-wrap">
                                <img src="<?= htmlspecialchars((string) $image['image_path']) ?>" class="pe-thumb" alt="">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="danger-zone mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="delete_existing_images" id="delimg">
                            <label class="form-check-label text-danger fw-medium" for="delimg">Tüm mevcut görselleri sil</label>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">Henüz görsel yok.</p>
                <?php endif; ?>

                <span class="pe-label">Yeni görseller ekle</span>
                <div class="pe-drop mb-2" onclick="document.getElementById('pe-files').click()">
                    <i class="fas fa-cloud-upload-alt text-secondary fa-lg mb-1"></i>
                    <p class="small mb-0">Tıklayın veya aşağıdan seçin · JPG PNG GIF WebP</p>
                </div>
                <input type="file" id="pe-files" class="form-control form-control-sm" name="product_images[]" multiple accept="image/*">
            </div>
        </div>

        <div class="pe-actions">
            <button type="submit" class="btn-pe-primary"><i class="fas fa-save me-1"></i>Kaydet</button>
            <a href="products.php" class="btn-pe-sec text-decoration-none">İptal</a>
        </div>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
