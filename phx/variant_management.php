<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/catalog_reset.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && catalog_reset_request_valid()) {
    try {
        $result = catalog_reset_all($pdo);
        $_SESSION['message'] = 'Katalog sıfırlandı: ' . count($result['tables']) . ' tablo temizlendi, '
            . (int) $result['files_deleted'] . ' görsel dosyası silindi.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Sıfırlama hatası: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: variant_management.php');
    exit;
}

if (!empty($_SESSION['message']) && is_string($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = ($_SESSION['message_type'] ?? 'success') === 'danger' ? 'error' : 'success';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Varyant türü ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $type_name = trim($_POST['type_name']);
    $type_description = trim($_POST['type_description']);
    $display_order = (int)$_POST['display_order'];

    if (!empty($type_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO product_variation_types (type_name, type_description, display_order) VALUES (?, ?, ?)");
            $stmt->execute([$type_name, $type_description, $display_order]);
            $message = 'Varyant türü başarıyla eklendi!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Hata: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Varyant seçeneği ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_option'])) {
    $type_id = (int)$_POST['type_id'];
    $option_name = trim($_POST['option_name']);
    $option_value = trim($_POST['option_value']);
    $option_color = trim($_POST['option_color']);
    $option_color_enabled = isset($_POST['option_color_enabled']) ? 1 : 0;
    $display_order = (int)$_POST['option_display_order'];

    if (!empty($type_id) && !empty($option_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO product_variation_options (type_id, option_name, option_value, option_color, option_color_enabled, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type_id, $option_name, $option_value, $option_color, $option_color_enabled, $display_order]);
            $message = 'Varyant seçeneği başarıyla eklendi!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Hata: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Varyant türü güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_type'])) {
    $type_id = (int)$_POST['type_id'];
    $type_name = trim($_POST['type_name']);
    $type_description = trim($_POST['type_description']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE product_variation_types SET type_name = ?, type_description = ?, display_order = ?, is_active = ? WHERE type_id = ?");
        $stmt->execute([$type_name, $type_description, $display_order, $is_active, $type_id]);
        $message = 'Varyant türü başarıyla güncellendi!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Varyant seçeneği güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_option'])) {
    $option_id = (int)$_POST['option_id'];
    $option_name = trim($_POST['option_name']);
    $option_value = trim($_POST['option_value']);
    $option_color = trim($_POST['option_color']);
    $option_color_enabled = isset($_POST['option_color_enabled']) ? 1 : 0;
    $display_order = (int)$_POST['option_display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE product_variation_options SET option_name = ?, option_value = ?, option_color = ?, option_color_enabled = ?, display_order = ?, is_active = ? WHERE option_id = ?");
        $stmt->execute([$option_name, $option_value, $option_color, $option_color_enabled, $display_order, $is_active, $option_id]);
        $message = 'Varyant seçeneği başarıyla güncellendi!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Varyant türü silme
if (isset($_GET['delete_type'])) {
    $type_id = (int)$_GET['delete_type'];
    try {
        $stmt = $pdo->prepare("DELETE FROM product_variation_types WHERE type_id = ?");
        $stmt->execute([$type_id]);
        $message = 'Varyant türü başarıyla silindi!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Varyant seçeneği silme
if (isset($_GET['delete_option'])) {
    $option_id = (int)$_GET['delete_option'];
    try {
        $stmt = $pdo->prepare("DELETE FROM product_variation_options WHERE option_id = ?");
        $stmt->execute([$option_id]);
        $message = 'Varyant seçeneği başarıyla silindi!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Verileri çek
$stmt = $pdo->query("SELECT * FROM product_variation_types ORDER BY display_order, type_name");
$variation_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT o.*, t.type_name
    FROM product_variation_options o
    JOIN product_variation_types t ON o.type_id = t.type_id
    ORDER BY t.display_order, o.display_order, o.option_name
");
$variation_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ürün listesi (atama için)
$products = $pdo->query("SELECT product_id, product_name FROM products ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);

// Varyant atama kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_variants'])) {
    $product_id = (int)($_POST['assign_product_id'] ?? 0);
    $selected_types = isset($_POST['assign_type_ids']) && is_array($_POST['assign_type_ids']) ? array_map('intval', $_POST['assign_type_ids']) : [];
    $required_types = isset($_POST['assign_required']) && is_array($_POST['assign_required']) ? array_map('intval', $_POST['assign_required']) : [];
    $orderMap = isset($_POST['assign_order']) && is_array($_POST['assign_order']) ? $_POST['assign_order'] : [];

    if ($product_id > 0) {
        try {
            $pdo->beginTransaction();
            // Eski atamaları sil
            $del = $pdo->prepare('DELETE FROM product_variation_assignments WHERE product_id = ?');
            $del->execute([$product_id]);

            // Yeni atamaları ekle
            if (!empty($selected_types)) {
                $ins = $pdo->prepare('INSERT INTO product_variation_assignments (product_id, type_id, is_required, display_order) VALUES (?, ?, ?, ?)');
                foreach ($selected_types as $type_id) {
                    $is_req = in_array($type_id, $required_types, true) ? 1 : 0;
                    $ord = isset($orderMap[$type_id]) ? (int) $orderMap[$type_id] : 0;
                    $ins->execute([$product_id, $type_id, $is_req, $ord]);
                }
            }
            $pdo->commit();
            $message = 'Varyant atamaları güncellendi!';
            $message_type = 'success';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Hata: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Lütfen bir ürün seçin.';
        $message_type = 'error';
    }
}
$page_title = 'Varyant Yönetimi';
include 'admin_header.php';
?>

<div class="container-fluid px-0 variant-mgmt-page">
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h1 class="mb-0"><i class="fas fa-layer-group"></i> Sınırsız Varyant Yönetimi</h1>
                    <form method="post" id="catalogResetFormVariants" class="m-0">
                        <input type="hidden" name="catalog_reset_all" value="1">
                        <input type="hidden" name="catalog_reset_confirm" id="catalogResetConfirmVariants" value="">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmCatalogReset('catalogResetFormVariants','catalogResetConfirmVariants')">
                            <i class="fas fa-trash-alt me-1"></i> Sıfırla
                        </button>
                    </form>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                    </div>
                <?php endif; ?>

                <!-- Varyant Türü Ekleme Formu -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Yeni Varyant Türü Ekle</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="type_name">Tür Adı *</label>
                                    <input type="text" class="form-control" id="type_name" name="type_name" required placeholder="örn: Renk, Boyut, Malzeme">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="type_description">Açıklama</label>
                                    <input type="text" class="form-control" id="type_description" name="type_description" placeholder="Kısa açıklama">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="display_order">Sıra</label>
                                    <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" name="add_type" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus"></i> Ekle
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Varyant Seçeneği Ekleme Formu -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Yeni Varyant Seçeneği Ekle</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="type_id">Varyant Türü *</label>
                                    <select class="form-control" id="type_id" name="type_id" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($variation_types as $type): ?>
                                            <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="option_name">Seçenek Adı *</label>
                                    <input type="text" class="form-control" id="option_name" name="option_name" required placeholder="örn: Kırmızı">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="option_value">Değer</label>
                                    <input type="text" class="form-control" id="option_value" name="option_value" placeholder="örn: red">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="option_color">Renk Kodu</label>
                                    <input type="color" class="form-control" id="option_color" name="option_color" value="#000000">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <label for="option_color_enabled">Renk?</label>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="option_color_enabled" name="option_color_enabled" checked>
                                        <label class="form-check-label small" for="option_color_enabled">Göster</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <label for="option_display_order">Sıra</label>
                                    <input type="number" class="form-control" id="option_display_order" name="option_display_order" value="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" name="add_option" class="btn btn-success btn-block">
                                        <i class="fas fa-plus"></i> Ekle
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Ürüne Varyant Türleri Atama -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-link"></i> Ürüne Varyant Türleri Ata</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="assign_product_id">Ürün</label>
                                    <select id="assign_product_id" name="assign_product_id" class="form-control" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= (int)$p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <?php foreach ($variation_types as $type): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="border rounded p-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="<?= (int)$type['type_id'] ?>" id="type_<?= (int)$type['type_id'] ?>" name="assign_type_ids[]">
                                                        <label class="form-check-label" for="type_<?= (int)$type['type_id'] ?>">
                                                            <strong><?= htmlspecialchars($type['type_name']) ?></strong>
                                                            <?php if ($type['type_description']): ?>
                                                                <small class="text-muted"> - <?= htmlspecialchars($type['type_description']) ?></small>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                    <div class="form-check mt-1">
                                                        <input class="form-check-input" type="checkbox" value="<?= (int)$type['type_id'] ?>" id="req_<?= (int)$type['type_id'] ?>" name="assign_required[]">
                                                        <label class="form-check-label" for="req_<?= (int)$type['type_id'] ?>">Zorunlu</label>
                                                    </div>
                                                    <div class="mt-2">
                                                        <label class="small text-muted mb-1" for="ord_<?= (int)$type['type_id'] ?>">Sıra</label>
                                                        <input type="number" class="form-control form-control-sm" id="ord_<?= (int)$type['type_id'] ?>" name="assign_order[<?= (int)$type['type_id'] ?>]" value="<?= (int)($type['display_order'] ?? 0) ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="assign_variants" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Atamayı Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Varyant Türleri Listesi -->
                <div class="row">
                    <?php foreach ($variation_types as $type): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card variant-type-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-tag"></i> <?= htmlspecialchars($type['type_name']) ?>
                                        <?php if ($type['type_description']): ?>
                                            <small class="text-muted">- <?= htmlspecialchars($type['type_description']) ?></small>
                                        <?php endif; ?>
                                    </h6>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='editType(<?= (int)$type['type_id'] ?>, <?= json_encode((string)$type['type_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode((string)($type['type_description'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= (int)$type['display_order'] ?>, <?= (int)($type['is_active'] ?? 0) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete_type=<?= $type['type_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu varyant türünü silmek istediğinize emin misiniz?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $type_options = array_filter($variation_options, function($option) use ($type) {
                                        return $option['type_id'] == $type['type_id'];
                                    });
                                    ?>

                                    <?php if (empty($type_options)): ?>
                                        <p class="text-muted">Henüz seçenek eklenmemiş.</p>
                                    <?php else: ?>
                                        <?php foreach ($type_options as $option): ?>
                                            <div class="option-item d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($option['option_color']) && !empty((int)($option['option_color_enabled'] ?? 1))): ?>
                                                        <div class="color-preview" style="background-color: <?= htmlspecialchars($option['option_color']) ?>"></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= htmlspecialchars($option['option_name']) ?></strong>
                                                        <?php if ($option['option_value']): ?>
                                                            <small class="text-muted">(<?= htmlspecialchars($option['option_value']) ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='editOption(<?= (int)$option['option_id'] ?>, <?= json_encode((string)$option['option_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode((string)($option['option_value'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode((string)($option['option_color'] ?? '#000000'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= (int)($option['display_order'] ?? 0) ?>, <?= (int)($option['is_active'] ?? 0) ?>, <?= (int)($option['option_color_enabled'] ?? 1) ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?delete_option=<?= $option['option_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu seçeneği silmek istediğinize emin misiniz?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Type Modal -->
    <div class="modal fade" id="editTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Varyant Türü Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_type_id" name="type_id">
                        <div class="form-group">
                            <label for="edit_type_name">Tür Adı</label>
                            <input type="text" class="form-control" id="edit_type_name" name="type_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_type_description">Açıklama</label>
                            <input type="text" class="form-control" id="edit_type_description" name="type_description">
                        </div>
                        <div class="form-group">
                            <label for="edit_display_order">Sıra</label>
                            <input type="number" class="form-control" id="edit_display_order" name="display_order">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="update_type" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Option Modal -->
    <div class="modal fade" id="editOptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Varyant Seçeneği Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_option_id" name="option_id">
                        <div class="form-group">
                            <label for="edit_option_name">Seçenek Adı</label>
                            <input type="text" class="form-control" id="edit_option_name" name="option_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_option_value">Değer</label>
                            <input type="text" class="form-control" id="edit_option_value" name="option_value">
                        </div>
                        <div class="form-group">
                            <label for="edit_option_color">Renk Kodu</label>
                            <input type="color" class="form-control" id="edit_option_color" name="option_color">
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="edit_option_color_enabled" name="option_color_enabled">
                            <label class="form-check-label" for="edit_option_color_enabled">Rengi göster</label>
                        </div>
                        <div class="form-group">
                            <label for="edit_option_display_order">Sıra</label>
                            <input type="number" class="form-control" id="edit_option_display_order" name="option_display_order">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_option_is_active" name="is_active">
                            <label class="form-check-label" for="edit_option_is_active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="update_option" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Bootstrap 5 modal API
        function editType(id, name, description, order, active) {
            document.getElementById('edit_type_id').value = id;
            document.getElementById('edit_type_name').value = name;
            document.getElementById('edit_type_description').value = description;
            document.getElementById('edit_display_order').value = order;
            document.getElementById('edit_is_active').checked = active == 1;
            const m = new bootstrap.Modal(document.getElementById('editTypeModal'));
            m.show();
        }

        function editOption(id, name, value, color, order, active, colorEnabled) {
            document.getElementById('edit_option_id').value = id;
            document.getElementById('edit_option_name').value = name;
            document.getElementById('edit_option_value').value = value;
            document.getElementById('edit_option_color').value = color;
            document.getElementById('edit_option_color_enabled').checked = (colorEnabled == 1);
            document.getElementById('edit_option_display_order').value = order;
            document.getElementById('edit_option_is_active').checked = active == 1;
            const m = new bootstrap.Modal(document.getElementById('editOptionModal'));
            m.show();
        }
    </script>
<script src="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'js/catalog-reset.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php include 'admin_footer_common.php'; ?>
