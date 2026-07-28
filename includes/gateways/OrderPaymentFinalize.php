<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_url.php';

final class OrderPaymentFinalize
{
    /**
     * Kapıda ödeme / havale veya online ödeme onayı sonrası bildirim + senkron.
     *
     * @param array<string,mixed> $ctx
     */
    public static function afterOrderConfirmed(PDO $pdo, int $orderId, array $ctx): void
    {
        $customer_name = (string) ($ctx['customer_name'] ?? '');
        $customer_phone = (string) ($ctx['customer_phone'] ?? '');
        $customer_address = (string) ($ctx['customer_address'] ?? '');
        $customer_city = (string) ($ctx['customer_city'] ?? '');
        $customer_district = (string) ($ctx['customer_district'] ?? '');
        $order_notes = (string) ($ctx['order_notes'] ?? '');
        $payment_method_id = (int) ($ctx['payment_method_id'] ?? 0);
        $product_id = (int) ($ctx['product_id'] ?? 0);
        $product = is_array($ctx['product'] ?? null) ? $ctx['product'] : [];
        $selected_variants = is_array($ctx['selected_variants'] ?? null) ? $ctx['selected_variants'] : [];
        $reklam = (string) ($ctx['reklam'] ?? '');
        $source = (string) ($ctx['source'] ?? '');
        $utmCaptureOn = (int) ($ctx['utm_capture_on'] ?? 1);
        $invoice_vkn = (string) ($ctx['invoice_vkn'] ?? '');
        $invoice_tax_office = (string) ($ctx['invoice_tax_office'] ?? '');
        $invoice_company_name = (string) ($ctx['invoice_company_name'] ?? '');
        $invoice_address = (string) ($ctx['invoice_address'] ?? '');
        $ip_address = (string) ($ctx['ip_address'] ?? app_client_ip());
        $cookie_lifetime = (int) ($ctx['cookie_lifetime'] ?? 60);
        $price = (float) ($product['product_price'] ?? 0);

        require_once dirname(__DIR__) . '/laravel4_sync.php';
        laravel4_sync_checkout_order([
            'pdo' => $pdo,
            'order_id' => $orderId,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_address' => $customer_address,
            'customer_city' => $customer_city,
            'customer_district' => $customer_district,
            'payment_method_id' => $payment_method_id,
            'product_id' => $product_id,
            'product' => $product,
            'selected_variants' => $selected_variants,
            'order_notes' => $order_notes,
            'customer_notes' => '',
            'reklam' => $reklam,
            'source' => $source,
            'utm_capture_on' => $utmCaptureOn,
            'invoice_vkn' => $invoice_vkn,
            'invoice_tax_office' => $invoice_tax_office,
            'invoice_company_name' => $invoice_company_name,
            'invoice_address' => $invoice_address,
        ]);

        require_once dirname(__DIR__) . '/order_guard_helpers.php';
        order_guard_set_browser_cookie($cookie_lifetime);

        require_once dirname(__DIR__, 2) . '/telegram.php';
        $message = "Yeni Sipariş: \nMüşteri: {$customer_name}\nTelefon: {$customer_phone}\nAdres: {$customer_address}, {$customer_district}, {$customer_city}\nTutar: {$price} TL\nSipariş ID: {$orderId}\nNotlar: {$order_notes}\nKaynak: {$source}";
        if ($invoice_vkn !== '' || $invoice_tax_office !== '' || $invoice_company_name !== '' || $invoice_address !== '') {
            $message .= "\n--- Kurumsal fatura ---\nVKN: {$invoice_vkn}\nVergi D.: {$invoice_tax_office}\nÜnvan: {$invoice_company_name}\nFatura adr.: {$invoice_address}";
        }
        sendTelegramNotification($pdo, 'new_order', $message);

        if (is_file(dirname(__DIR__) . '/app_log.php')) {
            require_once dirname(__DIR__) . '/app_log.php';
            app_log('order', 'confirmed', [
                'order_id' => $orderId,
                'phone' => $customer_phone,
                'total' => $price,
                'source' => $source,
                'payment_method_id' => $payment_method_id,
            ]);
        }

        try {
            self::markYarimKalanConverted($pdo, $orderId, $customer_phone, $ip_address);
            setcookie('yarim_kalan_sid', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } catch (Throwable $e) {
            require_once dirname(__DIR__) . '/app_log.php';
            app_log('order', 'yarim_kalanlar cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, true>
     */
    private static function yarimKalanColumns(PDO $pdo): array
    {
        static $cache = [];

        $key = spl_object_id($pdo);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $cols = [];
        try {
            $q = $pdo->query('SHOW COLUMNS FROM yarim_kalanlar');
            if ($q instanceof PDOStatement) {
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = (string) ($row['Field'] ?? '');
                    if ($name !== '') {
                        $cols[$name] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            $cols = [];
        }

        return $cache[$key] = $cols;
    }

    private static function markYarimKalanConverted(PDO $pdo, int $orderId, string $customerPhone, string $ipAddress): void
    {
        $cols = self::yarimKalanColumns($pdo);
        if ($cols === []) {
            return;
        }

        $sets = [];
        $setParams = [];
        if (isset($cols['is_converted'])) {
            $sets[] = 'is_converted = 1';
        }
        if (isset($cols['converted_at'])) {
            $sets[] = 'converted_at = NOW()';
        }
        if (isset($cols['converted_order_id'])) {
            $sets[] = 'converted_order_id = ?';
            $setParams[] = $orderId;
        }
        if ($sets === []) {
            return;
        }

        $sid = isset($_COOKIE['yarim_kalan_sid']) ? (string) $_COOKIE['yarim_kalan_sid'] : '';
        if ($sid !== '' && ! preg_match('/^[a-zA-Z0-9_-]{16,96}$/', $sid)) {
            $sid = '';
        }

        $telDigits = preg_replace('/\D+/', '', $customerPhone) ?? '';
        $telLast10 = strlen($telDigits) >= 10 ? substr($telDigits, -10) : $telDigits;

        $conds = [];
        $params = [];
        if ($telLast10 !== '' && isset($cols['tel'])) {
            $conds[] = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(IFNULL(tel, "")), " ", ""), "(", ""), ")", ""), "-", ""), "+", "") LIKE ?';
            $params[] = '%'.$telLast10;
        }
        if ($sid !== '' && isset($cols['session_id'])) {
            $conds[] = 'session_id = ?';
            $params[] = $sid;
        }
        if ($ipAddress !== '' && isset($cols['ip'])) {
            $conds[] = 'ip = ?';
            $params[] = $ipAddress;
        }

        if ($conds === []) {
            return;
        }

        $activeWhere = isset($cols['is_converted']) ? ' AND IFNULL(is_converted, 0) = 0' : '';

        $sql = 'UPDATE yarim_kalanlar SET '.implode(', ', $sets)
            .' WHERE ('.implode(' OR ', $conds).')'.$activeWhere;

        $pdo->prepare($sql)->execute(array_merge($setParams, $params));
    }

    public static function merchantOidForOrder(int $orderId): string
    {
        return 'DH3' . $orderId;
    }

    public static function orderIdFromMerchantOid(string $merchantOid): int
    {
        $merchantOid = trim($merchantOid);
        if (preg_match('/^DH3(\d+)$/', $merchantOid, $m)) {
            return (int) $m[1];
        }

        return ctype_digit($merchantOid) ? (int) $merchantOid : 0;
    }

    public static function syntheticEmailForOrder(int $orderId, ?PDO $pdo = null): string
    {
        $host = parse_url(app_site_url($pdo), PHP_URL_HOST) ?: 'musteri.local';

        return 'siparis' . $orderId . '@' . preg_replace('/[^a-z0-9.-]/i', '', $host);
    }

    /** @return array<string,mixed>|null */
    public static function loadOrderContext(PDO $pdo, int $orderId): ?array
    {
        $st = $pdo->prepare(
            'SELECT o.*, c.city_name AS customer_city_name, d.district_name AS customer_district_name,
                    p.product_id, p.product_name, p.product_price
             FROM orders o
             LEFT JOIN cities c ON o.customer_city = c.city_id
             LEFT JOIN districts d ON o.customer_district = d.district_id
             LEFT JOIN order_items oi ON oi.order_id = o.order_id
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE o.order_id = ?
             LIMIT 1'
        );
        $st->execute([$orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function markPaymentPaid(PDO $pdo, int $orderId, string $gateway, string $gatewayRef = ''): void
    {
        $st = $pdo->prepare(
            'UPDATE orders SET payment_status = ?, gateway_code = ?, gateway_transaction_id = ?, paid_at = NOW()
             WHERE order_id = ? AND (payment_status IS NULL OR payment_status IN (\'pending\', \'failed\'))'
        );
        $st->execute(['paid', $gateway, mb_substr($gatewayRef, 0, 190), $orderId]);
    }

    public static function markPaymentFailed(PDO $pdo, int $orderId): void
    {
        $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE order_id = ? AND payment_status = 'pending'")
            ->execute([$orderId]);
    }

    public static function gatewayCodeForMethod(PDO $pdo, int $paymentMethodId): string
    {
        $st = $pdo->prepare('SELECT gateway_code FROM payment_methods WHERE payment_method_id = ? LIMIT 1');
        $st->execute([$paymentMethodId]);
        $code = trim((string) $st->fetchColumn());

        return $code !== '' ? $code : 'cod';
    }
}
