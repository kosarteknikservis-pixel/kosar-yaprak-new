<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_rbac.php';

/**
 * Çağrı merkezi üst şerit sayıları (sipariş / yarım kalan / bayi / destek).
 *
 * @return array{orders:int,abandoned:int,dealers:int,support:int}
 */
function admin_quick_nav_counts(PDO $pdo): array
{
    $out = ['orders' => 0, 'abandoned' => 0, 'dealers' => 0, 'support' => 0];

    if (admin_user_can('menu_siparis')) {
        try {
            $out['orders'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM orders o
                 JOIN order_status s ON o.order_status_id = s.order_status_id
                 WHERE s.status_name = 'Beklemede'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $out['orders'] = 0;
        }

        try {
            $out['abandoned'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM yarim_kalanlar
                 WHERE tel IS NOT NULL AND TRIM(tel) != ''
                 AND IFNULL(is_converted, 0) = 0"
            )->fetchColumn();
        } catch (Throwable $e) {
            $out['abandoned'] = 0;
        }
    }

    if (admin_user_can('menu_destek')) {
        try {
            $out['dealers'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM dealer_requests
                 WHERE IFNULL(status, '') != 'approved'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $out['dealers'] = 0;
        }

        try {
            $out['support'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM support_requests
                 WHERE status IS NULL OR status = '' OR status = 'Beklemede' OR status = 'Yeni'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $out['support'] = 0;
        }
    }

    return $out;
}

/**
 * @return list<array{key:string,label:string,href:string,icon:string,count:int,files:list<string>}>
 */
function admin_quick_nav_items(PDO $pdo): array
{
    $counts = admin_quick_nav_counts($pdo);
    $items = [];

    if (admin_user_can('menu_siparis')) {
        $items[] = [
            'key' => 'orders',
            'label' => 'Siparişler',
            'href' => 'orders.php',
            'icon' => 'fa-shopping-cart',
            'count' => $counts['orders'],
            'files' => ['orders.php', 'order_manage.php'],
        ];
        $items[] = [
            'key' => 'abandoned',
            'label' => 'Yarım kalanlar',
            'href' => 'abandoned_orders.php',
            'icon' => 'fa-hourglass-half',
            'count' => $counts['abandoned'],
            'files' => ['abandoned_orders.php'],
        ];
    }

    if (admin_user_can('menu_destek')) {
        $items[] = [
            'key' => 'dealers',
            'label' => 'Bayiler',
            'href' => 'bayilik-basvuru.php',
            'icon' => 'fa-store',
            'count' => $counts['dealers'],
            'files' => ['bayilik-basvuru.php'],
        ];
        $items[] = [
            'key' => 'support',
            'label' => 'Destek talepleri',
            'href' => 'admin_support.php',
            'icon' => 'fa-headset',
            'count' => $counts['support'],
            'files' => ['admin_support.php'],
        ];
    }

    return $items;
}
