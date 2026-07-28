<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/admin_cc_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM custom_forms WHERE id = ?');
$st->execute([$id]);
$form = $st->fetch(PDO::FETCH_ASSOC);
if (!$form) {
    header('Location: custom_forms.php');
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$off = ($page - 1) * $perPage;

$where = 'form_id = ?';
$params = [$id];
if ($q !== '') {
    $where .= ' AND (payload_json LIKE ? OR ip LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$cSt = $pdo->prepare("SELECT COUNT(*) FROM custom_form_entries WHERE {$where}");
$cSt->execute($params);
$total = (int) $cSt->fetchColumn();

$lst = $pdo->prepare(
    "SELECT id, created_at, ip, LEFT(user_agent, 120) AS ua, payload_json FROM custom_form_entries
     WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?"
);
$bindIdx = 1;
foreach ($params as $p) {
    $lst->bindValue($bindIdx++, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$lst->bindValue($bindIdx++, $perPage, PDO::PARAM_INT);
$lst->bindValue($bindIdx, $off, PDO::PARAM_INT);
$lst->execute();
$entries = $lst->fetchAll(PDO::FETCH_ASSOC);

$todaySt = $pdo->prepare('SELECT COUNT(*) FROM custom_form_entries WHERE form_id = ? AND DATE(created_at) = CURDATE()');
$todaySt->execute([$id]);
$todayCount = (int) $todaySt->fetchColumn();

$page_title = 'Form başvuruları';
include 'admin_header.php';
$pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
?>

<div class="container-fluid py-3">
    <nav class="small mb-2"><a href="custom_forms.php">← Başvuru formları</a></nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h5 mb-1"><?= htmlspecialchars((string) ($form['title'] ?? '')) ?> — gönderilenler</h1>
            <p class="text-muted small mb-0"><?= (int) $total ?> kayıt<?= $q !== '' ? ' (filtreli)' : '' ?> · bugün <?= $todayCount ?></p>
        </div>
        <a href="custom_form_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Alanları düzenle</a>
    </div>

    <form method="get" class="cc-filter-bar">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div>
            <label for="q">Ara (içerik veya IP)</label>
            <input type="search" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Telefon, isim, metin…">
        </div>
        <div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Ara</button>
            <?php if ($q !== ''): ?>
                <a href="custom_form_entries.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm ms-1">Sıfırla</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($total === 0): ?>
        <p class="text-muted">Henüz başvuru yok veya arama sonucu bulunamadı.</p>
    <?php else: ?>
        <div class="vstack gap-3">
            <?php foreach ($entries as $e): ?>
                <?php
                $payload = json_decode((string) ($e['payload_json'] ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                $phone = cc_extract_phone_from_payload($payload);
                ?>
                <div class="cc-entry-card">
                    <div class="cc-entry-card__head">
                        <span><strong>#<?= (int) $e['id'] ?></strong> · <?= htmlspecialchars((string) $e['created_at']) ?></span>
                        <span><?= htmlspecialchars((string) $e['ip']) ?></span>
                    </div>
                    <?php if ($phone !== ''): ?>
                        <div class="px-3 py-2 border-bottom bg-light">
                            <?= cc_phone_actions_html($phone) ?>
                            <a href="orders.php?customer_phone=<?= urlencode($phone) ?>" class="small ms-2">Sipariş ara →</a>
                        </div>
                    <?php endif; ?>
                    <div class="cc-entry-card__body">
                        <table class="cc-entry-kv">
                            <?php foreach ($payload as $k => $v): ?>
                                <tr>
                                    <th><?= htmlspecialchars((string) $k) ?></th>
                                    <td><?= nl2br(htmlspecialchars(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <?php
        require_once __DIR__ . '/../includes/admin_pagination.php';
        admin_render_pagination($page, $pages, static function (int $p) use ($id, $q): string {
            $qs = ['id' => $id, 'page' => $p];
            if ($q !== '') {
                $qs['q'] = $q;
            }

            return 'custom_form_entries.php?' . http_build_query($qs);
        });
        ?>
    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
