<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/admin_cc_helpers.php';
require_once __DIR__ . '/../includes/abandoned_recovery.php';

$q = trim((string) ($_GET['q'] ?? ''));
$todayOnly = isset($_GET['today']) && $_GET['today'] === '1';
$hasPhone = isset($_GET['phone']) && $_GET['phone'] === '1';

$conds = [];
$params = [];
if ($q !== '') {
    $conds[] = '(ad LIKE ? OR tel LIKE ? OR urun LIKE ? OR ip LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($todayOnly) {
    $conds[] = 'DATE(tarih) = CURDATE()';
}
if ($hasPhone) {
    $conds[] = "tel IS NOT NULL AND TRIM(tel) != ''";
}
$where = $conds !== [] ? 'WHERE ' . implode(' AND ', $conds) : '';

$statTotal = (int) $pdo->query('SELECT COUNT(*) FROM yarim_kalanlar')->fetchColumn();
$statToday = (int) $pdo->query("SELECT COUNT(*) FROM yarim_kalanlar WHERE DATE(tarih) = CURDATE()")->fetchColumn();
$statPhone = (int) $pdo->query("SELECT COUNT(*) FROM yarim_kalanlar WHERE tel IS NOT NULL AND TRIM(tel) != ''")->fetchColumn();
$statTodayPhone = (int) $pdo->query("SELECT COUNT(*) FROM yarim_kalanlar WHERE DATE(tarih) = CURDATE() AND tel IS NOT NULL AND TRIM(tel) != ''")->fetchColumn();
$statConverted = 0;
try {
    $statConverted = (int) $pdo->query("SELECT COUNT(*) FROM yarim_kalanlar WHERE IFNULL(is_converted,0) = 1")->fetchColumn();
} catch (Throwable $e) {
    $statConverted = 0;
}

$listStmt = $pdo->prepare("SELECT * FROM yarim_kalanlar {$where} ORDER BY tarih DESC LIMIT 500");
$listStmt->execute($params);
$list = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$blockedIps = [];
$blockedPhones = [];
try {
    $blockedIps = $pdo->query('SELECT ip FROM blocked_ips')->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $blockedIps = [];
}
try {
    $blockedPhones = $pdo->query('SELECT phone_digits FROM blocked_phones')->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $blockedPhones = [];
}
$blockedIpSet = array_fill_keys(array_map('strval', $blockedIps), true);
$blockedPhoneSet = array_fill_keys(array_map('strval', $blockedPhones), true);

if (isset($_GET['del']) && ctype_digit((string) $_GET['del'])) {
    $id = (int) $_GET['del'];
    $pdo->prepare('DELETE FROM yarim_kalanlar WHERE id = ?')->execute([$id]);
    $_SESSION['message'] = 'Kayıt silindi.';
    header('Location: abandoned_orders.php');
    exit;
}

$page_title = 'Yarım kalan siparişler';
include 'admin_header.php';
?>

