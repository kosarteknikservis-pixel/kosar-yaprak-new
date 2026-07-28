<?php
declare(strict_types=1);

/**
 * Çarkıfelek çekirdek mantığı — JSON ve form POST ortak.
 */

const CARKIFELEK_COOLDOWN_SECONDS = 86400;

function carkifelek_client_ip(): string
{
    if (getenv('HTTP_CF_CONNECTING_IP')) {
        return (string) getenv('HTTP_CF_CONNECTING_IP');
    }
    if (getenv('HTTP_CLIENT_IP')) {
        return (string) getenv('HTTP_CLIENT_IP');
    }
    if (getenv('HTTP_X_FORWARDED_FOR')) {
        $ip = (string) getenv('HTTP_X_FORWARDED_FOR');
        if (strpos($ip, ',') !== false) {
            $tmp = explode(',', $ip);

            return trim($tmp[0]);
        }

        return $ip;
    }

    return (string) (getenv('REMOTE_ADDR') ?? '');
}

function carkifelek_ip_blocked(PDO $pdo, string $ip): bool
{
    $since = time() - CARKIFELEK_COOLDOWN_SECONDS;
    $st = $pdo->prepare('SELECT 1 FROM carkifelek_log WHERE ip = ? AND tarih >= ? LIMIT 1');
    $st->execute([$ip, $since]);

    return (bool) $st->fetchColumn();
}

function carkifelek_limit_msg(PDO $pdo): string
{
    try {
        $st = $pdo->query('SELECT limit_message FROM carkifelek_settings WHERE id = 1');
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
        $t = isset($row['limit_message']) ? trim((string) $row['limit_message']) : '';
        if ($t !== '') {
            return $t;
        }
    } catch (Throwable $e) {
        /* noop */
    }

    return 'Günde 1 kez çarkıfelek çevirerek indirim kazanabilirsiniz!';
}

/**
 * @param  list<string>  $oduller
 */
function carkifelek_find_free_shipping_reward(array $oduller): ?string
{
    foreach ($oduller as $label) {
        $raw = trim((string) $label);
        if ($raw === '') {
            continue;
        }
        $l = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
        $ascii = strtr($l, [
            'ü' => 'u', 'ğ' => 'g', 'ı' => 'i', 'ş' => 's', 'ö' => 'o', 'ç' => 'c',
        ]);
        $has_kargo = (strpos($l, 'kargo') !== false) || (strpos($ascii, 'kargo') !== false);
        if (!$has_kargo) {
            continue;
        }
        $ucretsiz = (strpos($l, 'ücretsiz') !== false) || (strpos($ascii, 'ucretsiz') !== false);
        $bedava = strpos($l, 'bedava') !== false;
        $free = strpos($l, 'free') !== false && strpos($l, 'ship') !== false;
        if ($ucretsiz || $bedava || $free) {
            return $raw;
        }
    }

    return null;
}

/** @param list<string> $oduller */
function carkifelek_rotation_degrees(int $segIndex, int $nSegments, int $fullSpins = 6): float
{
    if ($nSegments < 1) {
        return 720.0;
    }

    $arcDeg = 360 / $nSegments;
    $targetAngle = ($segIndex * $arcDeg) + ($arcDeg / 2);

    return (270 - $targetAngle) + ($fullSpins * 360);
}

/**
 * Günlük limit + ödül seçimi — başarılıysa log’a yazar.
 *
 * @return array{status:'blocked', message:string}|array{status:'ok', odul:string, indirim:int, seg_index:int, oduller:list<string>}
 */
function carkifelek_try_spin_and_log(PDO $pdo): array
{
    $limit_msg = carkifelek_limit_msg($pdo);
    $ip = carkifelek_client_ip();

    if (carkifelek_ip_blocked($pdo, $ip)) {
        return ['status' => 'blocked', 'message' => $limit_msg];
    }

    try {
        $ins = $pdo->prepare('INSERT INTO carkifelek_log (ip, tarih) VALUES (?, ?)');
        $ins->execute([$ip, time()]);
    } catch (Throwable $e) {
        return ['status' => 'blocked', 'message' => 'Kayıt hatası'];
    }

    $oduller = ['%10 İndirim', 'Kargo Bedava', '%5 İndirim', 'Sürpriz', '%20 İndirim', 'Pas', '%15 İndirim'];
    $zorla_kargo = 0;

    try {
        $st = $pdo->query('SELECT prizes_json, force_free_shipping FROM carkifelek_settings WHERE id = 1');
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
        if (is_array($row)) {
            $zorla_kargo = isset($row['force_free_shipping']) ? (int) $row['force_free_shipping'] : 0;
            if (!empty($row['prizes_json'])) {
                $dec = json_decode((string) $row['prizes_json'], true);
                if (is_array($dec) && count($dec) >= 4) {
                    $oduller = array_map('strval', array_values($dec));
                }
            }
        }
    } catch (Throwable $e) {
        /* fallback */
    }

    $kargo_odul = carkifelek_find_free_shipping_reward($oduller);

    if ($zorla_kargo === 1 && $kargo_odul !== null) {
        $kazanilan = $kargo_odul;
    } else {
        $kazanilan = $oduller[array_rand($oduller)];
    }

    $seg_index = array_search($kazanilan, $oduller, true);
    if ($seg_index === false) {
        $seg_index = 0;

    }

    $indirim = 0;
    if (preg_match('/(\d+)\s*%/', $kazanilan, $m)) {
        $indirim = (int) $m[1];

    }

    return [
        'status' => 'ok',
        'odul' => $kazanilan,
        'indirim' => $indirim,
        'seg_index' => $seg_index,
        'oduller' => $oduller,
    ];
}

