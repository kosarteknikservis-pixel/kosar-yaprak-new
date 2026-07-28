<?php

/**
 * 3dhesap → YQ Panel bağlantı ayarları (örnek).
 * Kurulum: bu dosyayı kopyalayın → laravel4_config.php
 * Her site için order_source_key farklı olmalı (quattro_web, zemin_web, …).
 */
return [
    // Canlı: https://qypanel.com
    'panel_base_url' => 'https://qypanel.com',

    // Laravel .env → EXTERNAL_SYNC_API_KEY ile birebir aynı
    'order_api_key' => 'CHANGE_ME_same_as_EXTERNAL_SYNC_API_KEY',

    // Panel integration_sources.key — site başına farklı
    'order_source_key' => 'quattro_web',

    // Form webhook kaynağı (ortak kalabilir)
    'form_source_key' => 'website',

    // Panel entegrasyon kaynağı webhook_secret doluysa buraya da yazın
    'webhook_secret' => '',
];
