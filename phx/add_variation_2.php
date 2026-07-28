<?php
require '../db.php';
require 'auth.php';


$stmt = $pdo->query("SELECT * FROM product_variations_2");
$variations = $stmt->fetchAll();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variation'])) {
    $variation_id = $_POST['variation_id'];
    $variation_name = $_POST['variation_name'];
    $show_variation = isset($_POST['show_variation']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE product_variations_2 SET variation_name = ?, show_variation = ? WHERE variation_id = ?");
    if ($stmt->execute([$variation_name, $show_variation, $variation_id])) {
        $message = 'Varyasyon başarıyla güncellendi!';
    } else {
        $message = 'Güncelleme sırasında bir hata oluştu.';
    }
}

if (isset($_GET['toggle_visibility'])) {
    $variation_id = $_GET['toggle_visibility'];
    $current_status = $_GET['current_status'];

    $new_status = $current_status == 1 ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE product_variations_2 SET show_variation = ? WHERE variation_id = ?");
    $stmt->execute([$new_status, $variation_id]);
    header("Location: add_variation_2.php");
    exit();
}

$page_title = 'Varyasyon 2 ayarları';
include 'admin_header.php';
?>

<div class="container-fluid px-0">
    <h1 class="h4 mb-3"><i class="fas fa-sliders-h me-2 text-muted"></i>Varyasyon 2 ayarları</h1>

    <?php if ($message): ?>
        <div class="alert alert-info mb-3"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <ul class="list-group mb-4">
        <?php foreach ($variations as $variation): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                <?= htmlspecialchars($variation['variation_name']) ?>
                <span>
                    <a href="edit_variation_2.php?variation_id=<?= $variation['variation_id'] ?>" class="btn btn-outline-secondary btn-sm">Varyant ekle</a>
                    <button type="button" class="btn btn-neutral btn-sm" onclick="openEditModal2('<?= $variation['variation_id'] ?>', '<?= htmlspecialchars($variation['variation_name'], ENT_QUOTES) ?>', <?= $variation['show_variation'] ?>)">Adı düzenle</button>
                    <?php if ($variation['show_variation'] == 1): ?>
                        <a href="?toggle_visibility=<?= $variation['variation_id'] ?>&current_status=1" class="btn btn-outline-warning btn-sm">Gizle</a>
                    <?php else: ?>
                        <a href="?toggle_visibility=<?= $variation['variation_id'] ?>&current_status=0" class="btn btn-outline-success btn-sm">Göster</a>
                    <?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="modal fade" id="editModal2" tabindex="-1" aria-labelledby="editModal2Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModal2Label">Varyasyon düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" id="variation_id_2" name="variation_id">
                        <div class="mb-3">
                            <label for="variation_name_2" class="form-label">Varyasyon adı</label>
                            <input type="text" class="form-control" id="variation_name_2" name="variation_name" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="show_variation_2" name="show_variation">
                            <label class="form-check-label" for="show_variation_2">Varyasyonu göster</label>
                        </div>
                        <button type="submit" class="btn btn-primary" name="update_variation" value="1">Kaydet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openEditModal2(variationId, variationName, showVariation) {
    document.getElementById('variation_id_2').value = variationId;
    document.getElementById('variation_name_2').value = variationName;
    document.getElementById('show_variation_2').checked = showVariation == 1;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal2')).show();
}
</script>

<?php include 'admin_footer_common.php'; ?>
