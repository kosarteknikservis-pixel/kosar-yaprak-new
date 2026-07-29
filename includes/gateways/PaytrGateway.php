<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_url.php';

/**
 * PayTR iFrame API — dev.paytr.com dökümantasyonu ile birebir.
 * @see https://dev.paytr.com/iframe-api/iframe-api-1-adim
 * @see https://dev.paytr.com/iframe-api/iframe-api-2-adim
 */
final class PaytrGateway
{
    private const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';
    private const IFRAME_BASE = 'https://www.paytr.com/odeme/guvenli/';

    private PDO $db;
    /** @var array<string,mixed> */
    private array $cfg;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $st = $pdo->query('SELECT * FROM paytr_settings WHERE id = 1');
        $row = ($st instanceof PDOStatement) ? $st->fetch(PDO::FETCH_ASSOC) : false;
        $this->cfg = is_array($row) ? $row : [];
    }

    public function enabled(): bool
    {
        return !empty($this->cfg['is_enabled']);
    }

    public function configured(): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        foreach (['merchant_id', 'merchant_key', 'merchant_salt'] as $k) {
            if (trim((string) ($this->cfg[$k] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $order
     * @return array{ok: bool, token?: string, iframe_url?: string, error?: string}
     */
    public function createIframeToken(array $order): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'PayTR ayarları eksik veya kapalı.'];
        }

        $orderId = (int) ($order['order_id'] ?? 0);
        $merchant_id = trim((string) $this->cfg['merchant_id']);
        $merchant_key = (string) $this->cfg['merchant_key'];
        $merchant_salt = (string) $this->cfg['merchant_salt'];
        $merchant_oid = (string) ($order['payment_merchant_oid'] ?? OrderPaymentFinalize::merchantOidForOrder($orderId));
        $email = OrderPaymentFinalize::syntheticEmailForOrder($orderId, $this->db);
        $price = (float) ($order['product_price'] ?? 0);
        if ($price <= 0) {
            $sumSt = $this->db->prepare('SELECT COALESCE(SUM(price),0) FROM order_items WHERE order_id = ?');
            $sumSt->execute([$orderId]);
            $price = (float) $sumSt->fetchColumn();
        }
        $payment_amount = (int) round($price * 100);
        if ($payment_amount < 1) {
            return ['ok' => false, 'error' => 'Geçersiz ödeme tutarı.'];
        }

        $productName = trim((string) ($order['product_name'] ?? 'Sipariş'));
        $user_basket = base64_encode(json_encode([
            [$productName, number_format($price, 2, '.', ''), 1],
        ], JSON_UNESCAPED_UNICODE));

        $user_ip = app_paytr_local_ip_override();
        $no_installment = (int) ($this->cfg['no_installment'] ?? 0);
        $max_installment = (int) ($this->cfg['max_installment'] ?? 0);
        $currency = 'TL';
        $test_mode = (int) ($this->cfg['test_mode'] ?? 0);
        $debug_on = (int) ($this->cfg['debug_on'] ?? 0);

        $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
        $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

        $post_vals = [
            'merchant_id' => $merchant_id,
            'user_ip' => $user_ip,
            'merchant_oid' => $merchant_oid,
            'email' => $email,
            'payment_amount' => $payment_amount,
            'paytr_token' => $paytr_token,
            'user_basket' => $user_basket,
            'debug_on' => $debug_on,
            'no_installment' => $no_installment,
            'max_installment' => $max_installment,
            'user_name' => mb_substr((string) ($order['customer_name'] ?? 'Müşteri'), 0, 60),
            'user_address' => mb_substr((string) ($order['customer_address'] ?? '-'), 0, 400),
            'user_phone' => mb_substr(preg_replace('/\D+/', '', (string) ($order['customer_phone'] ?? '')) ?: '5000000000', 0, 20),
            'merchant_ok_url' => app_url('payment/paytr_ok', ['order_id' => $orderId], $this->db),
            'merchant_fail_url' => app_url('payment/paytr_fail', [], $this->db),
            'timeout_limit' => (int) ($this->cfg['timeout_limit'] ?? 30),
            'currency' => $currency,
            'test_mode' => $test_mode,
            'lang' => 'tr',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::TOKEN_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_vals,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);

            return ['ok' => false, 'error' => 'PayTR bağlantı hatası: ' . $err];
        }
        curl_close($ch);

        $result = json_decode((string) $raw, true);
        if (!is_array($result) || ($result['status'] ?? '') !== 'success' || empty($result['token'])) {
            $reason = is_array($result) ? (string) ($result['reason'] ?? $raw) : (string) $raw;

            return ['ok' => false, 'error' => 'PayTR token alınamadı: ' . $reason];
        }

        $token = (string) $result['token'];

        return ['ok' => true, 'token' => $token, 'iframe_url' => self::IFRAME_BASE . $token];
    }

    /**
     * Bildirim URL doğrulama — 2. adım hash kontrolü.
     *
     * @param array<string,mixed> $post
     * @return array{ok: bool, status?: string, merchant_oid?: string, total_amount?: string, error?: string}
     */
    public function verifyCallback(array $post): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'PayTR kapalı'];
        }

        $merchant_key = (string) $this->cfg['merchant_key'];
        $merchant_salt = (string) $this->cfg['merchant_salt'];
        $merchant_oid = (string) ($post['merchant_oid'] ?? '');
        $status = (string) ($post['status'] ?? '');
        $total_amount = (string) ($post['total_amount'] ?? '');
        $hash = (string) ($post['hash'] ?? '');

        if ($merchant_oid === '' || $status === '' || $hash === '') {
            return ['ok' => false, 'error' => 'Eksik POST alanı'];
        }

        $hash_str = $merchant_oid . $merchant_salt . $status . $total_amount;
        $token = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));

        if (!hash_equals($token, $hash)) {
            return ['ok' => false, 'error' => 'PayTR hash doğrulaması başarısız'];
        }

        return [
            'ok' => true,
            'status' => $status,
            'merchant_oid' => $merchant_oid,
            'total_amount' => $total_amount,
        ];
    }
}

require_once __DIR__ . '/OrderPaymentFinalize.php';
