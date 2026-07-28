<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_rbac.php';

/**
 * @return list<array{href:string,icon:string,label:string,perm:string|null,external?:bool}>
 */
function admin_quick_menu_definitions(): array
{
    return [
        ['href' => 'orders.php', 'icon' => 'fa-shopping-cart', 'label' => 'Sipariş Yönetimi', 'perm' => 'menu_siparis'],
        ['href' => 'abandoned_orders.php', 'icon' => 'fa-hourglass-half', 'label' => 'Yarım Kalanlar', 'perm' => 'menu_siparis'],
        ['href' => 'export.php', 'icon' => 'fa-file-excel', 'label' => 'Excel İndir', 'perm' => 'menu_siparis'],
        ['href' => 'admin_support.php', 'icon' => 'fa-headset', 'label' => 'Destek Yönetimi', 'perm' => 'menu_destek'],
        ['href' => 'bayilik-basvuru.php', 'icon' => 'fa-store', 'label' => 'Bayi Başvuruları', 'perm' => 'menu_destek'],
        ['href' => 'products.php', 'icon' => 'fa-box', 'label' => 'Ürün Yönetimi', 'perm' => 'menu_urun'],
        ['href' => 'variant_management.php', 'icon' => 'fa-layer-group', 'label' => 'Varyant Yönetimi', 'perm' => 'menu_urun'],
        ['href' => 'admin_slider.php', 'icon' => 'fa-images', 'label' => 'Slider Yönetimi', 'perm' => 'menu_icerik'],
        ['href' => 'order_lookup_settings.php', 'icon' => 'fa-search', 'label' => 'Sipariş Sorgula Ayarları', 'perm' => 'menu_icerik'],
        ['href' => 'user_management.php', 'icon' => 'fa-users-cog', 'label' => 'Kullanıcı Ayarları', 'perm' => '__super_only__'],
        ['href' => 'admin_log.php', 'icon' => 'fa-history', 'label' => 'Kullanıcı Logları', 'perm' => 'menu_rapor'],
        ['href' => 'admin_footer.php', 'icon' => 'fa-shoe-prints', 'label' => 'Alt Görsel Yönetimi', 'perm' => 'menu_icerik'],
        ['href' => 'buttons.php', 'icon' => 'fa-share-alt', 'label' => 'WP-IG Buton Yönetimi', 'perm' => 'menu_icerik'],
        ['href' => 'about_us.php', 'icon' => 'fa-info-circle', 'label' => 'Hakkımızda Sayfası', 'perm' => 'menu_icerik'],
        ['href' => 'add_contact_info.php', 'icon' => 'fa-address-book', 'label' => 'İletişim Sayfası', 'perm' => 'menu_icerik'],
        ['href' => 'add_shipping_process.php', 'icon' => 'fa-truck', 'label' => 'Kargo Süreci Sayfası', 'perm' => 'menu_icerik'],
        ['href' => 'admin_notification_settings.php', 'icon' => 'fa-bell', 'label' => 'Bildirim Yönetimi', 'perm' => 'menu_entegrasyon'],
        ['href' => 'countdown_settings.php', 'icon' => 'fa-hourglass-half', 'label' => 'Geri Sayım Yönetimi', 'perm' => 'menu_entegrasyon'],
        ['href' => 'netgsm_settings.php', 'icon' => 'fa-sms', 'label' => 'SMS Ayarları', 'perm' => 'menu_entegrasyon'],
        ['href' => 'telegram_settings.php', 'icon' => 'fab fa-telegram', 'label' => 'Telegram Bildirim', 'perm' => 'menu_entegrasyon'],
        ['href' => 'admin_meta.php', 'icon' => 'fa-code', 'label' => 'Site Pazarlama Kodları', 'perm' => 'menu_pazarlama'],
        ['href' => 'call_confirmation.php', 'icon' => 'fa-chart-line', 'label' => 'Kullanıcı Raporları', 'perm' => 'menu_rapor'],
        ['href' => 'cirolar.php', 'icon' => 'fa-chart-bar', 'label' => 'Satış & Ciro Raporu', 'perm' => 'menu_rapor'],
        ['href' => 'final.php', 'icon' => 'fa-chart-pie', 'label' => 'Performans Raporu', 'perm' => 'menu_rapor'],
        ['href' => 'admin_smtp_settings.php', 'icon' => 'fa-envelope-open', 'label' => 'SMTP Ayarları', 'perm' => 'menu_entegrasyon'],
        ['href' => '__vitrin__', 'icon' => 'fa-eye', 'label' => 'Siteyi Gör', 'perm' => null, 'external' => true],
        ['href' => 'order_image_maker.php', 'icon' => 'fa-industry', 'label' => 'Sipariş Görsel Fabrikası', 'perm' => 'menu_yz'],
        ['href' => 'review_intro.php', 'icon' => 'fa-pen-nib', 'label' => 'YZ ile İçerik Oluştur', 'perm' => 'menu_yz'],
        ['href' => 'reviews.php', 'icon' => 'fa-comments', 'label' => 'YZ ile Yorum Oluştur', 'perm' => 'menu_yz'],
    ];
}

