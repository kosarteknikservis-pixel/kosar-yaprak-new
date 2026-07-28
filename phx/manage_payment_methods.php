<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nm = trim((string) ($_POST['method_name'] ?? ''));
    $gw = trim((string) ($_POST['gateway_code'] ?? 'cod'));
    $allowedGw = ['cod', 'bank_transfer', 'paytr', 'iyzico'];
    if (!in_array($gw, $allowedGw, true)) {
        $gw = 'cod';
    }
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $sort = max(0, (int) ($_POST['sort_order'] ?? 0));

    if (!empty($_POST['update_id'])) {
        $uid = (int) $_POST['update_id'];
        if ($uid > 0 && $nm !== '') {
            $pdo->prepare('UPDATE payment_methods SET method_name = ?, gateway_code = ?, is_active = ?, sort_order = ? WHERE payment_method_id = ?')
                ->execute([$nm, $gw, $isActive, $sort, $uid]);
            $_SESSION['message'] = 'Ödeme yöntemi güncellendi.';
            header('Location: manage_payment_methods.php');
            exit;
        }
    }

    if ($nm !== '') {
        try {
            $pdo->prepare('INSERT INTO payment_methods (method_name, gateway_code, is_active, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$nm, $gw, $isActive, $sort]);
            $_SESSION['message'] = 'Ödeme yöntemi eklendi.';
            header('Location: manage_payment_methods.php');
            exit;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
        }
    }
}

if (isset($_GET['del']) && ctype_digit((string) $_GET['del'])) {
    $id = (int) $_GET['del'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE payment_method_id = ?');
    $c->execute([$id]);
    if ((int) $c->fetchColumn() > 0) {
        $_SESSION['message'] = 'Bu yöntem siparişlerde kullanılıyor; silinemedi.';
    } else {
        $pdo->prepare('DELETE FROM payment_methods WHERE payment_method_id = ?')->execute([$id]);
        $_SESSION['message'] = 'Silindi.';
    }
    header('Location: manage_payment_methods.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order, payment_method_id')->fetchAll(PDO::FETCH_ASSOC);

$gwLabels = [
    'cod' => 'Kapıda / offline',
    'bank_transfer' => 'Havale / EFT',
    'paytr' => 'PayTR (online)',
    'iyzico' => 'iyzico (online)',
];

$page_title = 'Ödeme yöntemleri';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <h1 class="h4 mb-3">Ödeme yöntemleri</h1>
    <p class="text-muted small">PayTR ve iyzico için önce <a href="paytr_settings.php">PayTR</a> / <a href="iyzico_settings.php">iyzico</a> ayarlarını kaydedin, sonra ilgili satırı <strong>aktif</strong> yapın.</p>

    <form method="post" class="row g-2 mb-4 align-items-end border rounded p-3 bg-light">
        <div class="col-md-4">
            <label class="form-label">Yeni yöntem adı</label>
            <input type="text" name="method_name" class="form-control" required maxlength="128">
        </div>
        <div class="col-md-3">
            <label class="form-label">Geçit tipi</label>
            <select name="gateway_code" class="form-select">
                <?php foreach ($gwLabels as $code => $lbl): ?>
                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Sıra</label>
            <input type="number" name="sort_order" class="form-control" value="5" min="0">
        </div>
        <div class="col-md-2">
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="na" value="1" checked>
                <label class="form-check-label" for="na">Aktif</label>
            </div>
        </div>
        <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Ekle</button></div>
    </form>

    <div class="table-responsive card border-0 shadow-sm">
        <table class="table mb-0 cc-table-compact">
            <thead><tr><th>ID</th><th>Ad</th><th>Geçit</th><th>Aktif</th><th>Sıra</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <form method="post">
                            <input type="hidden" name="update_id" value="<?= (int) $r['payment_method_id'] ?>">
                            <td><?= (int) $r['payment_method_id'] ?></td>
                            <td><input type="text" name="method_name" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $r['method_name']) ?>"></td>
                            <td>
                                <select name="gateway_code" class="form-select form-select-sm">
                                    <?php foreach ($gwLabels as $code => $lbl): ?>
                                        <option value="<?= htmlspecialchars($code) ?>"<?= ($r['gateway_code'] ?? 'cod') === $code ? ' selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="is_active" value="1"<?= !empty((int) ($r['is_active'] ?? 1)) ? ' checked' : '' ?>>
                            </td>
                            <td><input type="number" name="sort_order" class="form-control form-control-sm" style="width:70px" value="<?= (int) ($r['sort_order'] ?? 0) ?>"></td>
                            <td class="text-nowrap">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Kaydet</button>
                                <a href="manage_payment_methods.php?del=<?= (int) $r['payment_method_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
