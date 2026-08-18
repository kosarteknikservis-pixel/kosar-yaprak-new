<?php
declare(strict_types=1);

/**
 * Dashboard istatistiklerini az sayıda sorguda toplar (index.php yükünü düşürür).
 *
 * @return array<string,mixed>
 */
function admin_dashboard_load_stats(PDO $pdo): array
{
    $orderRow = $pdo->query(
        "SELECT
            COUNT(CASE WHEN order_status_id != 19 THEN 1 END) AS total_orders,
            COUNT(CASE WHEN order_status_id = 16 THEN 1 END) AS approved_orders,
            COUNT(CASE WHEN order_status_id = 3 THEN 1 END) AS delivered_orders,
            COUNT(CASE WHEN order_status_id = 2 THEN 1 END) AS shipped_orders,
            COUNT(*) AS total_orders_all,
            COUNT(CASE WHEN order_status_id != 19 AND HOUR(order_date) = HOUR(NOW()) AND DATE(order_date) = CURDATE() THEN 1 END) AS hourly_orders,
            COUNT(CASE WHEN order_status_id != 19 AND DATE(order_date) = CURDATE() THEN 1 END) AS daily_orders,
            COUNT(CASE WHEN order_status_id != 19 AND YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS weekly_orders,
            COUNT(CASE WHEN order_status_id != 19 AND YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE()) THEN 1 END) AS monthly_orders,
            COUNT(CASE WHEN order_status_id != 19 AND DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) AS yesterday_orders,
            COUNT(CASE WHEN DATE(order_date) = CURDATE() THEN 1 END) AS daily_orders_conv,
            COUNT(CASE WHEN DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) AS yesterday_orders_conv,
            COUNT(CASE WHEN YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS weekly_orders_conv,
            COUNT(CASE WHEN YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE()) THEN 1 END) AS monthly_orders_conv
         FROM orders"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $revRow = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN o.order_status_id = 16 THEN oi.price END), 0) AS approved_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id = 3 THEN oi.price END), 0) AS delivered_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id != 19 THEN oi.price END), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id != 19 AND HOUR(o.order_date) = HOUR(NOW()) AND DATE(o.order_date) = CURDATE() THEN oi.price END), 0) AS hourly_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id != 19 AND DATE(o.order_date) = CURDATE() THEN oi.price END), 0) AS daily_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id != 19 AND YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1) THEN oi.price END), 0) AS weekly_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id != 19 AND YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE()) THEN oi.price END), 0) AS monthly_revenue,
            COALESCE(SUM(CASE WHEN o.order_status_id != 19 AND DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN oi.price END), 0) AS yesterday_revenue
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.order_id"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $pvRow = $pdo->query(
        "SELECT
            COUNT(*) AS total_visits,
            COUNT(DISTINCT ip_address) AS total_distinct_visits,
            COUNT(DISTINCT CASE WHEN visit_time >= NOW() - INTERVAL 5 MINUTE THEN ip_address END) AS live_distinct_visits,
            COUNT(CASE WHEN visit_time >= NOW() - INTERVAL 5 MINUTE THEN 1 END) AS live_visits,
            COUNT(DISTINCT CASE WHEN HOUR(visit_time) = HOUR(NOW()) AND DATE(visit_time) = CURDATE() THEN ip_address END) AS hourly_distinct_visits,
            COUNT(CASE WHEN HOUR(visit_time) = HOUR(NOW()) AND DATE(visit_time) = CURDATE() THEN 1 END) AS hourly_visits,
            COUNT(CASE WHEN DATE(visit_time) = CURDATE() THEN 1 END) AS daily_visits,
            COUNT(DISTINCT CASE WHEN DATE(visit_time) = CURDATE() THEN ip_address END) AS daily_distinct_visits,
            COUNT(CASE WHEN DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) AS yesterday_visits,
            COUNT(DISTINCT CASE WHEN DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN ip_address END) AS yesterday_distinct_visits,
            COUNT(CASE WHEN YEARWEEK(visit_time, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS weekly_visits,
            COUNT(DISTINCT CASE WHEN YEARWEEK(visit_time, 1) = YEARWEEK(CURDATE(), 1) THEN ip_address END) AS weekly_distinct_visits,
            COUNT(CASE WHEN YEAR(visit_time) = YEAR(CURDATE()) AND MONTH(visit_time) = MONTH(CURDATE()) THEN 1 END) AS monthly_visits,
            COUNT(DISTINCT CASE WHEN YEAR(visit_time) = YEAR(CURDATE()) AND MONTH(visit_time) = MONTH(CURDATE()) THEN ip_address END) AS monthly_distinct_visits
         FROM page_views"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $pages = ['index.php', 'order.php', 'thankyou.php'];
    $ph = implode(',', array_fill(0, count($pages), '?'));
    $paStmt = $pdo->prepare(
        "SELECT page_name,
            COUNT(DISTINCT CASE WHEN visit_time >= NOW() - INTERVAL 5 MINUTE THEN ip_address END) AS rt_u,
            COUNT(CASE WHEN visit_time >= NOW() - INTERVAL 5 MINUTE THEN 1 END) AS rt_t,
            COUNT(DISTINCT CASE WHEN HOUR(visit_time) = HOUR(NOW()) AND DATE(visit_time) = CURDATE() THEN ip_address END) AS hr_u,
            COUNT(CASE WHEN HOUR(visit_time) = HOUR(NOW()) AND DATE(visit_time) = CURDATE() THEN 1 END) AS hr_t,
            COUNT(DISTINCT CASE WHEN DATE(visit_time) = CURDATE() THEN ip_address END) AS dy_u,
            COUNT(CASE WHEN DATE(visit_time) = CURDATE() THEN 1 END) AS dy_t,
            COUNT(DISTINCT CASE WHEN YEARWEEK(visit_time, 1) = YEARWEEK(CURDATE(), 1) THEN ip_address END) AS wk_u,
            COUNT(CASE WHEN YEARWEEK(visit_time, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS wk_t,
            COUNT(DISTINCT CASE WHEN YEAR(visit_time) = YEAR(CURDATE()) AND MONTH(visit_time) = MONTH(CURDATE()) THEN ip_address END) AS mo_u,
            COUNT(CASE WHEN YEAR(visit_time) = YEAR(CURDATE()) AND MONTH(visit_time) = MONTH(CURDATE()) THEN 1 END) AS mo_t,
            COUNT(DISTINCT ip_address) AS tot_u,
            COUNT(*) AS tot_t
         FROM page_views
         WHERE page_name IN ($ph)
         GROUP BY page_name"
    );
    $paStmt->execute($pages);
    $pageAnalyticsRaw = [];
    while ($row = $paStmt->fetch(PDO::FETCH_ASSOC)) {
        $pageAnalyticsRaw[(string) $row['page_name']] = $row;
    }

    $page_analytics = [];
    foreach ($pages as $page) {
        $r = $pageAnalyticsRaw[$page] ?? [];
        $page_analytics[$page] = [
            // Tekil (benzersiz IP)
            'real_time_visitors' => (int) ($r['rt_u'] ?? 0),
            'hourly_visitors' => (int) ($r['hr_u'] ?? 0),
            'daily_visitors' => (int) ($r['dy_u'] ?? 0),
            'weekly_visitors' => (int) ($r['wk_u'] ?? 0),
            'monthly_visitors' => (int) ($r['mo_u'] ?? 0),
            'total_visitors' => (int) ($r['tot_u'] ?? 0),
            // Toplam (tüm gösterim / hit)
            'real_time_hits' => (int) ($r['rt_t'] ?? 0),
            'hourly_hits' => (int) ($r['hr_t'] ?? 0),
            'daily_hits' => (int) ($r['dy_t'] ?? 0),
            'weekly_hits' => (int) ($r['wk_t'] ?? 0),
            'monthly_hits' => (int) ($r['mo_t'] ?? 0),
            'total_hits' => (int) ($r['tot_t'] ?? 0),
        ];
    }

    $total_orders = (int) ($orderRow['total_orders'] ?? 0);
    $approved_orders = (int) ($orderRow['approved_orders'] ?? 0);
    $delivered_orders = (int) ($orderRow['delivered_orders'] ?? 0);
    $shipped_orders = (int) ($orderRow['shipped_orders'] ?? 0);

    $approval_rate = $total_orders > 0 ? ($approved_orders / $total_orders) * 100 : 0.0;
    $delivery_rate = $total_orders > 0 ? ($delivered_orders / $total_orders) * 100 : 0.0;
    $shipping_rate = $total_orders > 0 ? ($shipped_orders / $total_orders) * 100 : 0.0;

    $hourlySeries = array_fill(0, 24, ['unique' => 0, 'total' => 0]);
    try {
        $hs = $pdo->query(
            "SELECT HOUR(visit_time) AS h,
                    COUNT(DISTINCT ip_address) AS u,
                    COUNT(*) AS t
             FROM page_views
             WHERE DATE(visit_time) = CURDATE()
             GROUP BY HOUR(visit_time)"
        );
        if ($hs) {
            foreach ($hs as $row) {
                $h = (int) ($row['h'] ?? -1);
                if ($h >= 0 && $h <= 23) {
                    $hourlySeries[$h] = [
                        'unique' => (int) ($row['u'] ?? 0),
                        'total' => (int) ($row['t'] ?? 0),
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        /* boş seri */
    }

    $daily_visitors_result = ['daily_visits' => (int) ($pvRow['daily_visits'] ?? 0)];
    $yesterday_visitors_result = ['yesterday_visits' => (int) ($pvRow['yesterday_visits'] ?? 0)];
    $weekly_visitors_result = ['weekly_visits' => (int) ($pvRow['weekly_visits'] ?? 0)];
    $monthly_visitors_result = ['monthly_visits' => (int) ($pvRow['monthly_visits'] ?? 0)];
    $total_visitors_result = ['total_visits' => (int) ($pvRow['total_visits'] ?? 0)];

    $daily_orders_result = ['daily_orders' => (int) ($orderRow['daily_orders_conv'] ?? 0)];
    $yesterday_orders_result = ['yesterday_orders' => (int) ($orderRow['yesterday_orders_conv'] ?? 0)];
    $weekly_orders_result = ['weekly_orders' => (int) ($orderRow['weekly_orders_conv'] ?? 0)];
    $monthly_orders_result = ['monthly_orders' => (int) ($orderRow['monthly_orders_conv'] ?? 0)];
    $total_orders_result = ['total_orders' => (int) ($orderRow['total_orders_all'] ?? 0)];

    $rate = static function (int $visits, int $orders): float {
        return $visits > 0 ? ($orders / $visits) * 100 : 0.0;
    };

    $daily_distinct_visitors_result = ['daily_distinct_visits' => (int) ($pvRow['daily_distinct_visits'] ?? 0)];
    $yesterday_distinct_visitors_result = ['yesterday_distinct_visits' => (int) ($pvRow['yesterday_distinct_visits'] ?? 0)];
    $weekly_distinct_visitors_result = ['weekly_distinct_visits' => (int) ($pvRow['weekly_distinct_visits'] ?? 0)];
    $monthly_distinct_visitors_result = ['monthly_distinct_visits' => (int) ($pvRow['monthly_distinct_visits'] ?? 0)];
    $total_distinct_visitors_result = ['total_distinct_visits' => (int) ($pvRow['total_distinct_visits'] ?? 0)];

    $daily_distinct_orders_result = ['daily_distinct_orders' => (int) ($orderRow['daily_orders_conv'] ?? 0)];
    $yesterday_distinct_orders_result = ['yesterday_distinct_orders' => (int) ($orderRow['yesterday_orders_conv'] ?? 0)];
    $weekly_distinct_orders_result = ['weekly_distinct_orders' => (int) ($orderRow['weekly_orders_conv'] ?? 0)];
    $monthly_distinct_orders_result = ['monthly_distinct_orders' => (int) ($orderRow['monthly_orders_conv'] ?? 0)];
    $total_distinct_orders_result = ['total_distinct_orders' => (int) ($orderRow['total_orders_all'] ?? 0)];

    return [
        'total_orders' => $total_orders,
        'approved_orders' => $approved_orders,
        'approval_rate' => $approval_rate,
        'delivered_orders' => $delivered_orders,
        'delivery_rate' => $delivery_rate,
        'shipped_orders' => $shipped_orders,
        'shipping_rate' => $shipping_rate,
        'approved_revenue' => (float) ($revRow['approved_revenue'] ?? 0),
        'delivered_revenue' => (float) ($revRow['delivered_revenue'] ?? 0),
        'total_revenue' => (float) ($revRow['total_revenue'] ?? 0),
        'hourly_revenue' => (float) ($revRow['hourly_revenue'] ?? 0),
        'daily_revenue' => (float) ($revRow['daily_revenue'] ?? 0),
        'weekly_revenue' => (float) ($revRow['weekly_revenue'] ?? 0),
        'monthly_revenue' => (float) ($revRow['monthly_revenue'] ?? 0),
        'yesterday_revenue' => (float) ($revRow['yesterday_revenue'] ?? 0),
        'hourly_orders' => (int) ($orderRow['hourly_orders'] ?? 0),
        'daily_orders' => (int) ($orderRow['daily_orders'] ?? 0),
        'weekly_orders' => (int) ($orderRow['weekly_orders'] ?? 0),
        'monthly_orders' => (int) ($orderRow['monthly_orders'] ?? 0),
        'yesterday_orders' => (int) ($orderRow['yesterday_orders'] ?? 0),
        'daily_visitors_result' => $daily_visitors_result,
        'daily_orders_result' => $daily_orders_result,
        'daily_orders_rate' => $rate((int) $daily_visitors_result['daily_visits'], (int) $daily_orders_result['daily_orders']),
        'yesterday_visitors_result' => $yesterday_visitors_result,
        'yesterday_orders_result' => $yesterday_orders_result,
        'yesterday_orders_rate' => $rate((int) $yesterday_visitors_result['yesterday_visits'], (int) $yesterday_orders_result['yesterday_orders']),
        'weekly_visitors_result' => $weekly_visitors_result,
        'weekly_orders_result' => $weekly_orders_result,
        'weekly_orders_rate' => $rate((int) $weekly_visitors_result['weekly_visits'], (int) $weekly_orders_result['weekly_orders']),
        'monthly_visitors_result' => $monthly_visitors_result,
        'monthly_orders_result' => $monthly_orders_result,
        'monthly_orders_rate' => $rate((int) $monthly_visitors_result['monthly_visits'], (int) $monthly_orders_result['monthly_orders']),
        'total_visitors_result' => $total_visitors_result,
        'total_orders_result' => $total_orders_result,
        'total_orders_rate' => $rate((int) $total_visitors_result['total_visits'], (int) $total_orders_result['total_orders']),
        'daily_distinct_visitors_result' => $daily_distinct_visitors_result,
        'daily_distinct_orders_result' => $daily_distinct_orders_result,
        'daily_distinct_orders_rate' => $rate((int) $daily_distinct_visitors_result['daily_distinct_visits'], (int) $daily_distinct_orders_result['daily_distinct_orders']),
        'yesterday_distinct_visitors_result' => $yesterday_distinct_visitors_result,
        'yesterday_distinct_orders_result' => $yesterday_distinct_orders_result,
        'yesterday_distinct_orders_rate' => $rate((int) $yesterday_distinct_visitors_result['yesterday_distinct_visits'], (int) $yesterday_distinct_orders_result['yesterday_distinct_orders']),
        'weekly_distinct_visitors_result' => $weekly_distinct_visitors_result,
        'weekly_distinct_orders_result' => $weekly_distinct_orders_result,
        'weekly_distinct_orders_rate' => $rate((int) $weekly_distinct_visitors_result['weekly_distinct_visits'], (int) $weekly_distinct_orders_result['weekly_distinct_orders']),
        'monthly_distinct_visitors_result' => $monthly_distinct_visitors_result,
        'monthly_distinct_orders_result' => $monthly_distinct_orders_result,
        'monthly_distinct_orders_rate' => $rate((int) $monthly_distinct_visitors_result['monthly_distinct_visits'], (int) $monthly_distinct_orders_result['monthly_distinct_orders']),
        'total_distinct_visitors_result' => $total_distinct_visitors_result,
        'total_distinct_orders_result' => $total_distinct_orders_result,
        'total_distinct_orders_rate' => $rate((int) $total_distinct_visitors_result['total_distinct_visits'], (int) $total_distinct_orders_result['total_distinct_orders']),
        'live_distinct_visitors' => (int) ($pvRow['live_distinct_visits'] ?? 0),
        'live_visitors' => (int) ($pvRow['live_visits'] ?? 0),
        'hourly_distinct_visitors' => (int) ($pvRow['hourly_distinct_visits'] ?? 0),
        'hourly_visitors_hits' => (int) ($pvRow['hourly_visits'] ?? 0),
        'hourly_visitor_series' => $hourlySeries,
        'page_analytics' => $page_analytics,
    ];
}
