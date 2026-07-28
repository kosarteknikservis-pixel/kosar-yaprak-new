<?php
require '../db.php';
require 'auth.php';

// Tarih filtreleme
$date_filter = $_GET['date_filter'] ?? 'today';
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';

// Tarih aralığını belirle
if ($date_filter == 'custom' && !empty($custom_start) && !empty($custom_end)) {
    $start_date = $custom_start;
    $end_date = $custom_end;
} else {
    $end_date = date('Y-m-d');
    switch($date_filter) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        default:
            $start_date = date('Y-m-d');
            break;
    }
}

// SQL sorgusu
$sql = "
    SELECT
        COALESCE(o.updated_by, 'TOPLAM') as updated_by,
        COUNT(*) as total_orders,
        SUM(CASE WHEN o.order_status_id = 1 THEN 1 ELSE 0 END) as beklemede,
        SUM(CASE WHEN o.order_status_id = 7 THEN 1 ELSE 0 END) as arandi,
        SUM(CASE WHEN o.order_status_id = 12 THEN 1 ELSE 0 END) as arandi_2,
        SUM(CASE WHEN o.order_status_id = 16 THEN 1 ELSE 0 END) as onaylandi,
        SUM(CASE WHEN o.order_status_id = 2 THEN 1 ELSE 0 END) as kargoya_verildi,
        SUM(CASE WHEN o.order_status_id = 3 THEN 1 ELSE 0 END) as teslim_edildi,
        SUM(CASE WHEN o.order_status_id = 13 THEN 1 ELSE 0 END) as ulasilamadi,
        SUM(CASE WHEN o.order_status_id = 17 THEN 1 ELSE 0 END) as ulasilamadi_2,
        SUM(CASE WHEN o.order_status_id = 15 THEN 1 ELSE 0 END) as iade,
        SUM(CASE WHEN o.order_status_id = 14 THEN 1 ELSE 0 END) as iptal,
        SUM(CASE WHEN o.order_status_id = 18 THEN 1 ELSE 0 END) as randevu,
        SUM(CASE WHEN o.order_status_id = 19 THEN 1 ELSE 0 END) as mukerrer
    FROM orders o
    WHERE DATE(o.order_date) BETWEEN :start_date AND :end_date
    GROUP BY o.updated_by WITH ROLLUP
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    $results = [];
}

