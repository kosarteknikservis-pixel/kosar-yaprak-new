<?php
require '../db.php';
require 'auth.php';

$page_title = 'Slider Yönetimi';

$message = '';
$messageType = '';

// Silme işlemi
if (isset($_GET['delete'])) {
    $image_id = (int) $_GET['delete'];

    $stmt = $pdo->prepare("SELECT image_path FROM slider_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();

    if ($image) {
        if (file_exists($image['image_path'])) {
            unlink($image['image_path']);
        }

        $stmt = $pdo->prepare("DELETE FROM slider_images WHERE id = ?");
        $stmt->execute([$image_id]);

        $message = "Görsel başarıyla silindi!";
        $messageType = 'success';
    } else {
        $message = "Görsel bulunamadı.";
        $messageType = 'danger';
    }
}

// Toplu silme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    $selected_images = $_POST['selected_images'] ?? [];

    if (empty($selected_images)) {
        $message = "Silinecek görsel seçmediniz.";
        $messageType = 'warning';
    } else {
        $deleted_count = 0;
        $error_count = 0;

        foreach ($selected_images as $image_id) {
            $stmt = $pdo->prepare("SELECT image_path FROM slider_images WHERE id = ?");
            $stmt->execute([$image_id]);
            $image = $stmt->fetch();

            if ($image) {
                if (file_exists($image['image_path'])) {
                    unlink($image['image_path']);
                }

                $stmt = $pdo->prepare("DELETE FROM slider_images WHERE id = ?");
                if ($stmt->execute([$image_id])) {
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        }

        if ($deleted_count > 0) {
            $message = "{$deleted_count} görsel başarıyla silindi!";
            $messageType = 'success';
        }

        if ($error_count > 0) {
            $message .= " {$error_count} görsel silinirken hata oluştu.";
            $messageType = 'warning';
        }
    }
}

// Sıralama işlemi
if (isset($_GET['move']) && isset($_GET['id'])) {
    $direction = $_GET['move'];
    $image_id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT id, display_order FROM slider_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $current_image = $stmt->fetch();

    if ($current_image) {
        if ($direction == 'up') {
            $stmt = $pdo->prepare("SELECT id, display_order FROM slider_images WHERE display_order < ? ORDER BY display_order DESC LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT id, display_order FROM slider_images WHERE display_order > ? ORDER BY display_order ASC LIMIT 1");
        }
        $stmt->execute([$current_image['display_order']]);
        $swap_image = $stmt->fetch();

        if ($swap_image) {
            $stmt = $pdo->prepare("UPDATE slider_images SET display_order = ? WHERE id = ?");
            $stmt->execute([$swap_image['display_order'], $current_image['id']]);

            $stmt = $pdo->prepare("UPDATE slider_images SET display_order = ? WHERE id = ?");
            $stmt->execute([$current_image['display_order'], $swap_image['id']]);

            $message = "Sıralama güncellendi!";
            $messageType = 'success';
        }
    }
}

// Görsel yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['bulk_delete'])) {
    if (isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] == 0) {
        $upload_dir = '../uploads/';
        $fileName = time() . '_' . $_FILES['slider_image']['name'];
        $upload_file = $upload_dir . $fileName;
        $imageFileType = strtolower(pathinfo($upload_file, PATHINFO_EXTENSION));

        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['slider_image']['tmp_name'], $upload_file)) {
                $stmt = $pdo->query("SELECT MAX(display_order) as max_order FROM slider_images");
                $max_row = $stmt->fetch(PDO::FETCH_ASSOC);
                $max_order = (int)($max_row['max_order'] ?? 0) + 1;

                $stmt = $pdo->prepare("INSERT INTO slider_images (image_path, display_order) VALUES (?, ?)");
                $stmt->execute([$upload_file, $max_order]);

                $message = "Görsel başarıyla yüklendi!";
                $messageType = 'success';
            } else {
                $message = "Görsel yüklenirken bir hata oluştu.";
                $messageType = 'danger';
            }
        } else {
            $message = "Yalnızca JPG, JPEG, PNG ve GIF dosyalarına izin verilmektedir.";
            $messageType = 'danger';
        }
    } else {
        $message = "Görsel yüklenirken bir hata oluştu.";
        $messageType = 'danger';
    }
}

