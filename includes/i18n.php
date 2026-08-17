<?php
declare(strict_types=1);

/**
 * Çok dillilik (i18n) çekirdeği.
 * - Dil tespiti: ?lang= > çerez(site_lang) > varsayılan dil
 * - t($key, $fallback): arayüz metni çevirisi (site_translations)
 * - content_t(): dinamik içerik çevirisi (content_translations) + orijinale fallback
 *
 * Kaynak dil "tr"dir; çeviri yoksa Türkçe (fallback) gösterilir; asla boş kalmaz.
 */

/** @return array{lang:string,rtl:bool,langs:array<int,array<string,mixed>>,strings:array<string,string>,pdo:?PDO}|null */
function &i18n_state(): array
{
    static $state = [
        'booted' => false,
        'lang' => 'tr',
        'rtl' => false,
        'langs' => [],
        'strings' => [],
        'loaded_lang' => null,
        'pdo' => null,
    ];
    return $state;
}

function i18n_pdo(?PDO $pdo = null): ?PDO
{
    $st = &i18n_state();
    if ($pdo instanceof PDO) {
        $st['pdo'] = $pdo;
    }
    if (!($st['pdo'] instanceof PDO) && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $st['pdo'] = $GLOBALS['pdo'];
    }
    return $st['pdo'];
}

/** @return array<int,array<string,mixed>> */
function i18n_active_languages(?PDO $pdo = null): array
{
    $st = &i18n_state();
    if ($st['langs']) {
        return $st['langs'];
    }
    $pdo = i18n_pdo($pdo);
    if (!$pdo) {
        return [];
    }
    try {
        $rows = $pdo->query('SELECT code, name, native_name, is_default, is_rtl, flag FROM site_languages WHERE is_active = 1 ORDER BY sort_order ASC, code ASC')->fetchAll(PDO::FETCH_ASSOC);
        $st['langs'] = $rows ?: [];
    } catch (Throwable $e) {
        $st['langs'] = [];
    }
    return $st['langs'];
}

function i18n_default_lang(?PDO $pdo = null): string
{
    foreach (i18n_active_languages($pdo) as $l) {
        if ((int) ($l['is_default'] ?? 0) === 1) {
            return (string) $l['code'];
        }
    }
    $langs = i18n_active_languages($pdo);
    return $langs ? (string) $langs[0]['code'] : 'tr';
}

/** Paneldeki kaynak dil (ürün adı, başlık vb. TR olarak saklanır). */
function i18n_source_lang(): string
{
    return 'tr';
}

/** Dil çözümle ve durum kur. Çıktıdan önce çağrılmalı (çerez yazabilir). */
function i18n_boot(?PDO $pdo = null): string
{
    $st = &i18n_state();
    $pdo = i18n_pdo($pdo);
    if ($st['booted']) {
        return $st['lang'];
    }
    $st['booted'] = true;

    if (is_file(__DIR__ . '/i18n_seed.php')) {
        require_once __DIR__ . '/i18n_seed.php';
        if (function_exists('i18n_ensure_catalog')) {
            i18n_ensure_catalog($pdo);
        }
    }
    if (is_file(__DIR__ . '/currency.php')) {
        require_once __DIR__ . '/currency.php';
        if (function_exists('currency_boot')) {
            currency_boot($pdo);
        }
    }
    if (is_file(__DIR__ . '/shop_storefront.php')) {
        require_once __DIR__ . '/shop_storefront.php';
    }

    $langs = i18n_active_languages($pdo);
    $default = i18n_default_lang($pdo);
    $chosen = $default;

    $st['lang'] = $chosen;
    $st['rtl'] = false;
    foreach ($langs as $l) {
        if ((string) $l['code'] === $chosen) {
            $st['rtl'] = (int) ($l['is_rtl'] ?? 0) === 1;
            break;
        }
    }
    return $chosen;
}

function current_lang(?PDO $pdo = null): string
{
    $st = &i18n_state();
    if (!$st['booted']) {
        i18n_boot($pdo);
    }
    return $st['lang'];
}

function is_rtl(?PDO $pdo = null): bool
{
    $st = &i18n_state();
    if (!$st['booted']) {
        i18n_boot($pdo);
    }
    return (bool) $st['rtl'];
}

function i18n_dir(?PDO $pdo = null): string
{
    return is_rtl($pdo) ? 'rtl' : 'ltr';
}

/** Vitrin dili Türkçe değilse (EN / AR ve diğerleri). */
function i18n_is_foreign(?PDO $pdo = null): bool
{
    return current_lang($pdo) !== i18n_source_lang();
}

