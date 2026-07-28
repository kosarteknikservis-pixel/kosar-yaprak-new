<?php
/**
 * Reklam kanalı (ilk düzgün yüklemede oturumluk) ve tıklama kimlikleri (yenilenebilir).
 * fbclid / ttclid / gclid (+ wbraid/gbraid): oturuma yazılır; sunucu CAPI / analiz için kullanılır.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Referans linki: ?ref=veli veya ?ref=KAMPANYA123
 * Oturum + çerez (aynı istekte sipariş tamamlanabilsin diye session zorunlu).
 * Örnek: https://site.com/index.php?ref=veli  veya  order.php?product_id=1&ref=veli
 */
if (isset($_GET['ref']) && is_scalar($_GET['ref'])) {
    $refRaw = trim((string) $_GET['ref']);
    $refRaw = mb_substr($refRaw, 0, 128);
    if ($refRaw !== '') {
        $_SESSION['referral_code'] = $refRaw;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('referrer', $refRaw, time() + (86400 * 90), '/', '', $secure, true);
    }
} elseif (empty($_SESSION['referral_code']) && !empty($_COOKIE['referrer'])) {
    $rc = trim((string) $_COOKIE['referrer']);
    if ($rc !== '') {
        $_SESSION['referral_code'] = mb_substr($rc, 0, 128);
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https'
    : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$currentUrl = $scheme . '://' . $host . $uri;

$query = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
parse_str($query, $q);

$capture = static function (string $key, int $maxLen) use (&$q): void {
    if (!isset($q[$key]) || !is_scalar($q[$key])) {
        return;
    }
    $v = substr(trim((string)$q[$key]), 0, $maxLen);
    if ($v === '') {
        return;
    }
    $_SESSION['attr_' . $key] = $v;
};
$capture('fbclid', 512);
$capture('ttclid', 512);
$capture('gclid', 512);
$capture('wbraid', 256);
$capture('gbraid', 256);

// UTM (last-touch): yeni reklam linki tıklandığında oturum güncellenir — kreatif bazlı takip için.
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utmKey) {
    if (! isset($q[$utmKey]) || ! is_scalar($q[$utmKey])) {
        continue;
    }
    $uv = mb_substr(trim((string) $q[$utmKey]), 0, 512);
    if ($uv === '') {
        continue;
    }
    $_SESSION[$utmKey] = $uv;
}

$hasAttrInThisRequest = false;
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
    if (isset($q[$k]) && is_scalar($q[$k]) && trim((string) $q[$k]) !== '') {
        $hasAttrInThisRequest = true;
        break;
    }
}
if (! $hasAttrInThisRequest) {
    foreach (['gclid', 'fbclid', 'ttclid', 'wbraid', 'gbraid'] as $k) {
        if (isset($q[$k]) && is_scalar($q[$k]) && trim((string) $q[$k]) !== '') {
            $hasAttrInThisRequest = true;
            break;
        }
    }
}
if ($hasAttrInThisRequest) {
    $_SESSION['attr_landing_url'] = mb_substr($currentUrl, 0, 1024);
}

$hasFbclid = str_contains($currentUrl, 'fbclid=') || (($q['fbclid'] ?? '') !== '');
$hasGclid = str_contains($currentUrl, 'gclid=') || (($q['gclid'] ?? '') !== '')
    || (($q['wbraid'] ?? '') !== '') || (($q['gbraid'] ?? '') !== '');
$hasTtclid = str_contains($currentUrl, 'ttclid=') || (($q['ttclid'] ?? '') !== '');

$key = '__attr_channel_set';
/** @disregard orders.reklam için oturum ilk atama */
if (!isset($_SESSION[$key])) {
    if ($hasFbclid) {
        $_SESSION['reklam'] = 'Meta';
    } elseif ($hasGclid) {
        $_SESSION['reklam'] = 'Google';
    } elseif ($hasTtclid) {
        $_SESSION['reklam'] = 'TikTok';
    } elseif (!isset($_SESSION['reklam']) || $_SESSION['reklam'] === '') {
        $_SESSION['reklam'] = 'Direkt';
    }
    $_SESSION[$key] = 1;
} elseif (($_SESSION['reklam'] ?? '') === '' || $_SESSION['reklam'] === 'Direkt') {
    if ($hasFbclid) {
        $_SESSION['reklam'] = 'Meta';
    } elseif ($hasGclid) {
        $_SESSION['reklam'] = 'Google';
    } elseif ($hasTtclid) {
        $_SESSION['reklam'] = 'TikTok';
    }
}
