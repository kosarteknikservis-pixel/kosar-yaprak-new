<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/catalog_reset.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && catalog_reset_request_valid()) {
    try {
        $result = catalog_reset_all($pdo);
        $_SESSION['message'] = 'Katalog sıfırlandı: ' . count($result['tables']) . ' tablo temizlendi, '
            . (int) $result['files_deleted'] . ' görsel dosyası silindi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Sıfırlama hatası: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header('Location: products.php');
    exit;
}

if (!empty($_SESSION['message']) && is_string($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $mt = $_SESSION['message_type'] ?? 'success';
    if ($mt === 'danger') {
        $message = '❌ ' . $message;
    }
    unset($_SESSION['message'], $_SESSION['message_type']);
}

if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = '✅ Ürün başarıyla silindi!';
}

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
    try {
        $stmt = $pdo->prepare('INSERT INTO products (product_name, product_description, product_price, original_price, show_description, show_price, show_name_heading, status, display_order, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$product_name, $product_description, $product_price, $original_price, $show_description, $show_price, $show_name_heading, $status, $display_order, $sku]);
        $product_id = $pdo->lastInsertId();

        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $files = $_FILES['product_images'];
            $uploaded_count = 0;
            $error_messages = [];

            $upload_dir = "../uploads/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error_messages[] = "Uploads klasörü oluşturulamadı";
                }
            }

            if (!is_writable($upload_dir)) {
                $error_messages[] = "Uploads klasörü yazılamıyor";
            }

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = $files['name'][$i];
                    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');

                    if (in_array($file_extension, $allowed_types)) {
                        $new_filename = 'product_' . $product_id . '_' . time() . '_' . $i . '.' . $file_extension;
                        $target_file = $upload_dir . $new_filename;

                    if (move_uploaded_file($files["tmp_name"][$i], $target_file)) {
                        $stmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)');
                        $stmt->execute([$product_id, $target_file]);
                            $uploaded_count++;

                            if ($i === 0) {
                                $stmt = $pdo->prepare('UPDATE products SET product_image = ? WHERE product_id = ?');
                                $stmt->execute([$target_file, $product_id]);
                            }
                        } else {
                            $error_messages[] = "Dosya taşınamadı: " . $filename;
                        }
                    } else {
                        $error_messages[] = "Desteklenmeyen format: " . $filename;
                    }
                } else {
                    $error_messages[] = "Upload hatası: " . $files['name'][$i] . " (Kod: " . $files['error'][$i] . ")";
                }
            }

            if ($uploaded_count > 0) {
                $message .= " ✅ $uploaded_count görsel başarıyla yüklendi.";
            }
            if (!empty($error_messages)) {
                $message .= " ❌ Hatalar: " . implode(", ", $error_messages);
            }
        }
        
        if (isset($_POST['variants']) && is_array($_POST['variants'])) {
            foreach ($_POST['variants'] as $type_id) {
                $is_required = isset($_POST['required_' . $type_id]) ? 1 : 0;
                $stmt = $pdo->prepare('INSERT INTO product_variation_assignments (product_id, type_id, is_required) VALUES (?, ?, ?)');
                $stmt->execute([$product_id, $type_id, $is_required]);
            }
        }

        $message .= ' ✅ Ürün başarıyla eklendi!';
    } catch (Exception $e) {
        $message = '❌ Ürün ekleme hatası: ' . $e->getMessage();
    }
    }
}

$stmt = $pdo->query('SELECT * FROM products ORDER BY display_order ASC, product_id ASC');
$products = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM product_variation_types WHERE is_active = 1 ORDER BY display_order, type_name');
$variation_types = $stmt->fetchAll();

$page_title = 'Ürün Yönetimi';
include 'admin_header.php';
?>

