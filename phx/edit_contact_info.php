<?php
require '../db.php';
require 'auth.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: contact_info_list.php');
    exit();
}

$sql = "SELECT * FROM contact_info WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $_SESSION['message'] = 'Kayıt bulunamadı!';
    $_SESSION['message_type'] = 'error';
    header('Location: contact_info_list.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $image = trim($_POST['image'] ?? '');
    $title_1 = trim($_POST['title_1'] ?? '');
    $content_1 = trim($_POST['content_1'] ?? '');
    $title_2 = trim($_POST['title_2'] ?? '');
    $content_2 = trim($_POST['content_2'] ?? '');

    $sql = "UPDATE contact_info SET image = ?, title_1 = ?, content_1 = ?, title_2 = ?, content_2 = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$image, $title_1, $content_1, $title_2, $content_2, $id]);
        $_SESSION['message'] = 'Kayıt başarıyla güncellendi!';
        $_SESSION['message_type'] = 'success';
        header('Location: contact_info_list.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Hata: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

$page_title = 'İletişim Bilgisi Düzenle';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-edit me-2"></i>İletişim Bilgisini Düzenle</h2>
            <div>
                <a href="contact_info_list.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-list me-1"></i> Listeye Dön
                </a>
                <a href="add_contact_info.php" class="btn btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> Yeni Ekle
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] === 'error' ? 'danger' : 'success' ?> d-flex align-items-center">
            <i class="fas fa-<?= $_SESSION['message_type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-12">
                    <label for="image" class="form-label">Görsel URL</label>
                    <input type="url" class="form-control" id="image" name="image" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($row['image']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="title_1" class="form-label">Başlık 1</label>
                    <input type="text" class="form-control" id="title_1" name="title_1" placeholder="Başlık 1'i girin" value="<?= htmlspecialchars($row['title_1']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="title_2" class="form-label">Başlık 2</label>
                    <input type="text" class="form-control" id="title_2" name="title_2" placeholder="Başlık 2'yi girin" value="<?= htmlspecialchars($row['title_2']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="content_1" class="form-label">İçerik 1</label>
                    <textarea class="form-control" id="content_1" name="content_1" rows="4" placeholder="İçerik 1'i girin" required><?= htmlspecialchars($row['content_1']) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label for="content_2" class="form-label">İçerik 2</label>
                    <textarea class="form-control" id="content_2" name="content_2" rows="4" placeholder="İçerik 2'yi girin" required><?= htmlspecialchars($row['content_2']) ?></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
