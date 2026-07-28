<?php
require 'db.php';
require 'telegram.php';
require_once __DIR__ . '/includes/app_url.php';
require_once __DIR__ . '/includes/info_pages.php';
require_once __DIR__ . '/includes/laravel4_sync.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Istanbul');
info_page_track_view($pdo, 'destek_talebi.php');

$message = '';
$message_type = 'error';
$form_submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_submitted = true;
    $current_time = time();
    $last_request_time = $_SESSION['last_support_request'] ?? 0;
    $time_difference = $current_time - $last_request_time;

    if ($last_request_time && $time_difference < 1200) {
        $remaining_minutes = ceil((1200 - $time_difference) / 60);
        $message = "Son talebinizin üzerinden 20 dakika geçmeden yeni talep açamazsınız. Lütfen {$remaining_minutes} dakika sonra tekrar deneyiniz.";
        $message_type = 'error';
    } else {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $note = $_POST['note'] ?? '';
        $site = app_site_url($pdo);

        if (! $name || ! $email || ! $phone || ! $subject || ! $note) {
            $message = 'Lütfen tüm zorunlu alanları doldurunuz.';
            $message_type = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO support_requests (name, email, phone, subject, note) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, $subject, $note]);
            $supportRequestId = (string) $pdo->lastInsertId();

            laravel4_sync_form('destek-talebi-v1', [
                'ad_soyad' => $name,
                'telefon' => $phone,
                'konu' => $subject,
                'mesaj' => "E-posta: {$email}\n\n{$note}",
            ], $supportRequestId, [
                'site_url' => $site,
            ]);

            $_SESSION['last_support_request'] = $current_time;
            $message = 'Destek talebiniz başarıyla gönderildi. En kısa sürede sizinle iletişime geçilecektir.';
            $message_type = 'success';

            $telegram_message = "Yeni Destek Talebi: \nAd Soyad: $name\nE-posta: $email\nTelefon: $phone\nKonu: $subject\nMesaj: $note\nURL: $site";
            sendTelegramNotification($pdo, 'support', $telegram_message);
        }
    }
}

ob_start();
?>
<div class="sss-form-panel">
    <?php if ($message): ?>
        <div class="info-alert <?= $message_type === 'error' ? 'info-alert--error' : 'info-alert--success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="info-form">
        <div class="form-group">
            <label for="name">Adınız ve Soyadınız</label>
            <input type="text" class="form-control" id="name" name="name" required maxlength="30">
        </div>
        <div class="form-group">
            <label for="email">E-Posta Adresiniz</label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="30">
        </div>
        <div class="form-group">
            <label for="phone">Telefon Numaranız</label>
            <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9\s]*" inputmode="numeric" maxlength="12" oninput="this.value = this.value.replace(/[^0-9\s]/g, '');">
        </div>
        <div class="form-group">
            <label for="note">Mesajınız</label>
            <textarea class="form-control" id="note" name="note" rows="3" required maxlength="200"></textarea>
        </div>
        <div class="form-group">
            <label for="subject">İlgili Konuyu Seçiniz</label>
            <select class="form-control" id="subject" name="subject" required>
                <option value="" disabled selected>Lütfen bir konu seçiniz</option>
                <option value="Eksik Bilgim Var">Eksik Bilgim Var</option>
                <option value="Siparişim Gelmedi">Siparişim Gelmedi</option>
                <option value="Tekrar Sipariş Vermek İstiyorum">Tekrar Sipariş Vermek İstiyorum</option>
                <option value="Bilgi Güncelleme">Bilgi Güncelleme</option>
                <option value="Sipariş İptali">Sipariş İptali</option>
            </select>
        </div>
        <button type="submit" class="info-form__submit"><i class="fas fa-paper-plane"></i> Gönder</button>
    </form>
</div>
<?php
$slotHtml = (string) ob_get_clean();

$extraScripts = '<script>if(typeof window.paTrackSafe==="function"){window.paTrackSafe("page_support");';
if ($form_submitted) {
    $extraScripts .= 'window.paTrackSafe("support_form_submit");';
}
$extraScripts .= '}</script>';

info_page_render($pdo, [
    'page_file' => 'destek_talebi.php',
    'title' => 'Destek Talebi',
    'body_html' => '',
    'track_event' => '',
    'show_faq' => false,
    'slot_html' => $slotHtml,
    'extra_scripts' => $extraScripts,
    'hero_badge' => 'Destek',
    'hero_badge_icon' => 'fa-headset',
    'hero_lead' => 'Sipariş, teslimat veya genel sorularınız için formu doldurun — en kısa sürede dönüş yapalım.',
    'cta_title' => 'Siparişinizi mi arıyorsunuz?',
    'cta_text' => 'Telefon numaranızla anında sipariş durumunu kontrol edebilirsiniz.',
]);
