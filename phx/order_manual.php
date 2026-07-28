<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');
        $customer_city = isset($_POST['customer_city']) ? (int)$_POST['customer_city'] : 0;
        $customer_district = isset($_POST['customer_district']) ? (int)$_POST['customer_district'] : 0;
        $payment_method_id = isset($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 0;
        $order_status_id = isset($_POST['order_status_id']) ? (int)$_POST['order_status_id'] : 1;
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $notes = trim((string)($_POST['order_notes'] ?? ''));
        $invoice_vkn = mb_substr(preg_replace('/[^0-9]/', '', (string)($_POST['invoice_vkn'] ?? '')), 0, 11);
        $invoice_tax_office = mb_substr(trim((string)($_POST['invoice_tax_office'] ?? '')), 0, 128);
        $invoice_company_name = mb_substr(trim((string)($_POST['invoice_company_name'] ?? '')), 0, 255);
        $invoice_address = mb_substr(trim((string)($_POST['invoice_address'] ?? '')), 0, 3000);
        $ip = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($customer_name === '' || $customer_phone === '' || $customer_address === '' || $customer_city <= 0 || $customer_district <= 0 || $payment_method_id <= 0 || $product_id <= 0) {
            throw new InvalidArgumentException('Zorunlu alanları doldurun.');
        }

        $pst = $pdo->prepare('SELECT product_id, product_name, product_price FROM products WHERE product_id = ?');
        $pst->execute([$product_id]);
        $prod = $pst->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new InvalidArgumentException('Ürün bulunamadı.');
        }
        $price = (float)($prod['product_price']);

        $pdo->beginTransaction();

        /** @see ensure_feature_schema: is_manual kolonu */
        $ins = $pdo->prepare(
            'INSERT INTO orders (
                customer_name, customer_phone, customer_address, customer_city, customer_district,
                order_notes, customer_notes, payment_method_id, order_status_id,
                customer_ip, source, reklam, referrer, is_manual,
                invoice_vkn, invoice_tax_office, invoice_company_name, invoice_address
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $notesDb = $notes !== '' ? $notes : 'Manuel oluşturuldu.';
        $ins->execute([
            $customer_name,
            $customer_phone,
            $customer_address,
            $customer_city,
            $customer_district,
            $notesDb,
            $notesDb,
            $payment_method_id,
            $order_status_id,
            $ip,
            'Admin Manuel Sipariş',
            'Manuel',
            '',
            1,
            $invoice_vkn !== '' ? $invoice_vkn : null,
            $invoice_tax_office !== '' ? $invoice_tax_office : null,
            $invoice_company_name !== '' ? $invoice_company_name : null,
            $invoice_address !== '' ? $invoice_address : null,
        ]);

        $newId = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, 1, ?)')
            ->execute([$newId, $product_id, $price]);

        $pdo->commit();

        require_once __DIR__ . '/../includes/laravel4_sync.php';
        laravel4_sync_admin_order($newId, $pdo);

        $_SESSION['message'] = 'Manuel sipariş oluşturuldu #' . $newId;
        header('Location: order_manage.php?order_id=' . $newId);
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $ex->getMessage();
    }
}

