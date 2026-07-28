<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txt = trim((string)($_POST['note_text'] ?? ''));
    if ($txt === '') {
        $_SESSION['message'] = 'Not metni boş olamaz.';
    } else {
        try {
            $pdo->prepare('INSERT INTO admin_quick_notes (note_text, pinned, sort_order) VALUES (?,?,?)')->execute([
                $txt,
                isset($_POST['pinned']) ? 1 : 0,
                (int)($_POST['sort_order'] ?? 0),
            ]);
            $_SESSION['message'] = 'Not eklendi.';
        } catch (Throwable $e) {
            $_SESSION['message'] = 'Not kaydedilemedi.';
        }
    }
    header('Location: quick_notes.php');
    exit;
}
if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $pdo->prepare('DELETE FROM admin_quick_notes WHERE id = ?')->execute([(int)$_GET['del']]);
    $_SESSION['message'] = 'Silindi.';
    header('Location: quick_notes.php');
    exit;
}

$notes = [];
try {
    $notes = $pdo->query('SELECT * FROM admin_quick_notes ORDER BY pinned DESC, sort_order DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $notes = [];
}

$page_title = 'Hızlı notlar';
include 'admin_header.php';
?>

<div class="container-fluid py-3">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string)$_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <h1 class="h4 mb-3"><i class="fas fa-sticky-note text-warning"></i> Panel notları</h1>

    <form method="post" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-2">
            <div class="col-12"><textarea name="note_text" rows="4" class="form-control" placeholder="Yeni not…" required></textarea></div>
            <div class="col-auto form-check mt-2">
                <input class="form-check-input" type="checkbox" id="pin" name="pinned">
                <label class="form-check-label" for="pin">Sabit göster</label>
            </div>
            <div class="col-md-3">
                <input type="number" name="sort_order" class="form-control form-control-sm" value="0" title="sıra">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-warning">Kaydet</button></div>
        </div>
    </form>

    <div class="row g-3">
        <?php foreach ($notes as $n): ?>
            <div class="col-lg-6">
                <div class="card <?= !empty((int)$n['pinned']) ? 'border-warning' : '' ?> h-100">
                    <div class="card-body">
                        <?= nl2br(htmlspecialchars((string)$n['note_text'])) ?>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <small class="text-muted"><?= htmlspecialchars((string)($n['updated_at'] ?? '')) ?></small>
                        <a href="quick_notes.php?del=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($notes === []): ?>
            <p class="text-muted">Kayıtsız.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
