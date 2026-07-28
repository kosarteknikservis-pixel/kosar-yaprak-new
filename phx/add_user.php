<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/admin_rbac.php';

$page_title = 'Kullanıcı Ekle';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $profile_image_path = 'txrik.gif'; // Varsayılan profil resmi

    // Kullanıcı adı ve email kontrolü
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $checkStmt->execute([$username, $email]);
    if ($checkStmt->fetchColumn() > 0) {
        $message = 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.';
        $messageType = 'danger';
    } else {
        // Profil resmi yükleme
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_image']['tmp_name'];
            $fileName = time() . '_' . $_FILES['profile_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExtension, $allowedfileExtensions)) {
                $uploadFileDir = '../uploads/';
                $dest_path = $uploadFileDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $profile_image_path = $fileName;
                } else {
                    $message = "Dosya yükleme hatası! Lütfen tekrar deneyiniz.";
                    $messageType = 'danger';
                }
            } else {
                $message = "Geçersiz dosya türü. Yalnızca JPG, JPEG, PNG ve GIF dosyaları yüklenebilir.";
                $messageType = 'danger';
            }
        }

        if (!$message) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
            $menu_permissions = '[]';
            if (!$is_super_admin) {
                $permList = [];
                if (!empty($_POST['grant_all_menus'])) {
                    $permList = ['*'];
                } else {
                    foreach (array_keys(admin_menu_definitions()) as $mk) {
                        if (!empty($_POST['perm_' . $mk])) {
                            $permList[] = $mk;
                        }
                    }
                }
                if ($permList === []) {
                    $message = 'Süper yönetici değilken en az bir menü izni seçin veya «Tüm menüleri aç» kutusunu işaretleyin.';
                    $messageType = 'danger';
                } else {
                    $menu_permissions = json_encode(array_values(array_unique($permList)), JSON_UNESCAPED_UNICODE);
                }
            }

            if (!$message) {
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name, phone_number, profile_image, is_super_admin, menu_permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

                if ($stmt->execute([$username, $email, $hashedPassword, $full_name, $phone_number, $profile_image_path, $is_super_admin, $menu_permissions])) {
                    $message = 'Kullanıcı başarıyla eklendi!';
                    $messageType = 'success';

                    $_POST = [];
                } else {
                    $message = 'Kullanıcı eklenirken bir hata oluştu.';
                    $messageType = 'danger';
                }
            }
        }
    }
}
?>

<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <!-- Üst Bar -->
    <div class="top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bar-left">
                <h2 class="mb-0"><i class="fas fa-user-plus me-2"></i>Kullanıcı Ekle</h2>
                <p class="text-muted mb-0">Yeni sistem kullanıcısı oluşturun</p>
            </div>
            <div class="bar-right">
                <a href="user_management.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Geri Dön
                </a>
            </div>
        </div>
    </div>

    <!-- Uyarı Mesajı -->
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="fas fa-shield-alt me-2"></i>
        <div>
            <strong>Güvenlik Uyarısı:</strong> Bu sayfa sadece admin kullanıcıları tarafından kullanılabilir. Güçlü şifreler oluşturun.
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

    <!-- Form -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-user-plus"></i> Yeni Kullanıcı Bilgileri
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="userForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-1"></i>Kullanıcı Adı <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>E-posta <span class="text-danger">*</span>
                        </label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Şifre <span class="text-danger">*</span>
                        </label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div class="form-text">En az 6 karakter olmalıdır.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">
                            <i class="fas fa-id-card me-1"></i>Ad Soyad
                        </label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="phone_number" class="form-label">
                            <i class="fas fa-phone me-1"></i>Telefon Numarası
                        </label>
                        <input type="text" id="phone_number" name="phone_number" class="form-control"
                               value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="profile_image" class="form-label">
                            <i class="fas fa-image me-1"></i>Profil Resmi
                        </label>
                        <input type="file" id="profile_image" name="profile_image" class="form-control"
                               accept="image/*">
                        <div class="form-text">JPG, JPEG, PNG, GIF formatları desteklenir.</div>
                    </div>
                </div>

                <div class="border rounded p-3 mt-4 bg-light">
                    <h6 class="mb-3"><i class="fas fa-key me-1"></i> Yetkiler (RBAC)</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_super_admin" id="is_super_admin" value="1"
                            <?= !empty($_POST['is_super_admin']) ? ' checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_super_admin">Süper yönetici</label>
                        <div class="form-text">Kullanıcı yönetimi dahil tüm panel; diğer menü kutularını yok sayar.</div>
                    </div>
                    <div id="rbac-menus" class="ms-1">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="grant_all_menus" id="grant_all_menus" value="1"
                                <?= !empty($_POST['grant_all_menus']) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="grant_all_menus">Tüm menüleri aç (yıldız)</label>
                        </div>
                        <?php foreach (admin_menu_definitions() as $mk => $mlabel): ?>
                            <div class="form-check">
                                <input class="form-check-input perm-cb" type="checkbox" name="perm_<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>" value="1"
                                    <?= !empty($_POST['perm_' . $mk]) ? ' checked' : '' ?>>
                                <label class="form-check-label" for="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mlabel, ENT_QUOTES, 'UTF-8') ?> <code class="small"><?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?></code></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <script>
                (function(){
                    var sup = document.getElementById('is_super_admin');
                    var box = document.getElementById('rbac-menus');
                    function sync(){ if(sup && box){ box.style.opacity = sup.checked ? '0.45' : '1'; box.querySelectorAll('input').forEach(function(i){ i.disabled = sup.checked; }); } }
                    if(sup){ sup.addEventListener('change', sync); sync(); }
                })();
                </script>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Kullanıcı Ekle
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Temizle
                            </button>
                            <a href="user_management.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                    </div>
                </div>
            </form>
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
// Form başarılı olduğunda temizle
<?php if ($messageType === 'success'): ?>
    document.getElementById('userForm').reset();
    setTimeout(function() {
        window.location.href = 'user_management.php';
    }, 2000);
<?php endif; ?>
</script>

<?php include 'admin_footer_common.php'; ?>
