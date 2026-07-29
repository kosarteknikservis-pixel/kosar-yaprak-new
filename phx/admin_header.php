<?php
require_once dirname(__DIR__) . '/includes/admin_paths.php';
$admin_css_href = admin_asset('css/modern_admin.css');

$admin_cur = basename($_SERVER['PHP_SELF'] ?? '');
/** @param string|array<string> $files */
$admin_nav_active = static function ($files) use ($admin_cur): string {
    $list = is_array($files) ? $files : [$files];
    return in_array($admin_cur, $list, true) ? ' class="active"' : '';
};
if (!function_exists('admin_user_can')) {
    require_once dirname(__DIR__) . '/includes/admin_rbac.php';
}
require_once dirname(__DIR__) . '/includes/admin_locale.php';
require_once dirname(__DIR__) . '/includes/site_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_quick_menu.php';
$admin_vitrin_href = site_public_vitrin_href(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
$admin_shortcut_urls = [
    'dashboard' => admin_url('index.php'),
    'orders' => admin_user_can('menu_siparis') ? admin_url('orders.php') : '',
    'abandoned' => admin_user_can('menu_siparis') ? admin_url('abandoned_orders.php') : '',
    'support' => admin_user_can('menu_destek') ? admin_url('admin_support.php') : '',
];

$admin_quick_nav_items = [];
if (isset($pdo) && $pdo instanceof PDO) {
    require_once dirname(__DIR__) . '/includes/admin_quick_nav.php';
    $admin_quick_nav_items = admin_quick_nav_items($pdo);
}
$admin_quick_nav_active = static function (array $files) use ($admin_cur): bool {
    return in_array($admin_cur, $files, true);
};
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' — phxcore0' : 'phxcore0 Admin' ?></title>
    <!-- Tema başlangıcı: boyamadan önce uygula (FOUC yok). Varsayılan: aydınlık -->
    <script>
    (function () {
        try {
            var t = localStorage.getItem('phx_theme');
            if (t !== 'dark' && t !== 'light') { t = 'light'; }
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }
    })();
    </script>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Admin CSS (mutlak yol: alt dizin / rewrite ile de yüklenir) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($admin_css_href, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_asset('css/cc-workspace.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_asset('css/admin-panel-ui.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_asset('css/admin-theme-readability.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script>window.ADMIN_WEB_ROOT=<?= json_encode(ADMIN_WEB_ROOT, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Tema geçiş kontrolcüsü -->
    <script defer src="<?= htmlspecialchars(admin_asset('js/admin-theme.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="admin-app"
      data-url-dashboard="<?= htmlspecialchars($admin_shortcut_urls['dashboard'], ENT_QUOTES, 'UTF-8') ?>"
      data-url-orders="<?= htmlspecialchars($admin_shortcut_urls['orders'], ENT_QUOTES, 'UTF-8') ?>"
      data-url-abandoned="<?= htmlspecialchars($admin_shortcut_urls['abandoned'], ENT_QUOTES, 'UTF-8') ?>"
      data-url-support="<?= htmlspecialchars($admin_shortcut_urls['support'], ENT_QUOTES, 'UTF-8') ?>">

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-bug"></i></div>
        <div class="brand-copy">
            <div class="brand-text">phxcore0</div>
            <div class="brand-sub">
                <span class="brand-bounty-badge" title="Bug Bounty programı aktif"><i class="fas fa-shield-virus"></i> Bug Bounty</span>
                Admin Panel
            </div>
        </div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Özet</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('index.php') ?>"<?= $admin_nav_active('index.php') ?>><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= htmlspecialchars($admin_vitrin_href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Siteyi Gör</a>
        </nav>
    </div>

    <?php if (admin_user_can('menu_siparis')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Siparişler</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('order_manual.php') ?>"<?= $admin_nav_active('order_manual.php') ?>><i class="fas fa-cart-plus"></i> Manuel sipariş</a>
            <a href="<?= admin_href('abandoned_settings.php') ?>"<?= $admin_nav_active('abandoned_settings.php') ?>><i class="fas fa-sliders-h"></i> Yarım kalan ayarı</a>
            <a href="<?= admin_href('manage_order_status.php') ?>"<?= $admin_nav_active('manage_order_status.php') ?>><i class="fas fa-tasks"></i> Sipariş durumları</a>
            <a href="<?= admin_href('order_lookup_settings.php') ?>"<?= $admin_nav_active('order_lookup_settings.php') ?>><i class="fas fa-search"></i> Sipariş sorgulama</a>
            <a href="<?= admin_href('export.php') ?>"<?= $admin_nav_active('export.php') ?>><i class="fas fa-file-excel"></i> Excel İndir</a>
            <a href="<?= admin_href('cargo_csv_export.php') ?>"<?= $admin_nav_active('cargo_csv_export.php') ?>><i class="fas fa-truck-loading"></i> Aras Excel</a>
            <a href="<?= admin_href('quick_notes.php') ?>"<?= $admin_nav_active('quick_notes.php') ?>><i class="fas fa-sticky-note"></i> Hızlı notlar</a>
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Ödeme &amp; lokasyon</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('manage_payment_methods.php') ?>"<?= $admin_nav_active('manage_payment_methods.php') ?>><i class="fas fa-credit-card"></i> Ödeme yöntemleri</a>
            <a href="<?= admin_href('manage_locations.php') ?>"<?= $admin_nav_active('manage_locations.php') ?>><i class="fas fa-map-marked-alt"></i> İl / ilçe</a>
            <a href="<?= admin_href('bank_accounts.php') ?>"<?= $admin_nav_active('bank_accounts.php') ?>><i class="fas fa-university"></i> Banka hesapları</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_urun')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Ürün &amp; varyant</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('products.php') ?>"<?= $admin_nav_active('products.php') ?>><i class="fas fa-box"></i> Ürün Yönetimi</a>
            <a href="<?= admin_href('variant_management.php') ?>"<?= $admin_nav_active('variant_management.php') ?>><i class="fas fa-layer-group"></i> Varyant Yönetimi</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_rapor')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Raporlar</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('call_confirmation.php') ?>"<?= $admin_nav_active('call_confirmation.php') ?>><i class="fas fa-chart-line"></i> Kullanıcı raporları</a>
            <a href="<?= admin_href('cirolar.php') ?>"<?= $admin_nav_active('cirolar.php') ?>><i class="fas fa-chart-bar"></i> Satış &amp; ciro</a>
            <a href="<?= admin_href('final.php') ?>"<?= $admin_nav_active('final.php') ?>><i class="fas fa-chart-pie"></i> Performans</a>
            <a href="<?= admin_href('admin_log.php') ?>"<?= $admin_nav_active('admin_log.php') ?>><i class="fas fa-history"></i> Kullanıcı logları</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_yz')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">YZ araçları</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('review_intro.php') ?>"<?= $admin_nav_active('review_intro.php') ?>><i class="fas fa-pen-nib"></i> İçerik oluştur</a>
            <a href="<?= admin_href('reviews.php') ?>"<?= $admin_nav_active('reviews.php') ?>><i class="fas fa-comments"></i> Yorum oluştur</a>
            <a href="<?= admin_href('order_image_maker.php') ?>"<?= $admin_nav_active('order_image_maker.php') ?>><i class="fas fa-industry"></i> Görsel fabrikası</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_is_super()): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Sistem</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('user_management.php') ?>"<?= $admin_nav_active('user_management.php') ?>><i class="fas fa-users-cog"></i> Kullanıcılar</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_icerik')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Vitrin &amp; tasarım</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('admin_slider.php') ?>"<?= $admin_nav_active('admin_slider.php') ?>><i class="fas fa-images"></i> Slider</a>
            <a href="<?= admin_href('homepage_products_section.php') ?>"<?= $admin_nav_active('homepage_products_section.php') ?>><i class="fas fa-tags"></i> Ana sayfa ürün bölümü</a>
            <a href="<?= admin_href('order_page_ui.php') ?>"<?= $admin_nav_active('order_page_ui.php') ?>><i class="fas fa-cart-shopping"></i> Sipariş sayfası görünümü</a>
            <a href="<?= admin_href('admin_footer.php') ?>"<?= $admin_nav_active('admin_footer.php') ?>><i class="fas fa-shoe-prints"></i> Logo &amp; alt görsel</a>
            <a href="<?= admin_href('buttons.php') ?>"<?= $admin_nav_active('buttons.php') ?>><i class="fas fa-share-alt"></i> WP / IG butonları</a>
            <a href="<?= admin_href('theme_backup.php') ?>"<?= $admin_nav_active('theme_backup.php') ?>><i class="fas fa-archive"></i> Tema yedeği</a>
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Sayfalar &amp; formlar</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('cms_pages.php') ?>"<?= $admin_nav_active(['cms_pages.php', 'cms_edit.php']) ?>><i class="fas fa-copy"></i> CMS sayfaları</a>
            <a href="<?= admin_href('landing_pages.php') ?>"<?= $admin_nav_active(['landing_pages.php', 'landing_edit.php']) ?>><i class="fas fa-rocket"></i> Landing sayfalar</a>
            <a href="<?= admin_href('languages.php') ?>"<?= $admin_nav_active('languages.php') ?>><i class="fas fa-globe"></i> Diller &amp; Para</a>
            <a href="<?= admin_href('sss.php') ?>"<?= $admin_nav_active('sss.php') ?>><i class="fas fa-question-circle"></i> SSS</a>
            <a href="<?= admin_href('custom_forms.php') ?>"<?= $admin_nav_active(['custom_forms.php', 'custom_form_edit.php', 'custom_form_entries.php']) ?>><i class="fas fa-wpforms"></i> Dinamik formlar</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_is_super() || admin_user_can('menu_pazarlama')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Pazarlama &amp; takip</div>
        <nav class="sidebar-nav">
            <?php if (admin_user_can('menu_pazarlama')): ?>
            <a href="<?= admin_href('admin_meta.php') ?>"<?= $admin_nav_active('admin_meta.php') ?>><i class="fas fa-code"></i> Site meta kodları</a>
            <a href="<?= admin_href('conversion_api_settings.php') ?>"<?= $admin_nav_active('conversion_api_settings.php') ?>><i class="fas fa-bullseye"></i> Dönüşüm API</a>
            <a href="<?= admin_href('attribution_settings.php') ?>"<?= $admin_nav_active('attribution_settings.php') ?>><i class="fas fa-link"></i> Kampanya / UTM</a>
            <a href="<?= admin_href('attribution_orders.php') ?>"<?= $admin_nav_active('attribution_orders.php') ?>><i class="fas fa-filter"></i> Kampanya siparişleri</a>
            <?php endif; ?>
            <?php if (admin_user_can('menu_siparis')): ?>
            <a href="<?= admin_href('fake_notifications.php') ?>"<?= $admin_nav_active('fake_notifications.php') ?>><i class="fas fa-comments-dollar"></i> Sahte bildirimler</a>
            <?php endif; ?>
            <?php if (admin_user_can('menu_entegrasyon')): ?>
            <a href="<?= admin_href('admin_notification_settings.php') ?>"<?= $admin_nav_active('admin_notification_settings.php') ?>><i class="fas fa-bell"></i> Üst şerit bildirimi</a>
            <a href="<?= admin_href('countdown_settings.php') ?>"<?= $admin_nav_active('countdown_settings.php') ?>><i class="fas fa-hourglass-half"></i> Geri sayım</a>
            <a href="<?= admin_href('carkifelek_settings.php') ?>"<?= $admin_nav_active('carkifelek_settings.php') ?>><i class="fas fa-gift"></i> Şans çarkı</a>
            <a href="<?= admin_href('carkifelek_logs.php') ?>"<?= $admin_nav_active('carkifelek_logs.php') ?>><i class="fas fa-list"></i> Çark logları</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_entegrasyon')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Entegrasyonlar</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('telegram_settings.php') ?>"<?= $admin_nav_active('telegram_settings.php') ?>><i class="fab fa-telegram"></i> Telegram</a>
            <a href="<?= admin_href('netgsm_settings.php') ?>"<?= $admin_nav_active('netgsm_settings.php') ?>><i class="fas fa-sms"></i> SMS Ayarları</a>
            <a href="<?= admin_href('parasut_settings.php') ?>"<?= $admin_nav_active('parasut_settings.php') ?>><i class="fas fa-file-invoice-dollar"></i> Paraşüt</a>
            <a href="<?= admin_href('paytr_settings.php') ?>"<?= $admin_nav_active('paytr_settings.php') ?>><i class="fas fa-credit-card"></i> PayTR</a>
            <a href="<?= admin_href('iyzico_settings.php') ?>"<?= $admin_nav_active('iyzico_settings.php') ?>><i class="fas fa-wallet"></i> iyzico</a>
            <a href="<?= admin_href('admin_smtp_settings.php') ?>"<?= $admin_nav_active('admin_smtp_settings.php') ?>><i class="fas fa-envelope-open"></i> SMTP</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_siparis') || admin_user_can('menu_rapor')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Güvenlik</div>
        <nav class="sidebar-nav">
            <?php if (admin_user_can('menu_siparis')): ?>
            <a href="<?= admin_href('blocked_ips.php') ?>"<?= $admin_nav_active('blocked_ips.php') ?>><i class="fas fa-ban"></i> IP engeli</a>
            <a href="<?= admin_href('blocked_phones.php') ?>"<?= $admin_nav_active('blocked_phones.php') ?>><i class="fas fa-phone-slash"></i> Telefon engeli</a>
            <?php endif; ?>
            <?php if (admin_user_can('menu_rapor')): ?>
            <a href="<?= admin_href('cloaker_settings.php') ?>"<?= $admin_nav_active('cloaker_settings.php') ?>><i class="fas fa-user-shield"></i> Cloaker ayarları</a>
            <a href="<?= admin_href('cloaker_traffic.php') ?>"<?= $admin_nav_active('cloaker_traffic.php') ?>><i class="fas fa-wave-square"></i> Cloaker trafik</a>
            <a href="<?= admin_href('cache_settings.php') ?>"<?= $admin_nav_active('cache_settings.php') ?>><i class="fas fa-bolt"></i> Site hızlandırma</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>

    <?php if (admin_user_can('menu_rapor')): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Geçit Merkezi</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_href('link_cloak/campaigns.php') ?>"<?= $admin_nav_active(['campaigns.php', 'campaign_edit.php']) ?>><i class="fas fa-route"></i> Geçit kampanyaları</a>
            <a href="<?= admin_href('link_cloak/traffic.php') ?>"<?= $admin_nav_active('traffic.php') ?>><i class="fas fa-chart-bar"></i> Geçit trafiği</a>
            <a href="<?= admin_href('safe_page_settings.php') ?>"<?= $admin_nav_active('safe_page_settings.php') ?>><i class="fas fa-newspaper"></i> Güvenli sayfa</a>
        </nav>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <nav class="sidebar-nav">
            <a href="<?= admin_href('logout.php') ?>"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
        </nav>
    </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="topbar-menu-btn" onclick="toggleSidebar()" id="menuBtn">
                <i class="fas fa-bars"></i>
            </button>
            <span class="topbar-title"><?= isset($page_title) ? $page_title : 'Admin Panel' ?></span>
        </div>
        <div class="topbar-right">
            <a class="topbar-site-link d-none d-sm-inline-flex" href="<?= htmlspecialchars($admin_vitrin_href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Vitrini yeni sekmede aç">
                <i class="fas fa-external-link-alt"></i>
                <span>Siteyi Gör</span>
            </a>
            <div class="topbar-menu-search" id="adminMenuSearch">
                <div class="topbar-menu-search__field">
                    <i class="fas fa-search topbar-menu-search__icon" aria-hidden="true"></i>
                    <input type="search" id="adminMenuSearchInput" placeholder="Menü ara…" aria-label="Admin menüsünde ara" autocomplete="off" spellcheck="false">
                </div>
                <div class="topbar-menu-search__panel" id="adminMenuSearchPanel" hidden></div>
            </div>
            <button type="button" class="topbar-theme-btn" id="adminThemeToggle" title="Tema değiştir (aydınlık / karanlık)" aria-label="Tema değiştir">
                <span class="theme-icon-light"><i class="fas fa-moon"></i></span>
                <span class="theme-icon-dark"><i class="fas fa-sun"></i></span>
            </button>
            <button type="button" class="topbar-shortcuts-btn" id="adminShortcutsTrigger" title="Klavye kısayolları (?)" aria-label="Klavye kısayolları">
                <i class="fas fa-keyboard"></i>
            </button>
            <a class="topbar-user" href="<?= admin_href('edit_password.php') ?>" title="Hesap ve şifre">
                <div class="topbar-avatar"><i class="fas fa-user" style="font-size:13px;"></i></div>
                <span class="d-none d-md-inline"><?= htmlspecialchars((string) ($_SESSION['admin_username'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <a class="topbar-logout" href="<?= admin_href('logout.php') ?>" title="Çıkış Yap">
                <i class="fas fa-right-from-bracket"></i>
                <span class="d-none d-lg-inline">Çıkış</span>
            </a>
        </div>
    </header>

    <?php if ($admin_quick_nav_items !== []): ?>
    <nav class="admin-quick-nav" aria-label="İş akışı kısayolları">
        <?php foreach ($admin_quick_nav_items as $qnItem): ?>
            <?php
            $qnActive = $admin_quick_nav_active($qnItem['files']);
            $qnCount = (int) $qnItem['count'];
            ?>
            <a href="<?= htmlspecialchars((string) $qnItem['href'], ENT_QUOTES, 'UTF-8') ?>"
               class="admin-quick-nav__item admin-quick-nav__item--<?= htmlspecialchars((string) $qnItem['key'], ENT_QUOTES, 'UTF-8') ?><?= $qnActive ? ' is-active' : '' ?>">
                <i class="fas <?= htmlspecialchars((string) $qnItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <span class="admin-quick-nav__label"><?= htmlspecialchars((string) $qnItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="admin-quick-nav__count<?= $qnCount > 0 ? ' has-value' : '' ?>"><?= number_format($qnCount, 0, ',', '.') ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <!-- Page content starts here (closed in admin_footer_common.php) -->
    <div class="page-content">
<script>
(function () {
    // Kenar menü bölümlerini aç/kapa yapılabilir hale getir; durumu tarayıcıda sakla.
    var sections = document.querySelectorAll('#sidebar .sidebar-section');
    sections.forEach(function (sec) {
        var label = sec.querySelector('.sidebar-section-label');
        var nav = sec.querySelector('.sidebar-nav');
        if (!label || !nav) return;

        var labelTxt = (label.textContent || '').trim();
        var key = 'sb_col_' + labelTxt;
        var hasActive = !!sec.querySelector('.sidebar-nav a.active');
        var defaultOpen = hasActive || /^(Özet|Siparişler)$/i.test(labelTxt);

        var chev = document.createElement('i');
        chev.className = 'fas fa-chevron-down sec-chevron';
        label.appendChild(chev);
        label.setAttribute('role', 'button');
        label.setAttribute('tabindex', '0');

        var stored = null;
        try { stored = localStorage.getItem(key); } catch (e) {}
        var open = stored === null ? defaultOpen : stored === '1';
        sec.classList.toggle('is-collapsed', !open);

        function toggle() {
            var willOpen = sec.classList.contains('is-collapsed');
            sec.classList.toggle('is-collapsed', !willOpen);
            try { localStorage.setItem(key, willOpen ? '1' : '0'); } catch (e) {}
        }
        label.addEventListener('click', toggle);
        label.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
        });
    });
})();
(function () {
    var root = window.ADMIN_WEB_ROOT || '';
    var vitrin = <?= json_encode($admin_vitrin_href, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var wrap = document.getElementById('adminMenuSearch');
    var input = document.getElementById('adminMenuSearchInput');
    var panel = document.getElementById('adminMenuSearchPanel');
    if (!wrap || !input || !panel) return;

    var timer = null;
    var lastQ = '';

    function searchUrl(q) {
        var base = root ? root + '/ajax/menu_search.php' : 'ajax/menu_search.php';
        return base + '?q=' + encodeURIComponent(q);
    }

    function render(items) {
        if (!items.length) {
            panel.innerHTML = '<div class="topbar-menu-search__empty">Sonuç bulunamadı</div>';
            panel.hidden = false;
            return;
        }
        panel.innerHTML = items.map(function (item) {
            var href = item.external ? vitrin : (root ? root + '/' + item.href : item.href);
            var target = item.external ? ' target="_blank" rel="noopener"' : '';
            return '<a class="topbar-menu-search__item" href="' + href + '"' + target + '>' +
                '<span class="topbar-menu-search__item-label">' + item.label + '</span>' +
                '<span class="topbar-menu-search__item-section">' + item.section + '</span></a>';
        }).join('');
        panel.hidden = false;
    }

    function closePanel() {
        panel.hidden = true;
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = (input.value || '').trim();
        if (q.length < 2) {
            closePanel();
            return;
        }
        timer = setTimeout(function () {
            if (q === lastQ) return;
            lastQ = q;
            fetch(searchUrl(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if ((input.value || '').trim() !== q) return;
                    render((data && data.items) ? data.items : []);
                })
                .catch(function () {
                    panel.innerHTML = '<div class="topbar-menu-search__empty">Arama hatası</div>';
                    panel.hidden = false;
                });
        }, 220);
    });

    input.addEventListener('focus', function () {
        if ((input.value || '').trim().length >= 2 && panel.innerHTML) {
            panel.hidden = false;
        }
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closePanel();
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePanel();
            input.blur();
        }
    });
})();
</script>
