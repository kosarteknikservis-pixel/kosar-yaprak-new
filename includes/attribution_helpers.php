<?php

declare(strict_types=1);

/**
 * Sipariş satırına yazılacak UTM / tıklama kimliği değerleri (checkout_module_settings.utm_capture_enabled açıksa).
 *
 * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string}
 *         utm_source, utm_medium, utm_campaign, utm_content, utm_term, attribution_click_json, attribution_landing_url
 */
function attribution_order_values_for_db(bool $captureEnabled): array
{
    if (! $captureEnabled) {
        return [null, null, null, null, null, null, null];
    }

    $trim = static function (?string $v, int $max): ?string {
        if ($v === null || $v === '') {
            return null;
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $max);
    };

    $utm_source = $trim($_SESSION['utm_source'] ?? null, 255);
    $utm_medium = $trim($_SESSION['utm_medium'] ?? null, 255);
    $utm_campaign = $trim($_SESSION['utm_campaign'] ?? null, 255);
    $utm_content = $trim($_SESSION['utm_content'] ?? null, 255);
    $utm_term = $trim($_SESSION['utm_term'] ?? null, 255);

    $click = [];
    foreach (['gclid', 'fbclid', 'ttclid', 'wbraid', 'gbraid'] as $k) {
        $sk = 'attr_'.$k;
        if (! empty($_SESSION[$sk]) && is_scalar($_SESSION[$sk])) {
            $click[$k] = mb_substr((string) $_SESSION[$sk], 0, 512);
        }
    }
    $clickJson = $click === [] ? null : json_encode($click, JSON_UNESCAPED_UNICODE);

    $landing = $trim($_SESSION['attr_landing_url'] ?? null, 1024);

    return [$utm_source, $utm_medium, $utm_campaign, $utm_content, $utm_term, $clickJson, $landing];
}

/**
 * orders satırından UTM alanları (PayTR/Iyzico callback gibi oturumsuz senaryolar).
 *
 * @param  array<string, mixed>  $orderRow
 * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string}
 */
function attribution_order_values_from_row(array $orderRow): array
{
    $trim = static function (mixed $v, int $max): ?string {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, $max);
    };

    return [
        $trim($orderRow['utm_source'] ?? null, 255),
        $trim($orderRow['utm_medium'] ?? null, 255),
        $trim($orderRow['utm_campaign'] ?? null, 255),
        $trim($orderRow['utm_content'] ?? null, 255),
        $trim($orderRow['utm_term'] ?? null, 255),
        $trim($orderRow['attribution_click_json'] ?? null, 65535),
        $trim($orderRow['attribution_landing_url'] ?? null, 1024),
    ];
}

/**
 * Laravel API gövdesi için (assoc). Oturum boşsa isteğe bağlı orders satırından okur.
 *
 * @param  array<string, mixed>|null  $orderRow
 * @return array<string, mixed>
 */
function attribution_api_payload_slice(bool $captureEnabled, ?array $orderRow = null): array
{
    if (! $captureEnabled) {
        return [
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_content' => null,
            'utm_term' => null,
            'attribution_click_ids' => null,
            'attribution_landing_url' => null,
        ];
    }

    [$a, $b, $c, $d, $e, $json, $land] = attribution_order_values_for_db(true);

    if ($orderRow !== null) {
        [$ra, $rb, $rc, $rd, $re, $rjson, $rland] = attribution_order_values_from_row($orderRow);
        $a = $a ?: $ra;
        $b = $b ?: $rb;
        $c = $c ?: $rc;
        $d = $d ?: $rd;
        $e = $e ?: $re;
        $json = $json ?: $rjson;
        $land = $land ?: $rland;
    }

    $clickIds = null;
    if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, true);
        $clickIds = is_array($decoded) ? $decoded : null;
    }

    return [
        'utm_source' => $a,
        'utm_medium' => $b,
        'utm_campaign' => $c,
        'utm_content' => $d,
        'utm_term' => $e,
        'attribution_click_ids' => $clickIds,
        'attribution_landing_url' => $land,
    ];
}

/**
 * Terk edilen sepet için minimal attribution (kampanya raporu için yeterli).
 *
 * @return array{
 *     ad_source: ?string,
 *     utm_source: ?string,
 *     utm_medium: ?string,
 *     utm_campaign: ?string,
 *     utm_content: ?string,
 *     utm_term: ?string
 * }
 */
function attribution_abandoned_slice(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $trim = static function (?string $v, int $max): ?string {
        if ($v === null || $v === '') {
            return null;
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $max);
    };

    $reklam = $trim($_SESSION['reklam'] ?? null, 64);
    if ($reklam === 'Reklam Olmayabilir') {
        $reklam = null;
    }

    return [
        'ad_source' => $reklam,
        'utm_source' => $trim($_SESSION['utm_source'] ?? null, 255),
        'utm_medium' => $trim($_SESSION['utm_medium'] ?? null, 255),
        'utm_campaign' => $trim($_SESSION['utm_campaign'] ?? null, 255),
        'utm_content' => $trim($_SESSION['utm_content'] ?? null, 255),
        'utm_term' => $trim($_SESSION['utm_term'] ?? null, 255),
    ];
}

/**
 * Kayıtlı UTM şablonundan tam takip URL’si üretir.
 *
 * @param  array<string, mixed>  $link
 */
function attribution_build_tracking_url(string $siteBaseUrl, array $link): string
{
    $path = trim((string) ($link['landing_path'] ?? '/'));
    if ($path === '') {
        $path = '/';
    }
    if (! str_starts_with($path, '/')) {
        $path = '/'.$path;
    }

    $base = rtrim($siteBaseUrl, '/');
    $params = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
        $val = trim((string) ($link[$key] ?? ''));
        if ($val !== '') {
            $params[$key] = $val;
        }
    }

    $url = $base.$path;

    return $params === [] ? $url : $url.'?'.http_build_query($params);
}
