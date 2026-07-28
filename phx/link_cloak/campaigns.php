<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $name = mb_substr(trim((string) ($_POST['name'] ?? 'Yeni kampanya')), 0, 128);
    $token = LinkCloakService::generateToken();
    $pdo->prepare(
        "INSERT INTO link_cloak_campaigns (name, token, run_status, rotate_after_clicks) VALUES (?, ?, 'passive', 25)"
    )->execute([$name, $token]);
    $_SESSION['message'] = 'Kampanya oluşturuldu.';
    $_SESSION['message_type'] = 'success';
    header('Location: ' . lc_url('campaign_edit.php?id=' . (int) $pdo->lastInsertId()));
    exit;
}

$campaigns = $pdo->query(
    'SELECT * FROM link_cloak_campaigns ORDER BY id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

link_cloak_admin_header('Geçit Merkezi');
?>

<div class="container-fluid py-3 lc-admin">
    <?php if (! empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="lc-page-head">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-route text-indigo"></i> Geçit Merkezi</h1>
            <p class="text-muted small mb-0">Reklam hedefi: <code>lc?c=TOKEN</code> — bot paravana, müşteri gerçek URL’ye gider.</p>
        </div>
        <form method="post" class="m-0">
            <button type="submit" name="create_campaign" value="1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Yeni geçit</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="lc-stat-card">
                <span>Kampanya</span>
                <strong><?= count($campaigns) ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <div class="lc-stat-card lc-stat-card--human">
                <span>Toplam insan geçişi</span>
                <strong><?= array_sum(array_column($campaigns, 'human_hits')) ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <div class="lc-stat-card lc-stat-card--bot">
                <span>Bot / inceleme</span>
                <strong><?= array_sum(array_column($campaigns, 'bot_hits')) ?></strong>
            </div>
        </div>
    </div>

    <?php if ($campaigns === []): ?>
        <div class="lc-empty">Henüz geçit yok. «Yeni geçit» ile başlayın — reklama vereceğiniz link otomatik üretilir.</div>
    <?php else: ?>
        <div class="lc-campaign-list">
            <?php foreach ($campaigns as $c): ?>
                <?php
                $gw = LinkCloakService::gatewayUrl($pdo, (string) $c['token']);
                $active = (string) ($c['run_status'] ?? '') === 'active';
                $enabled = ! empty((int) ($c['is_enabled'] ?? 1));
                ?>
                <div class="lc-campaign-card <?= $enabled ? '' : 'is-off' ?>">
                    <div class="lc-campaign-card__top">
                        <div>
                            <h2 class="h6 mb-1"><?= htmlspecialchars((string) $c['name']) ?></h2>
                            <div class="lc-badges">
                                <?php if (! $enabled): ?><span class="badge bg-secondary">Kapalı</span><?php endif; ?>
                                <?php if ($active): ?><span class="badge bg-success">Aktif</span><?php else: ?><span class="badge bg-warning text-dark">Pasif — ilk tıklamada açılır</span><?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= lc_href('campaign_edit.php?id=' . (int) $c['id']) ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                    </div>
                    <div class="lc-link-row">
                        <span class="lc-link-label">Reklam linki</span>
                        <code class="lc-link-url"><?= htmlspecialchars($gw) ?></code>
                    </div>
                    <div class="lc-metrics">
                        <span><i class="fas fa-user-check text-success"></i> <?= (int) $c['human_hits'] ?></span>
                        <span><i class="fas fa-robot text-danger"></i> <?= (int) $c['bot_hits'] ?></span>
                        <span class="text-muted">Rotasyon: her <?= (int) ($c['rotate_after_clicks'] ?? 25) ?> tık</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="small text-muted mt-3 mb-0">
        <a href="<?= lc_href('traffic.php') ?>">Trafik günlüğü</a> ·
        <a href="<?= admin_href('cloaker_settings.php') ?>">Site cloaker</a> (ayrı sistem)
    </p>
</div>

<?php include __DIR__ . '/../admin_footer_common.php'; ?>