<div class="container-fluid py-3 abandoned-admin-page orders-admin-page">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success py-2 mb-3">
            <i class="fas fa-check-circle me-1"></i>
            <?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div class="orders-page-header">
        <div>
            <h2><i class="fas fa-hourglass-half text-warning me-1"></i> Yarım kalan siparişler</h2>
            <p class="text-muted mb-0 small">Form, kaydırma veya ürünler bölümü tetiklenince kaydedilen kartlar — çağrı merkezi geri dönüş listesi. <a href="abandoned_settings.php">Yakalama ayarları</a></p>
        </div>
        <div class="orders-page-header__meta">
            <span class="badge bg-<?= $statTodayPhone > 0 ? 'warning text-dark' : 'secondary' ?>"><?= $statTodayPhone ?> bugün tel.</span>
            <span class="badge bg-primary"><?= $statTotal ?> kayıt</span>
            <a href="orders.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-shopping-cart"></i> Tam siparişler</a>
        </div>
    </div>

    <div class="cc-stat-grid">
        <div class="cc-stat">
            <div class="cc-stat__val"><?= $statTodayPhone ?></div>
            <div class="cc-stat__lbl">Bugün telefonlu</div>
        </div>
        <div class="cc-stat">
            <div class="cc-stat__val"><?= $statToday ?></div>
            <div class="cc-stat__lbl">Bugün toplam</div>
        </div>
        <div class="cc-stat">
            <div class="cc-stat__val"><?= $statPhone ?></div>
            <div class="cc-stat__lbl">Telefonlu kayıt</div>
        </div>
        <div class="cc-stat">
            <div class="cc-stat__val"><?= $statTotal ?></div>
            <div class="cc-stat__lbl">Tüm kayıtlar</div>
        </div>
        <div class="cc-stat">
            <div class="cc-stat__val"><?= $statConverted ?></div>
            <div class="cc-stat__lbl">Siparişe dönen</div>
        </div>
    </div>

    <div class="cc-toolbar">
        <span class="cc-toolbar__label">Hızlı</span>
        <a class="cc-chip cc-chip--warn" href="abandoned_orders.php?today=1&amp;phone=1"><i class="fas fa-bolt"></i> Bugün + telefon <strong><?= $statTodayPhone ?></strong></a>
        <a class="cc-chip cc-chip--primary" href="abandoned_orders.php?phone=1"><i class="fas fa-phone"></i> Telefonlu <strong><?= $statPhone ?></strong></a>
        <a class="cc-chip cc-chip--primary" href="abandoned_orders.php?today=1"><i class="fas fa-calendar-day"></i> Bugün <strong><?= $statToday ?></strong></a>
        <a class="cc-chip cc-chip--primary" href="abandoned_orders.php"><i class="fas fa-list"></i> Tümü</a>
    </div>

    <div class="orders-panel">
        <div class="orders-panel__head">
            <span><i class="fas fa-search me-1"></i> Ara (ad, tel, ürün, IP)</span>
            <?php if ($q !== '' || $todayOnly || $hasPhone): ?>
                <a href="abandoned_orders.php" class="btn btn-sm btn-outline-secondary">Filtreyi sıfırla</a>
            <?php endif; ?>
        </div>
        <div class="orders-panel__body">
            <form method="get" class="abandoned-search-form">
                <?php if ($todayOnly): ?><input type="hidden" name="today" value="1"><?php endif; ?>
                <?php if ($hasPhone): ?><input type="hidden" name="phone" value="1"><?php endif; ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-8 col-lg-9">
                        <label for="q" class="form-label small text-muted mb-1">Anahtar kelime</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-magnifying-glass text-muted"></i></span>
                            <input type="search" class="form-control" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Örn. 532, Ahmet, ürün adı veya IP">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filtrele</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($list === []): ?>
        <div class="orders-panel">
            <div class="orders-panel__body">
                <div class="alert alert-info border-0 mb-0">Kayıt yok veya filtreye uygun sonuç bulunamadı.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="orders-panel">
            <div class="orders-panel__head">
                <span><i class="fas fa-table-list me-1"></i> Liste</span>
                <span class="text-muted small fw-normal"><?= count($list) ?> kayıt<?= count($list) >= 500 ? ' (en fazla 500)' : '' ?></span>
            </div>
            <div class="orders-panel__body p-0">
        <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 cc-table-compact">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ad</th>
                            <th>Telefon</th>
                            <th>Ürün</th>
                            <th>Fiyat</th>
                            <th>Kaynak</th>
                            <th>Tarih</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r): ?>
                            <?php
                            $rip = (string) ($r['ip'] ?? '');
                            $rtel = (string) ($r['tel'] ?? '');
                            $last10 = cc_phone_tail10($rtel);
                            $isBlocked = ($rip !== '' && isset($blockedIpSet[$rip]))
                                || ($last10 !== '' && (isset($blockedPhoneSet[$last10]) || isset($blockedPhoneSet[ltrim($last10, '0') ?: $last10])));
                            $isFresh = !empty($r['tarih']) && date('Y-m-d', strtotime((string) $r['tarih'])) === date('Y-m-d');
                            $isConverted = (int) ($r['is_converted'] ?? 0) === 1;
                            $smsSent = ! empty($r['recovery_sms_sent_at']);
                            $waText = abandoned_recovery_staff_whatsapp_prefill(
                                (int) $r['id'],
                                (string) ($r['ad'] ?? ''),
                                (string) ($r['urun'] ?? '')
                            );
                            $rowCls = $isConverted
                                ? 'cc-row-converted'
                                : ($isBlocked ? 'cc-row-blocked' : ($isFresh ? 'cc-row-fresh' : ''));
                            $utm = array_filter([
                                $r['utm_source'] ?? null,
                                $r['utm_medium'] ?? null,
                                $r['utm_campaign'] ?? null,
                            ]);
                            ?>
                            <tr class="<?= $rowCls ?>">
                                <td><?= (int) $r['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars((string) ($r['ad'] ?? '—')) ?></strong>
                                    <?php if ($isConverted): ?>
                                        <span class="cc-badge cc-badge--ok ms-1">Siparişe döndü<?= !empty($r['converted_order_id']) ? ' #'.(int) $r['converted_order_id'] : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($smsSent): ?>
                                        <span class="cc-badge cc-badge--muted ms-1">SMS gönderildi</span>
                                    <?php endif; ?>
                                    <?php if ($isBlocked): ?>
                                        <span class="cc-badge cc-badge--bad ms-1">Engelli</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= cc_phone_actions_html($rtel, true, $waText) ?>
                                    <?php if ($rtel !== ''): ?>
                                        <div class="mt-1">
                                            <a href="orders.php?customer_phone=<?= urlencode($rtel) ?>" class="small text-muted">Sipariş ara →</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small" title="<?= htmlspecialchars((string) ($r['urun'] ?? '')) ?>">
                                    <?= htmlspecialchars(mb_substr((string) ($r['urun'] ?? ''), 0, 72)) ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($r['fiyat'] ?? '')) ?></td>
                                <td class="small text-muted">
                                    <?php if (!empty($r['ad_source'])): ?>
                                        <span class="cc-badge cc-badge--muted"><?= htmlspecialchars((string) $r['ad_source']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($utm !== []): ?>
                                        <div><?= htmlspecialchars(implode(' / ', $utm)) ?></div>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap small"><?= htmlspecialchars((string) ($r['tarih'] ?? '')) ?></td>
                                <td class="text-nowrap">
                                    <a href="abandoned_orders.php?del=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silinsin mi?');" title="Sil">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
