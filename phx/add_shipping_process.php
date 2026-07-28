<?php
require '../db.php';
require 'auth.php';

// Kaydetme işlemi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $pdo->query("SELECT COUNT(*) FROM shipping_process");
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $_SESSION['message'] = 'Zaten bir kargo süreci kaydı mevcut, yeni kayıt eklenemez. Lütfen güncel olanı düzenleyin veya silin.';
        $_SESSION['message_type'] = 'error';
    } else {
        $image = trim($_POST['image'] ?? '');
        $title_1 = trim($_POST['title_1'] ?? '');
        $content_1 = trim($_POST['content_1'] ?? '');
        $title_2 = trim($_POST['title_2'] ?? '');
        $content_2 = trim($_POST['content_2'] ?? '');
        $title_3 = trim($_POST['title_3'] ?? '');
        $content_3 = trim($_POST['content_3'] ?? '');

        $sql = "INSERT INTO shipping_process (image, title_1, content_1, title_2, content_2, title_3, content_3) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([$image, $title_1, $content_1, $title_2, $content_2, $title_3, $content_3]);
            $_SESSION['message'] = 'Kayıt başarıyla eklendi!';
            $_SESSION['message_type'] = 'success';
            header('Location: shipping_process_list.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }
}

$page_title = 'Kargo Süreci Ekle';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Kargo Süreci Ekle</h2>
            <a href="shipping_process_list.php" class="btn btn-outline-primary">
                <i class="fas fa-list me-1"></i> Kayıtlı Süreçleri Gör
            </a>
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
                    <input type="url" class="form-control" id="image" name="image" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($_POST['image'] ?? '') ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="title_1" class="form-label">Başlık 1</label>
                    <input type="text" class="form-control" id="title_1" name="title_1" placeholder="Başlık 1'i girin" value="<?= htmlspecialchars($_POST['title_1'] ?? '') ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="title_2" class="form-label">Başlık 2</label>
                    <input type="text" class="form-control" id="title_2" name="title_2" placeholder="Başlık 2'yi girin" value="<?= htmlspecialchars($_POST['title_2'] ?? '') ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="title_3" class="form-label">Başlık 3</label>
                    <input type="text" class="form-control" id="title_3" name="title_3" placeholder="Başlık 3'ü girin" value="<?= htmlspecialchars($_POST['title_3'] ?? '') ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="content_1" class="form-label">İçerik 1</label>
                    <textarea class="form-control" id="content_1" name="content_1" rows="4" placeholder="İçerik 1'i girin" required><?= htmlspecialchars($_POST['content_1'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label for="content_2" class="form-label">İçerik 2</label>
                    <textarea class="form-control" id="content_2" name="content_2" rows="4" placeholder="İçerik 2'yi girin" required><?= htmlspecialchars($_POST['content_2'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label for="content_3" class="form-label">İçerik 3</label>
                    <textarea class="form-control" id="content_3" name="content_3" rows="4" placeholder="İçerik 3'ü girin" required><?= htmlspecialchars($_POST['content_3'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
