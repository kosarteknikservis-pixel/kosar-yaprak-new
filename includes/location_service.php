<?php
declare(strict_types=1);

if (function_exists('location_pack_dir')) {
    return;
}

/**
 * Sipariş formu il/ilçe — ülke paketi (TR, AU, SA, AE).
 * Mevcut siparişlerdeki city_id değerleri korunur; import yalnızca eksikleri ekler.
 */

function location_pack_dir(): string
{
    return dirname(__DIR__) . '/data/locations';
}

/** @return list<string> */
function location_pack_codes(): array
{
    return ['TR', 'AU', 'SA', 'AE'];
}

/** @return array<string, string> */
function location_pack_titles(): array
{
    return [
        'TR' => 'Türkiye (il / ilçe)',
        'AU' => 'Avustralya (eyalet / şehir)',
        'SA' => 'Suudi Arabistan (bölge / şehir)',
        'AE' => 'Birleşik Arap Emirlikleri (emirlik / şehir)',
    ];
}

function location_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $has = $pdo->query("SHOW COLUMNS FROM cities LIKE 'country_code'");
        if ($has instanceof PDOStatement && !$has->fetch()) {
            $pdo->exec("ALTER TABLE cities ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT 'TR' AFTER city_name");
        }
        $pdo->exec('UPDATE cities SET country_code = \'TR\' WHERE country_code IS NULL OR country_code = \'\'');
        try {
            $pdo->exec('CREATE INDEX idx_city_country ON cities (country_code, city_name)');
        } catch (Throwable $e) {
            /* indeks zaten var */
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('location_ensure_schema: ' . $e->getMessage());
        }
    }
}

function location_guess_country(?PDO $pdo = null): string
{
    $code = function_exists('current_currency_code') ? strtoupper(current_currency_code($pdo)) : 'TRY';
    return match ($code) {
        'AUD' => 'AU',
        'SAR' => 'SA',
        'AED' => 'AE',
        default => 'TR',
    };
}

function location_checkout_country(?PDO $pdo = null): string
{
    $pdo = $pdo ?? (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : null);
    if ($pdo instanceof PDO) {
        location_ensure_schema($pdo);
        try {
            $v = $pdo->query("SELECT meta_value FROM schema_meta WHERE meta_key = 'checkout_country'")->fetchColumn();
            $v = strtoupper(trim((string) $v));
            if (in_array($v, location_pack_codes(), true)) {
                return $v;
            }
        } catch (Throwable $e) {
            /* yoksa tahmin */
        }
    }

    return location_guess_country($pdo);
}