<style>
    .products-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
    }
    
    .page-header-products {
        background: white;
        border-radius: 8px;
        padding: 20px 24px;
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .page-header-products h1 {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .page-header-products h1 i {
        color: #3b82f6;
        font-size: 24px;
    }
    
    .add-product-section {
        background: white;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .form-group-modern {
        margin-bottom: 16px;
    }
    
    .form-label-modern {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
    }
    
    .form-control-modern {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.15s;
        background: white;
    }
    
    .form-control-modern:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    textarea.form-control-modern {
        resize: vertical;
        min-height: 80px;
    }
    
    .form-check-modern {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-modern {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary-modern {
        background: #3b82f6;
        color: white;
    }
    
    .btn-primary-modern:hover {
        background: #2563eb;
    }
    
    .products-table-section {
        background: white;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    .table-header-section {
        padding: 16px 20px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }
    
    .table-header-section h2 {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }
    
    .products-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .products-table thead {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .products-table th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .products-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        color: #1f2937;
    }
    
    .products-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .product-img-thumb {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }
    
    .product-name-cell {
        font-weight: 500;
        color: #111827;
    }
    
    .sku-badge {
        background: #f3f4f6;
        color: #6b7280;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-family: 'Courier New', monospace;
        display: inline-block;
        margin-top: 3px;
    }
    
    .price-display {
        font-weight: 600;
        color: #059669;
        font-size: 14px;
    }
    
    .price-old {
        text-decoration: line-through;
        color: #9ca3af;
        font-size: 12px;
        margin-left: 6px;
    }
    
    .status-badge-visible {
        background: #d1fae5;
        color: #065f46;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-badge-hidden {
        background: #fee2e2;
        color: #991b1b;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .action-btn {
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 12px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        cursor: pointer;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        min-height: 34px;
        text-decoration: none;
        flex: 0 0 auto;
    }
    
    .product-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        max-width: 100%;
    }
    
    .products-table td.product-actions-cell {
        white-space: normal;
        vertical-align: middle;
    }
    
    .action-btn:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }
    
    .action-btn-edit { color: #f59e0b; border-color: #fbbf24; }
    .action-btn-edit:hover { background: #fef3c7; }
    
    .action-btn-delete { color: #ef4444; border-color: #fca5a5; }
    .action-btn-delete:hover { background: #fee2e2; }
    
    .file-upload-zone {
        border: 2px dashed #d1d5db;
        border-radius: 6px;
        padding: 30px;
        text-align: center;
        background: #f9fafb;
        cursor: pointer;
        transition: all 0.15s;
    }
    
    .file-upload-zone:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .variant-checkbox-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    
    .variant-checkbox-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="products-container">
    <?php if ($message): ?>
        <div class="alert <?= strpos($message, '❌') !== false ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="page-header-products d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="mb-0"><i class="fas fa-box"></i>Ürün Yönetimi</h1>
        <form method="post" id="catalogResetFormProducts" class="m-0">
            <input type="hidden" name="catalog_reset_all" value="1">
            <input type="hidden" name="catalog_reset_confirm" id="catalogResetConfirmProducts" value="">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmCatalogReset('catalogResetFormProducts','catalogResetConfirmProducts')">
                <i class="fas fa-trash-alt me-1"></i> Sıfırla
            </button>
        </form>
    </div>

    <div class="add-product-section">
        <div class="section-title">Yeni Ürün Ekle</div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group-modern">
                        <label class="form-label-modern">Ürün Adı *</label>
                        <input type="text" name="product_name" class="form-control-modern" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group-modern">
                        <label class="form-label-modern">SKU Kodu</label>
                        <input type="text" name="sku" class="form-control-modern" placeholder="Boş bırakırsanız otomatik oluşturulur">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group-modern">
                        <label class="form-label-modern">Durum</label>
                        <select name="status" class="form-control-modern">
                            <option value="visible">Görünür</option>
                            <option value="hidden">Gizli</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group-modern">
                        <label class="form-label-modern">Vitrin sırası</label>
                        <input type="number" name="display_order" class="form-control-modern" value="0" min="0" step="1" title="Küçük sayı vitrinde üstte görünür (10, 20, 30…)">
                    </div>
                </div>
            </div>

            <div class="form-group-modern">
                <label class="form-label-modern">Açıklama *</label>
                <textarea name="product_description" class="form-control-modern" required></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group-modern">
                        <label class="form-label-modern">Normal Fiyat (TL) *</label>
                        <input type="number" name="original_price" class="form-control-modern" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group-modern">
                        <label class="form-label-modern">İndirimli Fiyat (TL) *</label>
                        <input type="number" name="product_price" class="form-control-modern" step="0.01" required>
                    </div>
                </div>
            </div>

            <div class="form-group-modern">
                <label class="form-label-modern">Ürün Görselleri</label>
                <div class="file-upload-zone" onclick="document.getElementById('file-input').click()">
                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                    <p class="mb-0">Görselleri buraya sürükleyin veya tıklayarak seçin</p>
                    <small class="text-muted">JPG, PNG, GIF formatları</small>
                </div>
                <input type="file" id="file-input" name="product_images[]" multiple accept="image/*" style="display:none;">
            </div>

            <div class="row" style="margin-bottom: 16px;">
                <div class="col-md-4">
                    <div class="form-check-modern">
                        <input type="checkbox" name="show_description" id="show_desc">
                        <label for="show_desc">Açıklamayı göster</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check-modern">
                        <input type="checkbox" name="show_price" id="show_price">
                        <label for="show_price">Fiyatı göster</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check-modern">
                        <input type="checkbox" name="show_name_heading" id="show_name">
                        <label for="show_name">Ürün adını göster</label>
                    </div>
                </div>
            </div>

            <?php if (!empty($variation_types)): ?>
            <div class="form-group-modern">
                <label class="form-label-modern">Varyant Türleri</label>
                <div class="variant-checkbox-list">
                    <?php foreach ($variation_types as $type): ?>
                        <div class="variant-checkbox-item">
                            <input type="checkbox" name="variants[]" value="<?= (int)$type['type_id'] ?>" id="var_<?= (int)$type['type_id'] ?>">
                            <label for="var_<?= (int)$type['type_id'] ?>" class="flex-grow-1 mb-0"><?= htmlspecialchars((string)$type['type_name']) ?></label>
                            <label class="d-inline-flex align-items-center gap-1 small text-muted mb-0 ms-2" for="req_<?= (int)$type['type_id'] ?>">
                                <input type="checkbox" name="required_<?= (int)$type['type_id'] ?>" id="req_<?= (int)$type['type_id'] ?>" value="1">
                                Zorunlu
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" class="btn-modern btn-primary-modern">
                    <i class="fas fa-plus"></i>Ürün Ekle
                </button>
            </div>
        </form>
    </div>

    <div class="products-table-section" id="products-list">
        <div class="table-header-section">
            <h2>Ürün Listesi (<?= count($products) ?>)</h2>
        </div>
        
        <div class="table-responsive">
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Sıra</th>
                        <th style="width: 70px;">Görsel</th>
                        <th>Ürün Adı / SKU</th>
                        <th style="width: 120px;">Fiyat</th>
                        <th style="width: 100px;">Durum</th>
                        <th style="min-width: 200px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? LIMIT 1');
                        $stmt->execute([$product['product_id']]);
                        $image = $stmt->fetch();
                        $thumbSrc = '../uploads/txrik.gif';
                        if ($image && !empty($image['image_path'])) {
                            $ip = str_replace('\\', '/', (string) $image['image_path']);
                            if (str_starts_with($ip, '../')) {
                                $thumbSrc = $ip;
                            } elseif (str_starts_with($ip, 'uploads/')) {
                                $thumbSrc = '../' . $ip;
                            } else {
                                $thumbSrc = '../uploads/' . ltrim(basename($ip), '/');
                            }
                        }
                        ?>
                        <tr>
                            <td><strong><?= (int) ($product['display_order'] ?? 0) ?></strong></td>
                            <td>
                                <img src="<?= htmlspecialchars($thumbSrc) ?>" 
                                     class="product-img-thumb" alt="">
                            </td>
                            <td>
                                <div class="product-name-cell"><?= htmlspecialchars($product['product_name']) ?></div>
                                <?php if (!empty($product['sku'])): ?>
                                    <div class="sku-badge"><?= htmlspecialchars($product['sku']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="price-display"><?= number_format($product['product_price'], 2) ?> ₺</div>
                                <?php if ($product['original_price'] > $product['product_price']): ?>
                                    <span class="price-old"><?= number_format($product['original_price'], 2) ?> ₺</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= $product['status'] == 'visible' ? 'status-badge-visible' : 'status-badge-hidden' ?>">
                                    <?= $product['status'] == 'visible' ? 'Görünür' : 'Gizli' ?>
                                </span>
                            </td>
                            <td class="product-actions-cell">
                                <div class="product-actions">
                                <a href="edit_product.php?product_id=<?= $product['product_id'] ?>" class="action-btn action-btn-edit" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="assign_variants.php?product_id=<?= $product['product_id'] ?>" class="action-btn" title="Varyant">
                                    <i class="fas fa-layer-group"></i>
                                </a>
                                <a href="duplicate_product.php?product_id=<?= $product['product_id'] ?>" class="action-btn" title="Çoğalt">
                                    <i class="fas fa-copy"></i>
                                </a>
                                <a href="delete_product.php?product_id=<?= $product['product_id'] ?>" 
                                   class="action-btn action-btn-delete" 
                                   onclick="return confirm('Silmek istediğinize emin misiniz?')" 
                                   title="Sil">
                                    <i class="fas fa-trash"></i>
                                </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'js/catalog-reset.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php include 'admin_footer_common.php'; ?>
