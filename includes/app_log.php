<?php
declare(strict_types=1);

/**
 * Birleşik uygulama logu — kök dizinde hata_loglari.log
 *
 * Kullanım: app_log('order', 'created', ['order_id' => 123]);
 * Eski tek argüman: app_log('mesaj') → kanal app
 */
function app_log(string $channel, string $message = '', array $context = []): void
{
    if ($message === '' && $context === []) {
        $message = $channel;
        $channel = 'app';
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [' . $channel . '] ' . $message;
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $line .= ' ' . $json;
        }
    }
    $line .= "\n";

    $path = dirname(__DIR__) . '/hata_loglari.log';
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    error_log(rtrim($line));
}
