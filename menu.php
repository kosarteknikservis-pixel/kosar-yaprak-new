<?php
require 'db.php';
require_once __DIR__ . '/includes/app_url.php';

$menu_home = app_url('', [], $pdo);

$menu_custom_forms = [];
try {
    $menu_custom_forms = $pdo->query(
        'SELECT slug, title, menu_label, menu_sort FROM custom_forms WHERE is_active = 1 AND show_in_menu = 1 ORDER BY menu_sort ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $menu_custom_forms = [];
}

// Logo ayarlarını çek
$stmt = $pdo->query("SELECT logo_type, logo_text, logo_icon, logo_main_text, logo_sub_text FROM footer_images WHERE id = 6");
$logo_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'logo_type' => 'text',
    'logo_text' => '',
    'logo_icon' => 'fas fa-store',
    'logo_main_text' => 'Mağaza',
    'logo_sub_text' => '',
];

$menu_on_homepage = (basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'index.php')
    || (trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/') === trim(app_install_subpath(), '/'));

$__lang = function_exists('current_lang') ? current_lang($pdo) : 'tr';
$__dir = function_exists('i18n_dir') ? i18n_dir($pdo) : 'ltr';
$__langs = function_exists('i18n_active_languages') ? i18n_active_languages($pdo) : [];
$__curs = function_exists('currency_active_list') ? currency_active_list($pdo) : [];
$__curCode = function_exists('current_currency_code') ? current_currency_code($pdo) : 'TRY';
$__has_switcher = count($__curs) > 1;
?>

<script>document.documentElement.setAttribute('lang','<?= htmlspecialchars($__lang, ENT_QUOTES) ?>');document.documentElement.setAttribute('dir','<?= htmlspecialchars($__dir, ENT_QUOTES) ?>');</script>
<?php if ($__dir === 'rtl'): ?><link rel="stylesheet" href="css/rtl.css"><?php endif; ?>

<div id="app-nav">
<div class="custom-header">
    <div class="custom-header-inner">
    <?php if ($logo_settings['logo_type'] === 'icon'): ?>
        <!-- Sadece ikon modu -->
        <a href="<?= htmlspecialchars($menu_home) ?>" class="logo-icon-only">
            <i class="<?= htmlspecialchars($logo_settings['logo_icon']) ?>" style="color: white; font-size: 34px;"></i>
        </a>
    <?php else: ?>
        <!-- Sadece yazı modu -->
        <a href="<?= htmlspecialchars($menu_home) ?>" class="logo">
            <span class="logo-main"><?= htmlspecialchars($logo_settings['logo_main_text']) ?></span>
            <span class="logo-sub"><?= htmlspecialchars($logo_settings['logo_sub_text']) ?></span>
        </a>
    <?php endif; ?>

    <div class="custom-header-actions">
    <div class="custom-menu-toggle">
        <i class="fas fa-bars"></i>
        <span><?= te('menu.title', 'Menü') ?></span>
    </div>
    </div>
    </div>
 </div>

<div class="custom-menu" id="custom-menu" role="dialog" aria-modal="true" aria-label="Site menüsü">
    <div class="custom-menu-head">
        <div class="custom-menu-head-brand">
            <span class="custom-menu-head-kicker"><?= htmlspecialchars((string) ($logo_settings['logo_main_text'] ?? 'Mağaza')) ?></span>
            <span class="custom-menu-head-title"><?= te('menu.title', 'Menü') ?></span>
        </div>
        <button type="button" class="custom-close-btn" id="custom-close-btn" aria-label="Menüyü kapat">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <?php if ($menu_on_homepage): ?>
    <button type="button" class="custom-menu-cta" data-go-products>
        <span class="custom-menu-cta-icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></span>
        <span class="custom-menu-cta-copy">
            <strong><?= te('menu.order_cta_title', 'Tıkla Sipariş Ver') ?></strong>
            <span><?= te('menu.order_cta_sub', 'Ürünleri incele ve hemen sipariş oluştur') ?></span>
        </span>
        <span class="custom-menu-cta-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
    </button>
    <?php else: ?>
    <a href="<?= htmlspecialchars($menu_home) ?>#products-heading" class="custom-menu-cta">
        <span class="custom-menu-cta-icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></span>
        <span class="custom-menu-cta-copy">
            <strong><?= te('menu.order_cta_title', 'Tıkla Sipariş Ver') ?></strong>
            <span><?= te('menu.order_cta_sub', 'Ürünleri incele ve hemen sipariş oluştur') ?></span>
        </span>
        <span class="custom-menu-cta-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
    </a>
    <?php endif; ?>

    <div class="custom-menu-scroll" id="custom-menu-scroll">

    <nav class="custom-menu-nav" aria-label="Sayfa bağlantıları">
        <?php if ($menu_on_homepage): ?>
        <button type="button" class="custom-menu-link" data-go-products><span class="custom-menu-link-icon"><i class="fas fa-home"></i></span><span class="custom-menu-link-text"><?= te('menu.go_products', 'Ürünlere Git') ?></span></button>
        <?php else: ?>
        <a href="<?= htmlspecialchars($menu_home) ?>#products-heading" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-home"></i></span><span class="custom-menu-link-text"><?= te('menu.home', 'Ana Sayfa') ?></span></a>
        <?php endif; ?>
        <a href="sorgula.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-search"></i></span><span class="custom-menu-link-text"><?= te('menu.order_query', 'Sipariş Sorgula') ?></span></a>
        <a href="sss.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-circle-question"></i></span><span class="custom-menu-link-text"><?= te('menu.faq', 'Sıkça Sorulan Sorular') ?></span></a>
        <a href="destek_talebi.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-headset"></i></span><span class="custom-menu-link-text"><?= te('menu.support', 'Destek Talebi') ?></span></a>
        <a href="bayilik.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-handshake"></i></span><span class="custom-menu-link-text"><?= te('menu.dealership', 'Bayilik Başvurusu') ?></span></a>
        <a href="hakkimizda.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-info-circle"></i></span><span class="custom-menu-link-text"><?= te('menu.about', 'Hakkımızda') ?></span></a>
        <a href="kargo_sureci.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-shipping-fast"></i></span><span class="custom-menu-link-text"><?= te('menu.shipping', 'Kargo Süreci') ?></span></a>
        <a href="iletisim.php" class="custom-menu-link"><span class="custom-menu-link-icon"><i class="fas fa-envelope"></i></span><span class="custom-menu-link-text"><?= te('menu.contact', 'Bize Ulaşın') ?></span></a>
        <?php foreach ($menu_custom_forms as $mf): ?>
            <?php
            $mfSlug = (string) ($mf['slug'] ?? '');
            if ($mfSlug === '') {
                continue;
            }
            $ml = trim((string) ($mf['menu_label'] ?? ''));
            $mfLab = $ml !== '' ? $ml : (string) ($mf['title'] ?? '');
            ?>
            <a href="dinamik_form.php?f=<?= rawurlencode($mfSlug) ?>" class="custom-menu-link">
                <span class="custom-menu-link-icon"><i class="fas fa-wpforms"></i></span>
                <span class="custom-menu-link-text"><?= htmlspecialchars($mfLab, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="campaign-section" aria-label="Avantajlar">
        <p class="campaign-section-label"><?= te('perk.section', 'Avantajlarınız') ?></p>
        <div class="campaign-grid">
            <div class="campaign-card campaign-card--discount">
                <span class="campaign-card-icon"><i class="fas fa-percent" aria-hidden="true"></i></span>
                <strong><?= te('perk.discount', 'Özel İndirim') ?></strong>
                <span><?= te('perk.discount_sub', "%20'ye varan") ?></span>
            </div>
            <div class="campaign-card campaign-card--shipping">
                <span class="campaign-card-icon"><i class="fas fa-truck-fast" aria-hidden="true"></i></span>
                <strong><?= te('perk.free_shipping', 'Ücretsiz Kargo') ?></strong>
                <span><?= te('perk.free_shipping_sub', '500 TL üzeri') ?></span>
            </div>
            <div class="campaign-card campaign-card--fast">
                <span class="campaign-card-icon"><i class="fas fa-bolt" aria-hidden="true"></i></span>
                <strong><?= te('perk.fast_delivery', 'Hızlı Teslimat') ?></strong>
                <span><?= te('perk.fast_delivery_sub', 'Aynı gün kargo') ?></span>
            </div>
        </div>
    </div>

    <?php if ($__has_switcher): ?>
    <div class="site-switcher" aria-label="Para birimi seçimi">
        <div class="site-switcher-group site-cur-switch">
            <span class="site-switcher-label"><i class="fas fa-coins" aria-hidden="true"></i> <?= te('switcher.currency', 'Para Birimi') ?></span>
            <div class="site-switcher-opts">
                <?php foreach ($__curs as $cu): $cc = (string) $cu['code']; $ca = ($cc === $__curCode); ?>
                <a href="<?= htmlspecialchars(currency_switch_url($cc)) ?>" class="site-switcher-pill<?= $ca ? ' is-active' : '' ?>"<?= $ca ? ' aria-current="true"' : '' ?>>
                    <?php if (!empty($cu['symbol'])): ?><span class="site-switcher-sym"><?= htmlspecialchars((string) $cu['symbol']) ?></span> <?php endif; ?><span><?= htmlspecialchars($cc) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="custom-menu-scroll-hint" id="custom-menu-scroll-hint" aria-hidden="true">
        <i class="fas fa-chevron-down"></i>
        <span><?= te('menu.scroll_down', 'Aşağı kaydır') ?></span>
    </div>
    </div>
</div>

<style>
@import url('css/site-shell.css?v=20260817i18n2');

.site-switcher { margin: 14px 12px 4px; display: flex; flex-direction: column; gap: 12px; }
.site-switcher-group { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 14px; padding: 10px 12px; }
.site-switcher-label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; opacity: .82; margin-bottom: 8px; }
.site-switcher-opts { display: flex; flex-wrap: wrap; gap: 6px; }
.site-switcher-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; color: inherit; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); transition: background .18s, border-color .18s, transform .12s; }
.site-switcher-pill:hover { background: rgba(255,255,255,.18); transform: translateY(-1px); }
.site-switcher-pill.is-active { background: #ffffff; color: #111827; border-color: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,.18); }
.site-switcher-sym { font-weight: 800; }
</style>

<script>
(function() {
    if (window.__APP_MENU_INIT_DONE__) return;
    window.__APP_MENU_INIT_DONE__ = true;

    function getMenu() {
        return document.getElementById('custom-menu');
    }

    function isClickInsideToggle(target) {
        return !!(target && target.closest && target.closest('.custom-menu-toggle'));
    }

    function isClickInsideMenu(target) {
        var menu = getMenu();
        return !!(menu && target && menu.contains(target));
    }

    function syncMenuBackdrop() {
        var menu = getMenu();
        document.body.classList.toggle('app-menu-open', !!(menu && menu.classList.contains('open')));
    }

    function getMenuScroll() {
        return document.getElementById('custom-menu-scroll');
    }

    function updateMenuScrollHint() {
        var menu = getMenu();
        var scrollEl = getMenuScroll();
        if (!menu || !scrollEl) return;

        var hasOverflow = scrollEl.scrollHeight > scrollEl.clientHeight + 2;
        var atEnd = scrollEl.scrollTop + scrollEl.clientHeight >= scrollEl.scrollHeight - 8;

        scrollEl.classList.toggle('has-overflow', hasOverflow);
        scrollEl.classList.toggle('is-scrolled-end', atEnd);
        menu.classList.toggle('menu-has-scroll', hasOverflow && !atEnd);
    }

    function resetMenuScroll() {
        var scrollEl = getMenuScroll();
        if (scrollEl) scrollEl.scrollTop = 0;
        updateMenuScrollHint();
    }

    function openMenu() {
        var menu = getMenu();
        if (menu) {
            menu.classList.add('open');
            resetMenuScroll();
        }
        syncMenuBackdrop();
    }

    function closeMenu() {
        var menu = getMenu();
        if (menu) menu.classList.remove('open');
        syncMenuBackdrop();
    }

    window.closeAppMenu = closeMenu;

    function toggleMenu() {
        var menu = getMenu();
        if (!menu) return;
        var willOpen = !menu.classList.contains('open');
        menu.classList.toggle('open');
        if (willOpen) {
            resetMenuScroll();
        } else {
            updateMenuScrollHint();
        }
        syncMenuBackdrop();
    }

    document.addEventListener('click', function(e) {
        var target = e.target;
        var menuPanel = getMenu();

        if (!menuPanel) return;

        if (isClickInsideToggle(target)) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
            return;
        }

        if (target && target.closest && target.closest('#custom-close-btn')) {
            e.preventDefault();
            e.stopPropagation();
            closeMenu();
            return;
        }

        if (target && target.closest && target.closest('.custom-menu-nav a, .custom-menu-cta[href], [data-go-products]')) {
            if (!target.closest('.custom-menu-toggle')) {
                closeMenu();
            }
        }

        if (menuPanel.classList.contains('open') && !isClickInsideMenu(target)) {
            closeMenu();
        }
    }, true);

    // Keyboard escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMenu();
    });

    var scrollEl = getMenuScroll();
    if (scrollEl) {
        scrollEl.addEventListener('scroll', updateMenuScrollHint, { passive: true });
    }
    window.addEventListener('resize', updateMenuScrollHint, { passive: true });

})();
</script>
<script src="js/products-scroll.js?v=202604045"></script>
