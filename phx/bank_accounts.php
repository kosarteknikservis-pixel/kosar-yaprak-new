<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

function normalize_iban(string $raw): string
{
    return preg_replace('/\s+/', '', trim($raw));
}

/** @return array<string,mixed>|null */
function bank_row_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM bank_accounts WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $bank = trim((string)($_POST['bank_name'] ?? ''));
        $holder = trim((string)($_POST['account_holder'] ?? ''));
        $iban = normalize_iban((string)($_POST['iban'] ?? ''));
        if ($bank === '' || $holder === '' || $iban === '') {
            $_SESSION['message'] = 'Banka, hesap sahibi ve IBAN zorunlu.';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO bank_accounts (bank_name, account_holder, iban, branch, currency, notes, sort_order, is_active)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([
                    $bank,
                    $holder,
                    $iban,
                    trim((string)($_POST['branch'] ?? '')),
                    trim((string)($_POST['currency'] ?? 'TRY')) ?: 'TRY',
                    trim((string)($_POST['notes'] ?? '')),
                    (int)($_POST['sort_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                ]);
                $_SESSION['message'] = 'Hesap eklendi.';
            } catch (Throwable $e) {
                $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            }
        }
        header('Location: bank_accounts.php');
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $row = bank_row_by_id($pdo, $id);
        if (!$row) {
            $_SESSION['message'] = 'Kayıt bulunamadı.';
            header('Location: bank_accounts.php');
            exit;
        }
        $bank = trim((string)($_POST['bank_name'] ?? ''));
        $holder = trim((string)($_POST['account_holder'] ?? ''));
        $iban = normalize_iban((string)($_POST['iban'] ?? ''));
        if ($bank === '' || $holder === '' || $iban === '') {
            $_SESSION['message'] = 'Banka, hesap sahibi ve IBAN zorunlu.';
            header('Location: bank_accounts.php?edit=' . $id);
            exit;
        }
        try {
            $pdo->prepare(
                'UPDATE bank_accounts SET bank_name = ?, account_holder = ?, iban = ?, branch = ?, currency = ?, notes = ?, sort_order = ?, is_active = ? WHERE id = ?'
            )->execute([
                $bank,
                $holder,
                $iban,
                trim((string)($_POST['branch'] ?? '')),
                trim((string)($_POST['currency'] ?? 'TRY')) ?: 'TRY',
                trim((string)($_POST['notes'] ?? '')),
                (int)($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
                $id,
            ]);
            $_SESSION['message'] = 'Hesap güncellendi.';
        } catch (Throwable $e) {
            $_SESSION['message'] = 'Hata: ' . $e->getMessage();
        }
        header('Location: bank_accounts.php');
        exit;
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $row = bank_row_by_id($pdo, $id);
        if ($row) {
            try {
                $new = !empty($row['is_active']) ? 0 : 1;
                $pdo->prepare('UPDATE bank_accounts SET is_active = ? WHERE id = ?')->execute([$new, $id]);
                $_SESSION['message'] = $new ? 'Hesap aktifleştirildi.' : 'Hesap pasifleştirildi.';
            } catch (Throwable $e) {
                $_SESSION['message'] = 'Hata: ' . $e->getMessage();
            }
        } else {
            $_SESSION['message'] = 'Kayıt bulunamadı.';
        }
        header('Location: bank_accounts.php');
        exit;
    }
}

if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $did = (int)$_GET['del'];
    try {
        $dst = $pdo->prepare('DELETE FROM bank_accounts WHERE id = ?');
        $dst->execute([$did]);
        $_SESSION['message'] = ($dst->rowCount() > 0) ? 'Silindi.' : 'Kayıt bulunamadı.';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Silinemedi.';
    }
    header('Location: bank_accounts.php');
    exit;
}

$rows = [];
try {
    $rows = $pdo->query('SELECT * FROM bank_accounts ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}

$editId = 0;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
}
$editRow = $editId > 0 ? bank_row_by_id($pdo, $editId) : null;
if ($editId > 0 && $editRow === null) {
    $_SESSION['message'] = 'Düzenlenecek kayıt bulunamadı.';
}

$page_title = 'Banka hesapları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-3"><i class="fas fa-building-columns"></i> Banka / IBAN kayıtları</h1>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($editRow): ?>
                        <h2 class="h6">Hesap düzenle (#<?= (int)$editRow['id'] ?>)</h2>
                        <form method="post">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
                            <input type="text" name="bank_name" class="form-control mb-2" placeholder="Banka" required value="<?= htmlspecialchars((string)$editRow['bank_name']) ?>">
                            <input type="text" name="account_holder" class="form-control mb-2" placeholder="Hesap sahibi" required value="<?= htmlspecialchars((string)$editRow['account_holder']) ?>">
                            <input type="text" name="iban" class="form-control mb-2" placeholder="IBAN" required value="<?= htmlspecialchars((string)$editRow['iban']) ?>">
                            <input type="text" name="branch" class="form-control mb-2" placeholder="Şube" value="<?= htmlspecialchars((string)($editRow['branch'] ?? '')) ?>">
                            <?php $cur = (string)($editRow['currency'] ?? 'TRY'); ?>
                            <select name="currency" class="form-select mb-2">
                                <option value="TRY"<?= $cur === 'TRY' ? ' selected' : '' ?>>TRY</option>
                                <option value="EUR"<?= $cur === 'EUR' ? ' selected' : '' ?>>EUR</option>
                                <option value="USD"<?= $cur === 'USD' ? ' selected' : '' ?>>USD</option>
                            </select>
                            <input type="number" name="sort_order" class="form-control mb-2" value="<?= (int)($editRow['sort_order'] ?? 0) ?>">
                            <textarea name="notes" class="form-control mb-2" placeholder="Not" rows="2"><?= htmlspecialchars((string)($editRow['notes'] ?? '')) ?></textarea>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="bk_edit"<?= !empty($editRow['is_active']) ? ' checked' : '' ?>>
                                <label class="form-check-label" for="bk_edit">Aktif</label>
                            </div>
                            <button class="btn btn-primary" type="submit">Güncelle</button>
                            <a href="bank_accounts.php" class="btn btn-outline-secondary ms-1">İptal</a>
                        </form>
                    <?php else: ?>
                        <h2 class="h6">Yeni hesap</h2>
                        <form method="post">
                            <input type="hidden" name="action" value="save">
                            <input type="text" name="bank_name" class="form-control mb-2" placeholder="Banka" required>
                            <input type="text" name="account_holder" class="form-control mb-2" placeholder="Hesap sahibi" required>
                            <input type="text" name="iban" class="form-control mb-2" placeholder="IBAN (boşluksuz)" required>
                            <input type="text" name="branch" class="form-control mb-2" placeholder="Şube">
                            <select name="currency" class="form-select mb-2"><option value="TRY">TRY</option><option value="EUR">EUR</option><option value="USD">USD</option></select>
                            <input type="number" name="sort_order" class="form-control mb-2" value="0">
                            <textarea name="notes" class="form-control mb-2" placeholder="Not" rows="2"></textarea>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="bk" checked>
                                <label class="form-check-label" for="bk">Aktif</label>
                            </div>
                            <button class="btn btn-primary" type="submit">Kaydet</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-responsive card border-0 shadow-sm">
                <table class="table mb-0 table-sm align-middle">
                    <thead><tr><th>Banka</th><th>IBAN</th><th>Aktif</th><th class="text-end">İşlem</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr<?= empty($r['is_active']) ? ' class="table-secondary"' : '' ?>>
                                <td><?= htmlspecialchars((string)$r['bank_name']) ?><br><small class="text-muted"><?= htmlspecialchars((string)$r['account_holder']) ?></small></td>
                                <td class="font-monospace small"><?= htmlspecialchars((string)$r['iban']) ?></td>
                                <td><?= !empty($r['is_active']) ? '<span class="text-success">Evet</span>' : '<span class="text-muted">Hayır</span>' ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="bank_accounts.php?edit=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Durumu değiştirmek istiyor musunuz?');">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= !empty($r['is_active']) ? 'Pasif yap' : 'Aktif yap' ?></button>
                                    </form>
                                    <a href="bank_accounts.php?del=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="4">Kayıt yok.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
