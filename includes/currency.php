<?php
declare(strict_types=1);

/**
 * Para birimi çekirdeği.
 * - Fiyatlar veritabanında TRY (baz) olarak tutulur.
 * - Aktif para birimi: ?cur= > çerez(site_cur) > varsayılan.
 * - money($amountTRY): dönüştür + biçimlendir (sembol, ondalık, konum).
 */

function &currency_state(): array
{
    static $state = [
        'booted' => false,
        'code' => 'TRY',
        'list' => [],
        'current' => null,
    ];
    return $state;
}

function currency_pdo(?PDO $pdo = null): ?PDO
{
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    return (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) ? $GLOBALS['pdo'] : null;
}

/** @return array<int,array<string,mixed>> */
function currency_active_list(?PDO $pdo = null): array
{
    $st = &currency_state();
    if ($st['list']) {
        return $st['list'];
    }
    $pdo = currency_pdo($pdo);
    if (!$pdo) {
        return [];
    }
    try {
        $rows = $pdo->query('SELECT code, symbol, name, rate, is_default, decimals, symbol_position FROM site_currencies WHERE is_active = 1 ORDER BY sort_order ASC, code ASC')->fetchAll(PDO::FETCH_ASSOC);
        $st['list'] = $rows ?: [];
    } catch (Throwable $e) {
        $st['list'] = [];
    }
    return $st['list'];
}

function currency_default_code(?PDO $pdo = null): string
{
    foreach (currency_active_list($pdo) as $c) {
        if ((int) ($c['is_default'] ?? 0) === 1) {
            return (string) $c['code'];
        }
    }
    $list = currency_active_list($pdo);
    return $list ? (string) $list[0]['code'] : 'TRY';
}

function currency_boot(?PDO $pdo = null): string
{
    $st = &currency_state();
    if ($st['booted']) {
        return $st['code'];
    }
    $st['booted'] = true;

    $pdo = currency_pdo($pdo);
    if ($pdo instanceof PDO) {
        try {
            $pdo->exec("INSERT IGNORE INTO site_currencies (code, symbol, name, rate, is_active, is_default, decimals, symbol_position, sort_order) VALUES ('AUD','$','Avustralya Doları',0.046,1,0,2,'before',10)");
        } catch (Throwable $e) {
            /* tablo yoksa sessiz */
        }
    }

    $list = currency_active_list($pdo);
    $codes = array_map(static fn ($c) => (string) $c['code'], $list);
    $default = currency_default_code($pdo);

    $chosen = null;
    $req = isset($_GET['cur']) ? strtoupper(preg_replace('/[^A-Z]/i', '', (string) $_GET['cur'])) : '';
    if ($req !== '' && in_array($req, $codes, true)) {
        $chosen = $req;
        if (!headers_sent()) {
            setcookie('site_cur', $chosen, time() + 31536000, '/');
        }
        $_COOKIE['site_cur'] = $chosen;
    } elseif (isset($_COOKIE['site_cur'])) {
        $c = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $_COOKIE['site_cur']));
        if (in_array($c, $codes, true)) {
            $chosen = $c;
        }
    }
    if ($chosen === null) {
        $chosen = $default;
    }
    if (function_exists('current_lang') && current_lang($pdo) === 'en'
        && in_array('AUD', $codes, true)
        && $req === ''
        && !isset($_COOKIE['site_cur'])) {
        $chosen = 'AUD';
    }

    $st['code'] = $chosen;
    $st['current'] = null;
    foreach ($list as $c) {
        if ((string) $c['code'] === $chosen) {
            $st['current'] = $c;
            break;
        }
    }
    return $chosen;
}

function current_currency_code(?PDO $pdo = null): string
{
    $st = &currency_state();
    if (!$st['booted']) {
        currency_boot($pdo);
    }
    return $st['code'];
}

/** @return array<string,mixed>|null */
function current_currency(?PDO $pdo = null): ?array
{
    $st = &currency_state();
    if (!$st['booted']) {
        currency_boot($pdo);
    }
    return $st['current'];
}

/**
 * TRY bazlı tutarı aktif para biriminde biçimlendir.
 * $withSymbol=false ise yalnız sayı döner.
 */
function money(float $amountTry, bool $withSymbol = true, ?PDO $pdo = null): string
{
    $cur = current_currency($pdo);
    if (!$cur) {
        // güvenli varsayılan: TRY gibi davran
        $num = number_format($amountTry, 2, ',', '.');
        return $withSymbol ? $num . ' TL' : $num;
    }
    $rate = (float) ($cur['rate'] ?? 1.0);
    if ($rate <= 0) {
        $rate = 1.0;
    }
    $decimals = (int) ($cur['decimals'] ?? 2);
    $value = $amountTry * $rate;

    $code = (string) $cur['code'];
    if ($code === 'TRY') {
        $num = number_format($value, $decimals, ',', '.');
    } else {
        $num = number_format($value, $decimals, '.', ',');
    }
    if (!$withSymbol) {
        return $num;
    }
    $symbol = (string) ($cur['symbol'] ?? $code);
    $pos = (string) ($cur['symbol_position'] ?? 'after');
    $formatted = $pos === 'before' ? $symbol . $num : $num . ' ' . $symbol;
    $lang = function_exists('current_lang') ? current_lang($pdo) : 'tr';
    $suffixCodes = ['AUD', 'NZD', 'CAD'];
    if ($code !== 'TRY' && ($lang !== 'tr' || in_array($code, $suffixCodes, true))) {
        if (!str_ends_with($formatted, ' ' . $code)) {
            $formatted .= ' ' . $code;
        }
    }

    return $formatted;
}

/** Tasarruf tutarı: $60 (ISO kodu yok). */
function money_save(float $savedTry, ?PDO $pdo = null): string
{
    $cur = current_currency($pdo);
    $rate = $cur ? (float) ($cur['rate'] ?? 1.0) : 1.0;
    if ($rate <= 0) {
        $rate = 1.0;
    }
    $value = $savedTry * $rate;
    $decimals = (abs($value - round($value)) < 0.05) ? 0 : 2;
    $code = $cur ? (string) ($cur['code'] ?? 'TRY') : 'TRY';
    if ($code === 'TRY') {
        $num = number_format($value, $decimals, ',', '.');
        $symbol = $cur ? (string) ($cur['symbol'] ?? 'TL') : 'TL';

        return $num . ' ' . $symbol;
    }
    $symbol = $cur ? (string) ($cur['symbol'] ?? '$') : '$';
    $num = number_format($value, $decimals, '.', ',');
    $pos = $cur ? (string) ($cur['symbol_position'] ?? 'before') : 'before';

    return $pos === 'before' ? $symbol . $num : $num . ' ' . $symbol;
}

/** ?cur= parametreli URL üret (mevcut sorgu korunur). */
function currency_switch_url(string $code): string
{
    $parts = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'));
    $path = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $q);
    $q['cur'] = $code;
    return $path . '?' . http_build_query($q);
}
