<?php
declare(strict_types=1);

/**
 * Tek sefer oluştur: yeni özellik tabloları ve orders.is_manual kolonu.
 */
function ensure_feature_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // Kalıcı sürüm damgası: şema güncelse ağır SHOW/ALTER kontrollerini tümden atla.
    // Yeni migrasyon eklerken bu sürümü artır (ör. tarih-harf), tek seferde uygulansın.
    $schemaVersion = '2026-08-17-loc1';
    try {
        $cur = $pdo->query("SELECT meta_value FROM schema_meta WHERE meta_key = 'feature_version'")->fetchColumn();
        if ($cur === $schemaVersion) {
            return; // şema güncel — dosya başına ~1 küçük PK sorgusu, ağır kontroller çalışmaz
        }
    } catch (Throwable $eVer) {
        // schema_meta henüz yok (ilk kurulum/eski sürüm) — tam migrasyon çalışsın
    }

    try {
        // product_variation_options: renk göster toggle (varsa ekle)
        try {
            $ts = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote('product_variation_options'));
            if ($ts instanceof PDOStatement && $ts->rowCount() > 0) {
                $has = $pdo->query('SHOW COLUMNS FROM product_variation_options LIKE ' . $pdo->quote('option_color_enabled'));
                if ($has instanceof PDOStatement && !$has->fetch()) {
                    $pdo->exec("ALTER TABLE product_variation_options
                        ADD COLUMN option_color_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER option_color");
                }
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('product_variation_options color_enabled: ' . $e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS yarim_kalanlar (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ad VARCHAR(255) DEFAULT NULL,
            tel VARCHAR(40) DEFAULT NULL,
            urun VARCHAR(768) DEFAULT NULL,
            fiyat VARCHAR(64) DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            session_id VARCHAR(160) DEFAULT NULL,
            product_id INT UNSIGNED DEFAULT NULL,
            tarih TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_yk_ip (ip),
            KEY idx_yk_tel (tel),
            KEY idx_yk_tarih (tarih)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $ykCol = static function (PDO $pdo, string $column, string $definition): void {
                $chk = $pdo->query(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
                    . $pdo->quote('yarim_kalanlar')
                    . ' AND COLUMN_NAME = ' . $pdo->quote($column)
                );
                if ($chk instanceof PDOStatement && (int) $chk->fetchColumn() === 0) {
                    $pdo->exec('ALTER TABLE yarim_kalanlar ADD COLUMN '.$column.' '.$definition);
                }
            };
            $ykCol($pdo, 'ad_source', 'VARCHAR(64) NULL DEFAULT NULL AFTER product_id');
            $ykCol($pdo, 'utm_source', 'VARCHAR(255) NULL DEFAULT NULL AFTER ad_source');
            $ykCol($pdo, 'utm_medium', 'VARCHAR(255) NULL DEFAULT NULL AFTER utm_source');
            $ykCol($pdo, 'utm_campaign', 'VARCHAR(255) NULL DEFAULT NULL AFTER utm_medium');
            $ykCol($pdo, 'utm_content', 'VARCHAR(255) NULL DEFAULT NULL AFTER utm_campaign');
            $ykCol($pdo, 'utm_term', 'VARCHAR(255) NULL DEFAULT NULL AFTER utm_content');
            $ykCol($pdo, 'is_converted', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER utm_term');
            $ykCol($pdo, 'converted_at', 'DATETIME NULL DEFAULT NULL AFTER is_converted');
            $ykCol($pdo, 'converted_order_id', 'INT UNSIGNED NULL DEFAULT NULL AFTER converted_at');
            try {
                $idxConv = $pdo->query("SHOW INDEX FROM yarim_kalanlar WHERE Key_name = 'idx_yk_converted'");
                if ($idxConv instanceof PDOStatement && ! $idxConv->fetch()) {
                    $pdo->exec('ALTER TABLE yarim_kalanlar ADD KEY idx_yk_converted (is_converted, converted_at)');
                }
            } catch (Throwable $e) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('yarim_kalanlar converted index: '.$e->getMessage());
                }
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('yarim_kalanlar attribution columns: '.$e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bank_name VARCHAR(255) NOT NULL DEFAULT '',
            account_holder VARCHAR(255) NOT NULL DEFAULT '',
            iban VARCHAR(42) NOT NULL DEFAULT '',
            branch VARCHAR(255) DEFAULT NULL,
            currency VARCHAR(8) NOT NULL DEFAULT 'TRY',
            notes TEXT,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_bank_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(128) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            hero_image VARCHAR(512) DEFAULT NULL,
            html_content MEDIUMTEXT,
            meta_title VARCHAR(255) DEFAULT NULL,
            meta_description VARCHAR(512) DEFAULT NULL,
            head_extra MEDIUMTEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_slug (slug),
            KEY idx_cms_act (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS fake_notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name VARCHAR(128) NOT NULL DEFAULT '',
            city_name VARCHAR(128) NOT NULL DEFAULT '',
            time_label VARCHAR(64) NOT NULL DEFAULT 'biraz önce',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            weight INT NOT NULL DEFAULT 10,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_fake_act (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(64) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bip_ip (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_phones (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            phone_digits VARCHAR(20) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bph_phone (phone_digits)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS conversion_api_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            meta_pixel_id VARCHAR(64) DEFAULT NULL,
            meta_capi_access_token MEDIUMTEXT,
            meta_capi_test_code VARCHAR(64) DEFAULT NULL,
            meta_events_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ga4_measurement_id VARCHAR(32) DEFAULT NULL,
            ga4_api_secret VARCHAR(128) DEFAULT NULL,
            ga4_mp_enabled TINYINT(1) NOT NULL DEFAULT 0,
            tiktok_pixel_id VARCHAR(64) DEFAULT NULL,
            tiktok_events_api_token MEDIUMTEXT,
            tiktok_events_enabled TINYINT(1) NOT NULL DEFAULT 0,
            google_gtag_measurement_id VARCHAR(64) DEFAULT NULL,
            google_ads_conversion_id VARCHAR(24) DEFAULT NULL,
            google_ads_conversion_label VARCHAR(128) DEFAULT NULL,
            google_ads_conversion_enabled TINYINT(1) NOT NULL DEFAULT 0,
            head_snippet MEDIUMTEXT,
            body_snippet MEDIUMTEXT,
            yandex_metrica_counter_id VARCHAR(32) DEFAULT NULL,
            microsoft_clarity_project_id VARCHAR(64) DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("INSERT IGNORE INTO conversion_api_settings (id) VALUES (1)");

        try {
            $yc = $pdo->query('SHOW COLUMNS FROM conversion_api_settings LIKE ' . $pdo->quote('yandex_metrica_counter_id'));
            if ($yc instanceof PDOStatement && !$yc->fetch()) {
                $pdo->exec('ALTER TABLE conversion_api_settings ADD COLUMN yandex_metrica_counter_id VARCHAR(32) DEFAULT NULL AFTER body_snippet');
            }
            $mc = $pdo->query('SHOW COLUMNS FROM conversion_api_settings LIKE ' . $pdo->quote('microsoft_clarity_project_id'));
            if ($mc instanceof PDOStatement && !$mc->fetch()) {
                $pdo->exec('ALTER TABLE conversion_api_settings ADD COLUMN microsoft_clarity_project_id VARCHAR(64) DEFAULT NULL AFTER yandex_metrica_counter_id');
            }

        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('conversion_api_settings extras: ' . $e->getMessage());
            }

        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_product_section (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            section_enabled TINYINT(1) NOT NULL DEFAULT 1,
            show_heading TINYINT(1) NOT NULL DEFAULT 1,
            show_heading_main TINYINT(1) NOT NULL DEFAULT 1,
            show_heading_sub TINYINT(1) NOT NULL DEFAULT 1,
            heading_main VARCHAR(255) NOT NULL DEFAULT '',
            heading_sub VARCHAR(512) NOT NULL DEFAULT '',
            heading_main_color VARCHAR(24) NOT NULL DEFAULT '#f97316',
            heading_sub_color VARCHAR(24) NOT NULL DEFAULT '#283458',
            heading_main_font VARCHAR(420) DEFAULT NULL,
            heading_sub_font VARCHAR(420) DEFAULT NULL,
            card_name_color VARCHAR(24) NOT NULL DEFAULT '#15803d',
            card_description_color VARCHAR(24) NOT NULL DEFAULT '#374151',
            card_original_price_color VARCHAR(24) NOT NULL DEFAULT '#6b7280',
            card_sale_price_color VARCHAR(24) NOT NULL DEFAULT '#15803d',
            cta_bg_color VARCHAR(24) NOT NULL DEFAULT '#5fbd0f',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec('INSERT IGNORE INTO homepage_product_section (id, section_enabled) VALUES (1, 1)');

        try {
            $hj = $pdo->query(
                'SELECT heading_main FROM homepage_product_section WHERE id = 1'
            )->fetch(PDO::FETCH_ASSOC);
            $mainEmpty = !$hj || trim((string) ($hj['heading_main'] ?? '')) === '';
            if ($mainEmpty) {
                $fj = $pdo->query(
                    'SELECT home_heading_main, home_heading_sub FROM footer_images WHERE id = 8'
                )->fetch(PDO::FETCH_ASSOC);

                if ($fj && (
                    trim((string) ($fj['home_heading_main'] ?? '')) !== ''
                    || trim((string) ($fj['home_heading_sub'] ?? '')) !== ''
                )) {
                    $u = $pdo->prepare(
                        'UPDATE homepage_product_section SET heading_main = ?, heading_sub = ? WHERE id = 1'
                    );

                    $u->execute([
                        (string) ($fj['home_heading_main'] ?? ''),
                        (string) ($fj['home_heading_sub'] ?? ''),
                    ]);

                }

            }

        } catch (Throwable $e) {
            /* noop */

        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_quick_notes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            note_text MEDIUMTEXT NOT NULL,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_note_pin (pinned, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // orders.is_manual — yoksa ekle
        $hasManual = $pdo->query("SHOW COLUMNS FROM orders LIKE 'is_manual'");
        if ($hasManual instanceof PDOStatement && !$hasManual->fetch()) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0');
        }

        try {
            $pc = $pdo->query('SHOW COLUMNS FROM cms_pages LIKE \'prefer_cms\'');
            if ($pc instanceof PDOStatement && !$pc->fetch()) {
                $pdo->exec('ALTER TABLE cms_pages ADD COLUMN prefer_cms TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
            }
        } catch (Throwable $e) { /* noop */ }

        // cms varsayılan sabit slug kayıtları (boş şablon; içerik admin’den gelir)
        $slugs = ['hakkimizda', 'iletisim', 'kargo-sureci', 'sss'];
        foreach ($slugs as $slug) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM cms_pages WHERE slug = ?');
            $st->execute([$slug]);
            if ((int)$st->fetchColumn() === 0) {
                $titles = [
                    'hakkimizda' => 'Hakkımızda',
                    'iletisim' => 'İletişim',
                    'kargo-sureci' => 'Kargo süreci',
                    'sss' => 'Sıkça Sorulan Sorular',
                ];
                $blank = '<p>Bu içerik yönetim panelinden (<strong>Sayfa yönetimi</strong>) düzenlenir.</p>';
                if ($slug === 'sss' && is_file(__DIR__ . '/legal_templates.php')) {
                    require_once __DIR__ . '/legal_templates.php';
                    if (function_exists('legal_sss_html')) {
                        $blank = legal_sss_html();
                    }
                }
                $ins = $pdo->prepare(
                    'INSERT INTO cms_pages (slug, title, html_content, meta_title, is_active, prefer_cms) VALUES (?,?,?,?,1,0)'
                );
                $ins->execute([$slug, $titles[$slug] ?? $slug, $blank, $titles[$slug] ?? $slug]);
            }
        }

        try {
            $nsCol = $pdo->query("SHOW COLUMNS FROM notification_settings LIKE 'show_discount_rate'");
            if ($nsCol instanceof PDOStatement && ! $nsCol->fetch()) {
                $pdo->exec('ALTER TABLE notification_settings ADD COLUMN show_discount_rate TINYINT(1) NOT NULL DEFAULT 0 AFTER discount_rate');
            }
        } catch (Throwable $eNs) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('notification_settings show_discount_rate: ' . $eNs->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS checkout_module_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            corporate_invoice_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec('INSERT IGNORE INTO checkout_module_settings (id, corporate_invoice_enabled) VALUES (1, 0)');

        try {
            $hasC1 = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('order_cookie_gate_enabled'));
            if ($hasC1 instanceof PDOStatement && !$hasC1->fetch()) {
                $pdo->exec('ALTER TABLE checkout_module_settings
                    ADD COLUMN order_cookie_gate_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER corporate_invoice_enabled,
                    ADD COLUMN order_cookie_seconds INT NOT NULL DEFAULT 60 AFTER order_cookie_gate_enabled,
                    ADD COLUMN order_dupe_server_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER order_cookie_seconds,
                    ADD COLUMN order_dupe_window_seconds INT NOT NULL DEFAULT 86400 AFTER order_dupe_server_enabled');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings guard cols: ' . $e->getMessage());
            }

        }

        try {
            $hasUtm = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('utm_capture_enabled'));
            if ($hasUtm instanceof PDOStatement && ! $hasUtm->fetch()) {
                $pdo->exec('ALTER TABLE checkout_module_settings
                    ADD COLUMN utm_capture_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER order_dupe_window_seconds');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings utm_capture_enabled: ' . $e->getMessage());
            }
        }

        try {
            $hasLookup = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('order_lookup_source'));
            if ($hasLookup instanceof PDOStatement && ! $hasLookup->fetch()) {
                $pdo->exec("ALTER TABLE checkout_module_settings
                    ADD COLUMN order_lookup_source VARCHAR(10) NOT NULL DEFAULT 'local' AFTER utm_capture_enabled");
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings order_lookup_source: ' . $e->getMessage());
            }
        }

        try {
            $hasAb = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('abandoned_capture_enabled'));
            if ($hasAb instanceof PDOStatement && ! $hasAb->fetch()) {
                $pdo->exec('ALTER TABLE checkout_module_settings
                    ADD COLUMN abandoned_capture_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER order_lookup_source,
                    ADD COLUMN abandoned_trigger_form TINYINT(1) NOT NULL DEFAULT 1 AFTER abandoned_capture_enabled,
                    ADD COLUMN abandoned_trigger_scroll TINYINT(1) NOT NULL DEFAULT 1 AFTER abandoned_trigger_form,
                    ADD COLUMN abandoned_trigger_products TINYINT(1) NOT NULL DEFAULT 1 AFTER abandoned_trigger_scroll,
                    ADD COLUMN abandoned_scroll_pct INT NOT NULL DEFAULT 50 AFTER abandoned_trigger_products,
                    ADD COLUMN abandoned_product_only TINYINT(1) NOT NULL DEFAULT 1 AFTER abandoned_scroll_pct,
                    ADD COLUMN abandoned_auto_trigger VARCHAR(16) NOT NULL DEFAULT \'scroll\' AFTER abandoned_product_only');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings abandoned capture: ' . $e->getMessage());
            }
        }

        try {
            $hasAuto = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('abandoned_auto_trigger'));
            if ($hasAuto instanceof PDOStatement && ! $hasAuto->fetch()) {
                $pdo->exec("ALTER TABLE checkout_module_settings
                    ADD COLUMN abandoned_auto_trigger VARCHAR(16) NOT NULL DEFAULT 'scroll' AFTER abandoned_product_only");
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings abandoned_auto_trigger: ' . $e->getMessage());
            }
        }

        try {
            $hasPc = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('page_cache_enabled'));
            if ($hasPc instanceof PDOStatement && ! $hasPc->fetch()) {
                $pdo->exec('ALTER TABLE checkout_module_settings
                    ADD COLUMN page_cache_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    ADD COLUMN page_cache_ttl INT UNSIGNED NOT NULL DEFAULT 3600');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings page_cache: ' . $e->getMessage());
            }
        }

        try {
            $hasSmsV = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('order_sms_verify_enabled'));
            if ($hasSmsV instanceof PDOStatement && ! $hasSmsV->fetch()) {
                $pdo->exec('ALTER TABLE checkout_module_settings
                    ADD COLUMN order_sms_verify_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    ADD COLUMN order_sms_front_otp_enabled TINYINT(1) NOT NULL DEFAULT 1');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings sms_verify: ' . $e->getMessage());
            }
        }

        try {
            $hasAbSms = $pdo->query('SHOW COLUMNS FROM checkout_module_settings LIKE ' . $pdo->quote('abandoned_sms_enabled'));
            if ($hasAbSms instanceof PDOStatement && ! $hasAbSms->fetch()) {
                $pdo->exec('ALTER TABLE checkout_module_settings
                    ADD COLUMN abandoned_sms_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    ADD COLUMN abandoned_whatsapp_number VARCHAR(20) NOT NULL DEFAULT \'05527391073\'');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('checkout_module_settings abandoned_sms: ' . $e->getMessage());
            }
        }

        try {
            $ykAb = $pdo->query('SHOW COLUMNS FROM yarim_kalanlar LIKE ' . $pdo->quote('recovery_sms_sent_at'));
            if ($ykAb instanceof PDOStatement && ! $ykAb->fetch()) {
                $pdo->exec('ALTER TABLE yarim_kalanlar ADD COLUMN recovery_sms_sent_at DATETIME NULL DEFAULT NULL AFTER converted_order_id');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('yarim_kalanlar recovery_sms_sent_at: ' . $e->getMessage());
            }
        }

        try {
            $hasAbOrd = $pdo->query('SHOW COLUMNS FROM orders LIKE ' . $pdo->quote('abandoned_yarim_id'));
            if ($hasAbOrd instanceof PDOStatement && ! $hasAbOrd->fetch()) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN abandoned_yarim_id INT UNSIGNED NULL DEFAULT NULL AFTER referrer');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('orders abandoned_yarim_id: ' . $e->getMessage());
            }
        }

        try {
            $ts = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote('reviews_settings'));
            if ($ts instanceof PDOStatement && $ts->rowCount() > 0) {
                $rq = $pdo->query('SHOW COLUMNS FROM reviews_settings LIKE ' . $pdo->quote('show_manual_front'));
                if ($rq instanceof PDOStatement && !$rq->fetch()) {
                    $pdo->exec('ALTER TABLE reviews_settings ADD COLUMN show_manual_front TINYINT(1) NOT NULL DEFAULT 1 AFTER show_reviews');
                    $pdo->exec('ALTER TABLE reviews_settings ADD COLUMN show_ai_front TINYINT(1) NOT NULL DEFAULT 1 AFTER show_manual_front');

                }

            }

        } catch (Throwable $e) {
            /* noop */

        }

        try {
            $pai = $pdo->query('SHOW COLUMNS FROM product_reviews LIKE ' . $pdo->quote('is_ai'));
            if ($pai instanceof PDOStatement && !$pai->fetch()) {
                $pdo->exec('ALTER TABLE product_reviews ADD COLUMN is_ai TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified');

            }

        } catch (Throwable $e) {
            /* noop */

        }


        $invoiceCols = [
            'invoice_vkn' => 'VARCHAR(32) NULL DEFAULT NULL',
            'invoice_tax_office' => 'VARCHAR(128) NULL DEFAULT NULL',
            'invoice_company_name' => 'VARCHAR(255) NULL DEFAULT NULL',
            'invoice_address' => 'TEXT NULL',
        ];
        foreach ($invoiceCols as $col => $def) {
            try {
                $chk = $pdo->query('SHOW COLUMNS FROM orders LIKE ' . $pdo->quote($col));
                if ($chk instanceof PDOStatement && !$chk->fetch()) {
                    $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $col . ' ' . $def);
                }
            } catch (Throwable $e) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('ensure_feature_schema orders col ' . $col . ': ' . $e->getMessage());
                }
            }
        }

        $orderAttrCols = [
            'utm_source' => 'VARCHAR(255) NULL DEFAULT NULL',
            'utm_medium' => 'VARCHAR(255) NULL DEFAULT NULL',
            'utm_campaign' => 'VARCHAR(255) NULL DEFAULT NULL',
            'utm_content' => 'VARCHAR(255) NULL DEFAULT NULL',
            'utm_term' => 'VARCHAR(255) NULL DEFAULT NULL',
            'attribution_click_json' => 'TEXT NULL',
            'attribution_landing_url' => 'VARCHAR(1024) NULL DEFAULT NULL',
        ];
        foreach ($orderAttrCols as $col => $def) {
            try {
                $chk = $pdo->query('SHOW COLUMNS FROM orders LIKE ' . $pdo->quote($col));
                if ($chk instanceof PDOStatement && ! $chk->fetch()) {
                    $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $col . ' ' . $def);
                }
            } catch (Throwable $e) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('ensure_feature_schema orders attribution ' . $col . ': ' . $e->getMessage());
                }
            }
        }

        try {
            $pic = $pdo->query('SHOW COLUMNS FROM orders LIKE ' . $pdo->quote('parasut_invoice_id'));
            if ($pic instanceof PDOStatement && !$pic->fetch()) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN parasut_invoice_id VARCHAR(64) NULL DEFAULT NULL AFTER invoice_address');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('ensure_feature_schema orders parasut_invoice_id: ' . $e->getMessage());
            }
        }

        $rbacUserCols = [
            'is_super_admin' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'menu_permissions' => 'TEXT NULL',
        ];
        foreach ($rbacUserCols as $col => $def) {
            try {
                $chk = $pdo->query('SHOW COLUMNS FROM users LIKE ' . $pdo->quote($col));
                if ($chk instanceof PDOStatement && !$chk->fetch()) {
                    $pdo->exec('ALTER TABLE users ADD COLUMN ' . $col . ' ' . $def);
                }
            } catch (Throwable $e) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('ensure_feature_schema users rbac ' . $col . ': ' . $e->getMessage());
                }
            }
        }
        try {
            $totalU = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $superU = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_super_admin = 1')->fetchColumn();
            if ($totalU > 0 && $superU === 0) {
                $pdo->exec('UPDATE users SET is_super_admin = 1');
            }
        } catch (Throwable $e) {
            /* noop */
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS parasut_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            company_id VARCHAR(32) NOT NULL DEFAULT '',
            client_id VARCHAR(128) NOT NULL DEFAULT '',
            client_secret VARCHAR(255) NOT NULL DEFAULT '',
            parasut_username VARCHAR(255) NOT NULL DEFAULT '',
            parasut_password VARCHAR(255) NOT NULL DEFAULT '',
            access_token MEDIUMTEXT NULL,
            refresh_token MEDIUMTEXT NULL,
            token_expires_at INT UNSIGNED NOT NULL DEFAULT 0,
            vat_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            kdv_included TINYINT(1) NOT NULL DEFAULT 1,
            bireysel_vergi_no VARCHAR(20) NOT NULL DEFAULT '11111111111',
            product_id VARCHAR(32) NOT NULL DEFAULT '',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec('INSERT IGNORE INTO parasut_settings (id) VALUES (1)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS utm_campaign_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(128) NOT NULL DEFAULT '',
            landing_path VARCHAR(512) NOT NULL DEFAULT '/',
            utm_source VARCHAR(255) NULL DEFAULT NULL,
            utm_medium VARCHAR(255) NULL DEFAULT NULL,
            utm_campaign VARCHAR(255) NOT NULL DEFAULT '',
            utm_content VARCHAR(255) NULL DEFAULT NULL,
            utm_term VARCHAR(255) NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_utm_links_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pst = $pdo->query('SHOW COLUMNS FROM parasut_settings LIKE ' . $pdo->quote('product_id'));
            if ($pst instanceof PDOStatement && (int)$pst->rowCount() === 0) {
                $pdo->exec("ALTER TABLE parasut_settings ADD COLUMN product_id VARCHAR(32) NOT NULL DEFAULT '' AFTER bireysel_vergi_no");
            }
        } catch (Throwable $e) {
            error_log('ensure_feature_schema parasut_settings product_id: ' . $e->getMessage());
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS cloaker_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            cloaker_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Master: 0 ise günlük ve engelleme çalışmaz',
            traffic_log_enabled TINYINT(1) NOT NULL DEFAULT 1,
            blocking_enabled TINYINT(1) NOT NULL DEFAULT 0,
            block_empty_ua TINYINT(1) NOT NULL DEFAULT 1,
            block_scraper_ua TINYINT(1) NOT NULL DEFAULT 1,
            respect_benign_bots TINYINT(1) NOT NULL DEFAULT 1,
            extra_block_substrings MEDIUMTEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('INSERT IGNORE INTO cloaker_settings (id) VALUES (1)');
        try {
            $ceCol = $pdo->query('SHOW COLUMNS FROM cloaker_settings LIKE ' . $pdo->quote('cloaker_enabled'));
            if ($ceCol instanceof PDOStatement && !$ceCol->fetch()) {
                $pdo->exec(
                    'ALTER TABLE cloaker_settings ADD COLUMN cloaker_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER id'
                );
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('cloaker_settings cloaker_enabled column: ' . $e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS cloaker_traffic (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(64) NOT NULL DEFAULT '',
            user_agent VARCHAR(512) NOT NULL DEFAULT '',
            request_uri VARCHAR(1024) NOT NULL DEFAULT '',
            referer VARCHAR(512) NOT NULL DEFAULT '',
            verdict VARCHAR(24) NOT NULL DEFAULT 'allow',
            block_reason VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_ct_created (created_at),
            KEY idx_ct_ip (ip),
            KEY idx_ct_verdict (verdict)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $cloakerV2Cols = [
            ['advanced_cloaker', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER extra_block_substrings'],
            ['ref_key', 'VARCHAR(128) NOT NULL DEFAULT \'\''],
            ['require_ref', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['safe_page_target', 'VARCHAR(512) NOT NULL DEFAULT \'safe-page.php\''],
            ['cloak_method', 'TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT \'1=redirect 2=shadow\''],
            ['dns_mode', 'VARCHAR(16) NOT NULL DEFAULT \'esnek\''],
            ['js_mode', 'VARCHAR(16) NOT NULL DEFAULT \'esnek\''],
            ['geoip_on', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['allowed_countries', 'VARCHAR(256) NOT NULL DEFAULT \'TR\''],
            ['geoip_mmdb_path', 'VARCHAR(512) NOT NULL DEFAULT \'\''],
            ['ip_whitelist', 'MEDIUMTEXT NULL'],
            ['ip_blacklist', 'MEDIUMTEXT NULL'],
            ['block_known_bots', 'TINYINT(1) NOT NULL DEFAULT 1'],
            ['threat_handling', 'VARCHAR(24) NOT NULL DEFAULT \'safe_shadow\''],
            ['cloaker_exempt_basenames', 'TEXT NULL'],
            ['stat_total', 'INT NOT NULL DEFAULT 0'],
            ['stat_passed', 'INT NOT NULL DEFAULT 0'],
            ['stat_blocked', 'INT NOT NULL DEFAULT 0'],
            ['public_base_url', 'VARCHAR(512) NOT NULL DEFAULT \'\' COMMENT \'Kampanya linki ve yönlendirme tabanı; boş=otomatik\''],
            ['campaign_mode', 'VARCHAR(16) NOT NULL DEFAULT \'direct\' COMMENT \'direct|paravan\''],
            ['paravan_url', 'VARCHAR(512) NOT NULL DEFAULT \'\' COMMENT \'Reklamda görünen sahte/paravan URL\''],
            ['paravan_label', 'VARCHAR(64) NOT NULL DEFAULT \'\' COMMENT \'Hazır paravan şablonu anahtarı\''],
            ['gateway_enabled', 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'go.php geçit aktif\''],
            ['gateway_token', 'VARCHAR(32) NOT NULL DEFAULT \'\' COMMENT \'Geçit güvenlik anahtarı\''],
            ['safe_page_brand_name', 'VARCHAR(128) NOT NULL DEFAULT \'\''],
            ['safe_page_meta_title', 'VARCHAR(255) NOT NULL DEFAULT \'\''],
            ['safe_page_meta_description', 'VARCHAR(512) NOT NULL DEFAULT \'\''],
            ['safe_page_hero_title', 'VARCHAR(255) NOT NULL DEFAULT \'\''],
            ['safe_page_hero_subtitle', 'VARCHAR(512) NOT NULL DEFAULT \'\''],
            ['safe_page_posts_json', 'MEDIUMTEXT NULL'],
        ];
        foreach ($cloakerV2Cols as $colPair) {
            [$cname, $cdef] = $colPair;
            try {
                $chk = $pdo->query('SHOW COLUMNS FROM cloaker_settings LIKE ' . $pdo->quote($cname));
                if ($chk instanceof PDOStatement && !$chk->fetch()) {
                    $pdo->exec('ALTER TABLE cloaker_settings ADD COLUMN `' . $cname . '` ' . $cdef);
                }
            } catch (Throwable $e) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('cloaker_settings column ' . $cname . ': ' . $e->getMessage());
                }
            }
        }

        try {
            $vcol = $pdo->query('SHOW COLUMNS FROM cloaker_traffic LIKE ' . $pdo->quote('vendor_label'));
            if ($vcol instanceof PDOStatement && !$vcol->fetch()) {
                $pdo->exec(
                    "ALTER TABLE cloaker_traffic ADD COLUMN vendor_label VARCHAR(32) NOT NULL DEFAULT '' AFTER user_agent"
                );
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('cloaker_traffic vendor_label: ' . $e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS link_cloak_campaigns (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(128) NOT NULL DEFAULT '',
            token VARCHAR(32) NOT NULL,
            paravan_url VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'Sahte/görünen URL — bot ve inceleme',
            money_url VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'Gerçek hedef (güncel)',
            money_urls_json MEDIUMTEXT NULL COMMENT 'Rotasyon için ek gerçek URL listesi JSON',
            rotation_index INT UNSIGNED NOT NULL DEFAULT 0,
            rotate_after_clicks INT UNSIGNED NOT NULL DEFAULT 25,
            warmup_paravan_hits INT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Aktif modda ilk N toplam tık paravan',
            clicks_since_rotate INT UNSIGNED NOT NULL DEFAULT 0,
            run_status VARCHAR(16) NOT NULL DEFAULT 'passive' COMMENT 'passive|active',
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            strict_filter TINYINT(1) NOT NULL DEFAULT 1,
            total_hits INT UNSIGNED NOT NULL DEFAULT 0,
            human_hits INT UNSIGNED NOT NULL DEFAULT 0,
            bot_hits INT UNSIGNED NOT NULL DEFAULT 0,
            activated_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lc_token (token),
            KEY idx_lc_enabled (is_enabled),
            KEY idx_lc_run (run_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS link_cloak_traffic (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(64) NOT NULL DEFAULT '',
            user_agent VARCHAR(512) NOT NULL DEFAULT '',
            vendor_label VARCHAR(32) NOT NULL DEFAULT '',
            request_uri VARCHAR(1024) NOT NULL DEFAULT '',
            referer VARCHAR(512) NOT NULL DEFAULT '',
            verdict VARCHAR(16) NOT NULL DEFAULT 'human',
            block_reason VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_lct_campaign (campaign_id),
            KEY idx_lct_created (created_at),
            KEY idx_lct_verdict (verdict)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $lcWarmup = $pdo->query('SHOW COLUMNS FROM link_cloak_campaigns LIKE ' . $pdo->quote('warmup_paravan_hits'));
            if ($lcWarmup instanceof PDOStatement && ! $lcWarmup->fetch()) {
                $pdo->exec('ALTER TABLE link_cloak_campaigns ADD COLUMN warmup_paravan_hits INT UNSIGNED NOT NULL DEFAULT 50 AFTER rotate_after_clicks');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('link_cloak_campaigns warmup_paravan_hits: ' . $e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_forms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            slug VARCHAR(128) NOT NULL,
            success_message VARCHAR(512) NOT NULL DEFAULT 'Gönderiminiz alındı, teşekkür ederiz.',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cf_slug (slug),
            KEY idx_cf_act (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_form_fields (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id INT UNSIGNED NOT NULL,
            field_key VARCHAR(64) NOT NULL,
            label VARCHAR(255) NOT NULL DEFAULT '',
            field_type VARCHAR(32) NOT NULL DEFAULT 'text',
            options_text TEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_cff_form (form_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_form_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id INT UNSIGNED NOT NULL,
            payload_json MEDIUMTEXT NOT NULL,
            ip VARCHAR(64) NOT NULL DEFAULT '',
            user_agent VARCHAR(512) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cfe_form (form_id),
            KEY idx_cfe_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $cfShow = $pdo->query("SHOW COLUMNS FROM custom_forms LIKE 'show_in_menu'");
        if ($cfShow instanceof PDOStatement && !$cfShow->fetch()) {
            $pdo->exec(
                'ALTER TABLE custom_forms ADD COLUMN show_in_menu TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active, ADD COLUMN menu_sort INT NOT NULL DEFAULT 50 AFTER show_in_menu, ADD COLUMN menu_label VARCHAR(255) NULL DEFAULT NULL AFTER menu_sort'
            );
        }


        $pdo->exec("CREATE TABLE IF NOT EXISTS carkifelek_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL DEFAULT 'Çarkıfelek Çevir, İndirim Kazan!',
            subtitle TEXT,
            prizes_json MEDIUMTEXT NOT NULL,
            color1 VARCHAR(16) NOT NULL DEFAULT '#ff6b6b',
            color2 VARCHAR(16) NOT NULL DEFAULT '#ee5a6f',
            accent VARCHAR(16) NOT NULL DEFAULT '#fbbf24',
            auto_popup TINYINT(1) NOT NULL DEFAULT 1,
            auto_delay_seconds SMALLINT NOT NULL DEFAULT 5,
            force_free_shipping TINYINT(1) NOT NULL DEFAULT 0,
            limit_message TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS carkifelek_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(64) NOT NULL,
            tarih INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_carkifelek_ip_time (ip, tarih)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $chkCf = $pdo->query('SELECT COUNT(*) FROM carkifelek_settings WHERE id = 1');
            if ($chkCf instanceof PDOStatement && (int) $chkCf->fetchColumn() === 0) {
                $defaultPrizes = json_encode([
                    '%10 İndirim', '%15 İndirim', '%20 İndirim', '%25 İndirim',
                    'Ücretsiz Kargo', 'Tekrar Deneyin',
                ], JSON_UNESCAPED_UNICODE);
                $insCf = $pdo->prepare(
                    'INSERT INTO carkifelek_settings (id, is_active, title, subtitle, prizes_json, color1, color2, accent, auto_popup, auto_delay_seconds, force_free_shipping, limit_message) VALUES (1, 0, ?, ?, ?, ?, ?, ?, 1, 5, 0, ?)'
                );
                $insCf->execute([
                    'Çarkıfelek Çevir, İndirim Kazan!',
                    'Günde 1 kez çarkıfelek çevirerek indirim kazanabilirsiniz!',
                    $defaultPrizes,
                    '#ff6b6b',
                    '#ee5a6f',
                    '#fbbf24',
                    'Günde 1 kez çarkıfelek çevirerek indirim kazanabilirsiniz!',
                ]);
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('ensure_feature_schema carkifelek_settings: ' . $e->getMessage());
            }
        }

        try {
            $fabCol = $pdo->query("SHOW COLUMNS FROM carkifelek_settings LIKE 'fab_enabled'");
            if ($fabCol instanceof PDOStatement && ! $fabCol->fetch()) {
                $pdo->exec('ALTER TABLE carkifelek_settings ADD COLUMN fab_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active');
            }
        } catch (Throwable $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_page_ui (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            config_json LONGTEXT,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO order_page_ui (id, config_json) VALUES (1, '{}')");

        // NETGSM / Telegram bildirim kolonları (anahtar + kural bazlı aç/kapa)
        try {
            $ncol = static function (PDO $pdo, string $table, string $column, string $definition): void {
                $chk = $pdo->query(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
                    . $pdo->quote($table)
                    . ' AND COLUMN_NAME = ' . $pdo->quote($column)
                );
                if ($chk instanceof PDOStatement && (int) $chk->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$column} {$definition}");
                }
            };
            $tnet = $pdo->query("SHOW TABLES LIKE 'netgsm_settings'");
            if ($tnet instanceof PDOStatement && $tnet->fetch()) {
                $ncol($pdo, 'netgsm_settings', 'is_enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
                $ncol($pdo, 'netgsm_settings', 'sms_new_order_enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
                $ncol($pdo, 'netgsm_settings', 'sms_status_change_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
                $ncol($pdo, 'netgsm_settings', 'sms_status_trigger_id', 'INT NOT NULL DEFAULT 16');
                $ncol($pdo, 'netgsm_settings', 'message_on_status', 'TEXT NULL');
                $ncol($pdo, 'netgsm_settings', 'sms_provider', "VARCHAR(20) NOT NULL DEFAULT 'mutlucell'");
                require_once __DIR__ . '/netgsm_customer_sms.php';
                $defaultNewOrderSms = netgsm_default_new_order_message();
                $pdo->prepare(
                    'UPDATE netgsm_settings SET message = ?
                     WHERE id = 1 AND (
                         message IS NULL OR TRIM(message) = \'\' OR TRIM(message) REGEXP \'^[0-9]{1,8}$\' OR CHAR_LENGTH(TRIM(message)) < 15
                     )'
                )->execute([$defaultNewOrderSms]);
            }
            $ttel = $pdo->query("SHOW TABLES LIKE 'telegram_settings'");
            if ($ttel instanceof PDOStatement && $ttel->fetch()) {
                $ncol($pdo, 'telegram_settings', 'is_enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
                $ncol($pdo, 'telegram_settings', 'notify_new_order', 'TINYINT(1) NOT NULL DEFAULT 1');
                $ncol($pdo, 'telegram_settings', 'notify_support', 'TINYINT(1) NOT NULL DEFAULT 1');
                $ncol($pdo, 'telegram_settings', 'notify_partner', 'TINYINT(1) NOT NULL DEFAULT 1');
                $ncol($pdo, 'telegram_settings', 'notify_admin_status', 'TINYINT(1) NOT NULL DEFAULT 0');
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('ensure_feature_schema netgsm/telegram columns: ' . $e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
            payment_method_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            method_name VARCHAR(128) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (payment_method_id),
            KEY idx_pm_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pmActiveCol = $pdo->query('SHOW COLUMNS FROM payment_methods LIKE ' . $pdo->quote('is_active'));
            if ($pmActiveCol instanceof PDOStatement && !$pmActiveCol->fetch()) {
                $pdo->exec("ALTER TABLE payment_methods 
                    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER method_name,
                    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_active,
                    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order,
                    ADD KEY idx_pm_active (is_active, sort_order)");
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('payment_methods columns: ' . $e->getMessage());
            }
        }

        $pmCount = $pdo->query('SELECT COUNT(*) FROM payment_methods');
        if ($pmCount instanceof PDOStatement && (int)$pmCount->fetchColumn() === 0) {
            $pdo->exec("INSERT INTO payment_methods (payment_method_id, method_name, sort_order) VALUES
                (1, 'Kapıda Nakit Ödeme', 1),
                (2, 'Kapıda Kredi Kartı ile Ödeme', 2)");
        }

        // SKU kolonu ekleme (products tablosu)
        try {
            $skuCol = $pdo->query('SHOW COLUMNS FROM products LIKE ' . $pdo->quote('sku'));
            if ($skuCol instanceof PDOStatement && !$skuCol->fetch()) {
                $pdo->exec("ALTER TABLE products 
                    ADD COLUMN sku VARCHAR(64) DEFAULT NULL AFTER product_id,
                    ADD UNIQUE KEY idx_sku (sku)");
                
                // Mevcut ürünler için otomatik SKU oluştur
                $products = $pdo->query('SELECT product_id FROM products WHERE sku IS NULL OR sku = "" ORDER BY product_id ASC')->fetchAll(PDO::FETCH_COLUMN);
                $updateSku = $pdo->prepare('UPDATE products SET sku = ? WHERE product_id = ?');
                foreach ($products as $pid) {
                    $generatedSku = 'PRD-' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT);
                    $updateSku->execute([$generatedSku, $pid]);
                }
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('products SKU column: ' . $e->getMessage());
            }
        }

        // Vitrin ürün sırası (index.php)
        try {
            $sortCol = $pdo->query('SHOW COLUMNS FROM products LIKE ' . $pdo->quote('display_order'));
            if ($sortCol instanceof PDOStatement && ! $sortCol->fetch()) {
                $pdo->exec('ALTER TABLE products ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER status');
                $pdo->exec('ALTER TABLE products ADD KEY idx_products_vitrin_sort (status, display_order, product_id)');
                $pdo->exec(
                    'UPDATE products p
                     INNER JOIN (
                         SELECT product_id, ROW_NUMBER() OVER (ORDER BY product_price ASC, product_id ASC) * 10 AS sort_rank
                         FROM products
                     ) ranked ON ranked.product_id = p.product_id
                     SET p.display_order = ranked.sort_rank'
                );
            }
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('products display_order column: ' . $e->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS countdown_timer (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            total_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            end_time DATETIME NULL DEFAULT NULL,
            message VARCHAR(255) NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('INSERT IGNORE INTO countdown_timer (id, total_seconds, is_active) VALUES (1, 0, 0)');
        foreach (['end_time' => 'DATETIME NULL DEFAULT NULL AFTER is_active', 'message' => "VARCHAR(255) NULL DEFAULT NULL AFTER end_time"] as $col => $def) {
            try {
                $hasCol = $pdo->query('SHOW COLUMNS FROM countdown_timer LIKE ' . $pdo->quote($col));
                if ($hasCol instanceof PDOStatement && !$hasCol->fetch()) {
                    $pdo->exec('ALTER TABLE countdown_timer ADD COLUMN ' . $col . ' ' . $def);
                }
            } catch (Throwable $eCd) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('countdown_timer ' . $col . ': ' . $eCd->getMessage());
                }
            }
        }
        foreach (['show_on_index' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER message', 'show_on_order' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER show_on_index'] as $col => $def) {
            try {
                $hasCol = $pdo->query('SHOW COLUMNS FROM countdown_timer LIKE ' . $pdo->quote($col));
                if ($hasCol instanceof PDOStatement && !$hasCol->fetch()) {
                    $pdo->exec('ALTER TABLE countdown_timer ADD COLUMN ' . $col . ' ' . $def);
                }
            } catch (Throwable $eCd) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    error_log('countdown_timer ' . $col . ': ' . $eCd->getMessage());
                }
            }
        }

        $fakeCount = $pdo->query('SELECT COUNT(*) FROM fake_notifications');
        if ($fakeCount instanceof PDOStatement && (int) $fakeCount->fetchColumn() === 0) {
            require_once __DIR__ . '/fake_notification_defaults.php';
            fake_notifications_seed_defaults($pdo, true);
        }

        try {
            $smtpCol = $pdo->query("SHOW COLUMNS FROM smtp_settings LIKE 'is_enabled'");
            if ($smtpCol instanceof PDOStatement && ! $smtpCol->fetch()) {
                $pdo->exec('ALTER TABLE smtp_settings ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER id');
            }
        } catch (Throwable $eSmtp) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('smtp_settings is_enabled: ' . $eSmtp->getMessage());
            }
        }

        // Ödeme geçitleri (PayTR / iyzico) + çağrı merkezi notu
        try {
            $ocol = static function (PDO $pdo, string $column, string $definition): void {
                $chk = $pdo->query(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
                    . $pdo->quote('orders') . ' AND COLUMN_NAME = ' . $pdo->quote($column)
                );
                if ($chk instanceof PDOStatement && (int) $chk->fetchColumn() === 0) {
                    $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $column . ' ' . $definition);
                }
            };
            $ocol($pdo, 'payment_status', "VARCHAR(20) NULL DEFAULT 'paid' AFTER payment_method_id");
            $ocol($pdo, 'gateway_code', 'VARCHAR(24) NULL DEFAULT NULL AFTER payment_status');
            $ocol($pdo, 'payment_merchant_oid', 'VARCHAR(64) NULL DEFAULT NULL AFTER gateway_code');
            $ocol($pdo, 'gateway_transaction_id', 'VARCHAR(190) NULL DEFAULT NULL AFTER payment_merchant_oid');
            $ocol($pdo, 'gateway_token', 'VARCHAR(190) NULL DEFAULT NULL AFTER gateway_transaction_id');
            $ocol($pdo, 'paid_at', 'DATETIME NULL DEFAULT NULL AFTER gateway_token');
            $ocol($pdo, 'cc_last_call_note', 'VARCHAR(500) NULL DEFAULT NULL AFTER paid_at');
            $ocol($pdo, 'cc_last_call_at', 'DATETIME NULL DEFAULT NULL AFTER cc_last_call_note');

            $pmgw = $pdo->query('SHOW COLUMNS FROM payment_methods LIKE ' . $pdo->quote('gateway_code'));
            if ($pmgw instanceof PDOStatement && ! $pmgw->fetch()) {
                $pdo->exec("ALTER TABLE payment_methods ADD COLUMN gateway_code VARCHAR(24) NOT NULL DEFAULT 'cod' AFTER method_name");
            }
        } catch (Throwable $eGw) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('payment gateway schema: ' . $eGw->getMessage());
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS paytr_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            merchant_id VARCHAR(32) NOT NULL DEFAULT '',
            merchant_key VARCHAR(255) NOT NULL DEFAULT '',
            merchant_salt VARCHAR(255) NOT NULL DEFAULT '',
            test_mode TINYINT(1) NOT NULL DEFAULT 0,
            no_installment TINYINT(1) NOT NULL DEFAULT 0,
            max_installment INT NOT NULL DEFAULT 0,
            debug_on TINYINT(1) NOT NULL DEFAULT 0,
            timeout_limit INT NOT NULL DEFAULT 30,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('INSERT IGNORE INTO paytr_settings (id) VALUES (1)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS iyzico_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            api_key VARCHAR(128) NOT NULL DEFAULT '',
            secret_key VARCHAR(255) NOT NULL DEFAULT '',
            sandbox TINYINT(1) NOT NULL DEFAULT 1,
            enabled_installments VARCHAR(64) NOT NULL DEFAULT '2,3,6,9',
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('INSERT IGNORE INTO iyzico_settings (id) VALUES (1)');

        try {
            require_once __DIR__ . '/fake_notification_defaults.php';
            fake_notifications_purge_foreign($pdo);
        } catch (Throwable $eFn) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('fake_notifications purge: ' . $eFn->getMessage());
            }
        }

        try {
            $hasPaytr = $pdo->query("SELECT COUNT(*) FROM payment_methods WHERE gateway_code = 'paytr'")->fetchColumn();
            if ((int) $hasPaytr === 0) {
                $pdo->exec("INSERT INTO payment_methods (method_name, gateway_code, is_active, sort_order) VALUES ('Kredi Kartı (PayTR)', 'paytr', 0, 10)");
            }
            $hasIyz = $pdo->query("SELECT COUNT(*) FROM payment_methods WHERE gateway_code = 'iyzico'")->fetchColumn();
            if ((int) $hasIyz === 0) {
                $pdo->exec("INSERT INTO payment_methods (method_name, gateway_code, is_active, sort_order) VALUES ('Kredi Kartı (iyzico)', 'iyzico', 0, 11)");
            }
        } catch (Throwable $ePmSeed) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('payment_methods gateway seed: ' . $ePmSeed->getMessage());
            }
        }

        try {
            $pdo->exec(
                "UPDATE footer_images SET order_note_text = TRIM(REPLACE(order_note_text, ' Özel istekleriniz buna dahildir.', ''))
                 WHERE id = 5 AND order_note_text LIKE " . $pdo->quote('%Özel istekleriniz buna dahildir%')
            );
        } catch (Throwable $e) {
            // ignore
        }

        try {
            $pmCol = static function (PDO $pdo, string $table, string $column, string $definition): void {
                $chk = $pdo->query(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
                    . $pdo->quote($table)
                    . ' AND COLUMN_NAME = ' . $pdo->quote($column)
                );
                if ($chk instanceof PDOStatement && (int) $chk->fetchColumn() === 0) {
                    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
                }
            };
            $pmCol($pdo, 'page_meta', 'meta_description', 'VARCHAR(512) NULL DEFAULT NULL AFTER page_title');
            $pmCol($pdo, 'page_meta', 'meta_keywords', 'VARCHAR(512) NULL DEFAULT NULL AFTER meta_description');
            $pmCol($pdo, 'footer_images', 'footer_display_mode', "VARCHAR(16) NOT NULL DEFAULT 'image' AFTER image_path");
            $pmCol($pdo, 'product_variation_assignments', 'display_order', 'INT NOT NULL DEFAULT 0 AFTER is_required');
            $pdo->exec('INSERT IGNORE INTO footer_images (id, footer_display_mode) VALUES (9, \'image\')');
        } catch (Throwable $eSeo) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('page_meta/footer seo columns: ' . $eSeo->getMessage());
            }
        }

        try {
            $pages = [
                'index.php', 'order.php', 'thankyou.php', 'sorgula.php', 'destek_talebi.php', 'error.php',
                'hakkimizda.php', 'bayilik.php', 'kargo_sureci.php', 'iletisim.php',
                'sss.php', 'kvkk.php', 'mesafeli_satis.php', 'iade_degisim.php',
            ];
            $ins = $pdo->prepare('INSERT IGNORE INTO page_meta (page_name, page_title) VALUES (?, ?)');
            require_once __DIR__ . '/page_seo.php';
            $defaults = page_seo_default_titles();
            foreach ($pages as $pg) {
                $ins->execute([$pg, $defaults[$pg] ?? 'Online Alışveriş']);
            }
        } catch (Throwable $ePmSeed) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('page_meta seed: ' . $ePmSeed->getMessage());
            }
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_install (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
                is_complete TINYINT(1) NOT NULL DEFAULT 0,
                site_base_title VARCHAR(255) NULL DEFAULT NULL,
                completed_at DATETIME NULL DEFAULT NULL,
                installer_version INT NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec('INSERT IGNORE INTO site_install (id, is_complete) VALUES (1, 0)');
        } catch (Throwable $eInst) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('site_install: ' . $eInst->getMessage());
            }
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS social_button_clicks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                channel ENUM('whatsapp','instagram') NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                page_name VARCHAR(128) NULL DEFAULT NULL,
                clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_social_channel_ip (channel, ip_address),
                KEY idx_social_channel_date (channel, clicked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $eSoc) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('social_button_clicks: ' . $eSoc->getMessage());
            }
        }

        // Performans index'leri: dashboard analitiği ve sipariş listeleme hızlansın.
        try {
            $ensureIndex = static function (PDO $pdo, string $table, string $index, string $cols): void {
                $exists = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
                if (! ($exists instanceof PDOStatement) || $exists->rowCount() === 0) {
                    return;
                }
                $chk = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = " . $pdo->quote($index));
                if ($chk instanceof PDOStatement && $chk->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE `$table` ADD INDEX `$index` ($cols)");
                }
            };
            $ensureIndex($pdo, 'page_views', 'idx_pv_page_time', '`page_name`, `visit_time`');
            $ensureIndex($pdo, 'page_views', 'idx_pv_time', '`visit_time`');
            $ensureIndex($pdo, 'page_views', 'idx_pv_ip', '`ip_address`');
            $ensureIndex($pdo, 'orders', 'idx_orders_date', '`order_date`');
        } catch (Throwable $eIdx) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('perf index: ' . $eIdx->getMessage());
            }
        }

        // Landing page (açılış sayfası) sistemi — blok tabanlı vitrin sayfaları
        $pdo->exec("CREATE TABLE IF NOT EXISTS landing_pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(160) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(16) NOT NULL DEFAULT 'draft',
            blocks_json MEDIUMTEXT NULL,
            theme VARCHAR(24) NOT NULL DEFAULT 'aurora',
            meta_title VARCHAR(255) NULL DEFAULT NULL,
            meta_description VARCHAR(512) NULL DEFAULT NULL,
            og_image VARCHAR(512) NULL DEFAULT NULL,
            head_extra MEDIUMTEXT NULL,
            show_pixels TINYINT(1) NOT NULL DEFAULT 1,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lp_slug (slug),
            KEY idx_lp_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ================= Çok dillilik (i18n) + Para birimi =================
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_languages (
            code VARCHAR(5) NOT NULL,
            name VARCHAR(64) NOT NULL DEFAULT '',
            native_name VARCHAR(64) NOT NULL DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_rtl TINYINT(1) NOT NULL DEFAULT 0,
            flag VARCHAR(8) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (code),
            KEY idx_lang_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $langSeed = [
            ['tr', 'Turkish', 'Türkçe', 1, 1, 0, '🇹🇷', 1],
            ['en', 'English', 'English', 1, 0, 0, '🇬🇧', 2],
            ['ar', 'Arabic', 'العربية', 1, 0, 1, '🇸🇦', 3],
        ];
        $langIns = $pdo->prepare('INSERT IGNORE INTO site_languages (code, name, native_name, is_active, is_default, is_rtl, flag, sort_order) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($langSeed as $l) {
            $langIns->execute($l);
        }

        if (is_file(__DIR__ . '/location_service.php')) {
            require_once __DIR__ . '/location_service.php';
            location_ensure_schema($pdo);
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_currencies (
            code VARCHAR(3) NOT NULL,
            symbol VARCHAR(8) NOT NULL DEFAULT '',
            name VARCHAR(64) NOT NULL DEFAULT '',
            rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            decimals TINYINT UNSIGNED NOT NULL DEFAULT 2,
            symbol_position VARCHAR(6) NOT NULL DEFAULT 'after',
            sort_order INT NOT NULL DEFAULT 0,
            rate_updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (code),
            KEY idx_cur_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // rate = 1 TRY karşılığı bu para biriminden kaç birim (baz = TRY, rate 1)
        $curSeed = [
            ['TRY', 'TL', 'Türk Lirası', 1.0, 1, 1, 2, 'after', 1],
            ['USD', '$', 'ABD Doları', 0.031, 1, 0, 2, 'before', 2],
            ['EUR', '€', 'Euro', 0.029, 1, 0, 2, 'before', 3],
            ['GBP', '£', 'İngiliz Sterlini', 0.024, 1, 0, 2, 'before', 4],
            ['IQD', 'ع.د', 'Irak Dinarı', 40.5, 1, 0, 0, 'after', 5],
            ['AED', 'د.إ', 'BAE Dirhemi', 0.114, 1, 0, 2, 'after', 6],
            ['SAR', 'ر.س', 'Suudi Riyali', 0.116, 1, 0, 2, 'after', 7],
            ['QAR', 'ر.ق', 'Katar Riyali', 0.113, 1, 0, 2, 'after', 8],
            ['KWD', 'د.ك', 'Kuveyt Dinarı', 0.0095, 1, 0, 3, 'after', 9],
            ['AUD', '$', 'Avustralya Doları', 0.046, 1, 0, 2, 'before', 10],
        ];
        $curIns = $pdo->prepare('INSERT IGNORE INTO site_currencies (code, symbol, name, rate, is_active, is_default, decimals, symbol_position, sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($curSeed as $c) {
            $curIns->execute($c);
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_translations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lang_code VARCHAR(5) NOT NULL,
            t_key VARCHAR(191) NOT NULL,
            t_value TEXT NULL,
            t_group VARCHAR(64) NOT NULL DEFAULT 'general',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tr_lang_key (lang_code, t_key),
            KEY idx_tr_group (t_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS content_translations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(32) NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            field VARCHAR(64) NOT NULL,
            lang_code VARCHAR(5) NOT NULL,
            value MEDIUMTEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ct (entity_type, entity_id, field, lang_code),
            KEY idx_ct_lookup (entity_type, entity_id, lang_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS i18n_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            lang_switcher_enabled TINYINT(1) NOT NULL DEFAULT 1,
            currency_switcher_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('INSERT IGNORE INTO i18n_settings (id) VALUES (1)');

        // Temel arayüz çevirileri (en/ar) — tr için değer boşsa anahtar/orijinal kullanılır
        if (is_file(__DIR__ . '/i18n_seed.php')) {
            require_once __DIR__ . '/i18n_seed.php';
            if (function_exists('i18n_seed_default_translations')) {
                i18n_seed_default_translations($pdo);
            }
        }

        // Tüm migrasyonlar bitti — sürümü işaretle ki sonraki isteklerde ağır kontroller atlansın.
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_meta (
                meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
                meta_value VARCHAR(64) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $verStmt = $pdo->prepare("INSERT INTO schema_meta (meta_key, meta_value) VALUES ('feature_version', ?)
                ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
            $verStmt->execute([$schemaVersion]);
        } catch (Throwable $eVerW) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('schema_meta write: ' . $eVerW->getMessage());
            }
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('ensure_feature_schema: ' . $e->getMessage());
        }
        // İlk yüklemede tablo yoksa vb. kullanıcıya ham hata sızdırma
    }
}
