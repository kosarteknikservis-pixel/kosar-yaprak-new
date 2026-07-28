<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_url.php';
require_once __DIR__ . '/OrderPaymentFinalize.php';

/**
 * iyzico Checkout Form (CF) — docs.iyzico.com HMACSHA256 (IYZWSv2).
 * @see https://docs.iyzico.com/en/getting-started/preliminaries/authentication/hmacsha256-auth
 */
final class IyzicoGateway
{
    private PDO $db;
    /** @var array<string,mixed> */
    private array $cfg;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $st = $pdo->query('SELECT * FROM iyzico_settings WHERE id = 1');
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
        foreach (['api_key', 'secret_key'] as $k) {
            if (trim((string) ($this->cfg[$k] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function baseUrl(): string
    {
        return !empty($this->cfg['sandbox']) ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
    }

    /**
     * @return array{ok: bool, body?: array<string,mixed>, error?: string}
     */
    private function apiPost(string $uriPath, array $body): array
    {
        $apiKey = (string) $this->cfg['api_key'];
        $secretKey = (string) $this->cfg['secret_key'];
        $randomKey = (string) (int) (microtime(true) * 1000000);
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'JSON encode hatası'];
        }

        $payload = $randomKey . $uriPath . $json;
        $signature = base64_encode(hash_hmac('sha256', $payload, $secretKey, true));
        $authRaw = 'apiKey:' . $apiKey . '&randomKey:' . $randomKey . '&signature:' . $signature;
        $authorization = 'IYZWSv2 ' . base64_encode($authRaw);

        $ch = curl_init($this->baseUrl() . $uriPath);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authorization,
                'x-iyzi-rnd: ' . $randomKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);

            return ['ok' => false, 'error' => 'iyzico bağlantı hatası: ' . $err];
        }
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'iyzico geçersiz yanıt'];
        }

        return ['ok' => true, 'body' => $decoded];
    }

    /**
     * @param array<string,mixed> $order
     * @return array{ok: bool, token?: string, payment_page_url?: string, checkout_form_content?: string, error?: string}
     */
    public function initializeCheckoutForm(array $order): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'iyzico ayarları eksik veya kapalı.'];
        }

        $orderId = (int) ($order['order_id'] ?? 0);
        $conversationId = (string) ($order['payment_merchant_oid'] ?? OrderPaymentFinalize::merchantOidForOrder($orderId));
        $price = (float) ($order['product_price'] ?? 0);
        if ($price <= 0) {
            $sumSt = $this->db->prepare('SELECT COALESCE(SUM(price),0) FROM order_items WHERE order_id = ?');
            $sumSt->execute([$orderId]);
            $price = (float) $sumSt->fetchColumn();
        }
        if ($price <= 0) {
            return ['ok' => false, 'error' => 'Geçersiz ödeme tutarı.'];
        }

        $priceStr = number_format($price, 2, '.', '');
        $productName = trim((string) ($order['product_name'] ?? 'Ürün'));
        $cityName = (string) ($order['customer_city_name'] ?? 'Istanbul');
        $districtName = (string) ($order['customer_district_name'] ?? 'Merkez');
        $phone = preg_replace('/\D+/', '', (string) ($order['customer_phone'] ?? '')) ?: '5000000000';
        $email = OrderPaymentFinalize::syntheticEmailForOrder($orderId, $this->db);

        $installments = array_values(array_filter(array_map('intval', explode(',', (string) ($this->cfg['enabled_installments'] ?? '2,3,6,9')))));
        if ($installments === []) {
            $installments = [2, 3, 6, 9];
        }

        $body = [
            'locale' => 'tr',
            'conversationId' => $conversationId,
            'price' => $priceStr,
            'paidPrice' => $priceStr,
            'currency' => 'TRY',
            'basketId' => 'B' . $orderId,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => app_url('payment/iyzico_callback', [], $this->db),
            'enabledInstallments' => $installments,
            'buyer' => [
                'id' => 'BY' . $orderId,
                'name' => mb_substr((string) ($order['customer_name'] ?? 'Musteri'), 0, 30),
                'surname' => 'Musteri',
                'gsmNumber' => '+' . (str_starts_with($phone, '90') ? $phone : '90' . ltrim($phone, '0')),
                'email' => $email,
                'identityNumber' => '11111111111',
                'registrationAddress' => mb_substr((string) ($order['customer_address'] ?? 'Adres'), 0, 200),
                'ip' => app_client_ip(),
                'city' => mb_substr($cityName, 0, 40),
                'country' => 'Turkey',
            ],
            'shippingAddress' => [
                'contactName' => mb_substr((string) ($order['customer_name'] ?? 'Musteri'), 0, 50),
                'city' => mb_substr($cityName, 0, 40),
                'country' => 'Turkey',
                'address' => mb_substr((string) ($order['customer_address'] ?? 'Adres'), 0, 200),
            ],
            'billingAddress' => [
                'contactName' => mb_substr((string) ($order['customer_name'] ?? 'Musteri'), 0, 50),
                'city' => mb_substr($cityName, 0, 40),
                'country' => 'Turkey',
                'address' => mb_substr((string) ($order['customer_address'] ?? 'Adres'), 0, 200),
            ],
            'basketItems' => [[
                'id' => 'BI' . $orderId,
                'name' => mb_substr($productName, 0, 100),
                'category1' => 'Genel',
                'itemType' => 'PHYSICAL',
                'price' => $priceStr,
            ]],
        ];

        $resp = $this->apiPost('/payment/iyzipos/checkoutform/initialize/auth/ecom', $body);
        if (!$resp['ok']) {
            return $resp;
        }
        $decoded = $resp['body'];
        if (($decoded['status'] ?? '') !== 'success' || empty($decoded['token'])) {
            return ['ok' => false, 'error' => 'iyzico init: ' . (string) ($decoded['errorMessage'] ?? 'bilinmeyen hata')];
        }

        return [
            'ok' => true,
            'token' => (string) $decoded['token'],
            'payment_page_url' => (string) ($decoded['paymentPageUrl'] ?? ''),
            'checkout_form_content' => (string) ($decoded['checkoutFormContent'] ?? ''),
        ];
    }

    /** @return array{ok: bool, paid?: bool, payment_id?: string, error?: string} */
    public function retrieveCheckoutResult(string $token, string $conversationId): array
    {
        $resp = $this->apiPost('/payment/iyzipos/checkoutform/auth/ecom/detail', [
            'locale' => 'tr',
            'conversationId' => $conversationId,
            'token' => $token,
        ]);
        if (!$resp['ok']) {
            return $resp;
        }
        $decoded = $resp['body'];
        if (($decoded['status'] ?? '') !== 'success') {
            return ['ok' => false, 'error' => (string) ($decoded['errorMessage'] ?? 'iyzico retrieve başarısız')];
        }

        $paid = strtoupper((string) ($decoded['paymentStatus'] ?? '')) === 'SUCCESS';

        return [
            'ok' => true,
            'paid' => $paid,
            'payment_id' => (string) ($decoded['paymentId'] ?? ''),
        ];
    }
}
