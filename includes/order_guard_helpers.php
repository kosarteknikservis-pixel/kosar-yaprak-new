<?php
declare(strict_types=1);

const ORDER_GUARD_COOKIE_NAME = 'last_order_time';

/** @return array{order_cookie_gate_enabled:int,order_cookie_seconds:int,order_dupe_server_enabled:int,order_dupe_window_seconds:int} */
function order_guard_defaults(): array
{
    return [
        'order_cookie_gate_enabled' => 1,
        'order_cookie_seconds' => 60,
        'order_dupe_server_enabled' => 1,
        'order_dupe_window_seconds' => 86400,
    ];
}

/** @return array{order_cookie_gate_enabled:int,order_cookie_seconds:int,order_dupe_server_enabled:int,order_dupe_window_seconds:int} */
function order_guard_load(PDO $pdo): array
{
    $guard = order_guard_defaults();
    try {
        $row = $pdo->query(
            'SELECT COALESCE(order_cookie_gate_enabled,1) AS ocge,
                COALESCE(order_cookie_seconds,60) AS ocs,
                COALESCE(order_dupe_server_enabled,1) AS odse,
                COALESCE(order_dupe_window_seconds,86400) AS odws
             FROM checkout_module_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $guard['order_cookie_gate_enabled'] = (int) ($row['ocge'] ?? 1) !== 0 ? 1 : 0;
            $guard['order_cookie_seconds'] = max(60, min(2592000, (int) ($row['ocs'] ?? 60)));
            $guard['order_dupe_server_enabled'] = (int) ($row['odse'] ?? 1) !== 0 ? 1 : 0;
            $guard['order_dupe_window_seconds'] = max(120, min(2592000, (int) ($row['odws'] ?? 86400)));
        }
    } catch (Throwable $e) {
        /* schema henüz yoksa varsayılanlar */
    }

    return $guard;
}

function order_guard_phone_digits(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);

    return is_string($digits) ? $digits : '';
}

/** @param array{order_cookie_gate_enabled?:int,order_cookie_seconds?:int} $guard */
function order_guard_browser_blocks(array $guard): bool
{
    if ((int) ($guard['order_cookie_gate_enabled'] ?? 1) !== 1) {
        return false;
    }

    if (! isset($_COOKIE[ORDER_GUARD_COOKIE_NAME])) {
        return false;
    }

    $cv = $_COOKIE[ORDER_GUARD_COOKIE_NAME];
    $cts = is_numeric($cv) ? (int) $cv : (int) strtotime((string) $cv);
    $lifetime = max(60, (int) ($guard['order_cookie_seconds'] ?? 60));

    return $cts > 0 && (time() - $cts) < $lifetime;
}

function order_guard_set_browser_cookie(int $lifetime): void
{
    $lifetime = max(60, min(2592000, $lifetime));
    setcookie(ORDER_GUARD_COOKIE_NAME, (string) time(), [
        'expires' => time() + $lifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * @param array{order_dupe_server_enabled?:int,order_dupe_window_seconds?:int} $guard
 * @return array{blocked:bool,reason:?string}
 */
function order_guard_server_duplicate(PDO $pdo, array $guard, string $ip, string $phoneDigits): array
{
    if ((int) ($guard['order_dupe_server_enabled'] ?? 1) !== 1) {
        return ['blocked' => false, 'reason' => null];
    }

    $win = max(120, (int) ($guard['order_dupe_window_seconds'] ?? 86400));
    $phoneSql = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(IFNULL(customer_phone, "")), " ", ""), "(", ""), ")", ""), "-", ""), "+", "")';

    if ($phoneDigits !== '') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? SECOND)
             AND {$phoneSql} = ?"
        );
        $stmt->execute([$win, $phoneDigits]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['blocked' => true, 'reason' => 'phone'];
        }
    }

    if ($ip !== '') {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? SECOND) AND customer_ip = ?'
        );
        $stmt->execute([$win, $ip]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['blocked' => true, 'reason' => 'ip'];
        }
    }

    return ['blocked' => false, 'reason' => null];
}

function order_guard_format_window(int $seconds): string
{
    if ($seconds >= 86400 && $seconds % 86400 === 0) {
        $days = (int) ($seconds / 86400);

        return $days === 1 ? '24 saat' : $days . ' gün';
    }
    if ($seconds >= 3600 && $seconds % 3600 === 0) {
        $hours = (int) ($seconds / 3600);

        return $hours === 1 ? '1 saat' : $hours . ' saat';
    }
    if ($seconds >= 60 && $seconds % 60 === 0) {
        $mins = (int) ($seconds / 60);

        return $mins === 1 ? '1 dakika' : $mins . ' dakika';
    }

    return $seconds . ' saniye';
}
