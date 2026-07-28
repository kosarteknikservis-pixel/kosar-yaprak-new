<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/social_click.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social_settings'])) {
    $show_whatsapp = isset($_POST['show_whatsapp']) ? 1 : 0;
    $show_instagram = isset($_POST['show_instagram']) ? 1 : 0;
    $whatsapp_number = isset($_POST['whatsapp_number']) ? trim($_POST['whatsapp_number']) : '';
    $instagram_username = isset($_POST['instagram_username']) ? trim($_POST['instagram_username']) : '';

    $whatsapp_number = social_whatsapp_normalize($whatsapp_number);
    $instagram_username = preg_replace('/[^A-Za-z0-9._]/', '', $instagram_username);

    $stmt = $pdo->prepare('UPDATE notification_settings
        SET show_whatsapp = ?, show_instagram = ?, whatsapp_number = ?, instagram_username = ?
        WHERE id = 1');
    $stmt->execute([$show_whatsapp, $show_instagram, $whatsapp_number, $instagram_username]);

    $_SESSION['message'] = 'Ayarlar güncellendi!';
    header('Location: buttons.php');
    exit();
}

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$stats = social_click_stats($pdo, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null);
$clickRows = social_click_list(
    $pdo,
    $dateFrom !== '' ? $dateFrom : null,
    $dateTo !== '' ? $dateTo : null,
    200
);

// Verileri getir
$stmt = $pdo->query('SELECT show_whatsapp, show_instagram, whatsapp_number, instagram_username FROM notification_settings WHERE id = 1');
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'show_whatsapp' => 0,
    'show_instagram' => 0,
    'whatsapp_number' => '',
    'instagram_username' => ''
];

$page_title = 'Sosyal Buton Ayarları';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-share-alt me-2"></i>Sosyal Medya Butonları</h2>
            <span class="text-muted">WhatsApp ve Instagram ayarları</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="save_social_settings" value="1">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="show_whatsapp" name="show_whatsapp" <?= !empty($settings['show_whatsapp']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_whatsapp">WhatsApp butonunu göster</label>
                    </div>
                    <label for="whatsapp_number" class="form-label">WhatsApp Numarası</label>
                    <div class="input-group">
                        <span class="input-group-text">+90</span>
                        <?php
                        $waDisplay = social_whatsapp_normalize((string) ($settings['whatsapp_number'] ?? ''));
                        if (str_starts_with($waDisplay, '90')) {
                            $waDisplay = substr($waDisplay, 2);
                        }
                        ?>
                        <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" value="<?= htmlspecialchars($waDisplay) ?>" placeholder="5xxxxxxxxx" inputmode="numeric" autocomplete="tel">
                    </div>
                    <div class="form-text">5 ile başlayan numara girin; kayıtta otomatik <strong>90</strong> eklenir (ör. 5512345678 → 905512345678).</div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="show_instagram" name="show_instagram" <?= !empty($settings['show_instagram']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_instagram">Instagram butonunu göster</label>
                    </div>
                    <label for="instagram_username" class="form-label">Instagram Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input type="text" class="form-control" id="instagram_username" name="instagram_username" value="<?= htmlspecialchars($settings['instagram_username']) ?>" placeholder="kullaniciadi">
                    </div>
                    <div class="form-text">Sadece kullanıcı adını giriniz.</div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="fas fa-chart-line me-1"></i> Buton tıklama istatistikleri</span>
            <small class="text-muted">Her IP, kanal başına yalnızca bir kez sayılır</small>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-4">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="date_from">Başlangıç</label>
                    <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="date_to">Bitiş</label>
                    <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                           value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i> Filtrele</button>
                    <a href="buttons.php" class="btn btn-sm btn-outline-secondary">Sıfırla</a>
                </div>
            </form>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="text-muted small">WhatsApp (benzersiz IP)</div>
                        <div class="fs-3 fw-bold text-success"><?= (int) $stats['whatsapp'] ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="text-muted small">Instagram (benzersiz IP)</div>
                        <div class="fs-3 fw-bold text-primary"><?= (int) $stats['instagram'] ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="text-muted small">Toplam</div>
                        <div class="fs-3 fw-bold"><?= (int) $stats['total'] ?></div>
                    </div>
                </div>
            </div>

            <?php if ($clickRows === []): ?>
                <p class="text-muted mb-0">Henüz kayıtlı tıklama yok.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kanal</th>
                                <th>IP</th>
                                <th>Sayfa</th>
                                <th>İlk tıklama tarihi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clickRows as $row): ?>
                                <?php
                                $ch = (string) ($row['channel'] ?? '');
                                $chLabel = $ch === 'whatsapp' ? 'WhatsApp' : ($ch === 'instagram' ? 'Instagram' : $ch);
                                $chClass = $ch === 'whatsapp' ? 'success' : 'primary';
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $chClass ?>"><?= htmlspecialchars($chLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="font-monospace small"><?= htmlspecialchars((string) ($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($row['page_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string) ($row['clicked_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>

