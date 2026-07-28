<?php
ob_start();
require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/generic_xlsx.php';
require_once __DIR__ . '/../includes/orders/order_list_service.php';

$page_title = 'Excel Export';

$qf = $_GET;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['download_excel']) || isset($_POST['download_phones']))) {
    foreach (['status_name', 'start_date', 'end_date', 'customer_name', 'customer_phone', 'invoice_q', 'q', 'limit', 'page'] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') {
            $qf[$k] = is_scalar($_POST[$k]) ? (string)$_POST[$k] : '';
        }
    }
}

$filterBag = OrderListService::filtersFromInput($qf);
$whereBuilt = OrderListService::buildWhere($filterBag);
$params = $whereBuilt['params'];
$filter_sql_where = $whereBuilt['sql'];

// Sayfalama
$page = max(1, isset($qf['page']) ? (int)$qf['page'] : 1);
$limit = isset($qf['limit']) ? (int)$qf['limit'] : 100;
$limit = min($limit, 1000); // Maksimum 1000 kayıt
$offset = ($page - 1) * $limit;

/** orders.php ile aynı yapı — ürün / varyant BOŞ kalması (JOIN sırası, GROUP BY) önlenir */
$base_from = '
    FROM orders o
    LEFT JOIN cities c ON o.customer_city = c.city_id
    LEFT JOIN districts d ON o.customer_district = d.district_id
    LEFT JOIN order_status s ON o.order_status_id = s.order_status_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN order_variation_details ovd ON o.order_id = ovd.order_id
    LEFT JOIN product_variation_types vt ON ovd.type_id = vt.type_id
    LEFT JOIN product_variation_options vo ON ovd.option_id = vo.option_id
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
';

// Toplam kayıt sayısını al
$count_stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT o.order_id) AS n ' . $base_from . $filter_sql_where
);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();

$select_aggregate = '
    SELECT o.order_id, o.customer_name, o.customer_phone, o.customer_address,
           c.city_name AS customer_city,
           d.district_name AS customer_district,
           o.order_notes, o.customer_notes, o.order_date,
           o.source, o.reklam, o.referrer,
           o.invoice_vkn, o.invoice_tax_office, o.invoice_company_name, o.invoice_address,
           o.parasut_invoice_id,
           s.status_name,
           GROUP_CONCAT(DISTINCT CONCAT(COALESCE(p.product_name, CONCAT("Ürün #", oi.product_id)), " (", oi.price, " TL)") SEPARATOR ", ") AS products,
           GROUP_CONCAT(CONCAT(vt.type_name, ": ", vo.option_name,
                     CASE
                         WHEN vo.option_value IS NOT NULL AND vo.option_value != ""
                         THEN CONCAT(" (", vo.option_value, ")")
                         ELSE ""
                     END) SEPARATOR ", ") AS variant_details,
           pm.method_name AS payment_method,
           COUNT(*) OVER(PARTITION BY o.customer_phone) AS duplicate_phone_count,
           COUNT(*) OVER(PARTITION BY o.customer_ip) AS duplicate_ip_count';

$sql = $select_aggregate . $base_from . $filter_sql_where
    . ' GROUP BY o.order_id
          ORDER BY o.order_date DESC
          LIMIT ? OFFSET ?';

