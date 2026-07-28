<?php
declare(strict_types=1);

/**
 * 3dhesap sipariş satırını panel siparis_* şeklinde dizip Paraşüt’e iletir.
 */
final class ParasutOrderSync
{
    /**
     * @return array<string,mixed>|null
     */
    public static function fetchPanelstyleRow(PDO $pdo, int $orderId): ?array
    {
        $st = $pdo->prepare(
            'SELECT o.*, c.city_name, d.district_name
             FROM orders o
             LEFT JOIN cities c ON o.customer_city = c.city_id
             LEFT JOIN districts d ON o.customer_district = d.district_id
             WHERE o.order_id = ?'
        );
        $st->execute([$orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $sumSt = $pdo->prepare('SELECT SUM(oi.price) FROM order_items oi WHERE oi.order_id = ?');
        $sumSt->execute([$orderId]);
        $total = (float)($sumSt->fetchColumn() ?: 0);

        $pci = $pdo->prepare(
            'SELECT GROUP_CONCAT(DISTINCT CONCAT(COALESCE(p.product_name, \'\'), \' (\', CAST(oi.price AS CHAR), \' TL)\') SEPARATOR \' | \')
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = ?'
        );
        $pci->execute([$orderId]);
        $productsConcat = trim((string)($pci->fetchColumn() ?: ''));

        $il = trim((string)($row['city_name'] ?? ''));
        if ($il === '') {
            $il = (string)($row['customer_city'] ?? '');
        }
        $ilce = trim((string)($row['district_name'] ?? ''));
        if ($ilce === '') {
            $ilce = (string)($row['customer_district'] ?? '');
        }

        if ($productsConcat === '') {
            $productsConcat = 'Sipariş #' . $orderId;
        }

        return [
            'siparis_id' => (int)$row['order_id'],
            'siparis_ad' => (string)$row['customer_name'],
            'siparis_tel' => (string)$row['customer_phone'],
            'siparis_il' => mb_substr($il, 0, 128),
            'siparis_ilce' => mb_substr((string)$ilce, 0, 128),
            'siparis_adres' => (string)$row['customer_address'],
            'siparis_tarih' => $row['order_date'],
            'siparis_fiyat' => $total,
            'siparis_urun' => $productsConcat,
            'siparis_fatura_vn' => (string)($row['invoice_vkn'] ?? ''),
            'siparis_fatura_vd' => (string)($row['invoice_tax_office'] ?? ''),
            'siparis_fatura_unvan' => (string)($row['invoice_company_name'] ?? ''),
            'siparis_fatura_adres' => (string)($row['invoice_address'] ?? ''),
            'siparis_parasut_invoice_id' => trim((string)($row['parasut_invoice_id'] ?? '')),
        ];
    }

    /** @return array{ok: bool, parasut_invoice_id?: string, error?: string, contact_id?: string} */
    public static function send(PDO $pdo, int $orderId, bool $force): array
    {
        $mapped = self::fetchPanelstyleRow($pdo, $orderId);
        if ($mapped === null) {
            return ['ok' => false, 'error' => 'Sipariş bulunamadı.'];
        }
        require_once __DIR__ . '/ParasutClient.php';
        $c = new ParasutClient($pdo);

        return $c->sendSiparis($mapped, $force);
    }
}