$page_title = 'Kullanıcı Sipariş İstatistikleri';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Kullanıcı Sipariş İstatistikleri</h2>
            <span class="text-muted">Sipariş durumları ve yüzdeleri</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] === 'error' ? 'danger' : 'success' ?> d-flex align-items-center">
            <i class="fas fa-<?= $_SESSION['message_type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Tarih Filtresi</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="date_filter" class="form-label">Hazır Filtreler</label>
                    <select name="date_filter" id="date_filter" class="form-select" onchange="toggleCustomDates(this.value)">
                        <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Bugün</option>
                        <option value="week" <?= $date_filter == 'week' ? 'selected' : '' ?>>Bu Hafta</option>
                        <option value="month" <?= $date_filter == 'month' ? 'selected' : '' ?>>Bu Ay</option>
                        <option value="custom" <?= $date_filter == 'custom' ? 'selected' : '' ?>>Özel Tarih Aralığı</option>
                    </select>
                </div>

                <div id="customDateInputs" class="col-md-6" style="display: <?= $date_filter == 'custom' ? 'block' : 'none' ?>;">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="custom_start" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" name="custom_start" id="custom_start" class="form-control" value="<?= htmlspecialchars($custom_start) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="custom_end" class="form-label">Bitiş Tarihi</label>
                            <input type="date" name="custom_end" id="custom_end" class="form-control" value="<?= htmlspecialchars($custom_end) ?>">
                        </div>
                    </div>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filtrele
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>İstatistik Tablosu</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($results)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Seçilen tarih aralığında veri bulunamadı</h5>
                    <p class="text-muted">Farklı bir tarih aralığı seçmeyi deneyin.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Kullanıcı</th>
                                <th class="text-center">Toplam</th>
                                <th class="text-center">Beklemede</th>
                                <th class="text-center">Arandı</th>
                                <th class="text-center">Arandı 2</th>
                                <th class="text-center">Onaylandı</th>
                                <th class="text-center">Kargoya Verildi</th>
                                <th class="text-center">Teslim Edildi</th>
                                <th class="text-center">Ulaşılamadı</th>
                                <th class="text-center">Ulaşılamadı 2</th>
                                <th class="text-center">İade</th>
                                <th class="text-center">İptal</th>
                                <th class="text-center">Randevu</th>
                                <th class="text-center">Mükerrer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr <?= $row['updated_by'] === 'TOPLAM' ? 'class="table-primary fw-bold"' : '' ?>>
                                    <td>
                                        <?php if ($row['updated_by'] === 'TOPLAM'): ?>
                                            <i class="fas fa-calculator me-2 text-primary"></i>
                                            <strong><?= htmlspecialchars($row['updated_by']) ?></strong>
                                        <?php else: ?>
                                            <i class="fas fa-user me-2 text-muted"></i>
                                            <?= htmlspecialchars($row['updated_by']) ?>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Toplam -->
                                    <td class="text-center">
                                        <span class="fw-bold text-primary"><?= number_format((float)($row['total_orders'] ?? 0)) ?></span>
                                    </td>

                                    <!-- Beklemede -->
                                    <td class="text-center">
                                        <?php if (($row['beklemede'] ?? 0) > 0): ?>
                                            <span class="badge bg-warning text-dark"><?= number_format((float)($row['beklemede'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['beklemede'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Arandı -->
                                    <td class="text-center">
                                        <?php if (($row['arandi'] ?? 0) > 0): ?>
                                            <span class="badge bg-info"><?= number_format((float)($row['arandi'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['arandi'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Arandı 2 -->
                                    <td class="text-center">
                                        <?php if (($row['arandi_2'] ?? 0) > 0): ?>
                                            <span class="badge bg-info"><?= number_format((float)($row['arandi_2'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['arandi_2'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Onaylandı -->
                                    <td class="text-center">
                                        <?php if (($row['onaylandi'] ?? 0) > 0): ?>
                                            <span class="badge bg-success"><?= number_format((float)($row['onaylandi'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['onaylandi'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kargoya Verildi -->
                                    <td class="text-center">
                                        <?php if (($row['kargoya_verildi'] ?? 0) > 0): ?>
                                            <span class="badge bg-primary"><?= number_format((float)($row['kargoya_verildi'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['kargoya_verildi'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Teslim Edildi -->
                                    <td class="text-center">
                                        <?php if (($row['teslim_edildi'] ?? 0) > 0): ?>
                                            <span class="badge bg-success"><?= number_format((float)($row['teslim_edildi'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['teslim_edildi'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Ulaşılamadı -->
                                    <td class="text-center">
                                        <?php if (($row['ulasilamadi'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger"><?= number_format((float)($row['ulasilamadi'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['ulasilamadi'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Ulaşılamadı 2 -->
                                    <td class="text-center">
                                        <?php if (($row['ulasilamadi_2'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger"><?= number_format((float)($row['ulasilamadi_2'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['ulasilamadi_2'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- İade -->
                                    <td class="text-center">
                                        <?php if (($row['iade'] ?? 0) > 0): ?>
                                            <span class="badge bg-warning text-dark"><?= number_format((float)($row['iade'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['iade'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- İptal -->
                                    <td class="text-center">
                                        <?php if (($row['iptal'] ?? 0) > 0): ?>
                                            <span class="badge bg-secondary"><?= number_format((float)($row['iptal'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['iptal'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Randevu -->
                                    <td class="text-center">
                                        <?php if (($row['randevu'] ?? 0) > 0): ?>
                                            <span class="badge bg-info"><?= number_format((float)($row['randevu'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['randevu'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Mükerrer -->
                                    <td class="text-center">
                                        <?php if (($row['mukerrer'] ?? 0) > 0): ?>
                                            <span class="badge bg-dark"><?= number_format((float)($row['mukerrer'] ?? 0)) ?></span>
                                            <small class="d-block text-muted"><?= ($row['total_orders'] ?? 0) > 0 ? round(($row['mukerrer'] / $row['total_orders']) * 100, 1) : 0 ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCustomDates(value) {
    const customDateInputs = document.getElementById('customDateInputs');
    if (value === 'custom') {
        customDateInputs.style.display = 'block';
    } else {
        customDateInputs.style.display = 'none';
    }
}

// Sayfa yüklendiğinde mevcut seçime göre tarih alanlarını göster/gizle
document.addEventListener('DOMContentLoaded', function() {
    toggleCustomDates(document.getElementById('date_filter').value);
});
</script>

<?php include 'admin_footer_common.php'; ?>
