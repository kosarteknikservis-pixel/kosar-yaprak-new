<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/aras_kargo_excel.php';

$page_title = 'Kargo CSV / Aras Excel';

/** ../panel/xnull/controller/aras_kargo_export.php ile aynı: urun sütunu için site kimliği */
function cargo_aras_site_urun_label(PDO $pdo): string
{
    try {
        $raw = trim((string)$pdo->query('SELECT site_url FROM settings WHERE id = 1 LIMIT 1')->fetchColumn());
        if ($raw !== '') {
            if (preg_match('#^https?://([^/]+)#i', $raw, $m)) {
                return $m[1];
            }

            return $raw;
        }
    } catch (Throwable $e) {
        /** @ignore */
    }

    return isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'site';
}

$filters = [];
$params = [];

$statusGet = isset($_GET['status']) ? (string)$_GET['status'] : '';
if ($statusGet !== '') {
    $filters[] = 'o.order_status_id = ?';
    $params[] = (int)$statusGet;
}

$from = isset($_GET['from']) && $_GET['from'] !== '' ? date('Y-m-d', strtotime((string)$_GET['from'])) : date('Y-m-d', strtotime('-30 days'));
$to = isset($_GET['to']) && $_GET['to'] !== '' ? date('Y-m-d', strtotime((string)$_GET['to'])) : date('Y-m-d');
$filters[] = 'DATE(o.order_date) BETWEEN ? AND ?';
$params[] = $from;
$params[] = $to;

$whereSql = 'WHERE ' . implode(' AND ', $filters);

$baseSql = "
    SELECT
      o.order_id,
      o.order_date,
      s.status_name AS status_name,
      o.customer_name,
      o.customer_address,
      COALESCE(d.district_name, '') AS ilce_text,
      COALESCE(c.city_name, '') AS sehir_text,
      o.customer_phone,
      ROUND((
          SELECT COALESCE(SUM(oi2.price), 0)
          FROM order_items oi2
          WHERE oi2.order_id = o.order_id
      )) AS tutar_int
    FROM orders o
    INNER JOIN order_status s ON o.order_status_id = s.order_status_id
    LEFT JOIN cities c ON o.customer_city = c.city_id
    LEFT JOIN districts d ON o.customer_district = d.district_id
    $whereSql
    ORDER BY o.order_date DESC, o.order_id DESC
";

$sqlCount = '
    SELECT COUNT(DISTINCT o.order_id)
    FROM orders o
    INNER JOIN order_status s ON o.order_status_id = s.order_status_id
    ' . "\n        " . $whereSql;

$siteUrun = cargo_aras_site_urun_label($pdo);

if (isset($_GET['download']) && $_GET['download'] === 'excel') {
    $sqlDl = $baseSql . ' LIMIT 5000';
    $st = $pdo->prepare($sqlDl);
    $st->execute($params);
    $rowsData = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $rowsData[] = [
            'ad' => (string)($r['customer_name'] ?? ''),
            'adres' => (string)($r['customer_address'] ?? ''),
            'ilce' => (string)($r['ilce_text'] ?? ''),
            'sehir' => (string)($r['sehir_text'] ?? ''),
            'tel' => (string)($r['customer_phone'] ?? ''),
            'tutar' => (int)max(0, (int)($r['tutar_int'] ?? 0)),
        ];
    }

    cargo_aras_emit_xlsx($rowsData, $siteUrun);
}

$previewLimit = min(250, max(10, isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50));
$pageNum = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($pageNum - 1) * $previewLimit;

$cntSt = $pdo->prepare($sqlCount);
$cntSt->execute($params);
$totalRows = (int)$cntSt->fetchColumn();

$sqlPv = $baseSql . " LIMIT {$previewLimit} OFFSET {$offset}";
$pvSt = $pdo->prepare($sqlPv);
$pvSt->execute($params);
$list = $pvSt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $previewLimit) : 1;

$statuses = $pdo->query('SELECT order_status_id, status_name FROM order_status ORDER BY order_status_id')->fetchAll(PDO::FETCH_ASSOC);

$excelQuery = ['from' => $from, 'to' => $to, 'download' => 'excel'];
if ($statusGet !== '') {
    $excelQuery['status'] = $statusGet;
}

$queryForPager = ['from' => $from, 'to' => $to, 'per_page' => (string)$previewLimit];
if ($statusGet !== '') {
    $queryForPager['status'] = $statusGet;
}

include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <h1 class="h4 mb-3"><i class="fas fa-file-excel text-success"></i> Aras Kargo Excel</h1>

    <form method="get" action="" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="">(tümü)</option>
                    <?php foreach ($statuses as $s): ?>
                        <?php $sid = (string)(int)$s['order_status_id']; ?>
                        <option value="<?= (int)$s['order_status_id'] ?>" <?= $statusGet !== '' && $statusGet === $sid ? 'selected' : '' ?>><?= htmlspecialchars($s['status_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Başlangıç</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Bitiş</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Liste (sayfa başına)</label>
                <select name="per_page" class="form-select">
                    <?php foreach ([25, 50, 100, 150, 250] as $n): ?>
                        <option value="<?= $n ?>" <?= $previewLimit === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrele / Önizle</button>
                <a class="btn btn-outline-secondary btn-sm" href="cargo_csv_export.php"><i class="fas fa-times"></i> Temizle</a>
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="alert alert-secondary py-2 px-3 mb-0 flex-grow-1">
            <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> sipariş eşleşti —
            bu sayfada <strong><?= count($list) ?></strong> satır görüntüleniyor
            <?= $totalPages > 1 ? '(sayfa ' . $pageNum . ' / ' . $totalPages . ')' : '' ?>
        </div>
        <?php if ($totalRows > 0): ?>
            <a href="?<?= htmlspecialchars(http_build_query($excelQuery), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success">
                <i class="fas fa-download"></i> Excel indir (.xlsx, en fazla 5000)
            </a>
        <?php endif; ?>
    </div>

    <?php if ($totalRows === 0): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-muted text-center py-5">Bu filtreye uygun sipariş yok.</div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle mb-0" style="min-width: 960px;">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th>Ad</th>
                            <th>İl / İlçe</th>
                            <th>Telefon</th>
                            <th>Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= (int)$r['order_id'] ?></td>
                                <td style="white-space:nowrap;"><small><?= htmlspecialchars((string)($r['order_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string)($r['status_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string)($r['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><small><?= htmlspecialchars(trim(($r['sehir_text'] ?? '') . ' / ' . ($r['ilce_text'] ?? '')), ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td style="white-space:nowrap;"><small><?= htmlspecialchars(cargo_aras_normalize_phone((string)($r['customer_phone'] ?? '')), ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td class="text-end fw-semibold"><?= number_format((float)($r['tutar_int'] ?? 0), 2, ',', '.') ?> ₺</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php
            require_once __DIR__ . '/../includes/admin_pagination.php';
            admin_render_pagination($pageNum, $totalPages, static function (int $p) use ($queryForPager): string {
                return '?' . http_build_query(array_merge($queryForPager, ['page' => $p]));
            });
            ?>
            <p class="text-muted small text-center mb-0 mt-2">Excel çıktısı bu filtreye uygun <strong>tüm</strong> satırları içerir (üst limit 5000); tablo yalnızca önizlemedir.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'admin_footer_common.php'; ?>