// Slider görsellerini getir
$stmt = $pdo->query("SELECT * FROM slider_images ORDER BY display_order ASC");
$slider_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats = [
    'total' => count($slider_images),
    'today' => $pdo->query("SELECT COUNT(*) FROM slider_images WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0,
    'this_week' => $pdo->query("SELECT COUNT(*) FROM slider_images WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn() ?: 0,
    'this_month' => $pdo->query("SELECT COUNT(*) FROM slider_images WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn() ?: 0,
    'active' => count($slider_images)
];
?>

<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <!-- Üst Bar -->
    <div class="top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bar-left">
                <h2 class="mb-0"><i class="fas fa-images me-2"></i>Slider Yönetimi</h2>
                <p class="text-muted mb-0">Ana sayfa slider görsellerini yönetin</p>
            </div>
            <div class="bar-right">
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= $stats['total'] ?> Görsel</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Uyarı Mesajı -->
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="fas fa-shield-alt me-2"></i>
        <div>
            <strong>Güvenlik Uyarısı:</strong> Bu sayfa sadece admin kullanıcıları tarafından kullanılabilir. Slider görsellerini dikkatli bir şekilde yönetin.
        </div>
    </div>

    <!-- Mesaj -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- İstatistikler -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-images"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Toplam</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['today'] ?></h3>
                    <p>Bugün</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['this_week'] ?></h3>
                    <p>Bu Hafta</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['this_month'] ?></h3>
                    <p>Bu Ay</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['active'] ?></h3>
                    <p>Aktif</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Görsel Yükleme Formu -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-upload"></i> Yeni Slider Görseli Ekle
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="slider_image" class="form-label">
                            <i class="fas fa-image me-1"></i>Slider Görseli <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="slider_image" name="slider_image"
                               accept="image/*" required>
                        <div class="form-text">JPG, JPEG, PNG, GIF formatları desteklenir. Maksimum 5MB.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Görseli Ekle
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Mevcut Slider Görselleri -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-images"></i> Mevcut Slider Görselleri
                </div>
                <?php if (count($slider_images) > 0): ?>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="form-check-label small mb-0 d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input m-0" id="sliderSelectAll" title="Tümünü seç">
                            Tümünü seç
                        </label>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="sliderSelectNone">Seçimi kaldır</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($slider_images) > 0): ?>
                <form id="bulkForm" method="POST">
                    <div class="row">
                        <?php foreach ($slider_images as $index => $image): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                <div class="slider-card">
                                    <div class="slider-image-container">
                                        <img src="<?= htmlspecialchars($image['image_path']) ?>"
                                             class="slider-image"
                                             alt="Slider Image <?= $index + 1 ?>"
                                             onclick="openImageWindow('<?= htmlspecialchars($image['image_path']) ?>')">
                                        <div class="slider-order">
                                            <span class="badge bg-primary"><?= $index + 1 ?></span>
                                        </div>
                                        <div class="slider-checkbox">
                                            <input type="checkbox" class="form-check-input image-checkbox"
                                                   name="selected_images[]" value="<?= $image['id'] ?>">
                                        </div>
                                    </div>
                                    <div class="slider-actions">
                                        <div class="btn-group w-100" role="group">
                                            <a href="admin_slider.php?move=up&id=<?= $image['id'] ?>"
                                               class="btn btn-outline-secondary btn-sm"
                                               title="Yukarı Taşı">
                                                <i class="fas fa-arrow-up"></i>
                                            </a>
                                            <a href="admin_slider.php?move=down&id=<?= $image['id'] ?>"
                                               class="btn btn-outline-secondary btn-sm"
                                               title="Aşağı Taşı">
                                                <i class="fas fa-arrow-down"></i>
                                            </a>
                                            <a href="admin_slider.php?delete=<?= $image['id'] ?>"
                                               class="btn btn-outline-danger btn-sm"
                                               title="Sil"
                                               onclick="return confirm('Bu görseli silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Toplu Silme Butonu -->
                    <div class="text-center mt-4">
                        <button type="submit" name="bulk_delete" value="1" class="btn btn-danger btn-lg"
                                onclick="return confirm('Seçili görselleri silmek istediğinize emin misiniz? Bu işlem geri alınamaz!');">
                            <i class="fas fa-trash"></i> Seçilenleri Sil
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-images fa-3x mb-3"></i>
                        <p>Henüz yüklenmiş bir slider görseli yok.</p>
                        <p class="small">Yukarıdaki formu kullanarak ilk görselinizi yükleyin.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<style>
/* İstatistik Kartları */
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 20px;
}

.stat-content h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
}

