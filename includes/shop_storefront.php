<?php
declare(strict_types=1);

/**
 * Vitrin fiyat / kargo / taksit gösterimi — mevcut kart düzenini bozmaz.
 */

function shop_use_save_badge(?PDO $pdo = null): bool
{
    $lang = function_exists('current_lang') ? current_lang($pdo) : 'tr';
    $code = function_exists('current_currency_code') ? current_currency_code($pdo) : 'TRY';

    return $lang !== 'tr' || in_array($code, ['AUD', 'USD', 'GBP', 'NZD', 'CAD'], true);
}

function shop_heading_localized(string $field, string $original, string $fallbackKey, string $fallbackText): string
{
    $original = trim($original);
    $out = function_exists('content_t') ? content_t('homepage_section', 1, $field, $original) : $original;
    $lang = function_exists('current_lang') ? current_lang() : 'tr';
    if ($lang !== 'tr' && ($out === '' || $out === $original)) {
        return function_exists('t') ? t($fallbackKey, $fallbackText) : $fallbackText;
    }

    return $out !== '' ? $out : $fallbackText;
}

function shop_paytr_max_installment(?PDO $pdo = null): int
{
    $pdo = $pdo ?? (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : null);
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $paytr = $pdo->query("SELECT COUNT(*) FROM payment_methods WHERE is_active = 1 AND gateway_code = 'paytr'")->fetchColumn();
        if ((int) $paytr < 1) {
            return 0;
        }
        $cfg = $pdo->query('SELECT no_installment, max_installment FROM paytr_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return 0;
    }
    if (!is_array($cfg) || !empty($cfg['no_installment'])) {
        return 0;
    }
    $max = (int) ($cfg['max_installment'] ?? 0);

    return $max >= 2 ? $max : 0;
}

function shop_interest_free_label(float $saleTry, ?PDO $pdo = null): string
{
    $n = shop_paytr_max_installment($pdo);
    if ($n < 2 || $saleTry <= 0) {
        return '';
    }
    $each = $saleTry / $n;
    $amount = function_exists('money') ? money($each) : number_format($each, 2, ',', '.') . ' TL';
    $tpl = function_exists('t')
        ? t('shop.interest_free', '{n} interest-free payments of {amount}')
        : '{n} interest-free payments of {amount}';

    return str_replace(['{n}', '{amount}'], [(string) $n, $amount], $tpl);
}

function shop_time_label_localized(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || !function_exists('current_lang') || current_lang() === 'tr') {
        return $raw;
    }
    $lower = mb_strtolower($raw, 'UTF-8');
    if (in_array($lower, ['az önce', 'biraz önce', 'az once', 'biraz once'], true)) {
        return function_exists('t') ? t('shop.just_now', 'just now') : 'just now';
    }
    if (preg_match('/^(\d+)\s*dk\s*önce$/u', $lower, $m)) {
        $n = (int) $m[1];
        $tpl = function_exists('t') ? t('shop.mins_ago', '{n} min ago') : '{n} min ago';

        return str_replace('{n}', (string) $n, $tpl);
    }

    return $raw;
}

/**
 * @param list<array{customer_name?:string,city_name?:string,time_label?:string}> $alerts
 * @return list<array{customer_name:string,city_name:string,time_label:string}>
 */
function shop_fake_alerts_localized(array $alerts): array
{
    $out = [];
    foreach ($alerts as $row) {
        $out[] = [
            'customer_name' => (string) ($row['customer_name'] ?? ''),
            'city_name' => (string) ($row['city_name'] ?? ''),
            'time_label' => shop_time_label_localized((string) ($row['time_label'] ?? '')),
        ];
    }

    return $out;
}
