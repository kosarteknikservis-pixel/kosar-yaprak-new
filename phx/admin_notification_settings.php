<?php
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_message = trim($_POST['notification_message'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $show_discount_badge_index = isset($_POST['show_discount_badge_index']) ? 1 : 0;
    $show_discount_badge_order = isset($_POST['show_discount_badge_order']) ? 1 : 0;

    $stmt = $pdo->prepare(
        'UPDATE notification_settings SET message = ?, is_active = ?, animated_message = ?, show_animated_message = 0, show_discount_badge_index = ?, show_discount_badge_order = ? WHERE id = 1'
    );

    try {
        $stmt->execute([$notification_message, $is_active, '', $show_discount_badge_index, $show_discount_badge_order]);
        content_t_save($pdo, 'notification', 1, 'message', 'en', (string) ($_POST['notification_message_en'] ?? ''));
        content_t_save($pdo, 'notification', 1, 'message', 'ar', (string) ($_POST['notification_message_ar'] ?? ''));
        $_SESSION['message'] = 'Bildirim ayarları başarıyla güncellendi!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Hata: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }

    header('Location: admin_notification_settings.php');
    exit();
}

$stmt = $pdo->query('SELECT * FROM notification_settings WHERE id = 1');
$notification = $stmt->fetch(PDO::FETCH_ASSOC);
$nsEn = content_t_load_lang($pdo, 'notification', 1, 'en');
$nsAr = content_t_load_lang($pdo, 'notification', 1, 'ar');

if (!$notification) {
    $notification = [
        'message' => '',
        'is_active' => 0,
        'show_discount_badge_index' => 1,
        'show_discount_badge_order' => 1,
    ];
}

$page_title = 'Bildirim Ayarları';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="mb-0"><i class="fas fa-bell me-2"></i>Bildirim Ayarları</h2>
            <span class="text-muted small">Üst kampanya şeridi + indirim rozeti</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; ?>
        <div class="alert alert-<?= $mtp === 'error' ? 'danger' : 'success' ?> d-flex align-items-center">
            <i class="fas fa-<?= $mtp === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label for="notification_message" class="form-label">Bildirim Mesajı (üst turuncu şerit)</label>
                    <input type="text" class="form-control" id="notification_message" name="notification_message"
                           value="<?= htmlspecialchars((string) ($notification['message'] ?? '')) ?>"
                           placeholder="EKİM AYI | TEK FİYAT" required>
                    <div class="form-text">
                        Ana sayfa ve sipariş sayfasının en üstündeki şerit. İki satır için ortada <strong>|</strong> kullanın.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="notification_message_en" class="form-label">Bildirim (EN)</label>
                    <input type="text" class="form-control" id="notification_message_en" name="notification_message_en"
                           value="<?= htmlspecialchars((string) ($nsEn['message'] ?? '')) ?>"
                           placeholder="AUGUST SINGLE PRICE | LAST SUMMER DISCOUNT TODAY">
                </div>
                <div class="col-md-6">
                    <label for="notification_message_ar" class="form-label">Bildirim (AR)</label>
                    <input type="text" class="form-control" id="notification_message_ar" name="notification_message_ar" dir="rtl"
                           value="<?= htmlspecialchars((string) ($nsAr['message'] ?? '')) ?>">
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               <?= !empty($notification['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            <i class="fas fa-toggle-on me-1"></i> Üst şerit aktif
                        </label>
                    </div>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-6">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_discount_badge_index" name="show_discount_badge_index"
                               <?= ((int) ($notification['show_discount_badge_index'] ?? 1)) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_discount_badge_index">
                            <i class="fas fa-home me-1"></i> Ana sayfa — indirim rozeti
                        </label>
                    </div>
                    <div class="form-text">Ürün kartlarında satış fiyatının altındaki <code>% XX indirim</code> pill rozeti.</div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_discount_badge_order" name="show_discount_badge_order"
                               <?= ((int) ($notification['show_discount_badge_order'] ?? 1)) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_discount_badge_order">
                            <i class="fas fa-shopping-cart me-1"></i> Sipariş sayfası — indirim rozeti
                        </label>
                    </div>
                    <div class="form-text">Sipariş vitrininde fiyat bloğunun altındaki aynı rozet.</div>
                </div>

                <div class="col-12">
                    <div class="alert alert-light border small mb-0">
                        Rozet yüzdesi liste fiyatı ile satış fiyatından otomatik hesaplanır; fiyatlara dokunmaz.
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
