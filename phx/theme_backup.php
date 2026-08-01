<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

/** @var PDO $pdo */

$themeDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'theme_backups';
if (!is_dir($themeDir)) {
    @mkdir($themeDir, 0775, true);
}

/**
 * Tablo klasör yapısı: data/table.json + uploads/, meta.json (versiyon 1).
 */
function theme_zip_tree(string $source, string $zipPath): bool
{
    if (!extension_loaded('zip') || !is_dir($source)) {
        return false;
    }
    $base = realpath($source);
    if (!$base) {
        return false;
    }
    $zip = new ZipArchive();
    if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return false;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->isDir()) {
            continue;
        }
        $rp = realpath($f->getPathname());

        // phpcs:ignore
        assert($rp !== false);
        $rel = substr($rp, strlen($base) + 1);
        $zip->addFile($rp, str_replace('\\', '/', $rel));
    }

    return $zip->close();
}

function theme_rm_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            unlink($path);
        }

        return;
    }

    foreach (glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') ?: [] as $fn) {
        if (basename($fn) === '.' || basename($fn) === '..') {
            continue;
        }
        if (is_dir($fn)) {
            theme_rm_tree($fn);
        } else {
            @unlink($fn);
        }
    }
    @rmdir($path);
}

function theme_backup_table_ok(PDO $pdo, string $t): bool
{
    try {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
        $q = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));

        return $q && $q->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function theme_catalog_tables(): array
{
    return [
        'products',
        'product_images',
        'product_variation_types',
        'product_variation_options',
        'product_variation_assignments',
        'product_reviews',
        'product_review_images',
        'slider_images',
        'footer_images',
        'review_intro_items',
        'review_intro_settings',
        'ai_settings',
        'reviews_settings',
        'homepage_product_section',
    ];
}

/**
 * INSERT satırlar — ilk satırdaki sütun kümesinden prepared statement kurar (JSON'a göre gevşek).
 *
 * @param  list<array<string,mixed>>  $rows
 */
function theme_insert_any(PDO $pdo, string $table, array $rows): void
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    foreach ($rows as $row) {
        if (!is_array($row) || !$row) {
            continue;

        }

        try {
            $cols = [];
            foreach (array_keys($row) as $c) {

                $c = preg_replace('/[^a-zA-Z0-9_]/', '', $c ?? '');
                if ($c !== '') {
                    $cols[] = '`' . $c . '`';
                }

            }

            $vals = array_values($row);
            if (!$cols || count($cols) !== count($vals)) {

                continue;
            }

            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $table,

                implode(',', $cols),
                implode(',', array_fill(0, count($cols), '?'))
            );

            $pdo->prepare($sql)->execute(array_map(static function ($v) {
                if (is_scalar($v) || $v === null) {
                    return $v;
                }

                return json_encode($v);

            }, $vals));
        } catch (Throwable $e) {
            // satır uyumsuz — atlanır
        }
    }

}

if (isset($_GET['download']) && is_string($_GET['download'])) {
    $f = basename($_GET['download']);
    if (preg_match('/^[a-zA-Z0-9_\-\.]+\.zip$/', $f)) {
        $p = $themeDir . DIRECTORY_SEPARATOR . $f;
        if (is_readable($p)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $f . '"');
            header('Content-Length: ' . (string) filesize($p));
            header('Cache-Control: no-store');
            readfile($p);
        }
        exit;
    }
}

