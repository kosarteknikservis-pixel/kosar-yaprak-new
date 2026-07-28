<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/site_helpers.php';

date_default_timezone_set('Europe/Istanbul');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order_guards'])) {
    try {
        $pdo->prepare(
            'UPDATE checkout_module_settings SET
                order_cookie_gate_enabled = ?,
                order_cookie_seconds = ?,
                order_dupe_server_enabled = ?,
                order_dupe_window_seconds = ?
             WHERE id = 1'
        )->execute([
            isset($_POST['order_cookie_gate_enabled']) && (string) $_POST['order_cookie_gate_enabled'] === '1' ? 1 : 0,
            max(60, min(2592000, (int) ($_POST['order_cookie_seconds'] ?? 60))),
            isset($_POST['order_dupe_server_enabled']) && (string) $_POST['order_dupe_server_enabled'] === '1' ? 1 : 0,
            max(120, min(2592000, (int) ($_POST['order_dupe_window_seconds'] ?? 86400))),
        ]);
        header('Location: index.php?guard_saved=1');

    } catch (Throwable $e) {

        header('Location: index.php?guard_saved=0');

    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_corporate_checkout'])) {
    $en = isset($_POST['corporate_invoice_enabled']) && (string)$_POST['corporate_invoice_enabled'] === '1' ? 1 : 0;
    try {
        $pdo->prepare('UPDATE checkout_module_settings SET corporate_invoice_enabled = ? WHERE id = 1')->execute([$en]);
        header('Location: index.php?corp_saved=1');
    } catch (Throwable $e) {
        header('Location: index.php?corp_saved=0');
    }
    exit;
}

$page_title = 'Dashboard';
$admin_vitrin_href = site_public_vitrin_href($pdo);

require_once dirname(__DIR__) . '/includes/admin_dashboard_stats.php';
require_once dirname(__DIR__) . '/includes/admin_quick_menu.php';
extract(admin_dashboard_load_stats($pdo));

