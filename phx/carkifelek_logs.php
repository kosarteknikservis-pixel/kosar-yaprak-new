<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$page_title = 'Çark Logları';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_logs'])) {
    if (($_POST['reset_confirm'] ?? '') === 'SIFIRLA') {
        try {
            $pdo->exec('DELETE FROM carkifelek_log');
            $pdo->exec('ALTER TABLE carkifelek_log AUTO_INCREMENT = 1');
            $_SESSION['message'] = 'Çark logları temizlendi.';
            $_SESSION['message_type'] = 'success';
        } catch (Throwable $e) {
            $_SESSION['message'] = 'Temizlik hatası: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = 'Onay metni hatalı.';
        $_SESSION['message_type'] = 'error';
    }
    header('Location: carkifelek_logs.php');
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$q = trim((string) ($_GET['q'] ?? ''));

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND ip LIKE ?';
    $params[] = '%' . $q . '%';
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM carkifelek_log WHERE {$where}");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, ip, tarih FROM carkifelek_log WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = (int) $pdo->query('SELECT COUNT(*) FROM carkifelek_log WHERE tarih >= ' . (int) strtotime('today'))->fetchColumn();
$totalAll = (int) $pdo->query('SELECT COUNT(*) FROM carkifelek_log')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

include 'admin_header.php';
?>

<div class="container-fluid px-3 px-lg-4 pb-4">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= ($_SESSION['message_type'] ?? '') === 'error' ? 'danger' : 'success' ?>">
            <?= htmlspecialchars((string) $_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-list-alt text-primary me-2"></i>Çark logları</h1>
            <p class="text-muted small mb-0">IP bazlı günlük çevirme kayıtları</p>
        </div>
        <div class="d-flex gap-2">
            <a href="carkifelek_settings.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-cog me-1"></i> Ayarlar</a>
            <form method="post" id="carkLogResetForm" class="m-0">
                <input type="hidden" name="reset_logs" value="1">
                <input type="hidden" name="reset_confirm" id="carkLogResetConfirm" value="">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmCarkLogReset()"><i class="fas fa-trash-alt me-1"></i> Logları sıfırla</button>
            </form>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-auto"><div class="inbox-stat"><strong><?= $today ?></strong><span>Bugün</span></div></div>
        <div class="col-auto"><div class="inbox-stat"><strong><?= $totalAll ?></strong><span>Toplam</span></div></div>
        <div class="col-auto"><div class="inbox-stat"><strong><?= $total ?></strong><span>Filtre</span></div></div>
    </div>

    <form method="get" class="orders-ws-filters mb-3">
        <div class="orders-ws-filters__row">
            <div style="min-width:200px;">
                <label class="form-label" for="q">IP ara</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-primary btn-sm">Ara</button>
                <?php if ($q !== ''): ?><a href="carkifelek_logs.php" class="btn btn-outline-secondary btn-sm">Sıfırla</a><?php endif; ?>
            </div>
        </div>
    </form>

    <div class="inbox-table-wrap">
        <table class="inbox-table">
            <thead>
                <tr><th>#</th><th>IP</th><th>Tarih</th></tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Kayıt yok.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><code><?= htmlspecialchars((string) $r['ip'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= date('d.m.Y H:i:s', (int) $r['tarih']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="mt-3">
            <?php
            require_once __DIR__ . '/../includes/admin_pagination.php';
            admin_render_pagination($page, $totalPages, static function (int $p): string {
                return '?' . http_build_query(array_merge($_GET, ['page' => $p]));
            });
            ?>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="<?= htmlspecialchars((ADMIN_WEB_ROOT !== '' ? ADMIN_WEB_ROOT . '/' : '') . 'css/record-drawer.css', ENT_QUOTES, 'UTF-8') ?>">
<script>
function confirmCarkLogReset() {
    Swal.fire({
        title: 'Loglar silinsin mi?',
        text: 'Tüm çark log kayıtları kalıcı olarak silinir.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sıfırla',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#dc3545',
        input: 'text',
        inputPlaceholder: 'SIFIRLA yazın',
        preConfirm: function (v) {
            if ((v || '').trim().toUpperCase() !== 'SIFIRLA') {
                Swal.showValidationMessage('SIFIRLA yazmalısınız');
                return false;
            }
            return v;
        }
    }).then(function (r) {
        if (r.isConfirmed) {
            document.getElementById('carkLogResetConfirm').value = 'SIFIRLA';
            document.getElementById('carkLogResetForm').submit();
        }
    });
}
</script>
<?php include 'admin_footer_common.php'; ?>
