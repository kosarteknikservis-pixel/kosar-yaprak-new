<?php
declare(strict_types=1);

/**
 * Sipariş listesi — filtre, sayfalama ve export için ortak sorgu katmanı.
 */
final class OrderListService
{
    /** @return list<string> */
    public static function statusNames(PDO $pdo): array
    {
        try {
            $rows = $pdo->query(
                'SELECT status_name FROM order_status ORDER BY order_status_id ASC'
            )->fetchAll(PDO::FETCH_COLUMN);

            return array_values(array_filter(array_map('strval', $rows)));
        } catch (Throwable $e) {
            return [
                'Beklemede', 'Arandı', 'Arandı 2', 'Onaylandı', 'Kargoya Verildi',
                'Teslim Edildi', 'Ulaşılamadı', 'Ulaşılamadı 2', 'Randevu', 'İade', 'İptal', 'Mükerrer',
            ];
        }
    }

    /** @param array<string, mixed> $input */
    public static function filtersFromInput(array $input): array
    {
        return [
            'status_name' => trim((string) ($input['status_name'] ?? '')),
            'start_date' => trim((string) ($input['start_date'] ?? '')),
            'end_date' => trim((string) ($input['end_date'] ?? '')),
            'customer_name' => trim((string) ($input['customer_name'] ?? '')),
            'customer_phone' => trim((string) ($input['customer_phone'] ?? '')),
            'invoice_q' => trim((string) ($input['invoice_q'] ?? '')),
            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array{sql:string,params:list<mixed>}
     */
    public static function buildWhere(array $filters): array
    {
        $parts = [];
        $params = [];

        if ($filters['status_name'] !== '') {
            $parts[] = 's.status_name = ?';
            $params[] = $filters['status_name'];
        }

        if ($filters['start_date'] !== '' && $filters['end_date'] !== '') {
            $parts[] = 'o.order_date >= ? AND o.order_date < ?';
            $params[] = date('Y-m-d', strtotime($filters['start_date'])) . ' 00:00:00';
            $params[] = date('Y-m-d', strtotime($filters['end_date'] . ' +1 day')) . ' 00:00:00';
        }

        if ($filters['customer_name'] !== '') {
            $parts[] = 'o.customer_name LIKE ?';
            $params[] = '%' . $filters['customer_name'] . '%';
        }

        if ($filters['customer_phone'] !== '') {
            $parts[] = 'o.customer_phone LIKE ?';
            $params[] = '%' . $filters['customer_phone'] . '%';
        }

        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $parts[] = '(o.customer_name LIKE ? OR o.customer_phone LIKE ? OR CAST(o.order_id AS CHAR) LIKE ? OR o.order_notes LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        if ($filters['invoice_q'] !== '') {
            $iq = '%' . $filters['invoice_q'] . '%';
            $parts[] = '(COALESCE(o.invoice_vkn,\'\') LIKE ?
                OR COALESCE(o.invoice_tax_office,\'\') LIKE ?
                OR COALESCE(o.invoice_company_name,\'\') LIKE ?
                OR COALESCE(o.invoice_address,\'\') LIKE ?
                OR COALESCE(o.parasut_invoice_id,\'\') LIKE ?)';
            array_push($params, $iq, $iq, $iq, $iq, $iq);
        }

        $sql = $parts !== [] ? 'WHERE ' . implode(' AND ', $parts) : '';

        return ['sql' => $sql, 'params' => $params];
    }

    public static function listFromSql(): string
    {
        return '
            FROM orders o
            LEFT JOIN cities c ON o.customer_city = c.city_id
            LEFT JOIN districts d ON o.customer_district = d.district_id
            LEFT JOIN order_status s ON o.order_status_id = s.order_status_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
        ';
    }

    /**
     * Mükerrer tel/IP — sadece ekrandaki satırlar için (9440 kayıtta full scan yapmaz).
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function attachDuplicateCounts(PDO $pdo, array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $phones = [];
        $ips = [];
        foreach ($rows as $row) {
            $ph = trim((string) ($row['customer_phone'] ?? ''));
            if ($ph !== '') {
                $phones[$ph] = true;
            }
            $ip = trim((string) ($row['customer_ip'] ?? ''));
            if ($ip !== '') {
                $ips[$ip] = true;
            }
        }

        $phoneCounts = [];
        $ipCounts = [];

        if ($phones !== []) {
            $phList = array_keys($phones);
            $placeholders = implode(',', array_fill(0, count($phList), '?'));
            $stmt = $pdo->prepare(
                "SELECT customer_phone, COUNT(*) AS cnt FROM orders
                 WHERE customer_phone IN ($placeholders)
                 GROUP BY customer_phone"
            );
            $stmt->execute($phList);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $phoneCounts[(string) $r['customer_phone']] = (int) $r['cnt'];
            }
        }

        if ($ips !== []) {
            $ipList = array_keys($ips);
            $placeholders = implode(',', array_fill(0, count($ipList), '?'));
            $stmt = $pdo->prepare(
                "SELECT customer_ip, COUNT(*) AS cnt FROM orders
                 WHERE customer_ip IN ($placeholders)
                 GROUP BY customer_ip"
            );
            $stmt->execute($ipList);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $ipCounts[(string) $r['customer_ip']] = (int) $r['cnt'];
            }
        }

        foreach ($rows as &$row) {
            $ph = trim((string) ($row['customer_phone'] ?? ''));
            $ip = trim((string) ($row['customer_ip'] ?? ''));
            $row['duplicate_phone_count'] = $ph !== '' ? ($phoneCounts[$ph] ?? 1) : 1;
            $row['duplicate_ip_count'] = $ip !== '' ? ($ipCounts[$ip] ?? 1) : 1;
        }
        unset($row);
    }

    public static function countFromSql(): string
    {
        return '
            FROM orders o
            LEFT JOIN order_status s ON o.order_status_id = s.order_status_id
        ';
    }

    /** @deprecated export vb. için; liste sorgusu listFromSql kullanır */
    public static function baseFromSql(): string
    {
        return self::listFromSql();
    }

    public static function listSelectSql(): string
    {
        return '
            SELECT o.order_id, o.customer_name, o.customer_phone, o.customer_ip, o.customer_address,
                   o.cc_last_call_note, o.cc_last_call_at,
                   c.city_name AS customer_city, d.district_name AS customer_district,
                   o.order_notes, o.customer_notes, o.order_date,
                   s.status_name, o.source, o.reklam, o.updated_by,
                   o.referrer,
                   GROUP_CONCAT(DISTINCT CONCAT(p.product_name, \' (\', oi.price, \' TL)\') SEPARATOR \', \') AS products,
                   (SELECT COALESCE(SUM(oi_q.quantity), 0) FROM order_items oi_q WHERE oi_q.order_id = o.order_id) AS item_count,
                   (SELECT COALESCE(SUM(oi_t.price), 0) FROM order_items oi_t WHERE oi_t.order_id = o.order_id) AS total_price,
                   pm.method_name AS payment_method_name
        ';
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public static function fetchList(PDO $pdo, array $filters, int $limit, int $offset): array
    {
        $where = self::buildWhere($filters);
        $limit = max(1, min($limit, 250));
        $offset = max(0, $offset);

        $sql = self::listSelectSql() . self::listFromSql() . ' '
            . $where['sql'] . '
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        self::attachDuplicateCounts($pdo, $rows);

        return $rows;
    }

    /** @param array<string, string> $filters */
    public static function count(PDO $pdo, array $filters): int
    {
        $where = self::buildWhere($filters);
        $sql = 'SELECT COUNT(DISTINCT o.order_id) ' . self::countFromSql() . ' ' . $where['sql'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);

        return (int) $stmt->fetchColumn();
    }

    public static function ccStats(PDO $pdo): array
    {
        $pendingToday = (int) $pdo->query(
            "SELECT COUNT(*) FROM orders o
             WHERE o.order_status_id = (SELECT order_status_id FROM order_status WHERE status_name = 'Beklemede' LIMIT 1)
             AND o.order_date >= CURDATE() AND o.order_date < CURDATE() + INTERVAL 1 DAY"
        )->fetchColumn();
        $pendingAll = (int) $pdo->query(
            "SELECT COUNT(*) FROM orders o
             WHERE o.order_status_id = (SELECT order_status_id FROM order_status WHERE status_name = 'Beklemede' LIMIT 1)"
        )->fetchColumn();
        $abandonedToday = 0;
        try {
            $abandonedToday = (int) $pdo->query(
                "SELECT COUNT(*) FROM yarim_kalanlar WHERE DATE(tarih) = CURDATE() AND tel IS NOT NULL AND TRIM(tel) != ''"
            )->fetchColumn();
        } catch (Throwable $e) {
        }

        return [
            'pending_today' => $pendingToday,
            'pending_all' => $pendingAll,
            'abandoned_today' => $abandonedToday,
        ];
    }
}
