<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cloaker_clear_traffic'])) {
    try {
        $deleted = (int) $pdo->exec('DELETE FROM cloaker_traffic');
        $_SESSION['message'] = $deleted > 0
            ? 'Trafik günlüğü temizlendi (' . $deleted . ' kayıt silindi).'
            : 'Trafik günlüğü zaten boştu.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Günlük silinemedi: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header('Location: cloaker_traffic.php');
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$off = ($page - 1) * $perPage;

$fver = strtolower(trim((string) ($_GET['verdict'] ?? '')));
$params = [];

if ($fver !== '' && preg_match('/^[a-z_]+$/', $fver)) {
    $whereClause = 'WHERE verdict = ?';
    $params[] = $fver;
} else {
    $whereClause = '';
    $fver = '';
}

$verdictLabels = [
    'allow' => 'Geçti',
    'allow_bot' => 'Bot (izin)',
    'block' => 'Engellendi',
    'cloak' => 'Gölge sayfa',
    'log_only' => 'Sadece log',
];

$verdictColors = [
    'allow' => 'success',
    'allow_bot' => 'info',
    'block' => 'danger',
    'cloak' => 'secondary',
    'log_only' => 'warning',
];

$countSql = 'SELECT COUNT(*) FROM cloaker_traffic ' . $whereClause;
$countSt = $pdo->prepare($countSql);
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();

$summary = [];
try {
    $sumRows = $pdo->query('SELECT verdict, COUNT(*) AS c FROM cloaker_traffic GROUP BY verdict')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sumRows as $sr) {
        $summary[(string) $sr['verdict']] = (int) $sr['c'];
    }
} catch (Throwable $e) {
    $summary = [];
}

$sql = 'SELECT id, created_at, ip, verdict, vendor_label, block_reason,
        LEFT(user_agent, 240) AS user_agent_short,
        LEFT(request_uri, 320) AS request_uri_short
        FROM cloaker_traffic '
    . $whereClause . ' ORDER BY id DESC LIMIT ? OFFSET ?';
