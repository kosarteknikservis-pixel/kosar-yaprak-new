<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/admin_rbac.php';

$page_title = 'Kullanıcı Yönetimi';

// Kullanıcıları getir
$stmt = $pdo->query('SELECT user_id, username, email, created_at, phone_number, full_name, profile_image, is_super_admin FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats = [
    'total' => count($users),
    'today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'this_week' => $pdo->query("SELECT COUNT(*) FROM users WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn(),
    'this_month' => $pdo->query("SELECT COUNT(*) FROM users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn(),
    'super' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_super_admin = 1")->fetchColumn(),
    'with_logs' => $pdo->query("SELECT COUNT(DISTINCT u.user_id) FROM users u INNER JOIN login_logs l ON u.username = l.username")->fetchColumn()
];

// Son kayıt olan kullanıcı (özet panel için)
$latestUser = $users[0] ?? null;

// Avatar yükleme için CSRF token
if (empty($_SESSION['user_avatar_csrf'])) {
    $_SESSION['user_avatar_csrf'] = bin2hex(random_bytes(16));
}
$avatarCsrf = (string) $_SESSION['user_avatar_csrf'];
?>

<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <!-- Üst Bar -->
    <div class="top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bar-left">
                <h2 class="mb-0"><i class="fas fa-users me-2"></i>Kullanıcı Yönetimi</h2>
                <p class="text-muted mb-0">Sistem kullanıcılarını yönetin ve düzenleyin</p>
            </div>
            <div class="bar-right">
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= $stats['total'] ?> Kullanıcı</span>
                    <a href="add_user.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Kullanıcı Ekle
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Uyarı Mesajı -->
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="fas fa-shield-alt me-2"></i>
        <div>
            <strong>Güvenlik Uyarısı:</strong> Bu sayfa sadece admin kullanıcıları tarafından kullanılabilir. Kullanıcı bilgilerini dikkatli bir şekilde yönetin.
        </div>
    </div>

    <!-- Başarı/Hata Mesajları -->
    <?php if (isset($_GET['error']) && $_GET['error'] == 'log_exists'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Kullanıcının log kayıtları olduğu için silinemez. Şifresini değiştirebilirsiniz.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['message']) && $_GET['message'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            Kullanıcı başarıyla silindi.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- İstatistikler -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
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
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['with_logs'] ?></h3>
                    <p>Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon--danger">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['super'] ?></h3>
                    <p>Süper Admin</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($latestUser): ?>
    <div class="latest-user-panel mb-4">
        <div class="d-flex align-items-center flex-wrap gap-3">
            <div class="latest-avatar">
                <?php $lu_img = ($latestUser['profile_image'] && $latestUser['profile_image'] !== 'txrik.gif') ? $latestUser['profile_image'] : 'txrik.gif'; ?>
                <img src="../uploads/<?= htmlspecialchars($lu_img) ?>" alt="Son kayıt">
            </div>
            <div class="latest-info">
                <span class="latest-label"><i class="fas fa-clock me-1"></i>Son kayıt olan kullanıcı</span>
                <strong><?= htmlspecialchars($latestUser['username']) ?></strong>
                <?php if (!empty($latestUser['full_name'])): ?>
                    <span class="text-muted">· <?= htmlspecialchars($latestUser['full_name']) ?></span>
                <?php endif; ?>
                <span class="text-muted d-block small"><?= date('d.m.Y H:i', strtotime($latestUser['created_at'])) ?></span>
            </div>
            <div class="ms-auto">
                <a href="edit_user.php?id=<?= (int) $latestUser['user_id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i>Düzenle
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kullanıcılar Tablosu -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-table"></i> Kullanıcı Listesi
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-users">
                    <thead>
                        <tr>
                            <th style="width: 8%;">Profil</th>
                            <th style="width: 6%;">ID</th>
                            <th style="width: 15%;">Kullanıcı Adı</th>
                            <th style="width: 18%;">Ad Soyad</th>
                            <th style="width: 20%;">E-posta</th>
                            <th style="width: 12%;">Telefon</th>
                            <th style="width: 12%;">Kayıt Tarihi</th>
                            <th style="width: 9%;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?php $avatarSrc = ($user['profile_image'] && $user['profile_image'] !== 'txrik.gif')
                                            ? '../uploads/' . htmlspecialchars($user['profile_image'])
                                            : '../uploads/txrik.gif'; ?>
                                        <div class="profile-image-container">
                                            <label class="avatar-uploader" title="Profil fotoğrafını değiştir">
                                                <img src="<?= $avatarSrc ?>" alt="Profil Resmi" class="profile-image" data-user-id="<?= (int) $user['user_id'] ?>">
                                                <span class="avatar-overlay"><i class="fas fa-camera"></i></span>
                                                <span class="avatar-spinner"><i class="fas fa-spinner fa-spin"></i></span>
                                                <input type="file" class="avatar-input d-none" accept="image/png,image/jpeg,image/webp,image/gif" data-user-id="<?= (int) $user['user_id'] ?>">
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($user['user_id']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        <?php if (!empty($user['is_super_admin'])): ?>
                                            <span class="badge bg-danger ms-1">Süper</span>
                                        <?php elseif ($user['username'] === 'admin'): ?>
                                            <span class="badge bg-secondary ms-1">admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= ($fn = trim((string)($user['full_name'] ?? ''))) !== '' ? htmlspecialchars($fn) : '<span class="text-muted">Belirtilmemiş</span>' ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($user['phone_number']): ?>
                                            <a href="tel:<?= htmlspecialchars($user['phone_number']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($user['phone_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Belirtilmemiş</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical" role="group">
                                            <a href="edit_user.php?id=<?= (int)$user['user_id'] ?>"
                                               class="btn btn-warning btn-sm mb-1">
                                                <i class="fas fa-edit"></i> Düzenle
                                            </a>
                                            <a href="edit_password.php?id=<?= (int)$user['user_id'] ?>"
                                               class="btn btn-info btn-sm mb-1">
                                                <i class="fas fa-key"></i> Şifre
                                            </a>
                                            <?php if ((int) $user['user_id'] !== 1): ?>
                                                <a href="delete_user.php?id=<?= (int)$user['user_id'] ?>"
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');">
                                                    <i class="fas fa-trash"></i> Sil
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-shield-alt"></i> Birincil
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <p>Henüz kullanıcı bulunmuyor.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

/* Kullanıcı Tablosu */
.table-users {
    table-layout: fixed;
    width: 100%;
}

.table-users th,
.table-users td {
    font-size: 13px;
    padding: 12px 8px;
    vertical-align: middle;
}

.profile-image-container {
    display: flex;
    justify-content: center;
    align-items: center;
}

.profile-image {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
    display: block;
}

.stat-icon--danger {
    background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important;
}

/* Son kayıt paneli */
.latest-user-panel {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 15px;
    padding: 16px 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
}
.latest-avatar img {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
}
.latest-label {
    display: block;
    font-size: 12px;
    color: #7f8c8d;
    margin-bottom: 2px;
}
.latest-info strong { color: #2c3e50; }

/* Avatar yükleyici */
.avatar-uploader {
    position: relative;
    display: inline-block;
    cursor: pointer;
    margin: 0;
}
.avatar-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.55);
    color: #fff;
    font-size: 13px;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.avatar-uploader:hover .avatar-overlay { opacity: 1; }
.avatar-spinner {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.8);
    color: #4f46e5;
}
.avatar-uploader.is-uploading .avatar-spinner { display: flex; }
.avatar-uploader.is-uploading .avatar-overlay { opacity: 0; }

.btn-group-vertical .btn {
    font-size: 11px;
    padding: 4px 8px;
    margin-bottom: 2px;
}
</style>

<script>
(function () {
    var CSRF = <?= json_encode($avatarCsrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var MAX = 3 * 1024 * 1024;

    function flash(msg, ok) {
        var box = document.createElement('div');
        box.className = 'alert alert-' + (ok ? 'success' : 'danger') +
            ' alert-dismissible fade show position-fixed';
        box.style.cssText = 'top:20px;right:20px;z-index:2000;min-width:280px;box-shadow:0 10px 30px rgba(0,0,0,.15)';
        box.innerHTML = '<i class="fas fa-' + (ok ? 'check-circle' : 'exclamation-triangle') +
            ' me-2"></i>' + msg +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(box);
        setTimeout(function () { box.remove(); }, 4500);
    }

    document.querySelectorAll('.avatar-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            if (file.size > MAX) {
                flash('Dosya çok büyük (en fazla 3 MB).', false);
                input.value = '';
                return;
            }
            var uid = input.getAttribute('data-user-id');
            var wrap = input.closest('.avatar-uploader');
            var img = wrap ? wrap.querySelector('.profile-image') : null;
            if (wrap) wrap.classList.add('is-uploading');

            var fd = new FormData();
            fd.append('csrf', CSRF);
            fd.append('user_id', uid);
            fd.append('avatar', file);

            fetch('user_avatar_upload.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Sunucu yanıtı okunamadı' }; }); })
                .then(function (j) {
                    if (wrap) wrap.classList.remove('is-uploading');
                    if (!j.ok) {
                        flash(j.error || 'Yükleme başarısız.', false);
                        return;
                    }
                    if (img) img.src = j.url + '?t=' + Date.now();
                    flash('Profil fotoğrafı güncellendi.', true);
                })
                .catch(function () {
                    if (wrap) wrap.classList.remove('is-uploading');
                    flash('Bağlantı hatası — yüklenemedi.', false);
                })
                .finally(function () { input.value = ''; });
        });
    });
})();
</script>

<?php include 'admin_footer_common.php'; ?>