$cities = $pdo->query('SELECT * FROM cities ORDER BY city_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$districts = $pdo->query('SELECT * FROM districts ORDER BY city_id ASC, district_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$payment_methods = $pdo->query('SELECT * FROM payment_methods ORDER BY payment_method_id')->fetchAll(PDO::FETCH_ASSOC);
$statuses = $pdo->query('SELECT * FROM order_status ORDER BY order_status_id')->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query('SELECT product_id, product_name, product_price FROM products ORDER BY product_name')->fetchAll(PDO::FETCH_ASSOC);

$post_product_id = (int)($_POST['product_id'] ?? 0);
$post_payment_method_id = (int)($_POST['payment_method_id'] ?? 0);
$post_order_status_id = (int)($_POST['order_status_id'] ?? 0);
$post_city = (int)($_POST['customer_city'] ?? 0);
$post_district = (int)($_POST['customer_district'] ?? 0);
$post_invoice_vkn = (string)($_POST['invoice_vkn'] ?? '');
$post_invoice_tax_office = (string)($_POST['invoice_tax_office'] ?? '');
$post_invoice_company_name = (string)($_POST['invoice_company_name'] ?? '');
$post_invoice_address = (string)($_POST['invoice_address'] ?? '');

$page_title = 'Manuel sipariş ekle';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0"><i class="fas fa-cart-plus text-primary"></i> Manuel sipariş</h1>
        <span class="text-muted small">Vitrinden bağımsız kayıt. Kurumsal fatura alanları sipariş formuyla aynı veritabanı kolonlarına yazılır; vitrin «kurumsal fatura» kartının kapalı/açık olmasından bağımsızdır.</span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Ürün *</label>
                    <select name="product_id" class="form-select" required>
                        <option value=""<?= $post_product_id === 0 ? ' selected' : '' ?>>Seçiniz</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['product_id'] ?>"<?= $post_product_id === (int)$p['product_id'] ? ' selected' : '' ?>><?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars((string)$p['product_price']) ?> ₺)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ödeme yöntemi *</label>
                    <select name="payment_method_id" class="form-select" required>
                        <?php foreach ($payment_methods as $i => $pm): ?>
                            <option value="<?= (int)$pm['payment_method_id'] ?>"<?= (($post_payment_method_id === (int)$pm['payment_method_id']) || ($post_payment_method_id === 0 && $i === 0)) ? ' selected' : '' ?>><?= htmlspecialchars((string)$pm['method_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sipariş durumu</label>
                    <select name="order_status_id" class="form-select">
                        <?php foreach ($statuses as $i => $s): ?>
                            <option value="<?= (int)$s['order_status_id'] ?>"<?= (($post_order_status_id === (int)$s['order_status_id']) || ($post_order_status_id === 0 && $i === 0)) ? ' selected' : '' ?>><?= htmlspecialchars((string)$s['status_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Ad soyad *</label>
                    <input type="text" name="customer_name" class="form-control" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefon *</label>
                    <input type="text" name="customer_phone" class="form-control" required value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Adres *</label>
                    <textarea name="customer_address" class="form-control" rows="3" required><?= htmlspecialchars($_POST['customer_address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">İl *</label>
                    <select name="customer_city" class="form-select" id="om_city" required>
                        <option value="">İl seçin</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= (int)$c['city_id'] ?>"<?= $post_city === (int)$c['city_id'] ? ' selected' : '' ?>><?= htmlspecialchars($c['city_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">İlçe *</label>
                    <select name="customer_district" class="form-select" id="om_district" required>
                        <option value="">İl seçin önce</option>
                        <?php foreach ($districts as $d): ?>
                            <option data-city="<?= (int)$d['city_id'] ?>" value="<?= (int)$d['district_id'] ?>"<?= $post_district === (int)$d['district_id'] ? ' selected' : '' ?>><?= htmlspecialchars($d['district_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Not</label>
                    <textarea name="order_notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['order_notes'] ?? '') ?></textarea>
                </div>

                <div class="col-12 mt-2">
                    <details class="border rounded bg-light p-3" open>
                        <summary class="fw-semibold text-secondary" style="cursor:pointer;"><i class="fas fa-building me-1"></i> Kurumsal fatura bilgileri (isteğe bağlı)</summary>
                        <p class="text-muted small mt-2 mb-3">Sipariş formundaki kurumsal blokla aynı alanlar; Excel ve Paraşüt bu veriyi kullanır.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Vergi numarası</label>
                                <input type="text" name="invoice_vkn" id="om_invoice_vkn" class="form-control" maxlength="11" inputmode="numeric" autocomplete="off" placeholder="En fazla 11 rakam" value="<?= htmlspecialchars($post_invoice_vkn) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vergi dairesi</label>
                                <input type="text" name="invoice_tax_office" class="form-control" maxlength="128" value="<?= htmlspecialchars($post_invoice_tax_office) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Firma ünvanı</label>
                                <input type="text" name="invoice_company_name" class="form-control" maxlength="255" value="<?= htmlspecialchars($post_invoice_company_name) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Fatura adresi</label>
                                <textarea name="invoice_address" class="form-control" rows="4" maxlength="3000"><?= htmlspecialchars($post_invoice_address) ?></textarea>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Kaydet</button>
                    <a href="orders.php" class="btn btn-outline-secondary ms-2">Sipariş listesi</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
  var vn = document.getElementById('om_invoice_vkn');
  if (vn) {
    vn.addEventListener('input', function(){ this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11); });
  }
})();
(function(){
  var restore = <?= json_encode(['city' => $post_city, 'district' => $post_district]) ?>;
  var c = document.getElementById('om_city'); var d = document.getElementById('om_district'); if(!c||!d) return;
  function filter(){
    var cid = parseInt(c.value||'0',10);
    var opts = d.querySelectorAll('option');
    opts.forEach(function(o){
      if(o.value==='') { o.hidden = false; return; }
      var cc = parseInt(o.getAttribute('data-city')||'0',10);
      o.hidden = (cid>0 && cc!==cid);
    });
    if (cid > 0 && restore.district) {
      var want = String(restore.district);
      opts.forEach(function(o){
        if (o.value && o.value === want && !o.hidden) { d.value = want; }
      });
    }
    if (!d.value && cid <= 0) {
      try { Array.from(opts).some(function(o){ if(!o.hidden && o.value) { return (d.selectedIndex = Array.from(opts).indexOf(o)); } }); } catch(_){}
    }
  }
  c.addEventListener('change',filter);
  filter();
})();
</script>

<?php include 'admin_footer_common.php'; ?>