$st = $pdo->prepare($sql);
$b = 1;
foreach ($params as $p) {
    $st->bindValue($b++, $p);
}
$st->bindValue($b++, $perPage, PDO::PARAM_INT);
$st->bindValue($b++, $off, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Cloaker trafik';
include 'admin_header.php';

$pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
?>

<div class="container-fluid py-3 cloaker-admin-page cloaker-traffic-page">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $isErr = in_array($mtp, ['error', 'danger'], true); ?>
        <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="cloaker-page-head">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-chart-line text-primary"></i> Cloaker trafik günlüğü</h1>
            <p class="text-muted small mb-0">Cloaker kapalıyken yeni kayıt eklenmez; liste geçmiş kayıtları gösterir.</p>
        </div>
        <a href="cloaker_settings.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog"></i> Ayarlar</a>
    </div>

    <?php if ($summary !== []): ?>
    <div class="cloaker-traffic-summary mb-3">
        <?php foreach ($summary as $vk => $cnt): ?>
            <?php $bg = $verdictColors[$vk] ?? 'light'; ?>
            <a href="cloaker_traffic.php?verdict=<?= urlencode($vk) ?>" class="cloaker-traffic-pill cloaker-traffic-pill--<?= htmlspecialchars($bg) ?><?= $fver === $vk ? ' is-active' : '' ?>">
                <span><?= htmlspecialchars($verdictLabels[$vk] ?? $vk) ?></span>
                <strong><?= (int) $cnt ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="cloaker-traffic-toolbar mb-3">
        <span class="text-muted small"><strong><?= (int) $total ?></strong> kayıt<?= $fver !== '' ? ' · filtre: ' . htmlspecialchars($verdictLabels[$fver] ?? $fver) : '' ?></span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <a href="cloaker_traffic.php" class="btn btn-sm btn-outline-secondary<?= $fver === '' ? ' active' : '' ?>">Tümü</a>
            <?php foreach (['allow', 'allow_bot', 'cloak', 'block', 'log_only'] as $vv): ?>
                <a href="cloaker_traffic.php?verdict=<?= urlencode($vv) ?>" class="btn btn-sm btn-outline-secondary<?= $fver === $vv ? ' active' : '' ?>"><?= htmlspecialchars($verdictLabels[$vv] ?? $vv) ?></a>
            <?php endforeach; ?>
            <?php if ($total > 0): ?>
            <form method="post" onsubmit="return confirm('Tüm trafik günlüğü kalıcı olarak silinsin mi?');">
                <button type="submit" name="cloaker_clear_traffic" value="1" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash-alt"></i> Temizle
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($total === 0): ?>
        <div class="cloaker-empty-state">
            <i class="fas fa-inbox"></i>
            <p>Henüz kayıt yok veya filtreye uyan satır bulunamadı.</p>
        </div>
    <?php else: ?>

    <div class="cloaker-traffic-cards d-lg-none">
        <?php foreach ($rows as $row): ?>
            <?php
            $vv = (string) ($row['verdict'] ?? '');
            $bg = $verdictColors[$vv] ?? 'light';
            ?>
            <article class="cloaker-traffic-card">
                <div class="cloaker-traffic-card__top">
                    <span class="badge bg-<?= htmlspecialchars($bg) ?>"><?= htmlspecialchars($verdictLabels[$vv] ?? $vv) ?></span>
                    <span class="small text-muted">#<?= (int) $row['id'] ?></span>
                </div>
                <div class="cloaker-traffic-card__meta">
                    <span><i class="far fa-clock"></i> <?= htmlspecialchars((string) $row['created_at']) ?></span>
                    <span><i class="fas fa-network-wired"></i> <?= htmlspecialchars((string) $row['ip']) ?></span>
                </div>
                <?php if (!empty($row['vendor_label'])): ?>
                    <div class="small text-muted mb-1"><?= htmlspecialchars((string) $row['vendor_label']) ?></div>
                <?php endif; ?>
                <div class="cloaker-traffic-card__uri text-break small"><?= htmlspecialchars((string) $row['request_uri_short']) ?></div>
                <div class="cloaker-traffic-card__ua small text-muted text-break" title="<?= htmlspecialchars((string) ($row['user_agent_short'] ?? '')) ?>"><?= htmlspecialchars((string) $row['user_agent_short']) ?></div>
                <?php if (!empty($row['block_reason'])): ?>
                    <div class="cloaker-traffic-card__reason small"><?= htmlspecialchars((string) $row['block_reason']) ?></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive d-none d-lg-block cloaker-traffic-table-wrap">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Zaman</th>
                    <th>Karar</th>
                    <th>Vendor</th>
                    <th>IP</th>
                    <th>URI</th>
                    <th>User-Agent</th>
                    <th>Not</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $vv = (string) ($row['verdict'] ?? '');
                    $bg = $verdictColors[$vv] ?? 'light';
                    ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td class="text-nowrap small"><?= htmlspecialchars((string) $row['created_at']) ?></td>
                        <td><span class="badge bg-<?= htmlspecialchars($bg) ?>"><?= htmlspecialchars($verdictLabels[$vv] ?? $vv) ?></span></td>
                        <td class="small text-muted"><?= htmlspecialchars((string) ($row['vendor_label'] ?? '')) ?></td>
                        <td class="small font-monospace"><?= htmlspecialchars((string) $row['ip']) ?></td>
                        <td class="small text-break" style="max-width:220px"><?= htmlspecialchars((string) $row['request_uri_short']) ?></td>
                        <td class="small text-break" style="max-width:280px" title="<?= htmlspecialchars((string) ($row['user_agent_short'] ?? '')) ?>"><?= htmlspecialchars((string) $row['user_agent_short']) ?></td>
                        <td class="small"><?= htmlspecialchars((string) ($row['block_reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <?php
        require_once __DIR__ . '/../includes/admin_pagination.php';
        admin_render_pagination($page, $pages, static function (int $p) use ($fver): string {
            $qs = ['page' => $p];
            if ($fver !== '') {
                $qs['verdict'] = $fver;
            }

            return 'cloaker_traffic.php?' . http_build_query($qs);
        });
        ?>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
