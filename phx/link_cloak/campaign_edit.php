<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM link_cloak_campaigns WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($row === null) {
    header('Location: ' . lc_url('campaigns.php'));
    exit;
}

$paravanPresets = cloaker_paravan_presets();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_campaign'])) {
        $pdo->prepare('DELETE FROM link_cloak_traffic WHERE campaign_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM link_cloak_campaigns WHERE id = ?')->execute([$id]);
        $_SESSION['message'] = 'Kampanya silindi.';
        header('Location: ' . lc_url('campaigns.php'));
        exit;
    }
    if (isset($_POST['regenerate_token'])) {
        $tok = LinkCloakService::generateToken();
        $pdo->prepare('UPDATE link_cloak_campaigns SET token = ? WHERE id = ?')->execute([$tok, $id]);
        $_SESSION['message'] = 'Geçit token yenilendi — reklam linkini güncelleyin.';
        header('Location: ' . lc_url('campaign_edit.php?id=' . $id));
        exit;
    }
    if (isset($_POST['reset_stats'])) {
        $pdo->prepare('UPDATE link_cloak_campaigns SET total_hits=0, human_hits=0, bot_hits=0, clicks_since_rotate=0 WHERE id = ?')->execute([$id]);
        $_SESSION['message'] = 'İstatistikler sıfırlandı.';
        header('Location: ' . lc_url('campaign_edit.php?id=' . $id));
        exit;
    }

    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 128);
    $paravan = cloaker_normalize_external_url((string) ($_POST['paravan_url'] ?? ''));
    $money = cloaker_normalize_external_url((string) ($_POST['money_url'] ?? ''));
    $extraUrls = LinkCloakService::parseUrlList((string) ($_POST['money_urls_extra'] ?? ''));
    $rotate = max(5, min(500, (int) ($_POST['rotate_after_clicks'] ?? 25)));
    $warmup = max(0, min(500, (int) ($_POST['warmup_paravan_hits'] ?? 50)));
    $enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $strict = isset($_POST['strict_filter']) ? 1 : 0;
    $runStatus = trim((string) ($_POST['run_status'] ?? 'passive'));
    if (! in_array($runStatus, ['passive', 'active'], true)) {
        $runStatus = 'passive';
    }

    $jsonExtra = json_encode($extraUrls, JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'UPDATE link_cloak_campaigns SET name=?, paravan_url=?, money_url=?, money_urls_json=?,
         rotate_after_clicks=?, warmup_paravan_hits=?, is_enabled=?, strict_filter=?, run_status=? WHERE id=?'
    )->execute([$name, $paravan, $money, $jsonExtra, $rotate, $warmup, $enabled, $strict, $runStatus, $id]);

    $_SESSION['message'] = 'Kaydedildi.';
    header('Location: ' . lc_url('campaign_edit.php?id=' . $id));
    exit;
}

$gw = LinkCloakService::gatewayUrl($pdo, (string) $row['token']);
$extraRaw = '';
$decoded = json_decode((string) ($row['money_urls_json'] ?? ''), true);
if (is_array($decoded)) {
    $extraRaw = implode("\n", $decoded);
}

link_cloak_admin_header('Geçit düzenle — ' . ($row['name'] ?? ''));
?>

