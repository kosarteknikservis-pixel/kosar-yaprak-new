<?php
declare(strict_types=1);

/**
 * RBAC — panel yazılımındaki yaklaşıma benzer: süper admin + JSON menü izinleri.
 */

if (!function_exists('admin_menu_definitions')) {
    /**
     * @return array<string,string>
     */
    function admin_menu_definitions(): array
    {
        return [
            'menu_siparis' => 'Sipariş ve operasyon',
            'menu_urun' => 'Ürün ve varyant',
            'menu_rapor' => 'Raporlar',
            'menu_icerik' => 'İçerik ve sayfalar',
            'menu_destek' => 'Destek ve bayilik',
            'menu_yz' => 'YZ ve araçlar',
            'menu_entegrasyon' => 'Bildirim ve entegrasyon',
            'menu_pazarlama' => 'Pazarlama ve dönüşüm',
        ];
    }
}

if (!function_exists('admin_rbac_script_overrides')) {
    /**
     * İstisnalar: dosya adı => gerekli menü anahtarı veya null (giriş yapmış herkes) / __super_only__.
     * Yeni sayfada kural + sezgi yetmezse buraya tek satır ekleyin.
     *
     * @return array<string, '__super_only__'|non-empty-string|null>
     */
    function admin_rbac_script_overrides(): array
    {
        return [];
    }
}

if (!function_exists('admin_rbac_public_basenames')) {
    /**
     * @return list<string>
     */
    function admin_rbac_public_basenames(): array
    {
        return ['index.php', 'logout.php', 'check_notifications.php'];
    }
}

if (!function_exists('admin_rbac_super_only_basenames')) {
    /**
     * @return list<string>
     */
    function admin_rbac_super_only_basenames(): array
    {
        return [
            'user_management.php',
            'add_user.php',
            'edit_user.php',
            'delete_user.php',
            'edit_password.php',
            'user_avatar_upload.php',
            'hata_loglama.php',
        ];
    }
}

