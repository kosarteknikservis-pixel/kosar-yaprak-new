<?php

declare(strict_types=1);

require_once __DIR__ . '/gateways/OrderPaymentFinalize.php';

/**
 * Sipariş SMS doğrulama ayarları ve form öncesi OTP (katman A).
 */

function order_sms_verify_settings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'order_sms_verify_enabled' => 1,
        'order_sms_front_otp_enabled' => 1,
    ];

    try {
        $row = $pdo->query(
            'SELECT order_sms_verify_enabled, order_sms_front_otp_enabled
             FROM checkout_module_settings WHERE id = 1 LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $cache = array_merge($defaults, $row);
        } else {
            $cache = $defaults;
        }
    } catch (Throwable $e) {
        $cache = $defaults;
    }

    return $cache;
}

function order_payment_is_online_card(PDO $pdo, int $paymentMethodId): bool
{
    if ($paymentMethodId <= 0) {
        return false;
    }
    $gw = OrderPaymentFinalize::gatewayCodeForMethod($pdo, $paymentMethodId);

    return in_array($gw, ['paytr', 'iyzico'], true);
}

function order_payment_needs_sms_verify(PDO $pdo, int $paymentMethodId): bool
{
    $cfg = order_sms_verify_settings($pdo);
    if ((int) ($cfg['order_sms_verify_enabled'] ?? 1) !== 1) {
        return false;
    }
    if (order_payment_is_online_card($pdo, $paymentMethodId)) {
        return false;
    }

    return true;
}

function order_sms_front_otp_enabled(PDO $pdo): bool
{
    $cfg = order_sms_verify_settings($pdo);

    return (int) ($cfg['order_sms_front_otp_enabled'] ?? 1) === 1;
}

function order_sms_verify_pending_status_id(PDO $pdo): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }

    try {
        $st = $pdo->query("SELECT order_status_id FROM order_status WHERE status_name = 'SMS Doğrulama Bekliyor' LIMIT 1");
        $found = (int) ($st ? $st->fetchColumn() : 0);
        if ($found > 0) {
            $id = $found;

            return $id;
        }
        $pdo->exec("INSERT INTO order_status (status_name) VALUES ('SMS Doğrulama Bekliyor')");
        $id = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $id = 1;
    }

    return $id;
}

function order_sms_verify_approved_status_id(PDO $pdo): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }

    try {
        $st = $pdo->query("SELECT order_status_id FROM order_status WHERE status_name IN ('Yeni Sipariş', 'Beklemede', 'Yeni') ORDER BY order_status_id ASC LIMIT 1");
        $found = (int) ($st ? $st->fetchColumn() : 0);
        $id = $found > 0 ? $found : 1;
    } catch (Throwable $e) {
        $id = 1;
    }

    return $id;
}

function order_front_otp_is_active(): bool
{
    return isset($_SESSION['front_order_verify']) && is_array($_SESSION['front_order_verify']);
}

function order_front_otp_is_resend_request(array $post): bool
{
    return isset($post['otp_stage_resend_submit']) || (string) ($post['otp_stage_action'] ?? '') === 'resend';
}

function order_front_otp_is_verify_request(array $post): bool
{
    if (isset($post['otp_stage_verify_submit'])) {
        return true;
    }
    if ((string) ($post['otp_stage_action'] ?? '') === 'verify') {
        return true;
    }
    if (order_front_otp_is_resend_request($post)) {
        return false;
    }
    $code = preg_replace('/\D+/', '', (string) ($post['otp_code_front'] ?? ''));
    if (strlen($code) === 6 && order_front_otp_is_active()) {
        return true;
    }

    return false;
}

/**
 * Form öncesi OTP işleyicisi.
 *
 * @return 'skipped'|'passed'|'pending'|'invalid'|'expired'|'sms_fail'
 */
function order_front_otp_handle_post(PDO $pdo, array $post, string $customerPhone, int $paymentMethodId): string
{
    if (! order_payment_needs_sms_verify($pdo, $paymentMethodId)) {
        return 'skipped';
    }
    if (! order_sms_front_otp_enabled($pdo)) {
        return 'skipped';
    }

    require_once __DIR__ . '/order_verification.php';

    $telDigits = ov_normalize_tel($customerPhone);
    if (strlen($telDigits) !== 10) {
        return 'invalid';
    }

    if (! empty($post['otp_stage_passed'])) {
        return 'passed';
    }

    if (order_front_otp_is_resend_request($post)) {
        $pending = $_SESSION['front_order_verify'] ?? null;
        if (! is_array($pending)) {
            $pending = ['post_data' => $post];
        }
        $otp = ov_generate_otp();
        if (! ov_send_front_otp_sms($telDigits, $otp)) {
            return 'sms_fail';
        }
        $_SESSION['front_order_verify'] = [
            'otp_hash' => hash('sha256', $otp),
            'expires_at' => time() + 300,
            'sent_to' => $telDigits,
            'post_data' => $pending['post_data'] ?? $post,
        ];

        return 'pending';
    }

    if (order_front_otp_is_verify_request($post)) {
        $pending = $_SESSION['front_order_verify'] ?? null;
        if (! is_array($pending)) {
            return 'pending';
        }
        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['front_order_verify']);

            return 'expired';
        }
        $code = preg_replace('/\D+/', '', (string) ($post['otp_code_front'] ?? ''));
        $hash = hash('sha256', $code);
        if (! hash_equals((string) ($pending['otp_hash'] ?? ''), $hash)) {
            return 'invalid';
        }
        if ((string) ($pending['sent_to'] ?? '') !== $telDigits) {
            return 'invalid';
        }
        unset($_SESSION['front_order_verify']);
        $_POST['otp_stage_passed'] = '1';

        return 'passed';
    }

    if (order_front_otp_is_active()) {
        return 'pending';
    }

    if (empty($post['otp_stage_passed'])) {
        $otp = ov_generate_otp();
        if (! ov_send_front_otp_sms($telDigits, $otp)) {
            return 'sms_fail';
        }
        $_SESSION['front_order_verify'] = [
            'otp_hash' => hash('sha256', $otp),
            'expires_at' => time() + 300,
            'sent_to' => $telDigits,
            'post_data' => $post,
        ];

        return 'pending';
    }

    return 'passed';
}