<div class="container-fluid py-3 lc-admin">
    <?php if (! empty($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <a href="<?= lc_href('campaigns.php') ?>" class="small text-muted"><i class="fas fa-arrow-left"></i> Geçit Merkezi</a>
            <h1 class="h4 mb-0 mt-1"><?= htmlspecialchars((string) $row['name']) ?></h1>
        </div>
        <a href="<?= lc_href('traffic.php?campaign_id=' . $id) ?>" class="btn btn-sm btn-outline-secondary">Trafik</a>
    </div>

    <div class="lc-link-board mb-4">
        <div class="lc-link-box lc-link-box--ad">
            <span class="lc-link-box__tag">Reklama koyacağınız link</span>
            <code id="gw-url"><?= htmlspecialchars($gw) ?></code>
            <button type="button" class="btn btn-sm btn-primary lc-copy" data-target="gw-url">Kopyala</button>
        </div>
        <?php if (trim((string) ($row['paravan_url'] ?? '')) !== ''): ?>
        <div class="lc-link-box lc-link-box--paravan">
            <span class="lc-link-box__tag">Paravan (display / bot)</span>
            <code><?= htmlspecialchars((string) $row['paravan_url']) ?></code>
        </div>
        <?php endif; ?>
        <?php if (trim((string) ($row['money_url'] ?? '')) !== ''): ?>
        <div class="lc-link-box lc-link-box--money">
            <span class="lc-link-box__tag">Gerçek hedef (şu an)</span>
            <code><?= htmlspecialchars(LinkCloakService::currentMoneyUrl($row)) ?></code>
        </div>
        <?php endif; ?>
    </div>

    <div class="alert alert-light border lc-usage-help mb-4">
        <h2 class="h6 mb-2"><i class="fas fa-circle-info text-primary"></i> Nasıl kullanılır?</h2>
        <ol class="small mb-2 ps-3">
            <li><strong>Reklama koyacağınız link</strong> yukarıdaki mavi kutudaki adres — Meta / Google reklam hedefi olarak bunu girin (<code>lc?c=…</code>).</li>
            <li><strong>Paravan URL</strong> — Bot ve reklam incelemecisinin göreceği “masum” sayfa (ör. arabam, sahibinden). Boş bırakırsanız <a href="<?= admin_href('safe_page_settings.php') ?>">Güvenli sayfa</a> açılır.</li>
            <li><strong>Gerçek URL</strong> — Gerçek müşterinin gideceği sipariş / vitrin adresiniz. Örnek: <code><?= htmlspecialchars(site_public_url($pdo), ENT_QUOTES, 'UTF-8') ?></code> veya ürün sayfanız.</li>
        </ol>
        <p class="small text-muted mb-0">Site cloaker (<a href="<?= admin_href('cloaker_settings.php') ?>">Cloaker ayarları</a>) tüm siteyi korur; Geçit Merkezi sadece reklam linkini yönetir — birbirinden bağımsızdır.</p>
    </div>

    <form method="post" class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Temel</div>
                <div class="card-body vstack gap-2">
                    <div>
                        <label class="form-label small">Kampanya adı</label>
                        <input type="text" name="name" class="form-control" maxlength="128" required value="<?= htmlspecialchars((string) $row['name']) ?>">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="en1" <?= ! empty((int) $row['is_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="en1">Geçit açık</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="strict_filter" id="st1" <?= ! isset($row['strict_filter']) || (int) $row['strict_filter'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="st1">Sıkı bot filtresi</label>
                    </div>
                    <div>
                        <label class="form-label small">Durum</label>
                        <select name="run_status" class="form-select form-select-sm">
                            <option value="passive" <?= ($row['run_status'] ?? '') === 'passive' ? 'selected' : '' ?>>Pasif — herkes paravana (Meta incelemesi için)</option>
                            <option value="active" <?= ($row['run_status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif — onay sonrası gerçek URL</option>
                        </select>
                        <div class="form-text">Reklam onaylanana kadar Pasif bırakın; onay gelince Aktif yapın.</div>
                    </div>
                    <div>
                        <label class="form-label small">Isınma (paravan tık eşiği)</label>
                        <input type="number" name="warmup_paravan_hits" class="form-control form-control-sm" min="0" max="500"
                            value="<?= (int) ($row['warmup_paravan_hits'] ?? 50) ?>">
                        <div class="form-text">Aktif modda bile ilk N toplam tıklama paravana gider (kaçan botlar için). 0 = kapalı.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">URL’ler</div>
                <div class="card-body vstack gap-2">
                    <div>
                        <label class="form-label small">Paravan URL (sahte / görünen)</label>
                        <select class="form-select form-select-sm mb-1" id="paravan-preset">
                            <?php foreach ($paravanPresets as $pk => $pv): ?>
                            <option value="<?= htmlspecialchars($pk) ?>" data-url="<?= htmlspecialchars($pv['url']) ?>"><?= htmlspecialchars($pv['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="url" name="paravan_url" id="paravan-url" class="form-control form-control-sm font-monospace"
                            value="<?= htmlspecialchars((string) $row['paravan_url']) ?>" placeholder="https://www.arabam.com/...">
                    </div>
                    <div>
                        <label class="form-label small">Gerçek URL (birincil hedef)</label>
                        <input type="url" name="money_url" class="form-control form-control-sm font-monospace" required
                            value="<?= htmlspecialchars((string) $row['money_url']) ?>" placeholder="https://siteniz.com/?ref=kampanya">
                    </div>
                    <div>
                        <label class="form-label small">Ek gerçek URL’ler (rotasyon — satır başına)</label>
                        <textarea name="money_urls_extra" class="form-control form-control-sm font-monospace" rows="3" placeholder="https://...&#10;https://..."><?= htmlspecialchars($extraRaw) ?></textarea>
                        <div class="form-text">Her <?= (int) ($row['rotate_after_clicks'] ?? 25) ?> gerçek ziyaretçi tıklamasında sıradaki URL’ye geçer.</div>
                    </div>
                    <div>
                        <label class="form-label small">Rotasyon eşiği (tık)</label>
                        <input type="number" name="rotate_after_clicks" class="form-control form-control-sm" min="5" max="500"
                            value="<?= (int) ($row['rotate_after_clicks'] ?? 25) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
            <button type="submit" name="regenerate_token" value="1" class="btn btn-outline-warning" onclick="return confirm('Token değişir; reklam linki güncellenmeli.');">Token yenile</button>
            <button type="submit" name="reset_stats" value="1" class="btn btn-outline-secondary">İstatistik sıfırla</button>
            <button type="submit" name="delete_campaign" value="1" class="btn btn-outline-danger ms-auto" onclick="return confirm('Kampanya silinsin mi?');">Sil</button>
        </div>
    </form>
</div>

<script>
(function () {
    document.querySelectorAll('.lc-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-target');
            var el = document.getElementById(id);
            if (el && navigator.clipboard) navigator.clipboard.writeText(el.textContent || '');
        });
    });
    var preset = document.getElementById('paravan-preset');
    var input = document.getElementById('paravan-url');
    if (preset && input) {
        preset.addEventListener('change', function () {
            var opt = preset.options[preset.selectedIndex];
            var u = opt.getAttribute('data-url');
            if (u && preset.value !== 'custom') input.value = u;
        });
    }
})();
</script>

<?php include __DIR__ . '/../admin_footer_common.php'; ?>
