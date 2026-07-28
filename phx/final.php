<?php
require '../db.php';
require 'auth.php';

// Tarih filtreleme
$period = $_GET['period'] ?? 'today';

date_default_timezone_set('Europe/Istanbul');

// Tarih aralığını belirle
switch($period) {
    case 'today':
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $end_date = date('Y-m-d 23:59:59', strtotime('-1 day'));
        break;
    case 'this_week':
        $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $end_date = date('Y-m-d 23:59:59');
        break;
    case 'this_month':
        $start_date = date('Y-m-01 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        break;
    case 'last_month':
        $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;
    default:
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
}

// SQL sorgusu
$sql = "
    SELECT
        o.updated_by,
        COUNT(DISTINCT o.order_id) as total_orders,
        COUNT(DISTINCT CASE
            WHEN o.order_status_id IN (2, 3, 16) THEN o.order_id
            ELSE NULL
        END) as successful_orders,
        COALESCE(SUM(CASE
            WHEN o.order_status_id IN (2, 3, 16)
            THEN oi.price
            ELSE 0
        END), 0) as successful_revenue
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.edit_order_date BETWEEN :start_date AND :end_date
    AND o.updated_by IS NOT NULL
    AND o.order_status_id != 19
    GROUP BY o.updated_by
    HAVING total_orders > 0
    ORDER BY successful_orders DESC, successful_revenue DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    $performance_data = [];
}

$page_title = 'Performans Raporu';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-trophy me-2"></i>Performans Raporu</h2>
            <span class="text-muted">Kullanıcı performans analizi</span>
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
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Zaman Filtresi</h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="?period=today" class="btn <?= $period == 'today' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-day me-1"></i> Bugün
                </a>
                <a href="?period=yesterday" class="btn <?= $period == 'yesterday' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-minus me-1"></i> Dün
                </a>
                <a href="?period=this_week" class="btn <?= $period == 'this_week' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-week me-1"></i> Bu Hafta
                </a>
                <a href="?period=this_month" class="btn <?= $period == 'this_month' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-alt me-1"></i> Bu Ay
                </a>
                <a href="?period=last_month" class="btn <?= $period == 'last_month' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar me-1"></i> Geçen Ay
                </a>
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-undo me-1"></i> Sıfırla
                </a>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tarih Aralığı</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                <i class="fas fa-calendar-check me-2"></i>
                <strong>Seçilen Dönem:</strong>
                <?= date('d.m.Y H:i', strtotime($start_date)) ?> - <?= date('d.m.Y H:i', strtotime($end_date)) ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Önemli Notlar</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning mb-0">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="mb-0">
                            <li><strong>Mükerrer Siparişler:</strong> Bu rapora yansıtılmamaktadır.</li>
                            <li><strong>Başarılı Siparişler:</strong> Onaylandı, Kargoya Verildi ve Teslim Edildi durumundaki siparişlerdir.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="mb-0">
                            <li><strong>Sıralama:</strong> Başarılı sipariş sayısına göre yapılmaktadır.</li>
                            <li><strong>Başarı Oranı:</strong> (Başarılı Sipariş / Toplam Sipariş) × 100</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Performans Tablosu</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($performance_data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Seçilen dönemde veri bulunamadı</h5>
                    <p class="text-muted">Farklı bir zaman aralığı seçmeyi deneyin.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Sıra</th>
                                <th>Kullanıcı</th>
                                <th class="text-center">Toplam Sipariş</th>
                                <th class="text-center">Başarılı Sipariş</th>
                                <th class="text-center">Başarı Oranı</th>
                                <th class="text-end">Ciro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($performance_data as $index => $data): ?>
                                <tr <?= $index === 0 ? 'class="table-warning fw-bold"' : '' ?>>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($data['updated_by']) ?></td>
                                    <td class="text-center"><?= number_format((float)($data['total_orders'] ?? 0)) ?></td>
                                    <td class="text-center"><?= number_format((float)($data['successful_orders'] ?? 0)) ?></td>
                                    <td class="text-center">
                                        <?php
                                        $success_rate = ($data['successful_orders'] / $data['total_orders']) * 100;
                                        ?>
                                        %<?= number_format($success_rate, 1) ?>
                                    </td>
                                    <td class="text-end"><?= number_format((float)($data['successful_revenue'] ?? 0), 2, ',', '.') ?> TL</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
