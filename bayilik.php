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
info_page_track_view($pdo, 'bayilik.php');

$message = '';
$message_type = 'error';
$form_submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_submitted = true;
    $current_time = time();
    $last_request_time = $_SESSION['last_dealer_request'] ?? 0;
    $time_difference = $current_time - $last_request_time;

    if ($last_request_time && $time_difference < 1200) {
        $remaining_minutes = ceil((1200 - $time_difference) / 60);
        $message = "Son başvurunuzun üzerinden 20 dakika geçmeden yeni başvuru yapamazsınız. Lütfen {$remaining_minutes} dakika sonra tekrar deneyiniz.";
        $message_type = 'error';
    } else {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $company_name = $_POST['company_name'] ?? '';
        $address = $_POST['address'] ?? '';
        $note = $_POST['note'] ?? '';
        $role = $_POST['role'] ?? '';
        $site = app_site_url($pdo);

        if (! $name || ! $email || ! $phone || ! $company_name || ! $address || ! $role) {
            $message = 'Lütfen tüm zorunlu alanları doldurunuz.';
            $message_type = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO dealer_requests (name, email, phone, company_name, address, note, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, $company_name, $address, $note, $role]);
            $dealerRequestId = (string) $pdo->lastInsertId();

            laravel4_sync_form('bayilik-basvuru-v1', [
                'ad_soyad' => $name,
                'telefon' => $phone,
                'e_posta' => $email,
                'sirket' => $company_name,
                'adres' => $address,
                'basvuru_turu' => $role,
                'ek_not' => $note,
            ], $dealerRequestId, [
                'site_url' => $site,
            ]);

            $_SESSION['last_dealer_request'] = $current_time;
            $message = 'Başvurunuz başarıyla gönderildi. Sizinle en kısa sürede iletişime geçilecektir.';
            $message_type = 'success';

            $telegram_message = "Yeni Bayilik Başvurusu: \n"
                . "Ad Soyad: $name\n"
                . "E-posta: $email\n"
                . "Telefon: $phone\n"
                . "Şirket: $company_name\n"
                . "Adres: $address\n"
                . "Tür: $role\n"
                . "Not: $note\n"
                . "URL: $site";
            sendTelegramNotification($pdo, 'dealer', $telegram_message);
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
            <label for="email">E-posta Adresiniz</label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="30">
        </div>
        <div class="form-group">
            <label for="phone">Telefon Numaranız</label>
            <input type="tel" class="form-control" id="phone" name="phone" required maxlength="12" pattern="[0-9\s]*" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9\s]/g, '');">
        </div>
        <div class="form-group">
            <label for="company_name">Şirket Adı (yoksa adınız)</label>
            <input type="text" class="form-control" id="company_name" name="company_name" required maxlength="35">
        </div>
        <div class="form-group">
            <label for="address">Adresiniz</label>
            <textarea class="form-control" id="address" name="address" rows="2" required maxlength="150"></textarea>
        </div>
        <div class="form-group">
            <label for="role">Başvuru Türü</label>
            <select class="form-control" id="role" name="role" required>
                <option value="" disabled selected>Başvuru türünü seçiniz</option>
                <option value="Toptancı">Toptancı</option>
                <option value="Perakende">Perakende</option>
                <option value="Dağıtıcı">Dağıtıcı</option>
            </select>
        </div>
        <div class="form-group">
            <label for="note">Mesajınız</label>
            <textarea class="form-control" id="note" name="note" rows="2" required maxlength="200"></textarea>
        </div>
        <button type="submit" class="info-form__submit"><i class="fas fa-paper-plane"></i> Başvuruyu Gönder</button>
    </form>
</div>
<?php
$slotHtml = (string) ob_get_clean();

$extraScripts = '<script>if(typeof window.paTrackSafe==="function"){window.paTrackSafe("page_dealer");';
if ($form_submitted) {
    $extraScripts .= 'window.paTrackSafe("dealer_form_submit");';
}
$extraScripts .= '}</script>';

info_page_render($pdo, [
    'page_file' => 'bayilik.php',
    'title' => 'Bayilik Başvurusu',
    'body_html' => '',
    'track_event' => '',
    'show_faq' => false,
    'slot_html' => $slotHtml,
    'extra_scripts' => $extraScripts,
    'hero_badge' => 'İş ortaklığı',
    'hero_badge_icon' => 'fa-handshake',
    'hero_lead' => 'Bayi veya distribütör olmak için başvurunuzu iletin; ekibimiz sizinle iletişime geçsin.',
    'trust_pills' => [
        ['icon' => 'fa-store', 'label' => 'Toptan & perakende'],
        ['icon' => 'fa-truck', 'label' => '1–3 iş günü kargo'],
        ['icon' => 'fa-headset', 'label' => 'Özel destek'],
    ],
    'cta_title' => 'Önce tanışalım',
    'cta_text' => 'Mağazamızı inceleyin veya destek hattımıza yazın.',
]);
