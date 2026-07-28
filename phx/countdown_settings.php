<?php
require '../db.php';
require 'auth.php';

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hours = (int)($_POST['hours'] ?? 0);
    $minutes = (int)($_POST['minutes'] ?? 0);
    $seconds = (int)($_POST['seconds'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $show_on_index = isset($_POST['show_on_index']) ? 1 : 0;
    $show_on_order = isset($_POST['show_on_order']) ? 1 : 0;

    // Zaman değerlerini kontrol et
    if ($hours < 0 || $hours > 23) {
        $_SESSION['message'] = 'Saat değeri 0-23 arasında olmalıdır!';
        $_SESSION['message_type'] = 'error';
    } elseif ($minutes < 0 || $minutes > 59) {
        $_SESSION['message'] = 'Dakika değeri 0-59 arasında olmalıdır!';
        $_SESSION['message_type'] = 'error';
    } elseif ($seconds < 0 || $seconds > 59) {
        $_SESSION['message'] = 'Saniye değeri 0-59 arasında olmalıdır!';
        $_SESSION['message_type'] = 'error';
    } else {
        $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;

        if ($total_seconds > 0) {
            // end_time güncellenmezse eski tarih kullanıcıya yansır; süre her kayıtta şimdiden yeniden başlar.
            $end_time = date('Y-m-d H:i:s', time() + $total_seconds);
            $stmt = $pdo->prepare('UPDATE countdown_timer SET total_seconds = ?, is_active = ?, show_on_index = ?, show_on_order = ?, end_time = ? WHERE id = 1');

            try {
                $stmt->execute([$total_seconds, $is_active, $show_on_index, $show_on_order, $end_time]);
                $_SESSION['message'] = 'Geri sayım saati başarıyla güncellendi!';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Hata: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Lütfen geçerli bir zaman girin (toplam süre 0\'dan büyük olmalıdır).';
            $_SESSION['message_type'] = 'error';
        }
    }

    header("Location: countdown_settings.php");
    exit();
}

// Verileri getir
$stmt = $pdo->query("SELECT * FROM countdown_timer WHERE id = 1");
$countdown = $stmt->fetch(PDO::FETCH_ASSOC);

// Varsayılan değerler
if (!$countdown) {
    $countdown = [
        'total_seconds' => 0,
        'is_active' => 0,
        'show_on_index' => 1,
        'show_on_order' => 0,
    ];
}

$hours = floor($countdown['total_seconds'] / 3600);
$minutes = floor(($countdown['total_seconds'] % 3600) / 60);
$seconds = $countdown['total_seconds'] % 60;

$page_title = 'Geri Sayım Saati Ayarları';
include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="top-bar mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-clock me-2"></i>Geri Sayım Saati Ayarları</h2>
            <span class="text-muted">Site geri sayım saatini yönetin</span>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] === 'error' ? 'danger' : 'success' ?> d-flex align-items-center">
            <i class="fas fa-<?= $_SESSION['message_type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Bilgi:</strong> Geri sayım süresini ayarlayın; ana sayfa ve sipariş sayfasında ayrı ayrı gösterip kapatabilirsiniz.
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label for="hours" class="form-label">Saat</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="hours" name="hours"
                               value="<?= htmlspecialchars($hours) ?>"
                               min="0" max="23" placeholder="0" required>
                        <span class="input-group-text">saat</span>
                    </div>
                    <div class="form-text">0-23 arasında bir değer giriniz.</div>
                </div>

                <div class="col-md-4">
                    <label for="minutes" class="form-label">Dakika</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="minutes" name="minutes"
                               value="<?= htmlspecialchars($minutes) ?>"
                               min="0" max="59" placeholder="0" required>
                        <span class="input-group-text">dakika</span>
                    </div>
                    <div class="form-text">0-59 arasında bir değer giriniz.</div>
                </div>

                <div class="col-md-4">
                    <label for="seconds" class="form-label">Saniye</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="seconds" name="seconds"
                               value="<?= htmlspecialchars($seconds) ?>"
                               min="0" max="59" placeholder="0" required>
                        <span class="input-group-text">saniye</span>
                    </div>
                    <div class="form-text">0-59 arasında bir değer giriniz.</div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               <?= !empty($countdown['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            <i class="fas fa-toggle-on me-1"></i> Geri sayım aktif
                        </label>
                    </div>
                    <div class="form-text mb-3">Kapalıyken aşağıdaki sayfa seçenekleri de geçersiz olur.</div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_on_index" name="show_on_index"
                               <?= ((int) ($countdown['show_on_index'] ?? 1)) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_on_index">
                            <i class="fas fa-home me-1"></i> Ana sayfada göster
                        </label>
                    </div>
                    <div class="form-text">Ürün listesinin üstündeki geri sayım bandı.</div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_on_order" name="show_on_order"
                               <?= ((int) ($countdown['show_on_order'] ?? 0)) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_on_order">
                            <i class="fas fa-shopping-cart me-1"></i> Sipariş sayfasında göster
                        </label>
                    </div>
                    <div class="form-text">Sipariş formu vitrininin üstündeki geri sayım bandı.</div>
                </div>

                <div class="col-12">
                    <div class="alert alert-light border small mb-0">
                        Bildirim ayarlarındaki gibi sayfa bazlı aç/kapat. Süre ve mesaj her iki sayfada ortaktır.
                    </div>
                </div>

                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-calculator me-2"></i>Toplam Süre Hesaplama</h6>
                            <p class="card-text mb-0">
                                <strong>Toplam Süre:</strong>
                                <span id="total-time"><?= $hours ?> saat, <?= $minutes ?> dakika, <?= $seconds ?> saniye</span>
                                <br>
                                <strong>Toplam Saniye:</strong>
                                <span id="total-seconds"><?= $countdown['total_seconds'] ?></span> saniye
                            </p>
                        </div>
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

<script>
// Gerçek zamanlı hesaplama
document.addEventListener('DOMContentLoaded', function() {
    const hoursInput = document.getElementById('hours');
    const minutesInput = document.getElementById('minutes');
    const secondsInput = document.getElementById('seconds');
    const totalTimeSpan = document.getElementById('total-time');
    const totalSecondsSpan = document.getElementById('total-seconds');

    function updateTotal() {
        const hours = parseInt(hoursInput.value) || 0;
        const minutes = parseInt(minutesInput.value) || 0;
        const seconds = parseInt(secondsInput.value) || 0;

        const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;

        totalTimeSpan.textContent = `${hours} saat, ${minutes} dakika, ${seconds} saniye`;
        totalSecondsSpan.textContent = totalSeconds;
    }

    hoursInput.addEventListener('input', updateTotal);
    minutesInput.addEventListener('input', updateTotal);
    secondsInput.addEventListener('input', updateTotal);
});
</script>

<?php include 'admin_footer_common.php'; ?>
