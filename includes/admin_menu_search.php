<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_rbac.php';

/**
 * @return list<array{label:string,href:string,section:string,keywords:string,perm?:string,super?:bool,external?:bool}>
 */
function admin_menu_search_catalog(): array
{
    return [
        ['label' => 'Dashboard', 'href' => 'index.php', 'section' => 'Özet', 'keywords' => 'ana sayfa özet panel'],
        ['label' => 'Siteyi Gör', 'href' => '../index.php', 'section' => 'Özet', 'keywords' => 'vitrin site ön yüz', 'external' => true],

        ['label' => 'Sipariş Yönetimi', 'href' => 'orders.php', 'section' => 'Üst menü', 'keywords' => 'sipariş liste telefon ara durum beklemede', 'perm' => 'menu_siparis'],
        ['label' => 'Manuel sipariş', 'href' => 'order_manual.php', 'section' => 'Siparişler', 'keywords' => 'manuel ekle', 'perm' => 'menu_siparis'],
        ['label' => 'Yarım kalan satışlar', 'href' => 'abandoned_orders.php', 'section' => 'Üst menü', 'keywords' => 'yarım kalan sepet terk', 'perm' => 'menu_siparis'],
        ['label' => 'Yarım kalan ayarı', 'href' => 'abandoned_settings.php', 'section' => 'Siparişler', 'keywords' => 'yarım kalan yakalama scroll form', 'perm' => 'menu_siparis'],
        ['label' => 'Sipariş durumları', 'href' => 'manage_order_status.php', 'section' => 'Siparişler', 'keywords' => 'beklemede kargoda', 'perm' => 'menu_siparis'],
        ['label' => 'Sipariş sorgulama', 'href' => 'order_lookup_settings.php', 'section' => 'Siparişler', 'keywords' => 'sorgula telefon panel local', 'perm' => 'menu_siparis'],
        ['label' => 'Excel İndir', 'href' => 'export.php', 'section' => 'Siparişler', 'keywords' => 'excel export dışa aktar', 'perm' => 'menu_siparis'],
        ['label' => 'Aras Excel', 'href' => 'cargo_csv_export.php', 'section' => 'Siparişler', 'keywords' => 'kargo aras csv', 'perm' => 'menu_siparis'],
        ['label' => 'Hızlı notlar', 'href' => 'quick_notes.php', 'section' => 'Siparişler', 'keywords' => 'not şablon', 'perm' => 'menu_siparis'],
        ['label' => 'Ödeme yöntemleri', 'href' => 'manage_payment_methods.php', 'section' => 'Ödeme', 'keywords' => 'kapıda paytr iyzico', 'perm' => 'menu_siparis'],
        ['label' => 'İl / ilçe', 'href' => 'manage_locations.php', 'section' => 'Ödeme', 'keywords' => 'şehir ilçe lokasyon', 'perm' => 'menu_siparis'],
        ['label' => 'Banka hesapları', 'href' => 'bank_accounts.php', 'section' => 'Ödeme', 'keywords' => 'havale eft iban', 'perm' => 'menu_siparis'],

        ['label' => 'Ürün Yönetimi', 'href' => 'products.php', 'section' => 'Ürün', 'keywords' => 'ürün ekle düzenle fiyat', 'perm' => 'menu_urun'],
        ['label' => 'Varyant Yönetimi', 'href' => 'variant_management.php', 'section' => 'Ürün', 'keywords' => 'varyant renk beden', 'perm' => 'menu_urun'],

        ['label' => 'Kullanıcı raporları', 'href' => 'call_confirmation.php', 'section' => 'Raporlar', 'keywords' => 'çağrı onay rapor', 'perm' => 'menu_rapor'],
        ['label' => 'Satış & ciro', 'href' => 'cirolar.php', 'section' => 'Raporlar', 'keywords' => 'ciro gelir satış', 'perm' => 'menu_rapor'],
        ['label' => 'Performans', 'href' => 'final.php', 'section' => 'Raporlar', 'keywords' => 'performans analiz', 'perm' => 'menu_rapor'],
        ['label' => 'Kullanıcı logları', 'href' => 'admin_log.php', 'section' => 'Raporlar', 'keywords' => 'admin giriş log', 'perm' => 'menu_rapor'],

        ['label' => 'İçerik oluştur', 'href' => 'review_intro.php', 'section' => 'YZ', 'keywords' => 'ai içerik yazı', 'perm' => 'menu_yz'],
        ['label' => 'Yorum oluştur', 'href' => 'reviews.php', 'section' => 'YZ', 'keywords' => 'yorum review ai', 'perm' => 'menu_yz'],
        ['label' => 'Görsel fabrikası', 'href' => 'order_image_maker.php', 'section' => 'YZ', 'keywords' => 'görsel sipariş fabrika toplu', 'perm' => 'menu_yz'],

        ['label' => 'Kullanıcılar', 'href' => 'user_management.php', 'section' => 'Sistem', 'keywords' => 'admin kullanıcı yetki', 'super' => true],

        ['label' => 'Slider', 'href' => 'admin_slider.php', 'section' => 'Vitrin', 'keywords' => 'slider banner görsel', 'perm' => 'menu_icerik'],
        ['label' => 'Ana sayfa ürün bölümü', 'href' => 'homepage_products_section.php', 'section' => 'Vitrin', 'keywords' => 'ana sayfa ürün başlık', 'perm' => 'menu_icerik'],
        ['label' => 'Sipariş sayfası görünümü', 'href' => 'order_page_ui.php', 'section' => 'Vitrin', 'keywords' => 'checkout sipariş form', 'perm' => 'menu_icerik'],
        ['label' => 'Logo & alt görsel', 'href' => 'admin_footer.php', 'section' => 'Vitrin', 'keywords' => 'logo footer alt', 'perm' => 'menu_icerik'],
        ['label' => 'WP / IG butonları', 'href' => 'buttons.php', 'section' => 'Vitrin', 'keywords' => 'whatsapp instagram sosyal', 'perm' => 'menu_icerik'],
        ['label' => 'Tema yedeği', 'href' => 'theme_backup.php', 'section' => 'Vitrin', 'keywords' => 'tema backup yedek', 'perm' => 'menu_icerik'],
        ['label' => 'CMS sayfaları', 'href' => 'cms_pages.php', 'section' => 'Sayfalar', 'keywords' => 'cms içerik hakkımızda', 'perm' => 'menu_icerik'],
        ['label' => 'Landing sayfalar', 'href' => 'landing_pages.php', 'section' => 'Sayfalar', 'keywords' => 'landing açılış kampanya reklam blok', 'perm' => 'menu_icerik'],
        ['label' => 'SSS', 'href' => 'sss.php', 'section' => 'Sayfalar', 'keywords' => 'sık sorulan sorular', 'perm' => 'menu_icerik'],
        ['label' => 'Dinamik formlar', 'href' => 'custom_forms.php', 'section' => 'Sayfalar', 'keywords' => 'form başvuru', 'perm' => 'menu_icerik'],

        ['label' => 'Destek Yönetimi', 'href' => 'admin_support.php', 'section' => 'Üst menü', 'keywords' => 'destek talep ticket', 'perm' => 'menu_destek'],
        ['label' => 'Bayi Başvuruları', 'href' => 'bayilik-basvuru.php', 'section' => 'Üst menü', 'keywords' => 'bayilik bayi', 'perm' => 'menu_destek'],

        ['label' => 'Site meta kodları', 'href' => 'admin_meta.php', 'section' => 'Pazarlama', 'keywords' => 'meta pixel analytics', 'perm' => 'menu_pazarlama'],
        ['label' => 'Dönüşüm API', 'href' => 'conversion_api_settings.php', 'section' => 'Pazarlama', 'keywords' => 'capi facebook tiktok', 'perm' => 'menu_pazarlama'],
        ['label' => 'Kampanya / UTM', 'href' => 'attribution_settings.php', 'section' => 'Pazarlama', 'keywords' => 'utm kampanya reklam', 'perm' => 'menu_pazarlama'],
        ['label' => 'Kampanya siparişleri', 'href' => 'attribution_orders.php', 'section' => 'Pazarlama', 'keywords' => 'utm sipariş', 'perm' => 'menu_pazarlama'],
        ['label' => 'Sahte bildirimler', 'href' => 'fake_notifications.php', 'section' => 'Pazarlama', 'keywords' => 'fake sosyal kanıt', 'perm' => 'menu_siparis'],
        ['label' => 'Üst şerit bildirimi', 'href' => 'admin_notification_settings.php', 'section' => 'Pazarlama', 'keywords' => 'bildirim şerit', 'perm' => 'menu_entegrasyon'],
        ['label' => 'Geri sayım', 'href' => 'countdown_settings.php', 'section' => 'Pazarlama', 'keywords' => 'countdown sayaç', 'perm' => 'menu_entegrasyon'],
        ['label' => 'Şans çarkı', 'href' => 'carkifelek_settings.php', 'section' => 'Pazarlama', 'keywords' => 'çarkıfelek wheel indirim', 'perm' => 'menu_entegrasyon'],
        ['label' => 'Çark logları', 'href' => 'carkifelek_logs.php', 'section' => 'Pazarlama', 'keywords' => 'çark log ip', 'perm' => 'menu_entegrasyon'],

        ['label' => 'Telegram', 'href' => 'telegram_settings.php', 'section' => 'Entegrasyon', 'keywords' => 'telegram bot', 'perm' => 'menu_entegrasyon'],
        ['label' => 'NETGSM', 'href' => 'netgsm_settings.php', 'section' => 'Entegrasyon', 'keywords' => 'sms netgsm', 'perm' => 'menu_entegrasyon'],
        ['label' => 'Paraşüt', 'href' => 'parasut_settings.php', 'section' => 'Entegrasyon', 'keywords' => 'fatura parasut', 'perm' => 'menu_entegrasyon'],
        ['label' => 'PayTR', 'href' => 'paytr_settings.php', 'section' => 'Entegrasyon', 'keywords' => 'paytr ödeme', 'perm' => 'menu_entegrasyon'],
        ['label' => 'iyzico', 'href' => 'iyzico_settings.php', 'section' => 'Entegrasyon', 'keywords' => 'iyzico ödeme', 'perm' => 'menu_entegrasyon'],
        ['label' => 'SMTP', 'href' => 'admin_smtp_settings.php', 'section' => 'Entegrasyon', 'keywords' => 'e-posta mail smtp', 'perm' => 'menu_entegrasyon'],

        ['label' => 'IP engeli', 'href' => 'blocked_ips.php', 'section' => 'Güvenlik', 'keywords' => 'ip ban engel', 'perm' => 'menu_siparis'],
        ['label' => 'Telefon engeli', 'href' => 'blocked_phones.php', 'section' => 'Güvenlik', 'keywords' => 'telefon ban engel', 'perm' => 'menu_siparis'],
        ['label' => 'Cloaker ayarları', 'href' => 'cloaker_settings.php', 'section' => 'Güvenlik', 'keywords' => 'cloaker bot', 'perm' => 'menu_rapor'],
        ['label' => 'Güvenli sayfa', 'href' => 'safe_page_settings.php', 'section' => 'Güvenlik', 'keywords' => 'safe page cloaker blog landing', 'perm' => 'menu_rapor'],
        ['label' => 'Cloaker trafik', 'href' => 'cloaker_traffic.php', 'section' => 'Güvenlik', 'keywords' => 'cloaker log trafik', 'perm' => 'menu_rapor'],
        ['label' => 'Geçit Merkezi', 'href' => 'link_cloak/campaigns.php', 'section' => 'Geçit', 'keywords' => 'link cloak paravan reklam lc', 'perm' => 'menu_rapor'],
        ['label' => 'Geçit trafiği', 'href' => 'link_cloak/traffic.php', 'section' => 'Geçit', 'keywords' => 'geçit bot insan log', 'perm' => 'menu_rapor'],
        ['label' => 'Site hızlandırma', 'href' => 'cache_settings.php', 'section' => 'Güvenlik', 'keywords' => 'önbellek cache hız rocket', 'perm' => 'menu_rapor'],

        ['label' => 'Şifre değiştir', 'href' => 'edit_password.php', 'section' => 'Hesap', 'keywords' => 'profil şifre parola hesap admin'],
        ['label' => 'Çıkış Yap', 'href' => 'logout.php', 'section' => 'Hesap', 'keywords' => 'logout çıkış'],
    ];
}

/**
 * @return list<array{label:string,href:string,section:string,external?:bool}>
 */
function admin_menu_search(string $query, int $limit = 12): array
{
    $query = mb_strtolower(trim($query));
    if ($query === '') {
        return [];
    }

    $tokens = preg_split('/\s+/u', $query) ?: [];
    $out = [];

    foreach (admin_menu_search_catalog() as $item) {
        if (! empty($item['perm']) && ! admin_user_can((string) $item['perm'])) {
            continue;
        }
        if (! empty($item['super']) && ! admin_is_super()) {
            continue;
        }

        $hay = mb_strtolower(
            ($item['label'] ?? '') . ' ' . ($item['section'] ?? '') . ' ' . ($item['keywords'] ?? '') . ' ' . ($item['href'] ?? '')
        );

        $ok = true;
        foreach ($tokens as $tok) {
            if ($tok !== '' && ! str_contains($hay, $tok)) {
                $ok = false;
                break;
            }
        }
        if (! $ok) {
            continue;
        }

        $row = [
            'label' => (string) $item['label'],
            'href' => (string) $item['href'],
            'section' => (string) $item['section'],
        ];
        if (! empty($item['external'])) {
            $row['external'] = true;
        }
        $out[] = $row;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}
