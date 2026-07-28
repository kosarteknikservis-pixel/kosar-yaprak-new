<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/ParasutClient.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['parasut_test'])) {
        $pwTry = (string)($_POST['parasut_password'] ?? '');
        if ($pwTry === '') {
            try {
                $pwTry = (string) ($pdo->query('SELECT parasut_password FROM parasut_settings WHERE id = 1')->fetchColumn() ?: '');
            } catch (Throwable $e) {
                $pwTry = '';
            }
        }
        $r = ParasutClient::probePasswordGrant(
            trim((string)($_POST['company_id'] ?? '')),
            trim((string)($_POST['client_id'] ?? '')),
            trim((string)($_POST['client_secret'] ?? '')),
            trim((string)($_POST['parasut_username'] ?? '')),
            $pwTry
        );
        $_SESSION['message'] = !empty($r['ok'])
            ? 'Paraşüt ile OAuth bağlantısı başarılı (token alındı). Bilgileri kaydederek kalıcı hale getirin.'
            : 'Bağlantı hatası: ' . htmlspecialchars((string)($r['error'] ?? 'bilinmiyor'));
        $_SESSION['message_type'] = !empty($r['ok']) ? 'success' : 'danger';
        header('Location: parasut_settings.php');
        exit;
    }

    try {
        $curPw = '';
        try {
            $curPw = (string) ($pdo->query('SELECT parasut_password FROM parasut_settings WHERE id = 1')->fetchColumn() ?: '');
        } catch (Throwable $e) {
            /* ilk kurulumda tablo garanti olmayabilir */
        }
        $newPw = (string)($_POST['parasut_password'] ?? '');
        $pwFinal = $newPw !== '' ? $newPw : $curPw;

        $vatRaw = preg_replace('/[^0-9.,\-]/', '', (string)($_POST['vat_rate'] ?? '0'));
        $vatRaw = str_replace(',', '.', $vatRaw === '' ? '0' : $vatRaw);
        $vat = $vatRaw === '' ? 0.0 : (float) $vatRaw;

        $bv = preg_replace('/[^0-9]/', '', (string)($_POST['bireysel_vergi_no'] ?? ''));
        if ($bv === '') {
            $bv = '11111111111';
        } else {
            $bv = mb_substr($bv, 0, 11);
        }

        $stmt = $pdo->prepare(
            'UPDATE parasut_settings SET
                enabled = ?,
                company_id = ?,
                client_id = ?,
                client_secret = ?,
                parasut_username = ?,
                parasut_password = ?,
                vat_rate = ?,
                kdv_included = ?,
                bireysel_vergi_no = ?,
                product_id = ?,
                access_token = NULL,
                refresh_token = NULL,
                token_expires_at = 0
             WHERE id = 1'
        );
        $stmt->execute([
            isset($_POST['enabled']) ? 1 : 0,
            mb_substr(trim((string)($_POST['company_id'] ?? '')), 0, 32),
            mb_substr(trim((string)($_POST['client_id'] ?? '')), 0, 128),
            mb_substr(trim((string)($_POST['client_secret'] ?? '')), 0, 255),
            mb_substr(trim((string)($_POST['parasut_username'] ?? '')), 0, 255),
            $pwFinal,
            $vat,
            isset($_POST['kdv_included']) ? 1 : 0,
            $bv,
            mb_substr(trim((string)($_POST['product_id'] ?? '')), 0, 32),
        ]);

        $_SESSION['message'] = 'Paraşüt ayarları kaydedildi. Kimlik bilgileri değiştiyse yeni oturum için token sıfırlandı.';
        $_SESSION['message_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Kayıt hatası: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: parasut_settings.php');
    exit;
}

$r = $pdo->query('SELECT * FROM parasut_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'Paraşüt Ayarları';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <?php $mtp = $_SESSION['message_type'] ?? 'success'; $isErr = in_array($mtp, ['error', 'danger'], true); ?>
        <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-2"><i class="fas fa-file-invoice-dollar text-success"></i> Paraşüt (muhasebe)</h1>
    <p class="text-muted small mb-4">
        OAuth2 ile bağlanır. Sipariş &rarr; fatura akışını ilerleyen adımda sipariş onayına bağlayabilirsiniz.
        Dokümantasyon: <a href="https://apidocs.parasut.com/" target="_blank" rel="noopener">apidocs.parasut.com</a>
    </p>

    <form method="post" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="enabled" id="en" <?= !empty((int)$r['enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="en">Paraşüt entegrasyonu açık</label>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Şirket ID (firma kodu)</label>
                    <input type="text" name="company_id" class="form-control" value="<?= htmlspecialchars((string)($r['company_id'] ?? '')) ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Uygulama Client ID</label>
                    <input type="text" name="client_id" class="form-control font-monospace" value="<?= htmlspecialchars((string)($r['client_id'] ?? '')) ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client Secret</label>
                    <input type="password" name="client_secret" class="form-control font-monospace" value="<?= htmlspecialchars((string)($r['client_secret'] ?? '')) ?>" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Paraşüt hesap e-postası (kullanıcı adı)</label>
                    <input type="text" name="parasut_username" class="form-control" value="<?= htmlspecialchars((string)($r['parasut_username'] ?? '')) ?>" autocomplete="username">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Paraşüt şifresi</label>
                    <input type="password" name="parasut_password" class="form-control" value="" autocomplete="new-password" placeholder="<?= ($r['parasut_password'] ?? '') !== '' ? '(değiştirmek için yeni şifreyi yazın)' : 'Zorunlu' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fatura kaleminde bağlanacak Paraşüt ürün ID</label>
                    <input type="text" name="product_id" class="form-control font-monospace" maxlength="32" value="<?= htmlspecialchars((string)($r['product_id'] ?? '')) ?>" placeholder="Örn: 9988776655 (Paraşüt &gt; Ürünler içinden)" autocomplete="off">
                    <div class="form-text">Boş bırakılırsa fatura satırı ürün ilişkisi olmadan oluşturulur; API kabul etmezse bu alanı doldurun.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">KDV oranı (%)</label>
                    <input type="text" name="vat_rate" class="form-control" value="<?= htmlspecialchars((string)($r['vat_rate'] ?? '10')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bireysel varsayılan vergi no (Paraşüt’e gönderilir)</label>
                    <input type="text" name="bireysel_vergi_no" class="form-control" maxlength="11" value="<?= htmlspecialchars((string)($r['bireysel_vergi_no'] ?? '11111111111')) ?>">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kdv_included" id="kdv" <?= !isset($r['kdv_included']) || (int)$r['kdv_included'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="kdv">Fiyatlar KDV dahil (sipariş tutarıyla uyum için)</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit" name="save" value="1"><i class="fas fa-save"></i> Kaydet</button>
                <button class="btn btn-outline-secondary" type="submit" name="parasut_test" value="1"><i class="fas fa-plug"></i> Formdaki bilgilerle bağlantıyı dene</button>
            </div>
            <p class="text-muted small mt-2 mb-0">Kayıtta şifreyi boş bıraktığınızda mevcut şifre korunur. Bağlantı testi için şifre boşsa veritabanındaki şifre kullanılır.</p>
        </div>
    </form>
</div>

<?php include 'admin_footer_common.php'; ?>
