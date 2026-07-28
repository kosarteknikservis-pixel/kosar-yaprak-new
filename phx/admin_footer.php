<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/footer_constants.php';

// Not metni için tekil kayıt: id=5 (tutarlılık için her iki sorguda da kullanılıyor)
$NOTE_ROW_ID = FOOTER_NOTE_ROW_ID;
// Logo ayarları için tekil kayıt: id=6
$LOGO_ROW_ID = FOOTER_LOGO_ROW_ID;
// Anasayfa başlığı için tekil kayıt: id=8
$HOMEPAGE_HEADING_ROW_ID = FOOTER_HOMEPAGE_HEADING_ROW_ID;
// Sipariş sonrası mesaj için tekil kayıt: id=7
$POST_ORDER_MSG_ROW_ID = FOOTER_POST_ORDER_ROW_ID;
$FOOTER_DISPLAY_ROW_ID = FOOTER_DISPLAY_ROW_ID;
$footerReservedIds = footer_reserved_row_ids();

// Not metnini oku
$stmt = $pdo->prepare("SELECT show_order_note, order_note_text FROM footer_images WHERE id = ?");
$stmt->execute([$NOTE_ROW_ID]);
$footer_note = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['show_order_note' => 0, 'order_note_text' => ''];

// Sipariş sonrası mesajını oku
$stmt = $pdo->prepare("SELECT show_post_order_msg, post_order_msg_text FROM footer_images WHERE id = ?");
$stmt->execute([$POST_ORDER_MSG_ROW_ID]);
$post_order_msg = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['show_post_order_msg' => 0, 'post_order_msg_text' => ''];

// Logo ayarlarını oku
$stmt = $pdo->prepare("SELECT logo_type, logo_text, logo_icon, logo_main_text, logo_sub_text FROM footer_images WHERE id = ?");
$stmt->execute([$LOGO_ROW_ID]);
$logo_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'logo_type' => 'text',
    'logo_text' => '',
    'logo_icon' => 'fas fa-store',
    'logo_main_text' => 'Mağaza',
    'logo_sub_text' => '',
];

// Anasayfa başlığını oku
$stmt = $pdo->prepare("SELECT home_heading_main, home_heading_sub FROM footer_images WHERE id = ?");
$stmt->execute([$HOMEPAGE_HEADING_ROW_ID]);
$home_heading = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'home_heading_main' => '',
    'home_heading_sub' => '',
];

$stmt = $pdo->prepare('SELECT footer_display_mode FROM footer_images WHERE id = ?');
$stmt->execute([$FOOTER_DISPLAY_ROW_ID]);
$footer_display_mode = $stmt->fetchColumn();
if (!in_array($footer_display_mode, ['image', 'legal', 'both'], true)) {
    $footer_display_mode = 'image';
}

