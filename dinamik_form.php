<?php
declare(strict_types=1);

require 'db.php';
require_once 'tracking.php';
require_once __DIR__ . '/includes/page_meta_load.php';
require_once __DIR__ . '/includes/page_seo.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Istanbul');

$slug = strtolower(trim((string) ($_GET['f'] ?? $_POST['slug'] ?? '')));
$slugInvalid = false;
if ($slug !== '' && !preg_match('/^[a-z0-9][a-z0-9\-]{0,126}$/', $slug)) {
    http_response_code(400);
    $slugInvalid = true;
    $slug = '';
}

$page_name = 'dinamik_form.php';
$meta = page_meta_load($pdo, $page_name) ?? [];

$ip_address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$visit_time = date('Y-m-d H:i:s');
try {
    $stmt = $pdo->prepare('INSERT INTO page_views (page_name, ip_address, visit_time) VALUES (?, ?, ?)');
    $stmt->execute([$page_name, $ip_address, $visit_time]);
} catch (Throwable $e) {
    /* */
}

$form = null;
$fields = [];
if ($slug !== '') {
    $fst = $pdo->prepare('SELECT * FROM custom_forms WHERE slug = ? AND is_active = 1');
    $fst->execute([$slug]);
    $form = $fst->fetch(PDO::FETCH_ASSOC);
    if ($form) {
        $pst = $pdo->prepare(
            'SELECT * FROM custom_form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $pst->execute([(int) $form['id']]);
        $fields = $pst->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!isset($_SESSION['csrf_cf'])) {
    $_SESSION['csrf_cf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['csrf_cf'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form) {
    $tok = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $tok)) {
        $error = 'Oturum güvenliği doğrulanamadı. Sayfayı yenileyin.';
    } else {
        $lastKey = '__cf_submit_' . (int) $form['id'];
        $now = time();
        if (isset($_SESSION[$lastKey]) && ($now - (int) $_SESSION[$lastKey]) < 5) {
            $error = 'Çok hızlı gönderim. Birkaç saniye sonra tekrar deneyin.';
        } else {
            $_SESSION[$lastKey] = $now;
            $payload = [];
            foreach ($fields as $frow) {
                $key = (string) $frow['field_key'];
                $type = (string) $frow['field_type'];
                $req = !empty((int) $frow['is_required']);

                $rawVal = '';
                if ($type === 'checkbox') {
                    $rawVal = !empty($_POST['f'][$key]) ? '1' : '';
                } else {
                    $rawVal = is_scalar($_POST['f'][$key] ?? '') ? trim((string) ($_POST['f'][$key] ?? '')) : '';
                }

                if ($req) {
                    if ($type !== 'checkbox' && $rawVal === '') {
                        $error = '"' . strip_tags((string) $frow['label']) . '" alanı zorunlu.';
                        break;
                    }
                    if ($type === 'checkbox' && ($rawVal === '' || $rawVal === '0')) {
                        $error = '"' . strip_tags((string) $frow['label']) . '" onayını vermelisiniz.';
                        break;
                    }
                }

                if ($type === 'email' && $rawVal !== '' && filter_var($rawVal, FILTER_VALIDATE_EMAIL) === false) {
                    $error = '"' . strip_tags((string) $frow['label']) . '" için geçerli e-posta girin.';
                    break;
                }
                if ($type === 'number' && $rawVal !== '' && !is_numeric($rawVal)) {
                    $error = '"' . strip_tags((string) $frow['label']) . '" sayısal olmalı.';
                    break;
                }
                if ($type === 'select' && $rawVal !== '') {
                    $opts = array_values(array_filter(
                        preg_split('/\r\n|\r|\n/', (string) ($frow['options_text'] ?? '')) ?: [],
                        static fn ($l) => trim((string) $l) !== ''
                    ));
                    $allowed = [];
                    foreach ($opts as $o) {
                        $allowed[] = trim((string) $o);
                    }
                    if (!in_array($rawVal, $allowed, true)) {
                        $error = 'Geçersiz seçim.';
                        break;
                    }
                }

                $payload[$key] = $rawVal;
            }

            if ($error === '') {
                $pdo->prepare(
                    'INSERT INTO custom_form_entries (form_id, payload_json, ip, user_agent) VALUES (?,?,?,?)'
                )->execute([
                    (int) $form['id'],
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    mb_substr($ip_address, 0, 64),
                    mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                ]);
                $entryId = (string) $pdo->lastInsertId();

                require_once __DIR__ . '/includes/laravel4_sync.php';
                require_once __DIR__ . '/includes/app_url.php';
                $siteUrl = app_site_url($pdo);

                $syncName = '';
                $syncPhone = '';
                foreach (['ad_soyad', 'ad', 'isim', 'name', 'musteri_adi'] as $nameKey) {
                    if (! empty($payload[$nameKey])) {
                        $syncName = (string) $payload[$nameKey];
                        break;
                    }
                }
                foreach (['telefon', 'tel', 'phone', 'gsm'] as $phoneKey) {
                    if (! empty($payload[$phoneKey])) {
                        $syncPhone = (string) $payload[$phoneKey];
                        break;
                    }
                }
                $syncFields = [
                    'ad_soyad' => $syncName !== '' ? $syncName : 'Dinamik Form',
                    'telefon' => $syncPhone,
                    'mesaj' => 'Form: ' . (string) ($form['title'] ?? '') . "\n"
                        . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ];

                $panelFormSynced = laravel4_sync_form('dinamik-form-v1', $syncFields, $entryId, [
                    'form_title' => (string) ($form['title'] ?? ''),
                    'form_slug' => (string) ($form['slug'] ?? ''),
                    'site_url' => $siteUrl,
                ]);
                if ($panelFormSynced) {
                    laravel4_mark_form_synced((int) $entryId, $pdo);
                }

                $_SESSION['csrf_cf'] = bin2hex(random_bytes(16));
                $csrf = (string) $_SESSION['csrf_cf'];
                $success = (string) ($form['success_message'] ?? 'Gönderiminiz alındı.');
            }
        }
    }
}

$stmt = $pdo->query("SELECT discount_rate, show_whatsapp, whatsapp_number, show_instagram, instagram_username FROM notification_settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = $form ? htmlspecialchars((string) $form['title']) : 'Başvuru formu';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <?php
    page_seo_render($pdo, $page_name, $meta, [
        'title' => $form ? (string) $form['title'] : 'Başvuru formu',
    ]);
    ?>
    <?php if ($meta && !empty($meta['head_content'])): ?>
        <?= $meta['head_content'] ?>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/site_tracking_head.php'; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="site-shell-app bg-light py-4">
<?php require 'menu.php'; ?>
<div class="container" style="max-width:520px;">
    <h1 class="h4 mb-3"><?= $form ? htmlspecialchars((string) $form['title']) : 'Form bulunamadı' ?></h1>

    <?php if ($slugInvalid): ?>
        <div class="alert alert-danger">Geçersiz form adresi.</div>
    <?php elseif ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error !== ''): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!$form && $slug === ''): ?>
        <p class="text-muted">Geçerli bir form için URL’de <code>?f=slug</code> kullanın.</p>
    <?php elseif (!$form && $slug !== ''): ?>
        <p class="text-muted">Bu form aktif değil veya silinmiş.</p>
    <?php elseif ($form && $success === ''): ?>
        <form method="post" class="card shadow-sm">
            <div class="card-body">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <?php foreach ($fields as $frow): ?>
                    <?php
                    $key = (string) $frow['field_key'];
                    $type = (string) $frow['field_type'];
                    $fid = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $key);
                    ?>
                    <div class="mb-3">
                        <label class="form-label" for="<?= htmlspecialchars($fid) ?>">
                            <?= htmlspecialchars((string) $frow['label']) ?>
                            <?php if (!empty((int) $frow['is_required'])): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <?php if ($type === 'textarea'): ?>
                            <textarea class="form-control" name="f[<?= htmlspecialchars($key) ?>]" id="<?= htmlspecialchars($fid) ?>" rows="3"<?= !empty((int) $frow['is_required']) ? ' required' : '' ?>></textarea>
                        <?php elseif ($type === 'select'): ?>
                            <select class="form-select" name="f[<?= htmlspecialchars($key) ?>]" id="<?= htmlspecialchars($fid) ?>"<?= !empty((int) $frow['is_required']) ? ' required' : '' ?>>
                                <option value="">Seçin</option>
                                <?php
                                $opts = preg_split('/\r\n|\r|\n/', (string) ($frow['options_text'] ?? '')) ?: [];
                                foreach ($opts as $o) {
                                    $o = trim((string) $o);
                                    if ($o === '') {
                                        continue;
                                    }
                                    echo '<option value="' . htmlspecialchars($o) . '">' . htmlspecialchars($o) . '</option>';
                                }
                                ?>
                            </select>
                        <?php elseif ($type === 'checkbox'): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="f[<?= htmlspecialchars($key) ?>]" value="1" id="<?= htmlspecialchars($fid) ?>">
                                <label class="form-check-label" for="<?= htmlspecialchars($fid) ?>">Evet</label>
                            </div>
                        <?php else: ?>
                            <input class="form-control" type="<?= htmlspecialchars($type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text')) ?>"
                                   name="f[<?= htmlspecialchars($key) ?>]" id="<?= htmlspecialchars($fid) ?>"
                                <?= !empty((int) $frow['is_required']) ? ' required' : '' ?>
                                <?= ($type === 'tel') ? ' inputmode="tel"' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary w-100">Gönder</button>
            </div>
        </form>
    <?php endif; ?>

    <p class="mt-4 small text-muted text-center">
        <a href="index.php">Ana sayfaya dön</a>
        <?php if ((int) ($settings['show_whatsapp'] ?? 0)): ?>
            · <a href="https://wa.me/<?= preg_replace('/\D/', '', (string) ($settings['whatsapp_number'] ?? '')) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