/** <html> için lang + dir. */
function i18n_html_attrs(?PDO $pdo = null): string
{
    return 'lang="' . htmlspecialchars(current_lang($pdo), ENT_QUOTES, 'UTF-8')
        . '" dir="' . htmlspecialchars(i18n_dir($pdo), ENT_QUOTES, 'UTF-8') . '"';
}

/** Aktif dilin tüm arayüz metinlerini yükle (istek başına 1 sorgu, statik önbellek). */
function i18n_load_strings(?PDO $pdo = null): void
{
    $st = &i18n_state();
    $lang = current_lang($pdo);
    if ($st['loaded_lang'] === $lang) {
        return;
    }
    $st['strings'] = [];
    $pdo = i18n_pdo($pdo);
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT t_key, t_value FROM site_translations WHERE lang_code = ?');
            $stmt->execute([$lang]);
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $st['strings'][(string) $k] = (string) $v;
            }
        } catch (Throwable $e) {
            /* tablo yoksa boş kalır, fallback devreye girer */
        }
    }
    $st['loaded_lang'] = $lang;
}

/**
 * Arayüz metni çevirisi. Çeviri yoksa $fallback, o da yoksa anahtar döner.
 * $fallback genelde Türkçe kaynak metindir; böylece hiçbir zaman boş kalmaz.
 */
function t(string $key, string $fallback = ''): string
{
    $st = &i18n_state();
    i18n_load_strings();
    if (isset($st['strings'][$key]) && $st['strings'][$key] !== '') {
        return $st['strings'][$key];
    }
    return $fallback !== '' ? $fallback : $key;
}

/** HTML-escape edilmiş çeviri. */
function te(string $key, string $fallback = ''): string
{
    return htmlspecialchars(t($key, $fallback), ENT_QUOTES, 'UTF-8');
}

/**
 * Dinamik içerik çevirisi (ürün adı, açıklama, CMS, landing…).
 * Aktif dil kaynak dille (varsayılan) aynıysa doğrudan orijinali döndürür.
 * Çeviri yoksa orijinale (fallback) düşer.
 */
function content_t(string $entityType, int $entityId, string $field, string $original, ?PDO $pdo = null): string
{
    $lang = current_lang($pdo);
    if ($lang === i18n_source_lang()) {
        return $original;
    }
    $pdo = i18n_pdo($pdo);
    if (!$pdo) {
        return $original;
    }
    static $cache = [];
    $ck = $entityType . ':' . $entityId . ':' . $lang;
    if (!isset($cache[$ck])) {
        $cache[$ck] = [];
        try {
            $stmt = $pdo->prepare('SELECT field, value FROM content_translations WHERE entity_type = ? AND entity_id = ? AND lang_code = ?');
            $stmt->execute([$entityType, $entityId, $lang]);
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $f => $v) {
                $cache[$ck][(string) $f] = (string) $v;
            }
        } catch (Throwable $e) {
            $cache[$ck] = [];
        }
    }
    $val = $cache[$ck][$field] ?? '';
    return $val !== '' ? $val : $original;
}

/**
 * Dinamik içerik çevirisini kaydet (ürün adı, ödeme yöntemi…).
 */
function content_t_save(PDO $pdo, string $entityType, int $entityId, string $field, string $lang, string $value): void
{
    $lang = strtolower(preg_replace('/[^a-z]/i', '', $lang));
    $value = trim($value);
    if ($lang === '' || $entityId <= 0 || $field === '') {
        return;
    }
    if ($value === '') {
        $pdo->prepare('DELETE FROM content_translations WHERE entity_type = ? AND entity_id = ? AND field = ? AND lang_code = ?')
            ->execute([$entityType, $entityId, $field, $lang]);

        return;
    }
    $st = $pdo->prepare(
        'INSERT INTO content_translations (entity_type, entity_id, field, lang_code, value)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $st->execute([$entityType, $entityId, $field, $lang, $value]);
}

/**
 * @return array<string, string> field => value
 */
function content_t_load_lang(PDO $pdo, string $entityType, int $entityId, string $lang): array
{
    try {
        $st = $pdo->prepare('SELECT field, value FROM content_translations WHERE entity_type = ? AND entity_id = ? AND lang_code = ?');
        $st->execute([$entityType, $entityId, $lang]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $f => $v) {
            $out[(string) $f] = (string) $v;
        }

        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/** ?lang= parametreli URL üret (mevcut sorgu korunur). */
function i18n_switch_url(string $lang): string
{
    $parts = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'));
    $path = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $q);
    $q['lang'] = $lang;
    return $path . '?' . http_build_query($q);
}
