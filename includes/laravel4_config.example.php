<?php

/**
 * Vitrin → Ortak Panel sipariş senkronu.
 * Kurulum: bu dosyayı kopyalayın → laravel4_config.php
 */
return [
    // Ortak Panel kök URL (sonunda / yok)
    // Yerel: http://localhost/ortak%20panel/public_html%20(19)/public_html
    // Canlı: https://panel.kosarvantilator.com
    'panel_base_url' => 'https://panel.kosarvantilator.com',

    // Ortak Panel → ayar.ayar_common_panel_key
    'order_api_key' => 'CHANGE_ME_panel_api_key',

    // Panelde görünecek site kaynağı (receive.php site_origin)
    'site_origin' => 'kosarvantilator.com',

    // Eski alanlar — form senkronu kullanılmıyorsa boş bırakılabilir
    'order_source_key' => 'nova_web',
    'form_source_key' => 'nova_form',
    'webhook_secret' => '',
];
