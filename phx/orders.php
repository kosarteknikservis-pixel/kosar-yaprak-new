<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/admin_cc_helpers.php';
require_once __DIR__ . '/../includes/orders/order_list_service.php';

$page_title = 'Sipariş Listesi';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cc_save_note'])) {
    $noteOid = (int) ($_POST['cc_order_id'] ?? 0);
    $noteText = mb_substr(trim((string) ($_POST['cc_note'] ?? '')), 0, 500);
    if ($noteOid > 0) {
        $pdo->prepare('UPDATE orders SET cc_last_call_note = ?, cc_last_call_at = NOW() WHERE order_id = ?')
            ->execute([$noteText !== '' ? $noteText : null, $noteOid]);
        $_SESSION['message'] = 'Arama notu kaydedildi.';
        header('Location: orders.php?' . http_build_query($_GET));
        exit;
    }
}

$filters = OrderListService::filtersFromInput($_GET);

// Varsayılan: Beklemede (9440 kayıt çekilmesin). "Tümü" ?status_name= ile gelir.
if (!array_key_exists('status_name', $_GET) && !isset($_GET['download_numbers'])) {
    $redirectQuery = array_merge($_GET, ['status_name' => 'Beklemede', 'page' => 1]);
    header('Location: orders.php?' . http_build_query($redirectQuery));
    exit;
}

$ccStats = OrderListService::ccStats($pdo);
$statusNames = OrderListService::statusNames($pdo);

if (isset($_GET['download_numbers']) && $_GET['download_numbers']) {
    $status_name = (string) $_GET['download_numbers'];
    $query = "
        SELECT DISTINCT o.customer_phone
        FROM orders o
        JOIN order_status s ON o.order_status_id = s.order_status_id
        WHERE s.status_name = ?";
    $dlParams = [$status_name];
    if ($filters['start_date'] !== '' && $filters['end_date'] !== '') {
        $query .= ' AND DATE(o.order_date) BETWEEN ? AND ?';
        $dlParams[] = $filters['start_date'];
        $dlParams[] = $filters['end_date'];
    }
    $number_stmt = $pdo->prepare($query);
    $number_stmt->execute($dlParams);
    $phone_numbers = $number_stmt->fetchAll(PDO::FETCH_COLUMN);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename=telefon_numaralari.csv');
    $csv_file = fopen('php://output', 'w');
    fputcsv($csv_file, ['Telefon No']);
    foreach ($phone_numbers as $number) {
        $number = preg_replace('/\D/', '', (string) $number);
        $number = ltrim($number, '0');
        fputcsv($csv_file, [$number]);
    }
    fclose($csv_file);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min((int) ($_GET['limit'] ?? 25), 250));
$offset = ($page - 1) * $limit;

$orders = OrderListService::fetchList($pdo, $filters, $limit, $offset);
$total_orders = OrderListService::count($pdo, $filters);
$total_pages = $limit > 0 ? (int) ceil(max(0, $total_orders) / $limit) : 1;

$activeStatus = $filters['status_name'];
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
?>

<?php include 'admin_header.php'; ?>
<link rel="stylesheet" href="<?= admin_href('css/record-drawer.css') ?>">
<style>
/* Sipariş modal — inline (CSS dosyası yüklenmese de çalışır) */
#orderManageOverlay.order-manage-overlay {
    position: fixed !important;
    inset: 0 !important;
    z-index: 99999 !important;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 14px;
    margin: 0 !important;
    box-sizing: border-box;
}
#orderManageOverlay.order-manage-overlay.is-open {
    display: flex !important;
}
#orderManageOverlay .order-manage-overlay__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    cursor: pointer;
}
#orderManageOverlay .order-manage-overlay__panel {
    position: relative;
    z-index: 1;
    width: min(740px, calc(100vw - 28px));
    height: min(78vh, 640px);
    max-height: calc(100vh - 28px);
    background: #f8fafc;
    border-radius: 14px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#orderManageOverlay .order-manage-overlay__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #0f172a, #1e3a5f);
    color: #fff;
    flex-shrink: 0;
}
#orderManageOverlay .order-manage-overlay__eyebrow {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}
#orderManageOverlay .order-manage-overlay__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #fff;
}
#orderManageOverlay .order-manage-overlay__actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
#orderManageOverlay .order-manage-overlay__close {
    border: none;
    background: rgba(255,255,255,0.15);
    width: 36px;
    height: 36px;
    border-radius: 10px;
    cursor: pointer;
    color: #fff;
}
#orderManageOverlay .order-manage-overlay__body {
    flex: 1;
    position: relative;
    min-height: 0;
    background: #f1f5f9;
}
#orderManageOverlay .order-manage-overlay__loading {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #64748b;
    z-index: 2;
    background: #f1f5f9;
}
#orderManageOverlay .order-manage-overlay__frame {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    background: #f8fafc;
}
</style>