/**
 * Sipariş / vitrin için döndürülür göreli URI (aynı site içinde kalır).
 */
function carkifelek_sanitize_return_path(string $u): string
{
    $u = trim($u);
    if ($u === '' || strlen($u) > 768) {
        return '/';
    }

    if ($u[0] !== '/') {
        return '/';

    }

    if (strncmp($u, '//', 2) === 0) {
        return '/';

    }

    $out = preg_replace('#\s#', '', $u);

    return $out !== '' ? $out : '/';
}

/**
 * HEX → RGB.
 *
 * @return array{r:int,g:int,b:int}|null
 */
function carkifelek_hex_to_rgb_triplet(string $hex): ?array
{
    $hex = strtoupper(trim($hex));
    if (!preg_match('/^#([0-9A-F]{6})$/', $hex, $m)) {
        return null;
    }

    return [
        'r' => (int) hexdec(substr($m[1], 0, 2)),
        'g' => (int) hexdec(substr($m[1], 2, 2)),
        'b' => (int) hexdec(substr($m[1], 4, 2)),
    ];
}

function carkifelek_rgb_to_hex(int $r, int $g, int $b): string
{
    return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

/**
 * Euclidean mesafe üst sınır √195075 ≈ 441.
 */
function carkifelek_rgb_distance_hex(string $a, string $b): float
{
    $xa = carkifelek_hex_to_rgb_triplet($a);
    $xb = carkifelek_hex_to_rgb_triplet($b);
    if ($xa === null || $xb === null) {
        return 450.0;
    }

    $dr = (float) $xa['r'] - (float) $xb['r'];
    $dg = (float) $xa['g'] - (float) $xb['g'];
    $db = (float) $xa['b'] - (float) $xb['b'];

    return sqrt(($dr * $dr) + ($dg * $dg) + ($db * $db));
}

/**
 * Panelden gelen iki renk birbirine çok yakınsa çark iki ton gibi görünür (varsayılan #ff6b6b / #ee5a6f gibi).
 * İkinci dilim rengi, birinciye göre tamlayıcı + vurgu renginden türetilerek doğal kontrast sağlar.
 *
 * @return array{0:string,1:string} Geçerli #RRGGBB
 */
function carkifelek_wheel_visual_pair(string $hex1, string $hex2, string $accent): array
{
    $pattern = '/^#[0-9a-f]{6}$/i';
    $a = preg_match($pattern, trim($hex1)) ? strtoupper($hex1) : '#EF4444';
    $b = preg_match($pattern, trim($hex2)) ? strtoupper($hex2) : '#7C3AED';
    $v = preg_match($pattern, trim($accent)) ? strtoupper($accent) : '#FBBF24';

    if (carkifelek_rgb_distance_hex($a, $b) >= 78.0) {
        return [$a, $b];
    }

    $p = carkifelek_hex_to_rgb_triplet($a);
    $aux = carkifelek_hex_to_rgb_triplet($v);
    if ($p === null || $aux === null) {
        return [$a, $b];
    }

    $cr = 255 - $p['r'];
    $cg = 255 - $p['g'];
    $cb = 255 - $p['b'];
    $r2 = (int) round($cr * 0.5 + $aux['r'] * 0.36 + $p['r'] * 0.14);
    $g2 = (int) round($cg * 0.5 + $aux['g'] * 0.36 + $p['g'] * 0.14);
    $b2 = (int) round($cb * 0.5 + $aux['b'] * 0.36 + $p['b'] * 0.14);

    $r2 = max(38, min(245, $r2));
    $g2 = max(38, min(245, $g2));
    $b2 = max(38, min(245, $b2));

    return [$a, carkifelek_rgb_to_hex($r2, $g2, $b2)];
}