/**
 * Font Awesome ikon sınıfına stil öneki (fas/far/fab…) yoksa ekler.
 * FA5+ sürümlerinde önek olmadan ikon boş kare (tofu) olarak görünür.
 */
function admin_fa_icon_class(string $icon): string
{
    $icon = trim($icon);
    if ($icon === '') {
        return 'fas fa-circle';
    }
    if (preg_match('/\b(fas|far|fal|fad|fab|fa-solid|fa-regular|fa-light|fa-duotone|fa-brands|fa-thin)\b/', $icon) === 1) {
        return $icon;
    }

    return 'fas ' . $icon;
}

function admin_quick_menu_can(?string $perm): bool
{
    if ($perm === null) {
        return true;
    }
    if ($perm === '__super_only__') {
        return admin_is_super();
    }

    return admin_user_can($perm);
}

/**
 * @return list<array{href:string,icon:string,label:string,external:bool}>
 */
function admin_quick_menu_items(?string $vitrinHref = null): array
{
    $out = [];
    foreach (admin_quick_menu_definitions() as $item) {
        if (!admin_quick_menu_can($item['perm'] ?? null)) {
            continue;
        }
        $href = (string) $item['href'];
        $external = !empty($item['external']);
        if ($href === '__vitrin__') {
            $href = (string) ($vitrinHref ?? 'index.php');
            $external = true;
        }
        $out[] = [
            'href' => $href,
            'icon' => admin_fa_icon_class((string) $item['icon']),
            'label' => (string) $item['label'],
            'external' => $external,
        ];
    }

    return $out;
}

/**
 * Mobil alt navigasyon öğeleri.
 *
 * @return list<array{key:string,href:string,icon:string,label:string,action?:string}>
 */
function admin_mobile_nav_items(?string $vitrinHref = null): array
{
    $cur = basename($_SERVER['PHP_SELF'] ?? '');
    $items = [];

    $items[] = ['key' => 'dashboard', 'href' => 'index.php', 'icon' => 'fa-home', 'label' => 'Özet'];

    if (admin_user_can('menu_siparis')) {
        $items[] = ['key' => 'orders', 'href' => 'orders.php', 'icon' => 'fa-shopping-cart', 'label' => 'Sipariş'];
        $items[] = ['key' => 'abandoned', 'href' => 'abandoned_orders.php', 'icon' => 'fa-hourglass-half', 'label' => 'Yarım'];
    }

    if (admin_user_can('menu_destek')) {
        $items[] = ['key' => 'support', 'href' => 'admin_support.php', 'icon' => 'fa-headset', 'label' => 'Destek'];
    }

    $items[] = ['key' => 'menu', 'href' => '#', 'icon' => 'fa-bars', 'label' => 'Menü', 'action' => 'toggle-sidebar'];

    foreach ($items as &$item) {
        $item['active'] = ($item['action'] ?? '') === '' && ($cur === $item['href'] || ($item['href'] === 'orders.php' && $cur === 'order_manage.php'));
    }
    unset($item);

    return $items;
}