// Alt bilgi görünüm modu
if (isset($_POST['footer_display_mode'])) {
    $mode = (string) $_POST['footer_display_mode'];
    if (!in_array($mode, ['image', 'legal', 'both'], true)) {
        $mode = 'image';
    }
    $stmt = $pdo->prepare('SELECT id FROM footer_images WHERE id = ?');
    $stmt->execute([$FOOTER_DISPLAY_ROW_ID]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare('UPDATE footer_images SET footer_display_mode = ? WHERE id = ?');
        $stmt->execute([$mode, $FOOTER_DISPLAY_ROW_ID]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO footer_images (id, footer_display_mode) VALUES (?, ?)');
        $stmt->execute([$FOOTER_DISPLAY_ROW_ID, $mode]);
    }
    $_SESSION['message'] = 'Alt bilgi görünümü güncellendi.';
    header('Location: admin_footer.php');
    exit();
}

// Not metnini güncelle
if (isset($_POST['order_note_text']) || isset($_POST['show_order_note'])) {
    $show_order_note = isset($_POST['show_order_note']) ? 1 : 0;
    $order_note_text = isset($_POST['order_note_text']) ? trim($_POST['order_note_text']) : '';

    // Önce kayıt var mı kontrol et, yoksa oluştur
    $stmt = $pdo->prepare("SELECT id FROM footer_images WHERE id = ?");
    $stmt->execute([$NOTE_ROW_ID]);

    if ($stmt->fetch()) {
        // Kayıt varsa güncelle
        $stmt = $pdo->prepare("UPDATE footer_images SET show_order_note = ?, order_note_text = ? WHERE id = ?");
        $stmt->execute([$show_order_note, $order_note_text, $NOTE_ROW_ID]);
    } else {
        // Kayıt yoksa oluştur
        $stmt = $pdo->prepare("INSERT INTO footer_images (id, show_order_note, order_note_text) VALUES (?, ?, ?)");
        $stmt->execute([$NOTE_ROW_ID, $show_order_note, $order_note_text]);
    }

    $_SESSION['message'] = 'Not metni başarıyla güncellendi!';
    header('Location: admin_footer.php');
    exit();
}

// Sipariş sonrası mesajını güncelle
if (isset($_POST['post_order_msg_text']) || isset($_POST['show_post_order_msg'])) {
    $show_post_order_msg = isset($_POST['show_post_order_msg']) ? 1 : 0;
    $post_order_msg_text = isset($_POST['post_order_msg_text']) ? trim($_POST['post_order_msg_text']) : '';

    // Kayıt var mı kontrol et, yoksa oluştur
    $stmt = $pdo->prepare("SELECT id FROM footer_images WHERE id = ?");
    $stmt->execute([$POST_ORDER_MSG_ROW_ID]);

    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE footer_images SET show_post_order_msg = ?, post_order_msg_text = ? WHERE id = ?");
        $stmt->execute([$show_post_order_msg, $post_order_msg_text, $POST_ORDER_MSG_ROW_ID]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO footer_images (id, show_post_order_msg, post_order_msg_text) VALUES (?, ?, ?)");
        $stmt->execute([$POST_ORDER_MSG_ROW_ID, $show_post_order_msg, $post_order_msg_text]);
    }

    $_SESSION['message'] = 'Sipariş sonrası mesajı güncellendi!';
    header('Location: admin_footer.php');
    exit();
}

// Logo ayarlarını güncelle
if (isset($_POST['logo_type']) || isset($_POST['logo_text']) || isset($_POST['logo_icon']) || isset($_POST['logo_main_text']) || isset($_POST['logo_sub_text'])) {
    $logo_type = $_POST['logo_type'] ?? 'text';
    $logo_text = trim($_POST['logo_text'] ?? '');
    $logo_icon = trim($_POST['logo_icon'] ?? '');
    $logo_main_text = trim($_POST['logo_main_text'] ?? '');
    $logo_sub_text = trim($_POST['logo_sub_text'] ?? '');

    // Önce kayıt var mı kontrol et, yoksa oluştur
    $stmt = $pdo->prepare("SELECT id FROM footer_images WHERE id = ?");
    $stmt->execute([$LOGO_ROW_ID]);

    if ($stmt->fetch()) {
        // Kayıt varsa güncelle
        $stmt = $pdo->prepare("UPDATE footer_images SET logo_type = ?, logo_text = ?, logo_icon = ?, logo_main_text = ?, logo_sub_text = ? WHERE id = ?");
        $stmt->execute([$logo_type, $logo_text, $logo_icon, $logo_main_text, $logo_sub_text, $LOGO_ROW_ID]);
    } else {
        // Kayıt yoksa oluştur
        $stmt = $pdo->prepare("INSERT INTO footer_images (id, logo_type, logo_text, logo_icon, logo_main_text, logo_sub_text) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$LOGO_ROW_ID, $logo_type, $logo_text, $logo_icon, $logo_main_text, $logo_sub_text]);
    }

    $_SESSION['message'] = 'Logo ayarları başarıyla güncellendi!';
    header('Location: admin_footer.php');
    exit();
}

// Anasayfa başlığını güncelle
if (isset($_POST['home_heading_main']) || isset($_POST['home_heading_sub'])) {
    $main = trim($_POST['home_heading_main'] ?? '');
    $sub  = trim($_POST['home_heading_sub'] ?? '');

    // Kayıt var mı kontrol et, yoksa oluştur
    $stmt = $pdo->prepare("SELECT id FROM footer_images WHERE id = ?");
    $stmt->execute([$HOMEPAGE_HEADING_ROW_ID]);

    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE footer_images SET home_heading_main = ?, home_heading_sub = ? WHERE id = ?");
        $stmt->execute([$main, $sub, $HOMEPAGE_HEADING_ROW_ID]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO footer_images (id, home_heading_main, home_heading_sub) VALUES (?, ?, ?)");
        $stmt->execute([$HOMEPAGE_HEADING_ROW_ID, $main, $sub]);
    }

    $_SESSION['message'] = 'Anasayfa başlığı güncellendi!';
    header('Location: admin_footer.php');
    exit();
}

// Boş kayıtları temizle (yalnız görsel satırları; id 5/6/7/8 ayar satırlarına dokunma)
if (isset($_GET['cleanup_empty'])) {
    $placeholders = implode(',', array_fill(0, count($footerReservedIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM footer_images WHERE id NOT IN ({$placeholders}) AND (image_path IS NULL OR image_path = '')");
    $stmt->execute($footerReservedIds);
    $deleted_count = $stmt->rowCount();
    $_SESSION['message'] = $deleted_count > 0 ? "$deleted_count boş kayıt temizlendi!" : "Temizlenecek boş kayıt bulunamadı.";
    header('Location: admin_footer.php');
    exit();
}

// Görsel sil
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    $stmt = $pdo->prepare('SELECT image_path FROM footer_images WHERE id = ?');
    $stmt->execute([$delete_id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($image) {
        $image_path = $image['image_path'];
        if ($image_path && file_exists($image_path)) {
            @unlink($image_path);
        }
        $stmt = $pdo->prepare('DELETE FROM footer_images WHERE id = ?');
        $stmt->execute([$delete_id]);
        $_SESSION['message'] = 'Görsel başarıyla silindi!';
    } else {
        $_SESSION['message'] = 'Görsel bulunamadı.';
    }
    header('Location: admin_footer.php');
    exit();
}

// Görsel yükle
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['footer_image']) && $_FILES['footer_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0775, true);
        }
        $original_name = basename($_FILES['footer_image']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed_types, true)) {
            $new_name = 'footer_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $target_path = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['footer_image']['tmp_name'], $target_path)) {
                $stmt = $pdo->prepare('INSERT INTO footer_images (image_path) VALUES (?)');
                $stmt->execute([$target_path]);
                $_SESSION['message'] = 'Görsel başarıyla yüklendi!';
            } else {
                $_SESSION['message'] = 'Görsel yüklenirken bir hata oluştu.';
            }
        } else {
            $_SESSION['message'] = 'Yalnızca JPG, JPEG, PNG ve GIF dosyalarına izin verilmektedir.';
        }
    } else {
        $_SESSION['message'] = 'Görsel yüklenirken bir hata oluştu.';
    }
    header('Location: admin_footer.php');
    exit();
}

// Görselleri getir (ayar satırlarını hariç tut)
$placeholders = implode(',', array_fill(0, count($footerReservedIds), '?'));
$stmt = $pdo->prepare("SELECT id, image_path, created_at FROM footer_images WHERE id NOT IN ({$placeholders}) AND image_path IS NOT NULL AND image_path != '' ORDER BY created_at DESC");
$stmt->execute($footerReservedIds);
$footer_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Footer Yönetimi';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-images me-2"></i>Footer Yönetimi</h2>
            <span class="text-muted">Görselleri yönetin ve not metnini düzenleyin</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info d-flex align-items-center">
            <i class="fas fa-info-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Üst kısım: Sipariş Notu ve Logo Yönetimi yan yana -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-sticky-note me-2"></i>Sipariş Notu Yönetimi
                    <span class="badge bg-<?= $footer_note['show_order_note'] ? 'success' : 'secondary' ?> ms-2">
                        <?= $footer_note['show_order_note'] ? 'Aktif' : 'Pasif' ?>
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="order_note_text" class="form-label">Sipariş Notu Metni</label>
                            <textarea class="form-control" id="order_note_text" name="order_note_text" rows="3" placeholder="Örn: Farklı renklerde istiyorsanız lütfen belirtin."><?= htmlspecialchars($footer_note['order_note_text']) ?></textarea>
                            <div class="form-text">Bu not sipariş formunda görünecektir.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" class="form-check-input" id="show_order_note" name="show_order_note" <?= $footer_note['show_order_note'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_order_note">
                                <strong>Sipariş notunu aktif et</strong>
                                <small class="d-block text-muted">Notu sipariş formunda göster</small>
                            </label>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Notu Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-palette me-2"></i>Logo Yönetimi
                    <span class="badge bg-info ms-2">Menü Sol Üst</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Logo Tipi</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="logo_type" id="logo_type_text" value="text" <?= $logo_settings['logo_type'] === 'text' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="logo_type_text">
                                    <i class="fas fa-font me-1"></i> Yazı
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="logo_type" id="logo_type_icon" value="icon" <?= $logo_settings['logo_type'] === 'icon' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="logo_type_icon">
                                    <i class="fas fa-icons me-1"></i> İkon
                                </label>
                            </div>
                        </div>

                        <div id="text_settings" style="display: <?= $logo_settings['logo_type'] === 'text' ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label for="logo_main_text" class="form-label">Ana Yazı</label>
                                <input type="text" class="form-control" id="logo_main_text" name="logo_main_text" value="<?= htmlspecialchars($logo_settings['logo_main_text']) ?>" placeholder="Örn: Quattro">
                            </div>
                            <div class="mb-3">
                                <label for="logo_sub_text" class="form-label">Alt Yazı</label>
                                <input type="text" class="form-control" id="logo_sub_text" name="logo_sub_text" value="<?= htmlspecialchars($logo_settings['logo_sub_text']) ?>" placeholder="Örn: yeykim">
                            </div>
                        </div>

                        <div id="icon_settings" style="display: <?= $logo_settings['logo_type'] === 'icon' ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label for="logo_icon" class="form-label">İkon Kodu</label>
                                <input type="text" class="form-control" id="logo_icon" name="logo_icon" value="<?= htmlspecialchars($logo_settings['logo_icon']) ?>" placeholder="Örn: fas fa-home">
                                <div class="form-text">FontAwesome ikon kodunu girin (örn: fas fa-home, fas fa-star)</div>
                            </div>
                            <div class="mb-3">
                                <label for="logo_text" class="form-label">İkon Yanındaki Yazı</label>
                                <input type="text" class="form-control" id="logo_text" name="logo_text" value="<?= htmlspecialchars($logo_settings['logo_text']) ?>" placeholder="Örn: Quattro">
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Logo Ayarlarını Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Orta kısım: Anasayfa Başlığı & Sipariş Sonrası Mesaj Yönetimi -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-heading me-2"></i>Anasayfa Başlığı (Ürünler Üstü)
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="home_heading_main" class="form-label">Ana Başlık</label>
                            <input type="text" id="home_heading_main" name="home_heading_main" class="form-control" value="<?= htmlspecialchars($home_heading['home_heading_main']) ?>" placeholder="Örn: Quattro T-1069">
                            <small class="text-muted">Turuncu renkle gösterilen ana satır.</small>
                        </div>
                        <div class="mb-3">
                            <label for="home_heading_sub" class="form-label">Alt Başlık</label>
                            <input type="text" id="home_heading_sub" name="home_heading_sub" class="form-control" value="<?= htmlspecialchars($home_heading['home_heading_sub']) ?>" placeholder="Örn: Termal Boyaları">
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Başlığı Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-bullhorn me-2"></i>Sipariş Sonrası Mesaj Yönetimi
                    <span class="badge bg-<?= $post_order_msg['show_post_order_msg'] ? 'success' : 'secondary' ?> ms-2">
                        <?= $post_order_msg['show_post_order_msg'] ? 'Aktif' : 'Pasif' ?>
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="post_order_msg_text" class="form-label">Mesaj Metni</label>
                            <textarea class="form-control" id="post_order_msg_text" name="post_order_msg_text" rows="2" placeholder="Örn: Sipariş sonrası ekibimiz, mesai saatleri içerisinde sizinle iletişime geçecektir."><?= htmlspecialchars($post_order_msg['post_order_msg_text']) ?></textarea>
                            <div class="form-text">Bu mesaj ürün fiyatının altında görüntülenir.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" class="form-check-input" id="show_post_order_msg" name="show_post_order_msg" <?= $post_order_msg['show_post_order_msg'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_post_order_msg">
                                <strong>Mesajı aktif et</strong>
                                <small class="d-block text-muted">Sipariş sayfasında göster</small>
                            </label>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Mesajı Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><i class="fas fa-shoe-prints me-2"></i>Alt Bilgi Görünümü</div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="footer_display_mode" class="form-label">Görünüm modu</label>
                            <select class="form-select" id="footer_display_mode" name="footer_display_mode">
                                <option value="image" <?= $footer_display_mode === 'image' ? 'selected' : '' ?>>Yalnızca görsel</option>
                                <option value="legal" <?= $footer_display_mode === 'legal' ? 'selected' : '' ?>>Yalnızca yasal linkler (KVKK, SSS…)</option>
                                <option value="both" <?= $footer_display_mode === 'both' ? 'selected' : '' ?>>Görsel + yasal linkler</option>
                            </select>
                            <div class="form-text">Yasal metinler genel şablondan gelir; site bazlı düzenleme gerekmez.</div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> Modu Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Alt kısım: Görsel Yükle -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><i class="fas fa-upload me-2"></i>Görsel Yükle</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_image" value="1">
                        <div class="mb-3">
                            <label for="footer_image" class="form-label">Footer Görseli</label>
                            <input type="file" class="form-control" id="footer_image" name="footer_image" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-1"></i> Görseli Ekle
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-images me-2"></i>Mevcut Footer Görselleri</span>
            <a href="?cleanup_empty=1" class="btn btn-outline-warning btn-sm" onclick="return confirm('Boş görsel kayıtlarını temizlemek istiyor musunuz?')">
                <i class="fas fa-broom me-1"></i> Boş Kayıtları Temizle
            </a>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($footer_images as $image): ?>
                    <div class="col-md-3 col-sm-4 col-6 mb-3">
                        <div class="card h-100">
                            <?php
                            $footerPathRaw = (string) $image['image_path'];
                            if (preg_match('#^https?://#i', trim($footerPathRaw))) {
                                $footerImgSrc = htmlspecialchars($footerPathRaw, ENT_QUOTES, 'UTF-8');
                            } else {
                                $footerImgSrc = '../' . htmlspecialchars(str_replace('../', '', $footerPathRaw), ENT_QUOTES, 'UTF-8');
                            }
                            ?>
                            <img src="<?= $footerImgSrc ?>" class="card-img-top" alt="Footer Görseli" onerror="this.style.display='none'">
                            <div class="card-body p-2 text-center">
                                <a href="?delete_id=<?= (int)$image['id'] ?>" class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('Bu görseli silmek istiyor musunuz?')">
                                    <i class="fas fa-trash me-1"></i> Sil
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($footer_images)): ?>
                    <div class="col-12 text-muted">Görsel bulunamadı.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Logo tipi değiştiğinde form alanlarını göster/gizle
document.addEventListener('DOMContentLoaded', function() {
    const logoTypeText = document.getElementById('logo_type_text');
    const logoTypeIcon = document.getElementById('logo_type_icon');
    const textSettings = document.getElementById('text_settings');
    const iconSettings = document.getElementById('icon_settings');

    function toggleSettings() {
        if (logoTypeText.checked) {
            textSettings.style.display = 'block';
            iconSettings.style.display = 'none';
        } else {
            textSettings.style.display = 'none';
            iconSettings.style.display = 'block';
        }
    }

    logoTypeText.addEventListener('change', toggleSettings);
    logoTypeIcon.addEventListener('change', toggleSettings);
});
</script>

<?php include 'admin_footer_common.php'; ?>

