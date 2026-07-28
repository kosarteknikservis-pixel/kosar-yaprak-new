<?php

declare(strict_types=1);

$sitePath = '/home/admin/domains/kosarvantilator.com/public_html';
$panelPath = '/home/admin/domains/panel.kosarvantilator.com/public_html';

require $panelPath . '/db.php';

$key = '123456';
try {
    $stmt = $db->query('SELECT ayar_common_panel_key FROM ayar WHERE ayar_id = 0 LIMIT 1');
    if ($stmt) {
        $fetched = trim((string) $stmt->fetchColumn());
        if ($fetched !== '') {
            $key = $fetched;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'API key DB okunamadi, varsayilan kullaniliyor: ' . $e->getMessage() . PHP_EOL);
}

$cfg = [
    'panel_base_url' => 'https://panel.kosarvantilator.com',
    'order_api_key' => $key,
    'site_origin' => 'kosarvantilator.com',
    'order_source_key' => 'nova_web',
    'form_source_key' => 'nova_form',
    'webhook_secret' => '',
];

$target = $sitePath . '/includes/laravel4_config.php';
$content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Ortak Panel siparis senkronu — panel.kosarvantilator.com\n */\nreturn "
    . var_export($cfg, true)
    . ";\n";

if (file_put_contents($target, $content) === false) {
    fwrite(STDERR, "Yazilamadi: {$target}\n");
    exit(1);
}

echo "OK: {$target}\n";
