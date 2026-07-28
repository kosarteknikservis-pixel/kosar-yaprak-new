<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/admin_cc_helpers.php';

$page_title = 'Destek Talepleri';

$q = trim((string) ($_GET['q'] ?? ''));
$openOnly = isset($_GET['open']) && $_GET['open'] === '1';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$stats = [
    'today' => (int) $pdo->query("SELECT COUNT(*) FROM support_requests WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'open' => (int) $pdo->query(
        "SELECT COUNT(*) FROM support_requests WHERE status IS NULL OR status = '' OR status = 'Beklemede' OR status = 'Yeni'"
    )->fetchColumn(),
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM support_requests')->fetchColumn(),
];

$where = '1=1';
$listParams = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR note LIKE ?)';
    $like = '%' . $q . '%';
    array_push($listParams, $like, $like, $like, $like, $like);
}
if ($openOnly) {
    $where .= " AND (status IS NULL OR status = '' OR status = 'Beklemede' OR status = 'Yeni')";
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM support_requests WHERE {$where}");
$countSt->execute($listParams);
$listTotal = (int) $countSt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM support_requests WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?");
$listParams[] = $limit;
$listParams[] = $offset;
$stmt->execute($listParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_pages = max(1, (int) ceil($listTotal / $limit));
?>

<?php include 'admin_header.php'; ?>
<link rel="stylesheet" href="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'css/record-drawer.css', ENT_QUOTES, 'UTF-8') ?>">

<div class="container-fluid px-3 px-lg-4 pb-4">
    <div class="inbox-page-head">
        <div>
            <h1><i class="fas fa-headset text-primary me-2"></i>Destek talepleri</h1>
            <p class="text-muted mb-0 small">Satıra tıklayın — detay ve cevap penceresi açılır</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="admin_support.php?open=1" class="btn btn-sm btn-outline-warning"><i class="fas fa-inbox me-1"></i> Açık (<?= $stats['open'] ?>)</a>
            <a href="orders.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-shopping-cart me-1"></i> Siparişler</a>
        </div>
    </div>

    <div class="inbox-stats">
        <div class="inbox-stat"><strong><?= $stats['today'] ?></strong><span>Bugün</span></div>
        <div class="inbox-stat"><strong><?= $stats['open'] ?></strong><span>Açık</span></div>
        <div class="inbox-stat"><strong><?= $listTotal ?></strong><span>Listelenen</span></div>
        <div class="inbox-stat"><strong><?= $stats['total'] ?></strong><span>Toplam</span></div>
    </div>

    <form method="get" class="orders-ws-filters mb-3">
        <?php if ($openOnly): ?><input type="hidden" name="open" value="1"><?php endif; ?>
        <div class="orders-ws-filters__row">
            <div class="flex-grow-1" style="min-width:200px;">
                <label class="form-label" for="q">Ara</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ad, telefon, konu…">
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Ara</button>
                <?php if ($q !== '' || $openOnly): ?>
                    <a href="admin_support.php" class="btn btn-outline-secondary btn-sm">Sıfırla</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="inbox-table-wrap">
        <table class="inbox-table">
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Gönderen</th>
                    <th>Konu / mesaj</th>
                    <th>Telefon</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">Kayıt bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $open = in_array((string) ($r['status'] ?? ''), ['', 'Beklemede', 'Yeni'], true) || ($r['status'] ?? null) === null;
                        $answered = (string) ($r['status'] ?? '') === 'Cevaplandı';
                        ?>
                        <tr class="inbox-row" data-record-type="support" data-record-id="<?= (int) $r['id'] ?>">
                            <td><span class="text-nowrap"><?= date('d.m.Y H:i', strtotime((string) $r['created_at'])) ?></span></td>
                            <td>
                                <div class="inbox-subject"><?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="inbox-preview"><?= htmlspecialchars((string) $r['email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <div class="inbox-subject"><?= htmlspecialchars((string) $r['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="inbox-preview"><?= htmlspecialchars(mb_substr((string) $r['note'], 0, 80), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td onclick="event.stopPropagation()"><?= cc_phone_actions_html((string) ($r['phone'] ?? ''), true) ?></td>
                            <td>
                                <?php if ($answered): ?>
                                    <span class="record-status-pill record-status-pill--done">Cevaplandı</span>
                                <?php elseif ($open): ?>
                                    <span class="record-status-pill record-status-pill--open">Açık</span>
                                <?php else: ?>
                                    <span class="record-status-pill record-status-pill--muted"><?= htmlspecialchars((string) ($r['status'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
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
