<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_url.php';
require_once __DIR__ . '/OrderPaymentFinalize.php';

final class NkolayGateway
{
    private PDO $db;
    /** @var array<string,mixed> */
    private array $cfg;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $st = $pdo->query('SELECT * FROM nkolay_settings WHERE id = 1');
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
        foreach (['sx_token', 'merchant_secret_key'] as $k) {
            if (trim((string) ($this->cfg[$k] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    public function paymentUrl(): string
    {
        $sandbox = !empty($this->cfg['sandbox']);

        return $sandbox
            ? 'https://paynkolaytest.nkolayislem.com.tr/Vpos'
            : 'https://paynkolay.nkolayislem.com.tr/Vpos';
    }

    /**
     * @param array<string,mixed> $order
     * @return array{ok:bool,url?:string,fields?:array<string,string>,error?:string}
     */
    public function createPaymentForm(array $order): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'N Kolay ayarları eksik veya kapalı.'];
        }

        $orderId = (int) ($order['order_id'] ?? 0);
        if ($orderId < 1) {
            return ['ok' => false, 'error' => 'Geçersiz sipariş.'];
        }

        $price = (float) ($order['product_price'] ?? 0);
        if ($price <= 0) {
            $sumSt = $this->db->prepare('SELECT COALESCE(SUM(price),0) FROM order_items WHERE order_id = ?');
            $sumSt->execute([$orderId]);
            $price = (float) $sumSt->fetchColumn();
        }
        if ($price <= 0) {
            return ['ok' => false, 'error' => 'Geçersiz ödeme tutarı.'];
        }

        $sx = trim((string) ($this->cfg['sx_token'] ?? ''));
        $merchantSecret = (string) ($this->cfg['merchant_secret_key'] ?? '');
        $clientRefCode = (string) ($order['payment_merchant_oid'] ?? OrderPaymentFinalize::merchantOidForOrder($orderId));
        $amount = number_format($price, 2, '.', '');
        $rnd = date('Y-m-d H:i:s');
        $customerKey = 'order-' . $orderId;
        $successUrl = app_url('payment/nkolay_callback', ['result' => 'ok'], $this->db);
        $failUrl = app_url('payment/nkolay_callback', ['result' => 'fail'], $this->db);

        $rawHash = implode('|', [$sx, $clientRefCode, $amount, $successUrl, $failUrl, $rnd, $customerKey, $merchantSecret]);
        $hashDataV2 = base64_encode(hash('sha512', $rawHash, true));

        $fields = [
            'sx' => $sx,
            'clientRefCode' => $clientRefCode,
            'successUrl' => $successUrl,
            'failUrl' => $failUrl,
            'amount' => $amount,
            'cardHolderIP' => app_client_ip(),
            'transactionType' => 'SALES',
            'use3D' => !empty($this->cfg['use_3d']) ? 'true' : 'false',
            'rnd' => $rnd,
            'hashDataV2' => $hashDataV2,
            'customerKey' => $customerKey,
            'currencyCode' => '949',
        ];

        $merchantCustomerNo = trim((string) ($this->cfg['merchant_customer_no'] ?? ''));
        if ($merchantCustomerNo !== '') {
            $fields['MerchantCustomerNo'] = $merchantCustomerNo;
        }

        return ['ok' => true, 'url' => $this->paymentUrl(), 'fields' => $fields];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{ok:bool,success?:bool,merchant_oid?:string,error?:string}
     */
    public function verifyResponse(array $post): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'N Kolay kapalı'];
        }

        $merchantNo = $this->pick($post, ['MERCHANT_NO', 'merchantNo', 'merchant_no']);
        $referenceCode = $this->pick($post, ['REFERENCE_CODE', 'referenceCode', 'clientRefCode']);
        $authCode = $this->pick($post, ['AUTH_CODE', 'authCode']);
        $responseCode = $this->pick($post, ['RESPONSE_CODE', 'responseCode']);
        $use3d = $this->pick($post, ['USE_3D', 'use3D']);
        $rnd = $this->pick($post, ['RND', 'rnd']);
        $installment = $this->pick($post, ['INSTALLMENT', 'installment']);
        $authorizationAmount = $this->pick($post, ['AUTHORIZATION_AMOUNT', 'authorizationAmount']);
        $currencyCode = $this->pick($post, ['CURRENCY_CODE', 'currencyCode']);
        $hashIncoming = $this->pick($post, ['hashDataV2', 'HASHDATAV2', 'hashdatav2']);

        if ($referenceCode === '' || $responseCode === '' || $hashIncoming === '') {
            return ['ok' => false, 'error' => 'Eksik N Kolay dönüş alanı'];
        }

        $merchantSecret = (string) ($this->cfg['merchant_secret_key'] ?? '');
        $raw = implode('|', [
            $merchantNo,
            $referenceCode,
            $authCode,
            $responseCode,
            $use3d,
            $rnd,
            $installment,
            $authorizationAmount,
            $currencyCode,
            $merchantSecret,
        ]);
        $calcHash = base64_encode(hash('sha512', $raw, true));
        if (!hash_equals($calcHash, $hashIncoming)) {
            return ['ok' => false, 'error' => 'N Kolay hash doğrulaması başarısız'];
        }

        $success = $responseCode === '2' && !in_array($authCode, ['', '0', '00'], true);

        return ['ok' => true, 'success' => $success, 'merchant_oid' => $referenceCode];
    }

    /**
     * @param array<string,mixed> $src
     * @param list<string> $keys
     */
    private function pick(array $src, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($src[$k])) {
                return trim((string) $src[$k]);
            }
        }

        return '';
    }
}
