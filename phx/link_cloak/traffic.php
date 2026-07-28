<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$campaignId = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;
$verdict = isset($_GET['verdict']) ? trim((string) $_GET['verdict']) : '';
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 100;
$offset = ($pageNum - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($campaignId > 0) {
    $where[] = 't.campaign_id = ?';
    $params[] = $campaignId;
}
if ($verdict !== '' && in_array($verdict, ['human', 'bot'], true)) {
    $where[] = 't.verdict = ?';
    $params[] = $verdict;
}
$whereSql = implode(' AND ', $where);

$countSt = $pdo->prepare('SELECT COUNT(*) FROM link_cloak_traffic t WHERE ' . $whereSql);
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$sql = 'SELECT t.*, c.name AS campaign_name FROM link_cloak_traffic t
    LEFT JOIN link_cloak_campaigns c ON c.id = t.campaign_id
    WHERE ' . $whereSql . ' ORDER BY t.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$campaigns = $pdo->query('SELECT id, name FROM link_cloak_campaigns ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

link_cloak_admin_header('Geçit trafiği');
?>

<div class="container-fluid py-3 lc-admin">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <a href="<?= lc_href('campaigns.php') ?>" class="small text-muted"><i class="fas fa-arrow-left"></i> Geçit Merkezi</a>
            <h1 class="h4 mb-0 mt-1"><i class="fas fa-chart-bar"></i> Geçit trafiği</h1>
        </div>
        <span class="badge bg-secondary fs-6"><?= $total ?> kayıt</span>
    </div>

    <form method="get" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Kampanya</label>
                <select name="campaign_id" class="form-select form-select-sm">
                    <option value="0">Tümü</option>
                    <?php foreach ($campaigns as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $campaignId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Sonuç</label>
                <select name="verdict" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <option value="human" <?= $verdict === 'human' ? 'selected' : '' ?>>İnsan</option>
                    <option value="bot" <?= $verdict === 'bot' ? 'selected' : '' ?>>Bot</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filtrele</button>
            </div>
        </div>
    </form>

    <?php if ($rows === []): ?>
        <div class="alert alert-info border-0">Henüz trafik yok.</div>
    <?php else: ?>
        <div class="table-responsive card border-0 shadow-sm">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Zaman</th>
                        <th>Kampanya</th>
                        <th>Sonuç</th>
                        <th>IP</th>
                        <th>Vendor</th>
                        <th>UA</th>
                        <th>Sebep</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="small text-nowrap"><?= htmlspecialchars((string) $r['created_at']) ?></td>
                        <td class="small"><?= htmlspecialchars((string) ($r['campaign_name'] ?? '')) ?></td>
                        <td>
                            <?php if (($r['verdict'] ?? '') === 'human'): ?>
                                <span class="badge bg-success">İnsan</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Bot</span>
                            <?php endif; ?>
                        </td>
                        <td class="small font-monospace"><?= htmlspecialchars((string) $r['ip']) ?></td>
                        <td class="small"><?= htmlspecialchars((string) ($r['vendor_label'] ?? '')) ?></td>
                        <td class="small text-truncate" style="max-width:180px" title="<?= htmlspecialchars((string) $r['user_agent']) ?>"><?= htmlspecialchars(mb_substr((string) $r['user_agent'], 0, 60)) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars((string) ($r['block_reason'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3"><ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= min($totalPages, 20); ++$p): ?>
            <li class="page-item <?= $p === $pageNum ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&campaign_id=<?= $campaignId ?>&verdict=<?= urlencode($verdict) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../admin_footer_common.php'; ?>
