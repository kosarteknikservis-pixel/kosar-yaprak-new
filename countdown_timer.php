<?php
require 'db.php';

// Countdown ayarlarını veritabanından çek
$stmt = $pdo->query("SELECT * FROM countdown_timer WHERE id = 1");
$countdown = $stmt->fetch(PDO::FETCH_ASSOC);

// Countdown aktif mi ve bu sayfada gösterilsin mi?
$countdownPage = !empty($GLOBALS['ORDER_PAGE_UI_ACTIVE']) ? 'order' : 'index';
$countdownAllowed = $countdownPage === 'order'
    ? ((int) ($countdown['show_on_order'] ?? 0)) === 1
    : ((int) ($countdown['show_on_index'] ?? 1)) === 1;

if ($countdown && $countdown['is_active'] && $countdownAllowed) {
    /** Bitiş zamanı: DB end_time gelecekteyse onu kullan; değilse total_seconds ile şimdiye göre hesapla (panel sadece total_seconds güncellediğinde eski end_time yakalayıcısı) */
    $totalSecCd = max(0, (int) ($countdown['total_seconds'] ?? 0));
    $dbEndCd = isset($countdown['end_time']) ? trim((string) $countdown['end_time']) : '';
    $parsedEndCd = $dbEndCd !== '' ? strtotime($dbEndCd) : false;
    $deadlineUnixCd = ($parsedEndCd !== false && $parsedEndCd > time())
        ? $parsedEndCd
        : (time() + $totalSecCd);
    /** @suppress */
    $countdownDeadlineMs = $deadlineUnixCd * 1000;

    $end_time = $countdown['end_time'] ?? date('Y-m-d H:i:s', time() + $countdown['total_seconds']);
    $countdownLabelDefault = function_exists('t') ? t('cd.soon', 'İndirim süresi dolmak üzere!') : 'İndirim süresi dolmak üzere!';
    $normalizeCountdownLabel = static function (string $raw, string $default): string {
        $t = trim($raw);
        if ($t === '') {
            return $default;
        }
        // Eski/yanlış kopya: süre hâlâ akarken "doldu" göstermesin
        if (preg_match('/indirim\s+süresi\s+doldu/i', $t)) {
            return $default;
        }

        return $t;
    };
    $message = $normalizeCountdownLabel((string) ($countdown['message'] ?? ''), $countdownLabelDefault);
    $lblGun = function_exists('t') ? t('cd.day', 'Gün') : 'Gün';

    /** @suppress */
    $lblSaat = function_exists('t') ? t('cd.hour', 'Saat') : 'Saat';

    /** @suppress */
    $lblDakika = function_exists('t') ? t('cd.min', 'Dakika') : 'Dakika';

    /** @suppress */
    $lblSaniye = function_exists('t') ? t('cd.sec', 'Saniye') : 'Saniye';


    /** @suppress */
    /** @suppress */
    /** @suppress */
    /** @suppress */
    $cdSkinActive = !empty($GLOBALS['ORDER_PAGE_UI_ACTIVE']);

    if ($cdSkinActive) {

        /** @suppress */
        /** @suppress */
        /** @suppress */
        /** @suppress */
        if (!function_exists('order_page_ui_hex')) {
            /** @suppress */
            require_once __DIR__ . '/includes/order_page_ui.php';


        }



        /** @suppress */
        $sk = isset($GLOBALS['ORDER_PAGE_COUNTDOWN_SKIN']) && is_array($GLOBALS['ORDER_PAGE_COUNTDOWN_SKIN'])
            ? $GLOBALS['ORDER_PAGE_COUNTDOWN_SKIN']
            : [];


        /** @suppress */
        $ov = trim((string) ($sk['message_override'] ?? ''));


        if ($ov !== '') {
            $message = $normalizeCountdownLabel($ov, $countdownLabelDefault);
        }


        $lblGun = (string) ($sk['lbl_gun'] ?? '');
        $lblSaat = (string) ($sk['lbl_saat'] ?? '');
        $lblDakika = (string) ($sk['lbl_dakika'] ?? '');
        $lblSaniye = (string) ($sk['lbl_saniye'] ?? '');
        if (function_exists('current_lang') && current_lang() !== 'tr') {
            $lblGun = function_exists('t') ? t('cd.day', 'Gün') : 'Days';
            $lblSaat = function_exists('t') ? t('cd.hour', 'Saat') : 'Hours';
            $lblDakika = function_exists('t') ? t('cd.min', 'Dakika') : 'Mins';
            $lblSaniye = function_exists('t') ? t('cd.sec', 'Saniye') : 'Secs';
        } else {
            if ($lblGun === '') {
                $lblGun = 'Gün';
            }
            if ($lblSaat === '') {
                $lblSaat = 'Saat';
            }
            if ($lblDakika === '') {
                $lblDakika = 'Dakika';
            }
            if ($lblSaniye === '') {
                $lblSaniye = 'Saniye';
            }
        }


    } else {

        /** @suppress */
        $sk = [];


    }


    $countdownBannerClass = 'countdown-banner';
    if (!empty($GLOBALS['COUNTDOWN_STICKY_HOME'])) {
        $countdownBannerClass .= ' countdown-banner--sticky-home';
    }
    static $cdCssLinked = false;
    if (!$cdCssLinked) {
        $cdCssLinked = true;
        echo '<link rel="stylesheet" href="css/countdown-banner.css">' . "\n";
    }
    ?>
    <div class="<?= htmlspecialchars($countdownBannerClass, ENT_QUOTES, 'UTF-8') ?>">
        <div class="countdown-inner">
            <div class="countdown-msg">
                <span class="countdown-badge"><i class="fas fa-bolt" aria-hidden="true"></i> Fırsat</span>
                <span class="countdown-label"><?= htmlspecialchars($message) ?></span>
            </div>
            <div class="countdown-timer" id="countdown-timer">
                <div class="time-unit">
                    <span class="time-value" id="days">00</span>
                    <span class="time-label"><?= htmlspecialchars($lblGun, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="time-separator" aria-hidden="true">:</div>
                <div class="time-unit">
                    <span class="time-value" id="hours">00</span>
                    <span class="time-label"><?= htmlspecialchars($lblSaat, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="time-separator" aria-hidden="true">:</div>
                <div class="time-unit">
                    <span class="time-value" id="minutes">00</span>
                    <span class="time-label"><?= htmlspecialchars($lblDakika, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="time-separator" aria-hidden="true">:</div>
                <div class="time-unit">
                    <span class="time-value" id="seconds">00</span>
                    <span class="time-label"><?= htmlspecialchars($lblSaniye, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
    </div>

<?php
    if (!empty($cdSkinActive) && ($sk['style_enabled'] ?? true)) {
        if (!function_exists('order_page_ui_hex')) {
            require_once __DIR__ . '/includes/order_page_ui.php';
        }
        /** @suppress */
        $g1 = htmlspecialchars(order_page_ui_hex(trim((string) ($sk['grad_start'] ?? '')), '#0d9488'), ENT_QUOTES, 'UTF-8');
        /** @suppress */
        $g2 = htmlspecialchars(order_page_ui_hex(trim((string) ($sk['grad_end'] ?? '')), '#fbbf24'), ENT_QUOTES, 'UTF-8');
        /** @suppress */
        $bc = htmlspecialchars(order_page_ui_hex(trim((string) ($sk['banner_text_color'] ?? '')), '#0f172a'), ENT_QUOTES, 'UTF-8');
        /** @suppress */
        $dc = htmlspecialchars(order_page_ui_hex(trim((string) ($sk['timer_digit_color'] ?? '')), '#fde047'), ENT_QUOTES, 'UTF-8');
        $lf = max(10, min(48, (int) ($sk['label_font_px'] ?? 14)));
        $tf = max(10, min(48, (int) ($sk['timer_font_px'] ?? 18)));
        $tbgRaw = preg_replace('#\s+#', ' ', trim((string) ($sk['timer_box_bg'] ?? 'rgba(15,23,42,0.92)')));
        if ($tbgRaw === '' || !preg_match('~^(rgba?\([^)]*\)|#[0-9a-f]{3,8}|transparent)$~i', $tbgRaw)) {
            /** @suppress */
            $tbgRaw = 'rgba(15,23,42,0.92)';
        }
?>

<style id="opui-countdown-skin-overlay">


.order-page-shell .countdown-banner {

    background: linear-gradient(135deg, <?= $g1 ?> 0%, <?= $g2 ?> 100%) !important;

color: <?= $bc ?> !important;



}







.order-page-shell .countdown-label {





font-size: <?= $lf ?>px !important;

color: <?= $bc ?> !important;





}






.order-page-shell .time-value {





font-size: <?= $tf ?>px !important;





background: <?= htmlspecialchars($tbgRaw, ENT_QUOTES, 'UTF-8') ?> !important;



color: <?= $dc ?> !important;



}

</style>

<?php
    }



?>



    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Bitiş zamanı PHP tarafında hesaplanır (milisaniye, sunucu ile aynı)
        const endTime = <?= (int) $countdownDeadlineMs ?>;

        const countdownTimer = document.getElementById('countdown-timer');

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                // Süre doldu
                countdownTimer.innerHTML = '<div class="time-expired">Süre Dolmak Üzere!</div>';
                setTimeout(() => {
                    location.reload();
                }, 2000);
                return;
            }

            // Zaman hesaplamaları
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // DOM güncellemeleri
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');

        }

        // İlk güncelleme — slot ölçümü zaman rakamları çizildikten sonra (sonraki karelerde sıkılır)
        updateCountdown();

        // Her saniye güncelle
        const countdownInterval = setInterval(updateCountdown, 1000);

        // Sayfa kapatılırken interval'i temizle
        window.addEventListener('beforeunload', function() {
            clearInterval(countdownInterval);
        });
    });
    </script>
    <?php
}
?>
