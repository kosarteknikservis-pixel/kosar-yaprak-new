<?php
require '../db.php';
require 'auth.php';

$page_title = 'Kullanıcı Giriş Logları';

// Sayfalama
$limit = 50;
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($page - 1) * $limit;

// Toplam log sayısı
$total_logs = $pdo->query('SELECT COUNT(*) FROM login_logs')->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Logları getir
$stmt = $pdo->prepare('SELECT * FROM login_logs ORDER BY login_time DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$login_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı giriş istatistikleri
$user_stats = $pdo->query('
    SELECT username, COUNT(*) as login_count,
           MAX(login_time) as last_login,
           SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_logins,
           SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_logins
    FROM login_logs
    GROUP BY username
    ORDER BY login_count DESC
')->fetchAll(PDO::FETCH_ASSOC);

// Genel istatistikler
$general_stats = [
    'total_logs' => $total_logs,
    'successful_logins' => $pdo->query("SELECT COUNT(*) FROM login_logs WHERE success = 1")->fetchColumn(),
    'failed_logins' => $pdo->query("SELECT COUNT(*) FROM login_logs WHERE success = 0")->fetchColumn(),
    'unique_users' => $pdo->query("SELECT COUNT(DISTINCT username) FROM login_logs")->fetchColumn(),
    'today_logs' => $pdo->query("SELECT COUNT(*) FROM login_logs WHERE DATE(login_time) = CURDATE()")->fetchColumn()
];

?>
<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <!-- Üst Bar -->
    <div class="top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bar-left">
                <h2 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Kullanıcı Giriş Logları</h2>
                <p class="text-muted mb-0">Sistem giriş loglarını görüntüleyin ve analiz edin</p>
            </div>
            <div class="bar-right">
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= $general_stats['total_logs'] ?> Log</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Genel İstatistikler -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $general_stats['total_logs'] ?></h3>
                    <p>Toplam Log</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $general_stats['successful_logins'] ?></h3>
                    <p>Başarılı</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $general_stats['failed_logins'] ?></h3>
                    <p>Başarısız</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $general_stats['unique_users'] ?></h3>
                    <p>Kullanıcı</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $general_stats['today_logs'] ?></h3>
                    <p>Bugün</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kullanıcı İstatistikleri -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-chart"></i> Kullanıcı Giriş İstatistikleri
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-user-stats">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Kullanıcı</th>
                            <th style="width: 15%;">Toplam Giriş</th>
                            <th style="width: 15%;">Başarılı</th>
                            <th style="width: 15%;">Başarısız</th>
                            <th style="width: 20%;">Son Giriş</th>
                            <th style="width: 15%;">Başarı Oranı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_stats as $user): ?>
                            <?php
                            $success_rate = $user['login_count'] > 0 ? round(($user['successful_logins'] / $user['login_count']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $user['login_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?= $user['successful_logins'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-danger"><?= $user['failed_logins'] ?></span>
                                </td>
                                <td>
                                    <small><?= date('d.m.Y H:i', strtotime($user['last_login'])) ?></small>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $success_rate >= 80 ? 'bg-success' : ($success_rate >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                             style="width: <?= $success_rate ?>%">
                                            <?= $success_rate ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detaylı Loglar -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> Detaylı Giriş Logları
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-logs">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Kullanıcı</th>
                            <th style="width: 10%;">IP Adresi</th>
                            <th style="width: 8%;">Ülke</th>
                            <th style="width: 8%;">Şehir</th>
                            <th style="width: 12%;">Giriş Zamanı</th>
                            <th style="width: 8%;">Durum</th>
                            <th style="width: 42%;">Mesaj</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_logs as $log): ?>
                            <tr class="<?= $log['success'] ? 'table-success' : 'table-danger' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($log['username']) ?></strong>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($log['ip_address']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($log['country']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($log['city']) ?></span>
                                </td>
                                <td>
                                    <small><?= date('d.m.Y H:i:s', strtotime($log['login_time'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($log['success']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Başarılı
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times"></i> Başarısız
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="message-preview" title="<?= htmlspecialchars($log['message']) ?>">
                                        <?= htmlspecialchars(substr($log['message'], 0, 60)) ?><?= strlen($log['message']) > 60 ? '...' : '' ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="container">
            <div class="card">
                <div class="card-body py-2">
                    <?php
                    require_once __DIR__ . '/../includes/admin_pagination.php';
                    admin_render_pagination($page, (int) $total_pages, static function (int $p): string {
                        return '?' . http_build_query(array_merge($_GET, ['page' => $p]));
                    });
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
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

/* Log Tabloları */
.table-user-stats,
.table-logs {
    table-layout: fixed;
    width: 100%;
}

.table-user-stats th,
.table-user-stats td,
.table-logs th,
.table-logs td {
    font-size: 13px;
    padding: 12px 8px;
    vertical-align: middle;
}

.message-preview {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: help;
}

.progress {
    background-color: #e9ecef;
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    font-size: 11px;
    line-height: 20px;
}
</style>

<?php include 'admin_footer_common.php'; ?>
