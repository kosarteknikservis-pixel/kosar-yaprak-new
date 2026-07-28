<?php
declare(strict_types=1);

/**
 * Paraşüt API v4 — OAuth2 (panel yazılımındaki yaklaşıma paralel).
 * @see https://apidocs.parasut.com/
 */
final class ParasutClient
{
    public const TOKEN_URL = 'https://api.parasut.com/oauth/token';
    public const API_BASE = 'https://api.parasut.com/v4/';

    private PDO $db;
    /** @var array<string,mixed> */
    private array $cfg = [];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $st = $pdo->query('SELECT * FROM parasut_settings WHERE id = 1');
        $row = ($st instanceof PDOStatement) ? $st->fetch(PDO::FETCH_ASSOC) : false;
        $this->cfg = is_array($row) ? $row : [];
    }

    public function reload(): void
    {
        $st = $this->db->query('SELECT * FROM parasut_settings WHERE id = 1');
        $row = ($st instanceof PDOStatement) ? $st->fetch(PDO::FETCH_ASSOC) : false;
        $this->cfg = is_array($row) ? $row : [];
    }

    /** @return array<string,mixed> */
    public function rawConfig(): array
    {
        return $this->cfg;
    }

    public function enabled(): bool
    {
        return !empty($this->cfg['enabled']);
    }

    public function configured(): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        $need = ['company_id', 'client_id', 'client_secret', 'parasut_username', 'parasut_password'];
        foreach ($need as $k) {
            if (trim((string) ($this->cfg[$k] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $body */
    private function requestToken(array $body): array
    {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $raw, true);
        if ($code >= 200 && $code < 300 && is_array($json) && !empty($json['access_token'])) {
            return $json;
        }
        $err = is_array($json) && isset($json['error_description']) ? (string) $json['error_description'] : (string) $raw;
        if ($err === '') {
            $err = 'HTTP ' . $code;
        }
        throw new RuntimeException('Paraşüt OAuth: ' . $err);
    }

    /** @param array<string,mixed> $tok */
    private function persistTokens(array $tok): void
    {
        $exp = time() + (int) ($tok['expires_in'] ?? 3600) - 90;
        $refresh = (string) ($tok['refresh_token'] ?? '');
        if ($refresh === '') {
            $refresh = (string) ($this->cfg['refresh_token'] ?? '');
        }
        $u = $this->db->prepare('UPDATE parasut_settings SET access_token = :a, refresh_token = :r, token_expires_at = :e WHERE id = 1');
        $u->execute([
            'a' => (string) ($tok['access_token'] ?? ''),
            'r' => $refresh,
            'e' => $exp,
        ]);
        $this->cfg['access_token'] = (string) ($tok['access_token'] ?? '');
        $this->cfg['refresh_token'] = $refresh;
        $this->cfg['token_expires_at'] = $exp;
    }

    public function ensureAccessToken(): string
    {
        $this->reload();
        $now = time();
        $access = (string) ($this->cfg['access_token'] ?? '');
        $exp = (int) ($this->cfg['token_expires_at'] ?? 0);
        $refresh = (string) ($this->cfg['refresh_token'] ?? '');
        if ($access !== '' && $exp > $now) {
            return $access;
        }
        if ($refresh !== '') {
            try {
                $tok = $this->requestToken([
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->cfg['client_id'],
                    'client_secret' => $this->cfg['client_secret'],
                    'refresh_token' => $refresh,
                ]);
                $this->persistTokens($tok);

                return (string) $tok['access_token'];
            } catch (Throwable $e) {
                /* sonra password grant */
            }
        }
        $tok = $this->requestToken([
            'grant_type' => 'password',
            'client_id' => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
            'username' => $this->cfg['parasut_username'],
            'password' => $this->cfg['parasut_password'],
            'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        ]);
        $this->persistTokens($tok);

        return (string) $tok['access_token'];
    }

    /**
     * @return array<mixed>|array{errors?:mixed}
     */
    public function api(string $method, string $path, ?array $jsonBody = null): array
    {
        $token = $this->ensureAccessToken();
        $company = trim((string) $this->cfg['company_id']);
        $url = self::API_BASE . rawurlencode($company) . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/vnd.api+json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_UNICODE));
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if ($code >= 200 && $code < 300) {
            return is_array($decoded) ? $decoded : [];
        }
        $msg = $raw;
        if (is_array($decoded) && isset($decoded['errors'][0]['detail'])) {
            $msg = (string) $decoded['errors'][0]['detail'];
        }
        throw new RuntimeException('Paraşüt API (' . $code . '): ' . mb_substr((string) $msg, 0, 800));
    }

    /**
     * Kaydetmeden sadece geçilen bilgilerle token alır (Bağlantı testi için).
     */
    public static function probePasswordGrant(
        string $companyId,
        string $clientId,
        string $clientSecret,
        string $parasutUser,
        string $parasutPass
    ): array {
        $body = [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $parasutUser,
            'password' => $parasutPass,
            'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        ];
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $raw, true);
        if ($code >= 200 && $code < 300 && is_array($json) && !empty($json['access_token'])) {
            return ['ok' => true];
        }
        $msg = is_array($json) && isset($json['error_description'])
            ? (string) $json['error_description'] : trim((string) $raw);

        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'HTTP ' . $code];
    }

    /**
     * Panel uyumlu sipariş satırını Paraşüt’te satış faturası olarak oluşturur; `orders.parasut_invoice_id` güncellenir.
     *
     * @param array<string,mixed> $siparis ParasutOrderSync::fetchPanelstyleRow çıktısı
     * @return array{ok: bool, parasut_invoice_id?: string, error?: string, contact_id?: string}
     */
    public function sendSiparis(array $siparis, bool $force): array
    {
        $this->reload();
        if (!$this->enabled()) {
            return ['ok' => false, 'error' => 'Paraşüt entegrasyonu kapalı.'];
        }
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'Paraşüt ayarları eksik (firma, OAuth bilgileri).'];
        }

        $existing = trim((string) ($siparis['siparis_parasut_invoice_id'] ?? ''));
        if ($existing !== '' && !$force) {
            return ['ok' => false, 'error' => 'Bu sipariş için Paraşüt satış faturası zaten oluşturulmuş. Yeniden göndermek için onay kutusunu işaretleyin.'];
        }

        $orderId = (int) ($siparis['siparis_id'] ?? 0);
        if ($orderId < 1) {
            return ['ok' => false, 'error' => 'Geçersiz sipariş numarası.'];
        }

        try {
            $contactId = $this->createContactForOrder($siparis);
            $invoiceId = $this->createSalesInvoiceForOrder($siparis, $contactId);
            $this->persistOrderParasutInvoiceId($orderId, $invoiceId);

            return ['ok' => true, 'parasut_invoice_id' => $invoiceId, 'contact_id' => $contactId];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string,mixed> $siparis */
    private function createContactForOrder(array $siparis): string
    {
        $unvan = trim((string) ($siparis['siparis_fatura_unvan'] ?? ''));
        $vknRaw = trim((string) ($siparis['siparis_fatura_vn'] ?? ''));
        $corp = $unvan !== '' || $vknRaw !== '';

        $vd = trim((string) ($siparis['siparis_fatura_vd'] ?? ''));
        $fadres = trim((string) ($siparis['siparis_fatura_adres'] ?? ''));

        $displayName = $corp ? ($unvan !== '' ? $unvan : (string) ($siparis['siparis_ad'] ?? '')) : (string) ($siparis['siparis_ad'] ?? '');
        $displayName = trim($displayName) !== '' ? $displayName : 'Müşteri #' . ($siparis['siparis_id'] ?? '');

        $digits = static function (string $s): string {
            return preg_replace('/\D+/', '', $s) ?? '';
        };

        $bireyselTc = preg_replace('/\D/', '', (string) ($this->cfg['bireysel_vergi_no'] ?? '11111111111'));
        if ($bireyselTc === '') {
            $bireyselTc = '11111111111';
        }

        $taxNo = $corp ? $digits($vknRaw) : $bireyselTc;
        if ($taxNo === '') {
            $taxNo = $bireyselTc;
        }
        $taxNo = mb_substr($taxNo, 0, 20);

        $addr = $fadres !== '' ? $fadres : (string) ($siparis['siparis_adres'] ?? '');
        $phone = $digits((string) ($siparis['siparis_tel'] ?? ''));

        $attrs = [
            'name' => mb_substr($displayName, 0, 255),
            'contact_type' => $corp ? 'company' : 'person',
            'tax_number' => $taxNo,
            'district' => mb_substr((string) ($siparis['siparis_ilce'] ?? ''), 0, 128),
            'city' => mb_substr((string) ($siparis['siparis_il'] ?? ''), 0, 128),
            'address' => mb_substr($addr, 0, 500),
            'account_type' => 'customer',
        ];
        if ($phone !== '') {
            $attrs['phone'] = mb_substr($phone, 0, 40);
        }
        if ($corp && $vd !== '') {
            $attrs['tax_office'] = mb_substr($vd, 0, 255);
        }

        $body = [
            'data' => [
                'type' => 'contacts',
                'attributes' => $attrs,
            ],
        ];

        $resp = $this->api('POST', 'contacts', $body);

        return $this->extractJsonApiResourceId($resp);
    }

    /**
     * @param array<string,mixed> $siparis
     */
    private function createSalesInvoiceForOrder(array $siparis, string $contactId): string
    {
        $issue = $this->normalizeIssueDate((string) ($siparis['siparis_tarih'] ?? ''));

        $gross = (float) ($siparis['siparis_fiyat'] ?? 0);
        $vatPct = (float) ($this->cfg['vat_rate'] ?? 0);
        if ($vatPct < 0) {
            $vatPct = 0;
        }
        $kdvIncl = !empty((int) ($this->cfg['kdv_included'] ?? 1));

        $netUnit = $gross;
        if ($kdvIncl && $vatPct > 0 && $gross > 0) {
            $netUnit = $gross / (1 + $vatPct / 100);
        }
        $netUnit = round($netUnit, 4);

        $descLong = trim((string) ($siparis['siparis_urun'] ?? ''));
        $lineDesc = mb_substr($descLong !== '' ? $descLong : ('Sipariş #' . $siparis['siparis_id']), 0, 500);
        $summary = mb_substr('Sipariş #' . $siparis['siparis_id'] . ($descLong !== '' ? ' — ' . $descLong : ''), 0, 450);

        $detailNode = [
            'type' => 'sales_invoice_details',
            'attributes' => [
                'quantity' => 1,
                'unit_price' => $netUnit,
                'vat_rate' => $vatPct,
                'description' => $lineDesc,
            ],
        ];

        $productId = trim((string) ($this->cfg['product_id'] ?? ''));
        if ($productId !== '') {
            $detailNode['relationships'] = [
                'product' => [
                    'data' => [
                        'type' => 'products',
                        'id' => $productId,
                    ],
                ],
            ];
        }

        $invoiceBody = [
            'data' => [
                'type' => 'sales_invoices',
                'attributes' => [
                    'item_type' => 'invoice',
                    'description' => $summary,
                    'issue_date' => $issue,
                    'due_date' => $issue,
                    'currency' => 'TRL',
                    'shipment_included' => false,
                ],
                'relationships' => [
                    'contact' => [
                        'data' => [
                            'type' => 'contacts',
                            'id' => $contactId,
                        ],
                    ],
                    'details' => [
                        'data' => [$detailNode],
                    ],
                ],
            ],
        ];

        $resp = $this->api('POST', 'sales_invoices', $invoiceBody);

        return $this->extractJsonApiResourceId($resp);
    }

    private function persistOrderParasutInvoiceId(int $orderId, string $parasutInvoiceId): void
    {
        $st = $this->db->prepare('UPDATE orders SET parasut_invoice_id = ? WHERE order_id = ? LIMIT 1');
        $st->execute([mb_substr($parasutInvoiceId, 0, 64), $orderId]);
    }

    /**
     * @param array<string,mixed> $resp
     */
    private function extractJsonApiResourceId(array $resp): string
    {
        if (isset($resp['data']['id'])) {
            return (string) $resp['data']['id'];
        }
        throw new RuntimeException('Paraşüt yanıtında kaynak ID okunamadı.');
    }

    private function normalizeIssueDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }
        $ts = strtotime($raw);

        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
