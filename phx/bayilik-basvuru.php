<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/admin_cc_helpers.php';

$page_title = 'Bayilik Başvuruları';

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;
$q = trim((string) ($_GET['q'] ?? ''));
$pendingOnly = isset($_GET['pending']) && $_GET['pending'] === '1';

$stats = [
    'today' => (int) $pdo->query("SELECT COUNT(*) FROM dealer_requests WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM dealer_requests WHERE IFNULL(status,'') != 'approved'")->fetchColumn(),
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM dealer_requests')->fetchColumn(),
];

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR company_name LIKE ? OR address LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($pendingOnly) {
    $where .= " AND IFNULL(status,'') != 'approved'";
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM dealer_requests WHERE {$where}");
$countSt->execute($params);
$listTotal = (int) $countSt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM dealer_requests WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_pages = max(1, (int) ceil($listTotal / $limit));
?>

<?php include 'admin_header.php'; ?>
<link rel="stylesheet" href="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'css/record-drawer.css', ENT_QUOTES, 'UTF-8') ?>">

<div class="container-fluid px-3 px-lg-4 pb-4">
    <div class="inbox-page-head">
        <div>
            <h1><i class="fas fa-store text-primary me-2"></i>Bayilik başvuruları</h1>
            <p class="text-muted mb-0 small">Satıra tıklayın — detay ve işlem penceresi açılır</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="bayilik-basvuru.php?pending=1" class="btn btn-sm btn-outline-warning"><i class="fas fa-clock me-1"></i> Bekleyen (<?= $stats['pending'] ?>)</a>
            <a href="orders.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-shopping-cart me-1"></i> Siparişler</a>
        </div>
    </div>

    <div class="inbox-stats">
        <div class="inbox-stat"><strong><?= $stats['today'] ?></strong><span>Bugün</span></div>
        <div class="inbox-stat"><strong><?= $stats['pending'] ?></strong><span>Bekleyen</span></div>
        <div class="inbox-stat"><strong><?= $listTotal ?></strong><span>Listelenen</span></div>
        <div class="inbox-stat"><strong><?= $stats['total'] ?></strong><span>Toplam</span></div>
    </div>

    <form method="get" class="orders-ws-filters mb-3">
        <?php if ($pendingOnly): ?><input type="hidden" name="pending" value="1"><?php endif; ?>
        <div class="orders-ws-filters__row">
            <div class="flex-grow-1" style="min-width:200px;">
                <label class="form-label" for="q">Ara</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ad, şirket, telefon…">
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Ara</button>
                <?php if ($q !== '' || $pendingOnly): ?>
                    <a href="bayilik-basvuru.php" class="btn btn-outline-secondary btn-sm">Sıfırla</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="inbox-table-wrap">
        <table class="inbox-table">
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Yetkili / şirket</th>
                    <th>İletişim</th>
                    <th>Tür</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">Başvuru bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $approved = (string) ($r['status'] ?? '') === 'approved'; ?>
                        <tr class="inbox-row" data-record-type="dealer" data-record-id="<?= (int) $r['id'] ?>">
                            <td><span class="text-nowrap"><?= date('d.m.Y H:i', strtotime((string) $r['created_at'])) ?></span></td>
                            <td>
                                <div class="inbox-subject"><?= htmlspecialchars((string) $r['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="inbox-preview"><?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <?= cc_phone_actions_html((string) ($r['phone'] ?? ''), true) ?>
                                <div class="inbox-preview"><?= htmlspecialchars((string) $r['email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars((string) $r['role'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($approved): ?>
                                    <span class="record-status-pill record-status-pill--done">Arandı</span>
                                <?php else: ?>
                                    <span class="record-status-pill record-status-pill--open">Beklemede</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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

<script src="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'js/record-drawer.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php include 'admin_footer_common.php'; ?>