<script>
function checkNewOrders() {
    fetch('check_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const _ar = (typeof window.ADMIN_WEB_ROOT === 'string' && window.ADMIN_WEB_ROOT) ? (window.ADMIN_WEB_ROOT + '/') : '';
            const sound = new Audio(_ar + 'css/notification.mp3');
            sound.play();
            setTimeout(() => new Audio(_ar + 'css/notification.mp3').play(), 300);
            document.getElementById('notification')?.remove();
            const n = document.createElement('div');
            n.id = 'notification';
            n.style.cssText = 'position:fixed;top:20px;right:20px;background:#28a745;color:#fff;padding:15px;border-radius:8px;z-index:9999;font-weight:700;';
            const d = new Date(data.order.order_date);
            n.innerHTML = '🔔 Yeni sipariş #' + data.order.order_id + '<br>' + data.order.customer_name + '<br>' + d.toLocaleString('tr-TR');
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 5000);
        }).catch(() => {});
}
document.addEventListener('DOMContentLoaded', function () { checkNewOrders(); setInterval(checkNewOrders, 15000); });
</script>

<div class="container-fluid orders-workspace px-3 px-lg-4 pb-4">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars((string) $_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="orders-ws-head">
        <div>
            <h1><i class="fas fa-shopping-cart text-primary me-2"></i>Siparişler</h1>
            <p class="text-muted small mb-0"><?= number_format($total_orders, 0, ',', '.') ?> kayıt · filtrelenmiş liste</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="export.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel me-1"></i> Excel</a>
            <a href="order_manual.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i> Manuel sipariş</a>
        </div>
    </div>

    <div class="cc-toolbar mb-3">
        <span class="cc-toolbar__label">Hızlı</span>
        <a class="cc-chip cc-chip--warn" href="?status_name=Beklemede&amp;start_date=<?= $today ?>&amp;end_date=<?= $today ?>">Bugün beklemede <strong><?= $ccStats['pending_today'] ?></strong></a>
        <a class="cc-chip cc-chip--primary" href="?status_name=Beklemede">Tüm beklemede <strong><?= $ccStats['pending_all'] ?></strong></a>
        <a class="cc-chip cc-chip--primary" href="abandoned_orders.php">Yarım kalan <strong><?= $ccStats['abandoned_today'] ?></strong></a>
    </div>

    <form method="get" class="orders-ws-filters">
        <div class="orders-ws-filters__row">
            <div style="min-width:160px;">
                <label class="form-label" for="q">Genel arama</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ad, tel, #no…">
            </div>
            <div style="min-width:130px;">
                <label class="form-label" for="start_date">Başlangıç</label>
                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?= htmlspecialchars($filters['start_date'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div style="min-width:130px;">
                <label class="form-label" for="end_date">Bitiş</label>
                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?= htmlspecialchars($filters['end_date'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div style="min-width:130px;">
                <label class="form-label" for="customer_name">Müşteri</label>
                <input type="text" class="form-control form-control-sm" id="customer_name" name="customer_name" value="<?= htmlspecialchars($filters['customer_name'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div style="min-width:130px;">
                <label class="form-label" for="customer_phone">Telefon</label>
                <input type="text" class="form-control form-control-sm" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($filters['customer_phone'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div style="min-width:120px;">
                <label class="form-label" for="limit">Sayfa</label>
                <select name="limit" id="limit" class="form-select form-select-sm">
                    <?php foreach ([25, 50, 100, 150, 250] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-1 align-items-end">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
                <a href="orders.php" class="btn btn-outline-secondary btn-sm">Sıfırla</a>
            </div>
        </div>
        <div class="orders-ws-status-pills">
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['status_name' => '', 'page' => 1])), ENT_QUOTES, 'UTF-8') ?>" class="orders-ws-pill<?= $activeStatus === '' ? ' is-active' : '' ?>">Tümü</a>
            <?php foreach ($statusNames as $st): ?>
                <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['status_name' => $st, 'page' => 1])), ENT_QUOTES, 'UTF-8') ?>" class="orders-ws-pill<?= $activeStatus === $st ? ' is-active' : '' ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top">
            <?php
            foreach ([['Bugün', $today, $today], ['Dün', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))], ['Bu hafta', $weekStart, $today], ['Bu ay', date('Y-m-01'), $today]] as [$lbl, $s, $e]):
                $qs = http_build_query(array_merge($_GET, ['start_date' => $s, 'end_date' => $e, 'page' => 1]));
            ?>
                <a class="btn btn-sm btn-light border" href="?<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="orders-ws-table-wrap mt-3">
        <form id="bulkForm" action="bulk_update.php" method="POST">
            <div class="orders-ws-bulk">
                <input type="checkbox" id="select_all" class="form-check-input" title="Tümünü seç">
                <select name="bulk_status" class="form-select form-select-sm" style="width:auto;min-width:160px;">
                    <option value="">Toplu durum…</option>
                    <?php foreach ($statusNames as $st): ?>
                        <option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-sync-alt me-1"></i> Uygula</button>
                <?php if (admin_user_can('menu_yz')): ?>
                <button type="button" class="btn btn-info btn-sm" id="btnBulkFactory" disabled title="Seçili siparişler için görsel fabrikasını aç">
                    <i class="fas fa-industry me-1"></i> Fabrikada üret
                </button>
                <?php endif; ?>
                <a href="export.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success btn-sm ms-auto"><i class="fas fa-download me-1"></i> Excel indir</a>
            </div>
        </form>

        <div class="table-responsive orders-ws-table-desktop">
            <table class="orders-ws-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>#</th>
                        <th>Müşteri</th>
                        <th>Telefon</th>
                        <th>Durum</th>
                        <th>Ürün / tutar</th>
                        <th>Kaynak</th>
                        <th>Tarih</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="9" class="text-center text-muted py-5">Sipariş bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $dup = ((int) ($order['duplicate_phone_count'] ?? 0) > 1) || ((int) ($order['duplicate_ip_count'] ?? 0) > 1);
                            [$badgeCls, $badgeLbl] = cc_status_badge((string) $order['status_name']);
                            $reklam = trim((string) ($order['reklam'] ?? '')) !== '' ? (string) $order['reklam'] : 'Organik';
                            ?>
                            <tr class="<?= $dup ? 'is-dup' : '' ?>">
                                <td><input type="checkbox" form="bulkForm" name="selected_orders[]" value="<?= (int) $order['order_id'] ?>" class="form-check-input"></td>
                                <td><strong>#<?= (int) $order['order_id'] ?></strong></td>
                                <td>
                                    <div class="orders-ws-customer"><?= htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ((int) ($order['abandoned_yarim_id'] ?? 0) > 0): ?>
                                        <span class="cc-badge cc-badge--call mt-1">↩ Yarım kalandan #<?= (int) $order['abandoned_yarim_id'] ?></span>
                                    <?php endif; ?>
                                    <div class="orders-ws-loc"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars((string) ($order['customer_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) ($order['customer_district'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($order['cc_last_call_note'])): ?>
                                        <div class="orders-ws-loc"><i class="fas fa-headset me-1"></i><?= htmlspecialchars(mb_substr((string) $order['cc_last_call_note'], 0, 60), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= cc_phone_actions_html((string) ($order['customer_phone'] ?? '')) ?></td>
                                <td><span class="cc-badge <?= htmlspecialchars($badgeCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeLbl, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <div><span class="badge bg-light text-dark border"><?= (int) ($order['item_count'] ?? 0) ?> ürün</span> <strong><?= admin_tr_money((float) ($order['total_price'] ?? 0)) ?></strong></div>
                                    <?php if (!empty($order['products'])): ?>
                                        <div class="orders-ws-products" title="<?= htmlspecialchars((string) $order['products'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $order['products'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="small"><?= htmlspecialchars($reklam, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="text-nowrap"><?= date('d.m.Y H:i', strtotime((string) $order['order_date'])) ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary js-order-edit" data-order-id="<?= (int) $order['order_id'] ?>" title="Düzenle"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="orders-mobile-list">
            <?php if ($orders === []): ?>
                <div class="text-center text-muted py-4">Sipariş bulunamadı.</div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $dup = ((int) ($order['duplicate_phone_count'] ?? 0) > 1) || ((int) ($order['duplicate_ip_count'] ?? 0) > 1);
                    [$badgeCls, $badgeLbl] = cc_status_badge((string) $order['status_name']);
                    $reklam = trim((string) ($order['reklam'] ?? '')) !== '' ? (string) $order['reklam'] : 'Organik';
                    ?>
                    <article class="order-mobile-card<?= $dup ? ' duplicate' : '' ?>">
                        <div class="order-mobile-card__top">
                            <div>
                                <div class="order-mobile-card__id">#<?= (int) $order['order_id'] ?></div>
                                <div class="order-mobile-card__name"><?= htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <span class="cc-badge <?= htmlspecialchars($badgeCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeLbl, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="order-mobile-card__row">
                            <?= cc_phone_actions_html((string) ($order['customer_phone'] ?? '')) ?>
                        </div>
                        <div class="order-mobile-card__row">
                            <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                            <?= htmlspecialchars((string) ($order['customer_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) ($order['customer_district'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="order-mobile-card__products">
                            <span class="badge bg-light text-dark border"><?= (int) ($order['item_count'] ?? 0) ?> ürün</span>
                            <strong><?= admin_tr_money((float) ($order['total_price'] ?? 0)) ?></strong>
                            <?php if (!empty($order['products'])): ?>
                                · <?= htmlspecialchars(mb_substr((string) $order['products'], 0, 80), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen((string) $order['products']) > 80 ? '…' : '' ?>
                            <?php endif; ?>
                        </div>
                        <div class="order-mobile-card__row small text-muted">
                            <?= htmlspecialchars($reklam, ENT_QUOTES, 'UTF-8') ?> · <?= date('d.m.Y H:i', strtotime((string) $order['order_date'])) ?>
                        </div>
                        <div class="order-mobile-card__actions">
                            <input type="checkbox" form="bulkForm" name="selected_orders[]" value="<?= (int) $order['order_id'] ?>" class="form-check-input" title="Seç">
                            <button type="button" class="btn btn-sm btn-primary js-order-edit" data-order-id="<?= (int) $order['order_id'] ?>">
                                <i class="fas fa-edit me-1"></i> Düzenle
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="mt-3">
            <?php
            require_once __DIR__ . '/../includes/admin_pagination.php';
            admin_render_pagination($page, $total_pages, static function (int $p): string {
                return '?' . http_build_query(array_merge($_GET, ['page' => $p]));
            });
            ?>
        </div>
    <?php endif; ?>
</div>

<div id="orderManageOverlay" class="order-manage-overlay" role="dialog" aria-modal="true" aria-label="Sipariş düzenle">
    <div class="order-manage-overlay__backdrop" data-om-close></div>
    <div class="order-manage-overlay__panel">
        <div class="order-manage-overlay__head">
            <div>
                <div class="order-manage-overlay__eyebrow"><i class="fas fa-receipt"></i> Sipariş düzenle</div>
                <h2 class="order-manage-overlay__title" id="orderManageModalTitle">Sipariş</h2>
            </div>
            <div class="order-manage-overlay__actions">
                <button type="button" class="btn btn-sm btn-light" id="orderManageModalReload" title="Yenile"><i class="fas fa-sync-alt"></i></button>
                <button type="button" class="order-manage-overlay__close" id="orderManageModalClose" aria-label="Kapat"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="order-manage-overlay__body">
            <div class="order-manage-overlay__loading" id="orderManageModalLoading"><i class="fas fa-spinner fa-spin"></i> Yükleniyor…</div>
            <iframe class="order-manage-overlay__frame" id="orderManageModalFrame" title="Sipariş düzenleme"></iframe>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('orderManageOverlay');
    if (overlay && overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
    }
    var iframe = document.getElementById('orderManageModalFrame');
    var loading = document.getElementById('orderManageModalLoading');
    var titleEl = document.getElementById('orderManageModalTitle');
    var manageBase = <?= json_encode(admin_url('order_manage.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

    function buildUrl(orderId) {
        var u = new URL(manageBase, window.location.href);
        u.searchParams.set('order_id', String(orderId));
        u.searchParams.set('embed', '1');
        u.searchParams.set('popup', '1');
        return u.toString();
    }

    function openOrderManage(orderId) {
        if (!overlay || !iframe) return;
        titleEl.textContent = 'Sipariş #' + orderId;
        loading.style.display = 'flex';
        iframe.classList.remove('is-ready');
        iframe.onload = function () {
            loading.style.display = 'none';
            iframe.classList.add('is-ready');
        };
        iframe.src = buildUrl(orderId);
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        document.getElementById('orderManageModalClose')?.focus();
    }

    function closeOrderManage() {
        if (!overlay) return;
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
        if (iframe) {
            iframe.src = 'about:blank';
            iframe.classList.remove('is-ready');
        }
    }

    document.querySelectorAll('.js-order-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-order-id');
            if (id) openOrderManage(id);
        });
    });

    document.getElementById('orderManageModalClose')?.addEventListener('click', function (e) {
        e.preventDefault();
        closeOrderManage();
    });
    overlay?.querySelector('[data-om-close]')?.addEventListener('click', closeOrderManage);
    overlay?.querySelector('.order-manage-overlay__panel')?.addEventListener('click', function (e) {
        e.stopPropagation();
    });
    document.getElementById('orderManageModalReload')?.addEventListener('click', function () {
        if (iframe && iframe.src && iframe.src !== 'about:blank') iframe.src = iframe.src;
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('is-open')) closeOrderManage();
    });
    window.addEventListener('message', function (e) {
        if (!e.data || e.data.type !== 'order-manage-saved') return;
        closeOrderManage();
        setTimeout(function () { location.reload(); }, 200);
    });

    window.openOrderManage = function (arg) {
        var id = typeof arg === 'number' ? arg : (String(arg).match(/order_id=(\d+)/) || [])[1];
        if (id) openOrderManage(id);
    };
    window.closeOrderManage = closeOrderManage;
})();
document.getElementById('select_all')?.addEventListener('change', function () {
    document.querySelectorAll('input[name="selected_orders[]"]').forEach(function (cb) { cb.checked = this.checked; }.bind(this));
    if (typeof oimOrdersSyncBulkFactory === 'function') oimOrdersSyncBulkFactory();
});
(function () {
    var btn = document.getElementById('btnBulkFactory');
    if (!btn) return;
    var factoryBase = <?= json_encode(admin_href('order_image_maker.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    function selectedIds() {
        return Array.prototype.slice
            .call(document.querySelectorAll('input[name="selected_orders[]"]:checked'))
            .map(function (cb) { return cb.value; })
            .filter(function (v) { return v; });
    }
    window.oimOrdersSyncBulkFactory = function () {
        var n = selectedIds().length;
        btn.disabled = n === 0;
        btn.innerHTML = '<i class="fas fa-industry me-1"></i> Fabrikada üret' + (n > 0 ? ' (' + n + ')' : '');
    };
    btn.addEventListener('click', function () {
        var ids = selectedIds();
        if (!ids.length) return;
        var url = factoryBase + (factoryBase.indexOf('?') >= 0 ? '&' : '?') + 'factory=1&order_ids=' + encodeURIComponent(ids.join(','));
        window.open(url, '_blank', 'noopener');
    });
    document.querySelectorAll('input[name="selected_orders[]"]').forEach(function (cb) {
        cb.addEventListener('change', window.oimOrdersSyncBulkFactory);
    });
    window.oimOrdersSyncBulkFactory();
})();
</script>
<?php include 'admin_footer_common.php'; ?>