function location_set_checkout_country(PDO $pdo, string $country): void
{
    location_ensure_schema($pdo);
    $country = strtoupper($country);
    if (!in_array($country, location_pack_codes(), true)) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_meta (
        meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
        meta_value VARCHAR(64) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $st = $pdo->prepare("INSERT INTO schema_meta (meta_key, meta_value) VALUES ('checkout_country', ?)
        ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
    $st->execute([$country]);
}

/** @return array<string, mixed>|null */
function location_pack_load(string $country): ?array
{
    $country = strtoupper($country);
    $path = location_pack_dir() . '/' . strtolower($country) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $raw = json_decode((string) file_get_contents($path), true);

    return is_array($raw) ? $raw : null;
}

/**
 * @return array{cities:int, districts:int, skipped:int}
 */
function location_pack_import(PDO $pdo, string $country): array
{
    location_ensure_schema($pdo);
    $pack = location_pack_load($country);
    if (!$pack || empty($pack['cities']) || !is_array($pack['cities'])) {
        return ['cities' => 0, 'districts' => 0, 'skipped' => 0];
    }
    $country = strtoupper((string) ($pack['country'] ?? $country));
    $cityIns = $pdo->prepare('INSERT INTO cities (city_name, country_code) VALUES (?, ?)');
    $cityFind = $pdo->prepare('SELECT city_id FROM cities WHERE country_code = ? AND city_name = ? LIMIT 1');
    $distFind = $pdo->prepare('SELECT district_id FROM districts WHERE city_id = ? AND district_name = ? LIMIT 1');
    $distIns = $pdo->prepare('INSERT INTO districts (city_id, district_name) VALUES (?, ?)');

    $cAdd = 0;
    $dAdd = 0;
    $skip = 0;
    foreach ($pack['cities'] as $cityName => $districts) {
        $cityName = trim((string) $cityName);
        if ($cityName === '') {
            continue;
        }
        $cityFind->execute([$country, $cityName]);
        $cityId = (int) $cityFind->fetchColumn();
        if ($cityId < 1) {
            $cityIns->execute([$cityName, $country]);
            $cityId = (int) $pdo->lastInsertId();
            $cAdd++;
        }
        if (!is_array($districts)) {
            continue;
        }
        foreach ($districts as $dn) {
            $dn = trim((string) $dn);
            if ($dn === '') {
                continue;
            }
            $distFind->execute([$cityId, $dn]);
            if ((int) $distFind->fetchColumn() > 0) {
                $skip++;
                continue;
            }
            $distIns->execute([$cityId, $dn]);
            $dAdd++;
        }
    }

    return ['cities' => $cAdd, 'districts' => $dAdd, 'skipped' => $skip];
}

/** @return list<array<string, mixed>> */
function location_cities(PDO $pdo, ?string $country = null): array
{
    location_ensure_schema($pdo);
    $country = strtoupper($country ?: location_checkout_country($pdo));
    $st = $pdo->prepare('SELECT city_id, city_name, country_code FROM cities WHERE country_code = ? ORDER BY city_name ASC');
    $st->execute([$country]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === [] && location_pack_load($country)) {
        location_pack_import($pdo, $country);
        $st->execute([$country]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $rows;
}

function location_country_counts(PDO $pdo, string $country): array
{
    location_ensure_schema($pdo);
    $country = strtoupper($country);
    $c = $pdo->prepare('SELECT COUNT(*) FROM cities WHERE country_code = ?');
    $c->execute([$country]);
    $d = $pdo->prepare(
        'SELECT COUNT(*) FROM districts d INNER JOIN cities c ON c.city_id = d.city_id WHERE c.country_code = ?'
    );
    $d->execute([$country]);

    return ['cities' => (int) $c->fetchColumn(), 'districts' => (int) $d->fetchColumn()];
}

/** @return array{city_label:string, district_label:string, city_select:string, district_first:string} */
function location_field_labels(?PDO $pdo = null): array
{
    $cc = location_checkout_country($pdo);
    $t = static function (string $key, string $fb): string {
        return function_exists('t') ? t($key, $fb) : $fb;
    };
    if ($cc === 'AU') {
        return [
            'city_label' => $t('order.state_label', 'State:'),
            'district_label' => $t('order.suburb_label', 'Suburb / City:'),
            'city_select' => $t('order.state_select', 'Select state'),
            'district_first' => $t('order.suburb_first', 'Select state first'),
        ];
    }
    if ($cc === 'AE') {
        return [
            'city_label' => $t('order.emirate_label', 'Emirate:'),
            'district_label' => $t('order.area_label', 'City:'),
            'city_select' => $t('order.emirate_select', 'Select emirate'),
            'district_first' => $t('order.emirate_first', 'Select emirate first'),
        ];
    }
    if ($cc === 'SA') {
        return [
            'city_label' => $t('order.region_label', 'Region:'),
            'district_label' => $t('order.area_label', 'City:'),
            'city_select' => $t('order.region_select', 'Select region'),
            'district_first' => $t('order.area_first', 'Select region first'),
        ];
    }

    return [
        'city_label' => $t('order.city_label', 'İl:'),
        'district_label' => $t('order.district_label', 'İlçe:'),
        'city_select' => $t('order.city_select', 'İl Seçiniz'),
        'district_first' => $t('order.district_first', 'Önce İl Seçiniz'),
    ];
}