.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 14px;
}

/* Slider Kartları */
.slider-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: all 0.3s ease;
}

.slider-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.slider-image-container {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.slider-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.slider-image:hover {
    transform: scale(1.05);
}

.slider-order {
    position: absolute;
    top: 10px;
    right: 10px;
}

.slider-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
}

.slider-checkbox .form-check-input {
    width: 20px;
    height: 20px;
    background-color: rgba(255, 255, 255, 0.9);
    border: 2px solid #007bff;
    border-radius: 4px;
}

.slider-checkbox .form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.slider-actions {
    padding: 15px;
    background: #f8f9fa;
}

.btn-group .btn {
    font-size: 12px;
    padding: 6px 12px;
}

/* Form Stilleri */
.form-label {
    font-weight: 600;
    color: #2c3e50;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn {
    font-weight: 600;
}
</style>

<script>
// Görsel yeni pencerede açma
function openImageWindow(imageSrc) {
    const newWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    newWindow.document.write(`
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Slider Görseli</title>
            <style>
                body {
                    margin: 0;
                    padding: 20px;
                    background: #f8f9fa;
                    font-family: Arial, sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .image-container {
                    background: white;
                    border-radius: 10px;
                    padding: 20px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 90%;
                    max-height: 90%;
                }
                .image-container img {
                    max-width: 100%;
                    max-height: 70vh;
                    object-fit: contain;
                    border-radius: 8px;
                }
                .close-btn {
                    margin-top: 15px;
                    padding: 10px 20px;
                    background: #dc3545;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                }
                .close-btn:hover {
                    background: #c82333;
                }
                h2 {
                    color: #2c3e50;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="image-container">
                <h2><i class="fas fa-image"></i> Slider Görseli</h2>
                <img src="${imageSrc}" alt="Slider Görseli">
                <br>
                <button class="close-btn" onclick="window.close()">
                    <i class="fas fa-times"></i> Kapat
                </button>
            </div>
        </body>
        </html>
    `);
    newWindow.document.close();
}

// Form başarılı olduğunda temizle
<?php if ($messageType === 'success'): ?>
    document.getElementById('uploadForm').reset();
<?php endif; ?>

(function () {
    var selectAll = document.getElementById('sliderSelectAll');
    var selectNone = document.getElementById('sliderSelectNone');
    var boxes = document.querySelectorAll('.image-checkbox');
    if (!boxes.length) return;

    function syncSelectAll() {
        if (!selectAll) return;
        var checked = document.querySelectorAll('.image-checkbox:checked').length;
        selectAll.checked = checked > 0 && checked === boxes.length;
        selectAll.indeterminate = checked > 0 && checked < boxes.length;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes.forEach(function (cb) { cb.checked = selectAll.checked; });
            selectAll.indeterminate = false;
        });
    }
    if (selectNone) {
        selectNone.addEventListener('click', function () {
            boxes.forEach(function (cb) { cb.checked = false; });
            if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
        });
    }
    boxes.forEach(function (cb) { cb.addEventListener('change', syncSelectAll); });
})();

</script>

<?php include 'admin_footer_common.php'; ?>
