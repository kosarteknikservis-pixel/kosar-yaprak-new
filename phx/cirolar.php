<?php
require_once '../db.php';
require 'auth.php';

// Tarih filtreleme
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));

// Input sanitization
$start_date = htmlspecialchars((string)$start_date, ENT_QUOTES, 'UTF-8');
$end_date = htmlspecialchars((string)$end_date, ENT_QUOTES, 'UTF-8');

// SQL sorgusu
$sql = "SELECT
            DATE(o.order_date) as sale_date,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(oi.price) as daily_revenue,
            COUNT(DISTINCT CASE WHEN o.order_status_id = 3 THEN o.order_id END) as confirmed_orders,
            SUM(CASE WHEN o.order_status_id = 3 THEN oi.price ELSE 0 END) as confirmed_revenue,
            COUNT(DISTINCT CASE WHEN o.order_status_id = 16 THEN o.order_id END) as shipped_orders,
            SUM(CASE WHEN o.order_status_id = 16 THEN oi.price ELSE 0 END) as shipped_revenue
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_date BETWEEN :start_date AND :end_date
        AND o.order_status_id != 19
        GROUP BY DATE(o.order_date)
        ORDER BY sale_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    $results = [];
}

$page_title = 'Günlük Ciro Raporu';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="admin-page-intro mb-3">
        <h1><i class="fas fa-chart-line text-success"></i> Günlük ciro raporu</h1>
        <p class="lead">Tüm tutarlar Türk Lirası (₺) — ondalık ayırıcı virgül, binlik ayırıcı nokta.</p>
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
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-1"></i> Filtrele
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-undo me-1"></i> Sıfırla
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Önemli Not</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                <div class="d-flex align-items-start">
                    <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                    <div>
                        <strong>Mükerrer Siparişler:</strong>
                        <p class="mb-0">Mükerrer olan siparişler bu rapora yansıtılmamaktadır. Bir siparişin mükerrer sayılması için, yönetim panelinden ilgili sipariş/siparişlerin seçilip "mükerrer" olarak işaretlenmesi gerekmektedir.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Ciro Raporu</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($results)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Seçilen tarih aralığında veri bulunamadı</h5>
                    <p class="text-muted">Farklı bir tarih aralığı seçmeyi deneyin.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0 admin-table-readable">
                        <thead class="table-dark">
                            <tr>
                                <th>Tarih</th>
                                <th class="text-end">Toplam Sipariş</th>
                                <th class="text-end">Toplam Ciro</th>
                                <th class="text-end">Onaylanan Sipariş</th>
                                <th class="text-end">Onaylanan Ciro</th>
                                <th class="text-end">Teslim Edilen Sipariş</th>
                                <th class="text-end">Teslim Edilen Ciro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_revenue = 0;
                            $total_confirmed_revenue = 0;
                            $total_shipped_revenue = 0;
                            $total_orders = 0;
                            $total_confirmed_orders = 0;
                            $total_shipped_orders = 0;

                            foreach ($results as $row):
                                $total_revenue += $row['daily_revenue'];
                                $total_confirmed_revenue += $row['confirmed_revenue'];
                                $total_shipped_revenue += $row['shipped_revenue'];
                                $total_orders += $row['total_orders'];
                                $total_confirmed_orders += $row['confirmed_orders'];
                                $total_shipped_orders += $row['shipped_orders'];
                            ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($row['sale_date'])) ?></td>
                                    <td class="text-end"><?= admin_tr_number((float) ($row['total_orders'] ?? 0)) ?></td>
                                    <td class="text-end"><?= admin_tr_money((float) ($row['daily_revenue'] ?? 0)) ?></td>
                                    <td class="text-end"><?= admin_tr_number((float) ($row['shipped_orders'] ?? 0)) ?></td>
                                    <td class="text-end"><?= admin_tr_money((float) ($row['shipped_revenue'] ?? 0)) ?></td>
                                    <td class="text-end"><?= admin_tr_number((float) ($row['confirmed_orders'] ?? 0)) ?></td>
                                    <td class="text-end"><?= admin_tr_money((float) ($row['confirmed_revenue'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th>TOPLAM</th>
                                <th class="text-end"><?= admin_tr_number((float) ($total_orders ?? 0)) ?></th>
                                <th class="text-end"><?= admin_tr_money((float) ($total_revenue ?? 0)) ?></th>
                                <th class="text-end"><?= admin_tr_number((float) ($total_shipped_orders ?? 0)) ?></th>
                                <th class="text-end"><?= admin_tr_money((float) ($total_shipped_revenue ?? 0)) ?></th>
                                <th class="text-end"><?= admin_tr_number((float) ($total_confirmed_orders ?? 0)) ?></th>
                                <th class="text-end"><?= admin_tr_money((float) ($total_confirmed_revenue ?? 0)) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