/** @var array<int,array<string,mixed>> $dashNotes */
$dashNotes = [];
try {
    $dashNotes = $pdo->query('SELECT id, note_text, pinned FROM admin_quick_notes ORDER BY pinned DESC, sort_order DESC, id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    $dashNotes = [];
}

$cc_pending_today = (int) $pdo->query(
    "SELECT COUNT(*) FROM orders o JOIN order_status s ON o.order_status_id = s.order_status_id
     WHERE s.status_name = 'Beklemede' AND DATE(o.order_date) = CURDATE()"
)->fetchColumn();
$cc_abandoned_today = 0;
$cc_support_open = 0;
try {
    $cc_abandoned_today = (int) $pdo->query(
        "SELECT COUNT(*) FROM yarim_kalanlar WHERE DATE(tarih) = CURDATE() AND tel IS NOT NULL AND TRIM(tel) != ''"
    )->fetchColumn();
} catch (Throwable $e) {
    $cc_abandoned_today = 0;
}
try {
    $cc_support_open = (int) $pdo->query(
        "SELECT COUNT(*) FROM support_requests WHERE status IS NULL OR status = '' OR status = 'Beklemede' OR status = 'Yeni'"
    )->fetchColumn();
} catch (Throwable $e) {
    $cc_support_open = 0;
}

$admin_quick_menu_items = admin_quick_menu_items($admin_vitrin_href);

// Kullanıcı bilgileri
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$profile_image = '../uploads/' . $user['profile_image'];
if (!$user['profile_image'] || !file_exists($profile_image)) {
    $profile_image = '../uploads/default_profile.png';
}

$corpCheckoutSaved = isset($_GET['corp_saved']) ? $_GET['corp_saved'] : null;
$guardSaved = isset($_GET['guard_saved']) ? $_GET['guard_saved'] : null;
$checkoutSettings = [
    'corporate_invoice_enabled' => 0,
    'order_cookie_gate_enabled' => 1,
    'order_cookie_seconds' => 60,
    'order_dupe_server_enabled' => 1,
    'order_dupe_window_seconds' => 86400,

];
try {
    $crow = $pdo->query(
        'SELECT corporate_invoice_enabled, COALESCE(order_cookie_gate_enabled,1) AS ocge, COALESCE(order_cookie_seconds,60) AS ocs,
            COALESCE(order_dupe_server_enabled,1) AS odse, COALESCE(order_dupe_window_seconds,86400) AS odws
            FROM checkout_module_settings WHERE id = 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($crow) {

        $checkoutSettings['corporate_invoice_enabled'] = (int) $crow['corporate_invoice_enabled'];
        $checkoutSettings['order_cookie_gate_enabled'] = (int) $crow['ocge'];
        $checkoutSettings['order_cookie_seconds'] = (int) $crow['ocs'];
        $checkoutSettings['order_dupe_server_enabled'] = (int) $crow['odse'];
        $checkoutSettings['order_dupe_window_seconds'] = (int) $crow['odws'];

    }

} catch (Throwable $e) {
    // tablo ilk kurulumda oluşsun
}

?>

<?php include 'admin_header.php'; ?>

<main class="admin-dash">
<div class="container-fluid px-0">
    <div class="admin-dash-intro">
        <div class="intro-text">
            <p class="intro-kicker mb-1">Genel Özet</p>
            <h1 class="intro-title mb-1">Hoş geldin, <?= htmlspecialchars($user['username']); ?></h1>
            <p class="intro-sub text-muted mb-0">Bugünün siparişleri, cirolar ve mağaza trafiği</p>
        </div>
        <div class="intro-aside">
            <img src="<?= htmlspecialchars($profile_image); ?>" alt="" class="dash-avatar">
        </div>
    </div>

    <div class="cc-dash-actions">
        <a class="cc-dash-action" href="orders.php?status_name=Beklemede&amp;start_date=<?= date('Y-m-d') ?>&amp;end_date=<?= date('Y-m-d') ?>">
            <strong><?= $cc_pending_today ?></strong>
            <span>Bugün beklemede sipariş</span>
        </a>
        <a class="cc-dash-action" href="abandoned_orders.php?today=1&amp;phone=1">
            <strong><?= $cc_abandoned_today ?></strong>
            <span>Bugün yarım kalan (telefonlu)</span>
        </a>
        <a class="cc-dash-action" href="admin_support.php">
            <strong><?= $cc_support_open ?></strong>
            <span>Açık destek talebi</span>
        </a>
        <a class="cc-dash-action" href="orders.php">
            <strong><i class="fas fa-search"></i></strong>
            <span>Sipariş listesi / telefon ara</span>
        </a>
    </div>

    <div class="admin-dash-notice mb-4" role="status">
        <i class="fas fa-info-circle admin-dash-notice__icon"></i>
        <div>
            <strong>Önemli</strong>
            <a href="sss.php" class="admin-dash-notice__link">SSS — lütfen okuyun</a>
        </div>
    </div>

    <?php if (isset($_GET['rbac']) && $_GET['rbac'] === 'denied'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            Bu sayfa veya menü için yetkiniz yok. Yöneticinizden ilgili bölüm iznini talep edin.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <?php if ($guardSaved !== null): ?>
        <div class="alert <?= $guardSaved === '1' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mb-3" role="alert">
            <?= $guardSaved === '1' ? 'Çerez ve tekrar sipariş koruma ayarları güncellendi.' : 'Ayar kaydedilemedi; veritabanını yenileyin veya daha sonra deneyin.' ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <?php if ($corpCheckoutSaved !== null): ?>
        <div class="alert <?= $corpCheckoutSaved === '1' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mb-3" role="alert">
            <?= $corpCheckoutSaved === '1' ? 'Kurumsal fatura ayarı güncellendi.' : 'Ayar kaydedilemedi; veritabanını yenileyin veya daha sonra deneyin.' ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <details class="dash-card admin-dash-corporate mb-4" <?= (int)$checkoutSettings['corporate_invoice_enabled'] === 1 ? 'open' : '' ?>>
        <summary class="admin-dash-corporate-summary">
            <span class="admin-dash-corporate-summary__title"><i class="fas fa-building"></i> Kurumsal fatura (sipariş formu)</span>
            <span class="admin-dash-corporate-summary__badge"><?= (int)$checkoutSettings['corporate_invoice_enabled'] === 1 ? 'Açık' : 'Kapalı' ?></span>
        </summary>
        <div class="card-body border-top">
            <p class="text-muted small mb-3">Panel yazılımındaki «Kurumsal / Fatura bilgileri» ile uyumlu: açıkken müşteri isteğe bağlı olarak vergi no, vergi dairesi, ünvan ve fatura adresi girebilir; sipariş detayında görünür.</p>
            <form method="post" action="">
                <input type="hidden" name="save_corporate_checkout" value="1">
                <div class="row align-items-end g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="corporate_invoice_enabled">Sipariş sayfasında alan gösterimi</label>
                        <select class="form-select" id="corporate_invoice_enabled" name="corporate_invoice_enabled">
                            <option value="0"<?= (int)$checkoutSettings['corporate_invoice_enabled'] === 0 ? ' selected' : '' ?>>Kapalı — fatura bilgisi istenmez</option>
                            <option value="1"<?= (int)$checkoutSettings['corporate_invoice_enabled'] === 1 ? ' selected' : '' ?>>Açık — tıklanınca açılan blokta isteğe bağlı alanlar</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
    </details>

    <details class="dash-card admin-dash-guard mb-4" open>
        <summary class="admin-dash-corporate-summary">
            <span class="admin-dash-corporate-summary__title"><i class="fas fa-shield-alt"></i> Sipariş koruması (çerez &amp; çift sipariş)</span>
            <span class="admin-dash-corporate-summary__badge"><?php
                $guardBits = [];
                $guardBits[] = (int) $checkoutSettings['order_dupe_server_enabled'] === 1 ? 'Sunucu aktif' : 'Sunucu pasif';
                $guardBits[] = (int) $checkoutSettings['order_cookie_gate_enabled'] === 1 ? 'Çerez aktif' : 'Çerez pasif';
                echo htmlspecialchars(implode(' · ', $guardBits), ENT_QUOTES, 'UTF-8');
            ?></span>
        </summary>
        <div class="card-body border-top">
            <p class="text-muted small mb-3">
                <strong>Tarayıcı çerezi</strong> ile kısa süreli tekrar tıklama engeli — çerez yüklü değilken yalnızca sunucu kuralları çalışır.
                <strong>Telefon ve IP</strong> üzerinden süreli ikinci sipariş engeli çerezden bağımsızdır (panel yazılımına benzer).
            </p>
            <form method="post" action="">
                <input type="hidden" name="save_order_guards" value="1">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="order_dupe_server_enabled" id="odse" value="1"
                                <?= (int) $checkoutSettings['order_dupe_server_enabled'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="odse">Sunucu tarafı ikinci sipariş engeli</label>
                        </div>
                        <label class="form-label small mb-0">Aynı telefon / IP ile tekrar süresi (saniye, örn: 86400 = 24 saat)</label>
                        <input type="number" name="order_dupe_window_seconds" class="form-control" min="120" max="2592000" step="60"
                            value="<?= (int) $checkoutSettings['order_dupe_window_seconds'] ?>">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="order_cookie_gate_enabled" id="ocge" value="1"
                                <?= (int) $checkoutSettings['order_cookie_gate_enabled'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ocge">«Bu tarayıcıdan yeni sipariş» çerez ile hızlı engel</label>
                        </div>
                        <label class="form-label small mb-0">Çerez süresi (saniye, min 60)</label>
                        <input type="number" name="order_cookie_seconds" class="form-control" min="60" max="2592000" step="60"
                            value="<?= (int) $checkoutSettings['order_cookie_seconds'] ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
    </details>

    <!-- Sipariş İstatistikleri -->
    <div class="dash-card admin-dash-panel mb-4">
        <div class="dash-panel-head">
            <i class="fas fa-shopping-cart"></i> Sipariş İstatistikleri
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $total_orders ?></h3>
                            <p>Toplam Sipariş</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($total_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $hourly_orders ?></h3>
                            <p>Saatlik Sipariş</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($hourly_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $daily_orders ?></h3>
                            <p>Günlük Sipariş</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($daily_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-minus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $yesterday_orders ?></h3>
                            <p>Dünkü Sipariş</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($yesterday_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $weekly_orders ?></h3>
                            <p>Haftalık Sipariş</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($weekly_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $monthly_orders ?></h3>
                            <p>Aylık Sipariş</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($monthly_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $approved_orders ?></h3>
                            <p>Onaylanmış</p>
                            <div class="stat-revenue">
                                <small><?= admin_tr_money($approved_revenue, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($dashNotes !== []): ?>
    <div class="dash-card admin-dash-panel mb-4">
        <div class="dash-panel-head d-flex justify-content-between align-items-center">
            <span><i class="fas fa-sticky-note text-warning"></i> Hızlı notlar</span>
            <a href="quick_notes.php" class="btn btn-sm btn-outline-secondary">Tümü</a>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($dashNotes as $dn): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 small h-100 <?= !empty((int)$dn['pinned']) ? 'border-warning' : '' ?>">
                            <?= nl2br(htmlspecialchars(mb_substr((string)$dn['note_text'], 0, 420))) ?><?= mb_strlen((string)$dn['note_text']) > 420 ? '…' : '' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hızlı Menü -->
    <div class="dash-card admin-dash-panel mb-4">
        <div class="dash-panel-head">
            <i class="fas fa-bars"></i> Hızlı Menü
        </div>
        <div class="card-body">
            <div class="quick-menu-grid">
                <?php foreach ($admin_quick_menu_items as $qmItem): ?>
                    <a href="<?= htmlspecialchars($qmItem['external'] ? (string) $qmItem['href'] : admin_url((string) $qmItem['href']), ENT_QUOTES, 'UTF-8') ?>"<?= $qmItem['external'] ? ' target="_blank" rel="noopener"' : '' ?>>
                        <i class="<?= htmlspecialchars((string) $qmItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        <span><?= htmlspecialchars((string) $qmItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Ciro İstatistikleri -->
    <div class="dash-card admin-dash-panel mb-4">
        <div class="dash-panel-head">
            <i class="fas fa-chart-line"></i> Ciro İstatistikleri
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_money($total_revenue, 0) ?></h3>
                            <p>Toplam Ciro</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_money($hourly_revenue, 0) ?></h3>
                            <p>Saatlik Ciro</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_money($daily_revenue, 0) ?></h3>
                            <p>Günlük Ciro</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_money($weekly_revenue, 0) ?></h3>
                            <p>Haftalık Ciro</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_money($monthly_revenue, 0) ?></h3>
                            <p>Aylık Ciro</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_percent($approval_rate) ?></h3>
                            <p>Onay Oranı</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ziyaretçi İstatistikleri -->
    <?php
    $hitMetric = static function (int $unique, int $total): string {
        return '<h3 class="hit-metric" data-unique="' . $unique . '" data-total="' . $total . '">'
            . number_format($unique, 0, ',', '.') . '</h3>';
    };
    ?>
    <div class="dash-card admin-dash-panel mb-4" id="visitorStatsPanel">
        <div class="dash-panel-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-users"></i> Ziyaretçi İstatistikleri</span>
            <div class="hit-mode-toggle btn-group btn-group-sm" role="group" aria-label="Sayım modu">
                <button type="button" class="btn active" data-hit-mode="unique" title="Benzersiz IP (erişim)"><i class="fas fa-user-check me-1"></i>Tekil</button>
                <button type="button" class="btn" data-hit-mode="total" title="Tüm gösterim (hit)"><i class="fas fa-eye me-1"></i>Toplam</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <?= $hitMetric((int) $daily_distinct_visitors_result['daily_distinct_visits'], (int) $daily_visitors_result['daily_visits']) ?>
                            <p>Günlük Ziyaretçi</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-content">
                            <?= $hitMetric((int) $weekly_distinct_visitors_result['weekly_distinct_visits'], (int) $weekly_visitors_result['weekly_visits']) ?>
                            <p>Haftalık Ziyaretçi</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <?= $hitMetric((int) $monthly_distinct_visitors_result['monthly_distinct_visits'], (int) $monthly_visitors_result['monthly_visits']) ?>
                            <p>Aylık Ziyaretçi</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <?= $hitMetric((int) $total_distinct_visitors_result['total_distinct_visits'], (int) $total_visitors_result['total_visits']) ?>
                            <p>Toplam Ziyaretçi</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $daily_orders_result['daily_orders'] ?></h3>
                            <p>Günlük Sipariş</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= admin_tr_percent($daily_orders_rate) ?></h3>
                            <p>Dönüşüm Oranı</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sayfa Analitikleri -->
    <div class="dash-card admin-dash-panel mb-4" id="pageAnalyticsPanel">
        <div class="dash-panel-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-chart-bar"></i> Sayfa Analitikleri</span>
            <div class="hit-mode-toggle btn-group btn-group-sm" role="group" aria-label="Sayım modu">
                <button type="button" class="btn active" data-hit-mode="unique" title="Benzersiz IP (erişim)"><i class="fas fa-user-check me-1"></i>Tekil</button>
                <button type="button" class="btn" data-hit-mode="total" title="Tüm gösterim (hit)"><i class="fas fa-eye me-1"></i>Toplam</button>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($page_analytics as $page => $analytics): ?>
            <div class="mb-4">
                <h5 class="mb-3">
                    <i class="fas fa-file-alt me-2"></i><?= htmlspecialchars($page); ?> Sayfası
                </h5>
                <div class="row">
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="stat-content">
                                <?= $hitMetric((int) $analytics['real_time_visitors'], (int) $analytics['real_time_hits']) ?>
                                <p>Anlık Ziyaretçi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <?= $hitMetric((int) $analytics['hourly_visitors'], (int) $analytics['hourly_hits']) ?>
                                <p>Saatlik Ziyaretçi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-content">
                                <?= $hitMetric((int) $analytics['daily_visitors'], (int) $analytics['daily_hits']) ?>
                                <p>Günlük Ziyaretçi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div class="stat-content">
                                <?= $hitMetric((int) $analytics['weekly_visitors'], (int) $analytics['weekly_hits']) ?>
                                <p>Haftalık Ziyaretçi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-content">
                                <?= $hitMetric((int) $analytics['monthly_visitors'], (int) $analytics['monthly_hits']) ?>
                                <p>Aylık Ziyaretçi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <?= $hitMetric((int) $analytics['total_visitors'], (int) $analytics['total_hits']) ?>
                                <p>Toplam Ziyaretçi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bilgi Notu -->
    <div class="admin-dash-footnote mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="fas fa-info-circle text-muted mt-1 flex-shrink-0"></i>
            <div class="small text-muted lh-base">
                <strong class="text-body">Önemli:</strong>
                Tekil IP ziyaretleri sayılır; ek analiz için Analytics / Metrica / Clarity kullanın.
            </div>
        </div>
    </div>
</div><!-- .container-fluid -->
</main>

<script>
// Tekil / Toplam ziyaretçi sayım modu geçişi (tarayıcıda saklanır).
(function () {
    var groups = document.querySelectorAll('.hit-mode-toggle');
    if (!groups.length) return;

    function fmt(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function apply(mode) {
        document.querySelectorAll('.hit-metric').forEach(function (el) {
            var v = mode === 'total' ? el.getAttribute('data-total') : el.getAttribute('data-unique');
            el.textContent = fmt(parseInt(v || '0', 10));
        });
        groups.forEach(function (g) {
            g.querySelectorAll('[data-hit-mode]').forEach(function (b) {
                b.classList.toggle('active', b.getAttribute('data-hit-mode') === mode);
            });
        });
        try { localStorage.setItem('admin_hit_mode', mode); } catch (e) {}
    }

    var saved = 'unique';
    try { saved = localStorage.getItem('admin_hit_mode') || 'unique'; } catch (e) {}
    apply(saved);

    groups.forEach(function (g) {
        g.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-hit-mode]');
            if (!btn) return;
            apply(btn.getAttribute('data-hit-mode'));
        });
    });
})();
</script>

<script>
// Bildirim kontrolü
function checkNewOrders() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // İki kez tik sesi çal
                const _ar = (typeof window.ADMIN_WEB_ROOT === 'string' && window.ADMIN_WEB_ROOT) ? (window.ADMIN_WEB_ROOT + '/') : '';
                const sound = new Audio(_ar + 'css/notification.mp3');
                sound.play();

                setTimeout(() => {
                    const sound2 = new Audio(_ar + 'css/notification.mp3');
                    sound2.play();
                }, 300);

                // Eski bildirimi kaldır
                const oldNotification = document.getElementById('notification');
                if (oldNotification) oldNotification.remove();

                // Yeni bildirim oluştur
                const notification = document.createElement('div');
                notification.id = 'notification';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #334155;
                    color: white;
                    padding: 14px 16px;
                    border-radius: 10px;
                    z-index: 9999;
                    box-shadow: 0 10px 30px rgba(15,23,42,0.2);
                    font-size: 13px;
                    font-weight: 600;
                `;

                const date = new Date(data.order.order_date);
                notification.innerHTML = `
                    🔔 Yeni Sipariş! <br>
                    Sipariş No: ${data.order.order_id}<br>
                    Müşteri: ${data.order.customer_name}<br>
                    Tarih: ${date.toLocaleString('tr-TR')}
                `;

                document.body.appendChild(notification);

                // 5 saniye sonra bildirimi kaldır
                setTimeout(() => notification.remove(), 5000);
            }
        })
        .catch(error => console.error('Hata:', error));
}

// Sayfa yüklendiğinde ve her 15 saniyede bir kontrol et
document.addEventListener('DOMContentLoaded', function() {
    checkNewOrders();
    setInterval(checkNewOrders, 15000);
});
</script>

<?php include 'admin_footer_common.php'; ?>