if (isset($_GET['sil']) && is_string($_GET['sil'])) {
    $f = basename($_GET['sil']);
    if (preg_match('/^[a-zA-Z0-9_\-\.]+\.zip$/', $f)) {

        @unlink($themeDir . DIRECTORY_SEPARATOR . $f);
    }

    header('Location: theme_backup.php');

    exit;

}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['theme_upload_zip'])) {
        try {
            if (!empty($_FILES['theme_zip_upload']['tmp_name']) && ($_FILES['theme_zip_upload']['error'] ?? 0) === UPLOAD_ERR_OK) {

                $fn = basename((string) ($_FILES['theme_zip_upload']['name'] ?? ''));

                $fnClean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $fn);

                if (strtolower(substr((string)$fnClean, -4)) === '.zip') {

                    $dest = $themeDir . DIRECTORY_SEPARATOR . $fnClean;

                    if (move_uploaded_file($_FILES['theme_zip_upload']['tmp_name'], $dest)) {
                        $_SESSION['theme_flash'] = 'Dosya yüklendi: ' . $fnClean;
                    }

                }

            } else {

                $_SESSION['theme_flash'] = 'Yüklenemedi.';

            }

            header('Location: theme_backup.php');

            exit;

        } catch (Throwable $e) {
            $_SESSION['theme_flash'] = 'Dosya yükleme hatası.';

            header('Location: theme_backup.php');

            exit;

        }

    }


        if (!empty($_POST['theme_backup'])) {

            set_time_limit(0);

            $nameRaw = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($_POST['theme_name'] ?? 'tema_backup'));
            if ($nameRaw === '') {
                $nameRaw = 'tema_backup_' . gmdate('Ymd_His');
            }

            $root = dirname(__DIR__);
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tbb_' . bin2hex(random_bytes(8));

            mkdir($tmp, 0777, true);
            mkdir($tmp . '/data', 0777, true);

            $meta = ['version' => 1, 'created' => gmdate('c'), 'tables' => []];

            foreach (theme_catalog_tables() as $tbl) {

                if (!theme_backup_table_ok($pdo, $tbl)) {
                    continue;
                }

                $meta['tables'][] = $tbl;

                $rows = $pdo->query('SELECT * FROM `' . $tbl . '`')->fetchAll(PDO::FETCH_ASSOC);

                file_put_contents(
                    $tmp . '/data/' . $tbl . '.json',
                    json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );

            }

            if (theme_backup_table_ok($pdo, 'notification_settings')) {
                try {
                    $r = $pdo->query('SELECT * FROM notification_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
                    file_put_contents(
                        $tmp . '/data/notification_settings.json',
                        json_encode($r ?: [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    );
                } catch (Throwable $e) {

                }

            }

            $upSrc = $root . DIRECTORY_SEPARATOR . 'uploads';
            if (is_dir($upSrc)) {
                mkdir($tmp . DIRECTORY_SEPARATOR . 'uploads', 0777, true);
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upSrc, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($it as $file) {

                    /** @var SplFileInfo $file */

                    $subPath = substr($file->getPathname(), strlen($upSrc));
                    $dest = $tmp . DIRECTORY_SEPARATOR . 'uploads' . $subPath;

                    if ($file->isDir()) {
                        mkdir($dest, 0777, true);
                    } else {
                        copy($file->getPathname(), $dest);
                    }
                }

            }

            file_put_contents($tmp . '/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $zipPath = $themeDir . DIRECTORY_SEPARATOR . $nameRaw . '.zip';
            if (@is_file($zipPath)) {
                @unlink($zipPath);
            }

            $okZip = theme_zip_tree($tmp, $zipPath);
            theme_rm_tree($tmp);

            $_SESSION['theme_flash'] = $okZip ? ('Yedek hazır: ' . $nameRaw . '.zip') : 'ZIP oluşturulamadı (zip eklentisi?).';

            header('Location: theme_backup.php');

            exit;

        }

        $postRestore = ($_POST['theme_restore_submit'] ?? '') === '1' && isset($_POST['zip_name']);
        $postConfirm = ($_POST['theme_restore_confirm'] ?? '') === '1';

        if ($postRestore && $postConfirm) {

            set_time_limit(0);

            ini_set('memory_limit', '512M');

            $zf = basename((string) $_POST['zip_name']);
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.zip$/', $zf)) {
                $_SESSION['theme_flash'] = 'Geçersiz dosya.';
                header('Location: theme_backup.php');

                exit;

            }

            $path = $themeDir . DIRECTORY_SEPARATOR . $zf;
            if (!is_readable($path)) {
                $_SESSION['theme_flash'] = 'ZIP bulunamadı.';
                header('Location: theme_backup.php');

                exit;

            }

            $ex = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'trx_' . bin2hex(random_bytes(10));
            mkdir($ex, 0777, true);
            $z = new ZipArchive();
            if ($z->open($path) !== true) {

                theme_rm_tree($ex);
                $_SESSION['theme_flash'] = 'ZIP açılamadı.';
                header('Location: theme_backup.php');

                exit;

            }

            $z->extractTo($ex);
            $z->close();

            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            try {

                foreach ([
                    'product_review_images',
                    'product_reviews',
                    'product_variation_assignments',
                    'product_images',
                    'products',
                    'product_variation_options',
                    'product_variation_types',
                    'slider_images',
                    'footer_images',
                    'review_intro_items',
                    'review_intro_settings',
                    'ai_settings',
                    'reviews_settings',
                ] as $tblClear) {

                    if (!theme_backup_table_ok($pdo, $tblClear)) {

                        continue;
                    }

                    try {

                        $pdo->exec('TRUNCATE TABLE `' . $tblClear . '`');
                    } catch (Throwable $e) {

                        $pdo->exec('DELETE FROM `' . $tblClear . '`');

                    }

                }

                foreach (theme_catalog_tables() as $tblFile) {

                    $jp = $ex . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $tblFile . '.json';

                    if (!is_readable($jp)) {

                        continue;
                    }

                    $rows = json_decode((string) file_get_contents($jp), true);
                    if (!is_array($rows) || !$rows) {

                        continue;
                    }

                    theme_insert_any($pdo, $tblFile, $rows);

                }

                $nsJson = $ex . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'notification_settings.json';
                if (is_readable($nsJson) && theme_backup_table_ok($pdo, 'notification_settings')) {
                    try {
                        $ns = json_decode((string) file_get_contents($nsJson), true);
                        if (is_array($ns) && $ns !== []) {
                            /** @var list<string>|false $__colsNotification */
                            $__colsNotification = false;
                            try {
                                $__colsNotification = false;
                                $cs = $pdo->query('SHOW COLUMNS FROM notification_settings')->fetchAll(PDO::FETCH_COLUMN);

                                $__colsNotification = is_array($cs) ? array_map(static function ($c): string {

                                    return (string) $c;

                                }, $cs) : false;
                            } catch (Throwable $e) {
                                $__colsNotification = false;
                            }

                            if (is_array($__colsNotification) && $__colsNotification !== []) {
                                $__keep = [];

                                foreach ($ns as $k => $v) {

                                    $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $k);
                                    if ($key !== '' && in_array($key, $__colsNotification, true)) {
                                        $__keep[$key] = $v;

                                    }

                                }

                                if (!$__keep) {

                                } else {

                                    $pdo->prepare('DELETE FROM notification_settings')->execute(); // güvenilir upsert için

                                    $pdo->prepare('INSERT INTO notification_settings (`' . implode('`,`', array_keys($__keep)) . '`)
                                        VALUES (' . implode(',', array_fill(0, count($__keep), '?')) . ')')->execute(array_values($__keep));

                                }

                            }

                        }

                    } catch (Throwable $e) {

                    }

                }

            } catch (Throwable $e) {
                $_SESSION['theme_flash'] = 'Geri yükleme sırasında hata: ' . htmlspecialchars($e->getMessage());

                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                theme_rm_tree($ex);
                header('Location: theme_backup.php');

                exit;

            }

            /** uploads — mevcut dosyaları SİLME; yedekten kopyala (merge) */

            $upRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';

            if (is_dir($ex . DIRECTORY_SEPARATOR . 'uploads')) {

                $itCopy = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ex . DIRECTORY_SEPARATOR . 'uploads', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($itCopy as $file) {

                    /** @var SplFileInfo $file */

                    $subPath = substr($file->getPathname(), strlen($ex . DIRECTORY_SEPARATOR . 'uploads'));
                    $dst = $upRoot . DIRECTORY_SEPARATOR . ltrim($subPath, DIRECTORY_SEPARATOR);
                    if ($file->isDir()) {

                        @mkdir($dst, 0777, true);

                    } else {

                        @mkdir(dirname($dst), 0777, true);
                        copy($file->getPathname(), $dst);

                    }

                }

            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            theme_rm_tree($ex);

            require_once dirname(__DIR__) . '/includes/media_guard.php';
            media_guard_sync_uploads_to_archive();
            media_guard_verify_and_restore($pdo);

            $_SESSION['theme_flash'] = 'Tema / içerik geri yüklendi. Sipariş geçmişi korunmuş olabilir; ürün ID’leri yenilendiyse eski sipariş satırı uyumsuz görünebilir.';

            header('Location: theme_backup.php');

            exit;

        }

}

$zips = glob($themeDir . DIRECTORY_SEPARATOR . '*.zip');

if (!is_array($zips)) {
    $zips = [];

}

sort($zips);

$flash = $_SESSION['theme_flash'] ?? null;
unset($_SESSION['theme_flash']);

$page_title = 'Tema — yedek / geri yükle';

require 'admin_header.php';

?>

<div class="container-fluid py-3" style="max-width:780px;">
    <?php if (!empty($flash)): ?>
        <div class="alert alert-info"><?= $flash ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header"><strong><i class="fas fa-file-archive"></i> Yedek al (ZIP)</strong></div>
        <div class="card-body">
            <p class="text-muted small">Ürünler, varyant tabloları, <strong>ürün yorumları ve yorum görselleri</strong>, slider/footer, <strong>YZ (review_intro, ai_settings, reviews görünümü)</strong>, bildirim satırı ve <code>uploads/</code> paketlenir. Sipariş tablolarına dokunulmaz.</p>
            <form method="post" class="d-flex gap-2 flex-wrap align-items-end">

                <div>
                    <label class="form-label">Dosya adı</label>

                    <input type="text" name="theme_name" class="form-control" maxlength="80" placeholder="Ornek_Yedek">

                </div>
                <div>
                    <button type="submit" name="theme_backup" value="1" class="btn btn-primary">ZIP oluştur</button>

                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">

        <div class="card-header"><strong>ZIP yükle</strong></div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">

                <input type="hidden" name="theme_upload_only" value="1">
                <label class="form-label">Dosya seç</label>

                <input type="file" accept=".zip" name="theme_zip_upload" class="form-control">

                <button type="submit" name="theme_upload_zip" value="1" class="btn btn-secondary mt-2">Sunucuya yükle</button>

            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Kayıtlı yedekler</strong></div>
        <div class="card-body p-0">
            <?php if (empty($zips)): ?>
                <p class="p-3 text-muted mb-0">Henüz yedek yok.</p>
            <?php else: ?>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Dosya</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zips as $zp): ?>
                            <?php $bn = basename($zp); ?>
                            <tr>
                                <td><code><?= htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars('theme_backup.php?download=' . rawurlencode($bn), ENT_QUOTES, 'UTF-8') ?>">İndir</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Ürün ve görseller bu yedekle değiştirilecek (sipariş tabloları dokunulmaz).');">
                                        <input type="hidden" name="zip_name" value="<?= htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="theme_restore_confirm" value="1">
                                        <button type="submit" name="theme_restore_submit" value="1" class="btn btn-sm btn-warning">Geri yükle</button>
                                    </form>
                                    <a class="btn btn-sm btn-outline-danger" href="theme_backup.php?sil=<?= rawurlencode($bn) ?>" onclick="return confirm('Silinsin mi?');">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="small text-muted mb-0 p-3">Geri yükleme: Ürün, varyant, yorumlar, slider/footer, bildirim ve <code>uploads/</code> içeriği yedekteki ile değiştirilir.</p>
        </div>
    </div>

</div>

<?php include 'admin_footer_common.php';