if (!function_exists('admin_rbac_menu_by_exact_basename_rules')) {
    /**
     * Bilinen panel betikleri — önce tam dosya adı eşleşmesi (hızlı, öngörülebilir).
     *
     * @return array<string, non-empty-string>
     */
    function admin_rbac_menu_by_exact_basename_rules(): array
    {
        return [
            'orders.php' => 'menu_siparis',
            'order_manage.php' => 'menu_siparis',
            'order_details.php' => 'menu_siparis',
            'order_edit.php' => 'menu_siparis',
            'update_status.php' => 'menu_siparis',
            'update_status_ajax.php' => 'menu_siparis',
            'update_customer_notes.php' => 'menu_siparis',
            'export.php' => 'menu_siparis',
            'cargo_csv_export.php' => 'menu_siparis',
            'abandoned_orders.php' => 'menu_siparis',
            'abandoned_settings.php' => 'menu_siparis',
            'order_manual.php' => 'menu_siparis',
            'manage_order_status.php' => 'menu_siparis',
            'manage_payment_methods.php' => 'menu_siparis',
            'manage_locations.php' => 'menu_siparis',
            'bank_accounts.php' => 'menu_siparis',
            'fake_notifications.php' => 'menu_siparis',
            'blocked_ips.php' => 'menu_siparis',
            'blocked_phones.php' => 'menu_siparis',
            'quick_notes.php' => 'menu_siparis',
            'download_numbers.php' => 'menu_siparis',

            'products.php' => 'menu_urun',
            'duplicate_product.php' => 'menu_urun',
            'variant_management.php' => 'menu_urun',
            'edit_product.php' => 'menu_urun',
            'delete_product.php' => 'menu_urun',
            'toggle_product_status.php' => 'menu_urun',
            'bulk_update.php' => 'menu_siparis',
            'add_variation.php' => 'menu_urun',
            'edit_variation.php' => 'menu_urun',
            'edit_variant.php' => 'menu_urun',
            'delete_variation.php' => 'menu_urun',
            'delete_variant.php' => 'menu_urun',
            'add_variation_2.php' => 'menu_urun',
            'edit_variation_2.php' => 'menu_urun',
            'edit_variant_2.php' => 'menu_urun',
            'delete_variation_2.php' => 'menu_urun',
            'delete_variant_2.php' => 'menu_urun',
            'assign_variants.php' => 'menu_urun',
            'get_variants.php' => 'menu_urun',
            'assign_variants.php' => 'menu_urun',

            'call_confirmation.php' => 'menu_rapor',
            'cirolar.php' => 'menu_rapor',
            'final.php' => 'menu_rapor',
            'admin_log.php' => 'menu_rapor',
            'cloaker_settings.php' => 'menu_rapor',
            'cloaker_traffic.php' => 'menu_rapor',
            'safe_page_settings.php' => 'menu_rapor',
            'cache_settings.php' => 'menu_rapor',
            'campaigns.php' => 'menu_rapor',
            'campaign_edit.php' => 'menu_rapor',
            'traffic.php' => 'menu_rapor',
            'traffic.php' => 'menu_rapor',

            'admin_slider.php' => 'menu_icerik',
            'cms_pages.php' => 'menu_icerik',
            'cms_edit.php' => 'menu_icerik',
            'landing_pages.php' => 'menu_icerik',
            'landing_edit.php' => 'menu_icerik',
            'landing_upload.php' => 'menu_icerik',
            'admin_footer.php' => 'menu_icerik',
            'about_us.php' => 'menu_icerik',
            'about_us_list.php' => 'menu_icerik',
            'edit_about_us.php' => 'menu_icerik',
            'delete_about_us.php' => 'menu_icerik',
            'edit_message.php' => 'menu_icerik',
            'contact_info_list.php' => 'menu_icerik',
            'add_contact_info.php' => 'menu_icerik',
            'edit_contact_info.php' => 'menu_icerik',
            'delete_contact_info.php' => 'menu_icerik',
            'shipping_process_list.php' => 'menu_icerik',
            'add_shipping_process.php' => 'menu_icerik',
            'edit_shipping_process.php' => 'menu_icerik',
            'delete_shipping_process.php' => 'menu_icerik',
            'buttons.php' => 'menu_icerik',
            'theme_backup.php' => 'menu_icerik',
            'sss.php' => 'menu_icerik',
            'custom_forms.php' => 'menu_icerik',
            'custom_form_edit.php' => 'menu_icerik',
            'custom_form_entries.php' => 'menu_icerik',
            'homepage_products_section.php' => 'menu_icerik',
            'order_page_ui.php' => 'menu_icerik',
            'order_lookup_settings.php' => 'menu_icerik',

            'admin_support.php' => 'menu_destek',
            'bayilik-basvuru.php' => 'menu_destek',

            'review_intro.php' => 'menu_yz',
            'reviews.php' => 'menu_yz',
            'order_image_maker.php' => 'menu_yz',
            'order_image_factory_api.php' => 'menu_yz',

            'admin_notification_settings.php' => 'menu_entegrasyon',
            'countdown_settings.php' => 'menu_entegrasyon',
            'carkifelek_settings.php' => 'menu_entegrasyon',
            'carkifelek_logs.php' => 'menu_entegrasyon',
            'netgsm_settings.php' => 'menu_entegrasyon',
            'telegram_settings.php' => 'menu_entegrasyon',
            'admin_smtp_settings.php' => 'menu_entegrasyon',
            'parasut_settings.php' => 'menu_entegrasyon',
            'paytr_settings.php' => 'menu_entegrasyon',
            'nkolay_settings.php' => 'menu_entegrasyon',
            'iyzico_settings.php' => 'menu_entegrasyon',

            'admin_meta.php' => 'menu_pazarlama',
            'conversion_api_settings.php' => 'menu_pazarlama',
            'attribution_settings.php' => 'menu_pazarlama',
            'attribution_orders.php' => 'menu_pazarlama',
        ];
    }
}

