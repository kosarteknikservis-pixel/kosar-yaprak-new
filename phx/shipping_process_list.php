<?php
require '../db.php';
require 'auth.php';

$sql = "SELECT * FROM shipping_process ORDER BY id DESC";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Kargo Süreçleri Listesi';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-list me-2"></i>Kargo Süreçleri</h2>
            <a href="add_shipping_process.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Yeni Kayıt Ekle
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (count($rows) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">Görsel</th>
                                <th width="60%">İçerik</th>
                                <th width="20%">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $row['id'] ?></span></td>
                                    <td>
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="<?= htmlspecialchars($row['image']) ?>" alt="Görsel" class="img-thumbnail" style="max-width: 100px; max-height: 80px;" onerror="this.src='../uploads/txrik.gif'">
                                        <?php else: ?>
                                            <span class="text-muted">Görsel yok</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="content-preview">
                                            <div class="mb-2">
                                                <strong class="text-primary"><?= htmlspecialchars($row['title_1']) ?></strong>
                                                <p class="mb-1 small text-muted"><?= htmlspecialchars(substr($row['content_1'], 0, 80)) ?><?= strlen($row['content_1']) > 80 ? '...' : '' ?></p>
                                            </div>
                                            <div class="mb-2">
                                                <strong class="text-primary"><?= htmlspecialchars($row['title_2']) ?></strong>
                                                <p class="mb-1 small text-muted"><?= htmlspecialchars(substr($row['content_2'], 0, 80)) ?><?= strlen($row['content_2']) > 80 ? '...' : '' ?></p>
                                            </div>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($row['title_3']) ?></strong>
                                                <p class="mb-1 small text-muted"><?= htmlspecialchars(substr($row['content_3'], 0, 80)) ?><?= strlen($row['content_3']) > 80 ? '...' : '' ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                            <a href="edit_shipping_process.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i> Düzenle
                                            </a>
                                            <a href="delete_shipping_process.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kaydı silmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-trash me-1"></i> Sil
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Henüz kayıt bulunmuyor</h5>
                    <p class="text-muted">İlk kargo sürecini eklemek için yukarıdaki butonu kullanın.</p>
                    <a href="add_shipping_process.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Yeni Kayıt Ekle
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.content-preview {
    max-height: 200px;
    overflow-y: auto;
}
</style>

<?php include 'admin_footer_common.php'; ?>