$page_params = $params;
$page_params[] = $limit;
$page_params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($page_params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Ana liste / telefon: filtre ile tüm kayıtlar (sayfa değil), üst limite kadar.
 *
 * @return list<array<string, mixed>>
 */
$fetch_orders_for_download = function (PDO $pdo, string $baseFrom, string $whereClause, array $baseParams, int $downloadLimit) use ($select_aggregate): array {
    $stmt = $pdo->prepare(
        $select_aggregate . $baseFrom . $whereClause . ' GROUP BY o.order_id ORDER BY o.order_date DESC LIMIT ?'
    );
    $stmt->execute(array_merge($baseParams, [$downloadLimit]));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

// Ana sipariş listesi: Excel (.xlsx) indirme
if (isset($_POST['download_excel'])) {
    ob_end_clean();
    $filenameBase = 'siparisler';

    $dlimit = isset($qf['limit']) ? (int)$qf['limit'] : 1000;
    $dlimit = max(1, min(1000, $dlimit));
    $excel_orders = $fetch_orders_for_download($pdo, $base_from, $filter_sql_where, $params, $dlimit);

    $headers = [
        'Sipariş ID', 'Müşteri Adı', 'Telefon', 'Adres', 'Şehir', 'İlçe',
        'Sipariş Notları', 'Müşteri Notları', 'Sipariş Tarihi', 'Durum',
        'Kaynak', 'Reklam', 'Referrer',
        'VKN/TCKN (fatura)',
        'Vergi dairesi',
        'Firma ünvanı',
        'Fatura adresi',
        'Paraşüt fatura ID',
        'Ürünler', 'Varyantlar', 'Ödeme Yöntemi', 'Duplicate Telefon', 'Duplicate IP',
    ];

    $matrix = [];
    foreach ($excel_orders as $order) {
        $order_notes_raw = isset($order['order_notes']) ? $order['order_notes'] : '';
        if (stripos((string)$order_notes_raw, 'Ek Notlar:') !== false) {
            $parts = preg_split('/Ek Notlar:\s*/i', (string)$order_notes_raw);
            $clean_order_notes = trim((string)end($parts));
        } else {
            $clean_order_notes = trim(preg_replace('/Seçilen Varyantlar:.*/is', '', (string)$order_notes_raw));
        }

        $matrix[] = [
            $order['order_id'],
            $order['customer_name'],
            $order['customer_phone'],
            $order['customer_address'],
            $order['customer_city'],
            $order['customer_district'],
            $clean_order_notes,
            $order['customer_notes'],
            $order['order_date'],
            $order['status_name'],
            $order['source'] ?? '',
            $order['reklam'] ?? '',
            $order['referrer'] ?? '',
            $order['invoice_vkn'] ?? '',
            $order['invoice_tax_office'] ?? '',
            $order['invoice_company_name'] ?? '',
            $order['invoice_address'] ?? '',
            $order['parasut_invoice_id'] ?? '',
            $order['products'],
            $order['variant_details'],
            $order['payment_method'],
            $order['duplicate_phone_count'],
            $order['duplicate_ip_count'],
        ];
    }

    cargo_emit_table_xlsx($headers, $matrix, 'Siparişler', $filenameBase);
}

// Telefon numaraları indirme
function cleanPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }
    return $phone;
}

if (isset($_POST['download_phones'])) {
    ob_end_clean();
    $filename = "telefon_numaralari_" . date('Y-m-d_H-i-s') . ".csv";

    $dlimit = isset($qf['limit']) ? (int)$qf['limit'] : 1000;
    $dlimit = max(1, min(1000, $dlimit));
    $phone_rows = $fetch_orders_for_download($pdo, $base_from, $filter_sql_where, $params, $dlimit);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Telefon Numaraları'], ';');

    foreach ($phone_rows as $order) {
        $cleanedPhone = cleanPhoneNumber($order['customer_phone']);
        fputcsv($output, ["\t" . $cleanedPhone], ';');
    }
    
    fclose($output);
    exit;
}

$total_pages = ceil($total_orders / $limit);
?>

<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <!-- Üst Bar -->
    <div class="top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="bar-left">
                <h2 class="mb-0"><i class="fas fa-file-excel me-2 text-success"></i>Excel Export</h2>
                <p class="text-muted mb-0">Siparişleri Excel (.xlsx) olarak indirin</p>
            </div>
            <div class="bar-right">
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= $total_orders ?> Sipariş</span>
                        <div class="btn-group">
                        <form method="POST" class="d-inline"><?php foreach (['status_name', 'start_date', 'end_date', 'customer_name', 'customer_phone', 'invoice_q', 'limit', 'page'] as $hid): ?>