if (!function_exists('admin_rbac_guess_menu_from_stem')) {
    /**
     * Haritada olmayan yeni betikler için dosya adından menü tahmini.
     * Yanlış tahmin riski var; gerekirse admin_rbac_script_overrides() kullanın.
     */
    function admin_rbac_guess_menu_from_stem(string $stem): ?string
    {
        $s = strtolower($stem);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(orders|order_|export|cargo_|abandoned|manage_order|manage_payment|manage_locations|bank_|fake_|blocked|quick_|update_status|update_customer|download_)/', $s) === 1) {
            return 'menu_siparis';
        }
        if (preg_match('/(product|variant|variation)/', $s) === 1) {
            return 'menu_urun';
        }
        if (preg_match('/^(call_|cirolar|final|admin_log|cloaker)/', $s) === 1) {
            return 'menu_rapor';
        }
        if (preg_match('/^(campaigns|campaign_edit|traffic)$/', $s) === 1) {
            return 'menu_rapor';
        }
        if (preg_match('/^(cms|admin_slider|admin_footer|about|contact|shipping|button|theme|sss|custom_|homepage|order_page|edit_message|add_contact|edit_contact|delete_contact|add_shipping|edit_shipping|delete_shipping)/', $s) === 1) {
            return 'menu_icerik';
        }
        if (preg_match('/(support|bayilik)/', $s) === 1) {
            return 'menu_destek';
        }
        if (preg_match('/(review|image_maker)/', $s) === 1) {
            return 'menu_yz';
        }
        if (preg_match('/(notification|countdown|carkifelek|netgsm|telegram|smtp|parasut)/', $s) === 1) {
            return 'menu_entegrasyon';
        }
        if (preg_match('/(meta|conversion|attribution)/', $s) === 1) {
            return 'menu_pazarlama';
        }

        return null;
    }
}

if (!function_exists('admin_script_required_menu')) {
    /**
     * @return '__super_only__'|non-empty-string|null
     */
    function admin_script_required_menu(string $basename): ?string
    {
        $over = admin_rbac_script_overrides();
        if (array_key_exists($basename, $over)) {
            return $over[$basename];
        }
        if (in_array($basename, admin_rbac_public_basenames(), true)) {
            return null;
        }
        if (in_array($basename, admin_rbac_super_only_basenames(), true)) {
            return '__super_only__';
        }
        $byName = admin_rbac_menu_by_exact_basename_rules();
        if (isset($byName[$basename])) {
            return $byName[$basename];
        }
        $stem = preg_replace('/\.php$/i', '', $basename) ?? '';
        $guess = admin_rbac_guess_menu_from_stem($stem);

        return $guess ?? '__super_only__';
    }
}

if (!function_exists('admin_is_super')) {
    function admin_is_super(): bool
    {
        return !empty($_SESSION['admin_is_super']);
    }
}

if (!function_exists('admin_user_can')) {
    function admin_user_can(?string $menu_key): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        if (admin_is_super()) {
            return true;
        }
        if ($menu_key === null) {
            return true;
        }
        $raw = $_SESSION['admin_menu_permissions'] ?? '[]';
        $arr = json_decode((string) $raw, true);
        if (!is_array($arr)) {
            return false;
        }
        if (in_array('*', $arr, true)) {
            return true;
        }

        return in_array($menu_key, $arr, true);
    }
}

if (!function_exists('admin_require_access')) {
    function admin_require_access(): void
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $req = admin_script_required_menu($script);
        if ($req === null) {
            return;
        }
        if ($req === '__super_only__') {
            if (!admin_is_super()) {
                header('Location: index.php?rbac=denied');
                exit;
            }

            return;
        }
        if (!admin_user_can($req)) {
            header('Location: index.php?rbac=denied');
            exit;
        }
    }
}

if (!function_exists('admin_session_refresh_from_db')) {
    function admin_session_refresh_from_db(PDO $pdo, int $user_id): void
    {
        $user_id = max(0, $user_id);
        if ($user_id <= 0) {
            return;
        }
        try {
            $st = $pdo->prepare('SELECT user_id, is_super_admin, menu_permissions FROM users WHERE user_id = ? LIMIT 1');
            $st->execute([$user_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }
            $_SESSION['admin_is_super'] = !empty($row['is_super_admin']);
            $_SESSION['admin_menu_permissions'] = isset($row['menu_permissions']) && $row['menu_permissions'] !== null
                ? (string) $row['menu_permissions']
                : '[]';
        } catch (Throwable $e) {
            $_SESSION['admin_is_super'] = true;
            $_SESSION['admin_menu_permissions'] = '[]';
        }
    }
}
