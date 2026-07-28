<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$page_title = 'Kampanya siparişleri';

$camp = isset($_GET['campaign']) ? trim((string) $_GET['campaign']) : '';
$src = isset($_GET['source']) ? trim((string) $_GET['source']) : '';
$days = isset($_GET['days']) ? max(1, min(3650, (int) $_GET['days'])) : 30;
$view = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'summary';
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 100;

$baseWhere = ['o.order_date >= DATE_SUB(NOW(), INTERVAL '.$days.' DAY)'];
$params = [];
if ($camp !== '') {
    $baseWhere[] = 'o.utm_campaign LIKE ?';
    $params[] = '%'.$camp.'%';
}
if ($src !== '') {
    $baseWhere[] = 'o.utm_source LIKE ?';
    $params[] = '%'.$src.'%';
}
$attrFilter = '(o.utm_source IS NOT NULL AND TRIM(o.utm_source) != \'\'
    OR o.utm_campaign IS NOT NULL AND TRIM(o.utm_campaign) != \'\'
    OR o.utm_medium IS NOT NULL AND TRIM(o.utm_medium) != \'\'
    OR o.attribution_click_json IS NOT NULL AND TRIM(o.attribution_click_json) != \'\')';
$baseWhere[] = $attrFilter;
$whereSql = implode(' AND ', $baseWhere);

$summarySql = "
    SELECT
        COALESCE(NULLIF(TRIM(o.utm_campaign), ''), '(kampanya yok)') AS campaign_key,
        COALESCE(NULLIF(TRIM(o.utm_source), ''), '—') AS source_key,
        COALESCE(NULLIF(TRIM(o.utm_medium), ''), '—') AS medium_key,
        COUNT(DISTINCT o.order_id) AS order_count,
        COALESCE(SUM((
            SELECT SUM(oi.price) FROM order_items oi WHERE oi.order_id = o.order_id
        )), 0) AS revenue
    FROM orders o
    WHERE {$whereSql}
    GROUP BY campaign_key, source_key, medium_key
    ORDER BY order_count DESC, revenue DESC
";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summaryRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$noUtmSql = "
    SELECT COUNT(DISTINCT o.order_id) AS c,
        COALESCE(SUM((SELECT SUM(oi.price) FROM order_items oi WHERE oi.order_id = o.order_id)), 0) AS revenue
    FROM orders o
    WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    AND NOT ({$attrFilter})
";
$noUtm = $pdo->query($noUtmSql)->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 'revenue' => 0];

$countSql = "SELECT COUNT(DISTINCT o.order_id) FROM orders o WHERE {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $perPage));
if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}
$offset = ($pageNum - 1) * $perPage;

$listSql = "SELECT o.order_id, o.order_date, o.customer_name, o.customer_phone, o.reklam, o.utm_source, o.utm_medium, o.utm_campaign,
    o.utm_content, o.utm_term, o.attribution_landing_url, s.status_name
    FROM orders o
    LEFT JOIN order_status s ON s.order_status_id = o.order_status_id
    WHERE {$whereSql}
    ORDER BY o.order_id DESC
    LIMIT {$perPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$totalAttributed = array_sum(array_column($summaryRows, 'order_count'));
$totalRevenue = array_sum(array_column($summaryRows, 'revenue'));

$queryBase = ['days' => (string) $days];
if ($camp !== '') {
    $queryBase['campaign'] = $camp;
}
if ($src !== '') {
    $queryBase['source'] = $src;
}

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-chart-line text-success"></i> Kampanya siparişleri</h1>
            <p class="text-muted small mb-0">Son <strong><?= (int) $days ?></strong> gün · UTM’li <strong><?= $totalAttributed ?></strong> sipariş · <?= number_format((float) $totalRevenue, 0, ',', '.') ?> ₺</p>
        </div>
        <a href="attribution_settings.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-cog me-1"></i> UTM ayarı</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label small mb-0">utm_campaign</label>
                    <input type="text" name="campaign" class="form-control form-control-sm" value="<?= htmlspecialchars($camp, ENT_QUOTES, 'UTF-8') ?>" placeholder="içerir">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label small mb-0">utm_source</label>
                    <input type="text" name="source" class="form-control form-control-sm" value="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" placeholder="içerir">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Gün</label>
                    <input type="number" name="days" class="form-control form-control-sm" value="<?= (int) $days ?>" min="1" max="3650">
                </div>
                <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i> Uygula</button>
                    <a href="attribution_orders.php" class="btn btn-sm btn-outline-secondary">Sıfırla</a>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'summary' ? 'active' : '' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($queryBase, ['view' => 'summary'])), ENT_QUOTES, 'UTF-8') ?>">Kampanya özeti</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'list' ? 'active' : '' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($queryBase, ['view' => 'list', 'page' => '1'])), ENT_QUOTES, 'UTF-8') ?>">Sipariş listesi (<?= $totalOrders ?>)</a>
        </li>
    </ul>

    <?php if ($view === 'summary'): ?>
        <?php if ($summaryRows === []): ?>
            <div class="alert alert-info border-0 shadow-sm">Bu filtrede UTM’li sipariş yok.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>utm_campaign</th>
                                <th>utm_source</th>
                                <th>utm_medium</th>
                                <th class="text-end">Sipariş</th>
                                <th class="text-end">Ciro</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryRows as $sr): ?>
                            <tr>
                                <td class="font-monospace small"><?= htmlspecialchars((string) $sr['campaign_key'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars((string) $sr['source_key'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars((string) $sr['medium_key'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end fw-semibold"><?= (int) $sr['order_count'] ?></td>
                                <td class="text-end"><?= number_format((float) $sr['revenue'], 0, ',', '.') ?> ₺</td>
                                <td>
                                    <?php
                                    $q = array_merge($queryBase, ['view' => 'list', 'page' => '1']);
                                    if (($sr['campaign_key'] ?? '') !== '(kampanya yok)') {
                                        $q['campaign'] = (string) $sr['campaign_key'];
                                    }
                                    if (($sr['source_key'] ?? '') !== '—') {
                                        $q['source'] = (string) $sr['source_key'];
                                    }
                                    ?>
                                    <a href="?<?= htmlspecialchars(http_build_query($q), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">Listele</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ((int) ($noUtm['c'] ?? 0) > 0): ?>
        <div class="card border-0 shadow-sm border-start border-4 border-secondary">
            <div class="card-body py-2 small">
                <strong>UTM’siz siparişler:</strong> <?= (int) $noUtm['c'] ?> adet · <?= number_format((float) ($noUtm['revenue'] ?? 0), 0, ',', '.') ?> ₺
                <span class="text-muted">— reklam dışı veya doğrudan giriş</span>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($rows === []): ?>
        <div class="alert alert-info border-0 shadow-sm">Kayıt yok.</div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tarih</th>
                            <th>Müşteri</th>
                            <th>Durum</th>
                            <th>utm_source</th>
                            <th>utm_campaign</th>
                            <th>utm_content</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int) $r['order_id'] ?></td>
                            <td class="small"><?= htmlspecialchars((string) ($r['order_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($r['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small"><?= htmlspecialchars((string) ($r['status_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small font-monospace"><?= htmlspecialchars((string) ($r['utm_source'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small font-monospace"><?= htmlspecialchars((string) ($r['utm_campaign'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small font-monospace"><?= htmlspecialchars((string) ($r['utm_content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="order_manage.php?order_id=<?= (int) $r['order_id'] ?>">Aç</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $totalPages; ++$p): ?>
                <li class="page-item <?= $p === $pageNum ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($queryBase, ['view' => 'list', 'page' => (string) $p])), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