<?php if (isset($qf[$hid]) && (string)$qf[$hid] !== ''): ?>
                            <input type="hidden" name="<?= htmlspecialchars((string)$hid, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$qf[$hid], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php endforeach; ?>
                            <button type="submit" name="download_excel" class="btn btn-success btn-sm">
                                <i class="fas fa-file-excel"></i> Excel İndir
                            </button>
    </form>
                        <form method="POST" class="d-inline ms-2"><?php foreach (['status_name', 'start_date', 'end_date', 'customer_name', 'customer_phone', 'invoice_q', 'limit', 'page'] as $hid): ?>
<?php if (isset($qf[$hid]) && (string)$qf[$hid] !== ''): ?>
                            <input type="hidden" name="<?= htmlspecialchars((string)$hid, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$qf[$hid], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php endforeach; ?>
                            <button type="submit" name="download_phones" class="btn btn-info btn-sm">
                                <i class="fas fa-phone"></i> Telefon Listesi
                            </button>
</form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtreler -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter"></i> Filtreler
        </div>
        <div class="card-body">
    <form method="GET" action="">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="customer_name" class="form-label">Müşteri Adı</label>
                            <input type="text" id="customer_name" name="customer_name" class="form-control"
                                   value="<?= isset($_GET['customer_name']) ? htmlspecialchars($_GET['customer_name']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="customer_phone" class="form-label">Telefon</label>
                            <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                                   value="<?= isset($_GET['customer_phone']) ? htmlspecialchars($_GET['customer_phone']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="status_name" class="form-label">Durum</label>
                            <select id="status_name" name="status_name" class="form-select">
                                <option value="">Tüm Durumlar</option>
                                <option value="Beklemede" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Beklemede' ? 'selected' : '' ?>>Beklemede</option>
                                <option value="Arandı" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Arandı' ? 'selected' : '' ?>>Arandı</option>
                                <option value="Arandı 2" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Arandı 2' ? 'selected' : '' ?>>Arandı 2</option>
                                <option value="Onaylandı" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Onaylandı' ? 'selected' : '' ?>>Onaylandı</option>
                                <option value="Kargoya Verildi" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Kargoya Verildi' ? 'selected' : '' ?>>Kargoya Verildi</option>
                                <option value="Teslim Edildi" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Teslim Edildi' ? 'selected' : '' ?>>Teslim Edildi</option>
                                <option value="Ulaşılamadı" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Ulaşılamadı' ? 'selected' : '' ?>>Ulaşılamadı</option>
                                <option value="Ulaşılamadı 2" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Ulaşılamadı 2' ? 'selected' : '' ?>>Ulaşılamadı 2</option>
                                <option value="Randevu" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Randevu' ? 'selected' : '' ?>>Randevu</option>
                                <option value="İade" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'İade' ? 'selected' : '' ?>>İade</option>
                                <option value="İptal" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'İptal' ? 'selected' : '' ?>>İptal</option>
                                <option value="Mükerrer" <?= isset($_GET['status_name']) && $_GET['status_name'] == 'Mükerrer' ? 'selected' : '' ?>>Mükerrer</option>
        </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="limit" class="form-label">Kayıt Limiti</label>
                            <select name="limit" id="limit" class="form-select">
                                <option value="50" <?= isset($_GET['limit']) && $_GET['limit'] == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= isset($_GET['limit']) && $_GET['limit'] == 100 ? 'selected' : '' ?>>100</option>
                                <option value="250" <?= isset($_GET['limit']) && $_GET['limit'] == 250 ? 'selected' : '' ?>>250</option>
                                <option value="500" <?= isset($_GET['limit']) && $_GET['limit'] == 500 ? 'selected' : '' ?>>500</option>
                                <option value="1000" <?= isset($_GET['limit']) && $_GET['limit'] == 1000 ? 'selected' : '' ?>>1000</option>
        </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="end_date" class="form-label">Bitiş Tarihi</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label for="invoice_q" class="form-label">Kurumsal fatura / Paraşüt (içeren)</label>
                            <input type="text" id="invoice_q" name="invoice_q" class="form-control"
                                   value="<?= isset($_GET['invoice_q']) ? htmlspecialchars((string)$_GET['invoice_q']) : '' ?>"
                                   placeholder="VKN, vergi dairesi, firma ünvanı, fatura adresi veya Paraşüt ID">
                            <small class="text-muted">Excel çıktısındaki kurumsal sütunlarda içerir.</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrele
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Temizle
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sonuçlar -->
    <?php if ($total_orders > 0): ?>
        <div class="alert alert-info d-flex align-items-center">
            <i class="fas fa-info-circle me-2"></i>
            <strong><?= $total_orders ?></strong> sipariş bulundu
        </div>
    <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Burada hiç sipariş yok
        </div>
    <?php endif; ?>

    <!-- Önizleme Tablosu -->
    <?php if (!empty($orders)): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> Önizleme (bu sayfadaki <?= count($orders) ?> / toplam <?= (int)$total_orders ?> sipariş)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-csv">
                        <thead>
                            <tr>
                                <th style="width: 8%;">Sipariş No.</th>
                                <th style="width: 20%;">Müşteri</th>
                                <th style="width: 12%;">Telefon</th>
                                <th style="width: 10%;">Durum</th>
                                <th style="width: 10%;">Reklam</th>
                                <th style="width: 20%;">Ürünler</th>
                                <th style="width: 14%;">Varyantlar</th>
                                <th style="width: 10%;">Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
    <?php
                                $row_class = '';
                                if ($order['duplicate_phone_count'] > 1 || $order['duplicate_ip_count'] > 1) {
                                    $row_class = 'duplicate';
                                }
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td><strong><?= htmlspecialchars($order['order_id']) ?></strong></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars((string) ($order['customer_name'] ?? '')) ?></div>
                                        <div class="badge bg-light text-dark border">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars((string) ($order['customer_city'] ?? '')) ?> / <?= htmlspecialchars((string) ($order['customer_district'] ?? '')) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="tel:<?= htmlspecialchars((string) ($order['customer_phone'] ?? '')) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars((string) ($order['customer_phone'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = strtolower(str_replace([' ', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['-', 'i', 'g', 'u', 's', 'o', 'c'], $order['status_name']));
                                        ?>
                                        <span class="status-badge <?= htmlspecialchars($status_class) ?>"><?= htmlspecialchars((string)$order['status_name']) ?></span>
                                    </td>
                                    <td>
                                        <?php $rk = trim((string)($order['reklam'] ?? '')); ?>
                                        <span class="small fw-semibold"><?= $rk !== '' ? htmlspecialchars($rk) : '—' ?></span>
                                        <?php $sr = trim((string)($order['source'] ?? '')); ?>
                                        <?php if ($sr !== ''): ?>
                                            <div class="text-muted"><small><?= htmlspecialchars($sr) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $plist = trim((string)($order['products'] ?? ''));
                                        ?>
                                        <?php if ($plist !== ''): ?>
                                            <div class="small text-break"><?= htmlspecialchars($plist) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $vlist = trim((string)($order['variant_details'] ?? ''));
                                        ?>
                                        <?php if ($vlist !== ''): ?>
                                            <div class="small text-break text-success"><?= htmlspecialchars($vlist) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">Varyant yok</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d.m.Y H:i', strtotime($order['order_date'])) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
        <div class="container">
            <div class="card">
                <div class="card-body py-2">
                    <?php
                    require_once __DIR__ . '/../includes/admin_pagination.php';
                    admin_render_pagination($page, (int) $total_pages, static function (int $p) use ($qf): string {
                        return '?' . http_build_query(array_merge($qf, ['page' => $p]));
                    });
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
