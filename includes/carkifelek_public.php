<?php
declare(strict_types=1);

/**
 * Çarkıfelek — panel ayarları; arka plan kapatıcıda minimal JS (tıklama sızması + scroll kilidi).
 */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

require_once __DIR__ . '/carkifelek_spin_core.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['carkifelek_csrf']) || !is_string($_SESSION['carkifelek_csrf'])) {
    $_SESSION['carkifelek_csrf'] = bin2hex(random_bytes(16));
}

try {
    $st = $pdo->query('SELECT * FROM carkifelek_settings WHERE id = 1');
    $cf = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
} catch (Throwable $e) {
    return;
}

if (!$cf || empty($cf['is_active'])) {
    return;
}

$c1 = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($cf['color1'] ?? '')) ? $cf['color1'] : '#ef4444';
$c2 = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($cf['color2'] ?? '')) ? $cf['color2'] : '#7c3aed';
$cv = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($cf['accent'] ?? '')) ? $cf['accent'] : '#f59e0b';

[$wheel1, $wheel2] = carkifelek_wheel_visual_pair($c1, $c2, $cv);

$cf1 = htmlspecialchars((string) $wheel1, ENT_QUOTES, 'UTF-8');
$cf2 = htmlspecialchars((string) $wheel2, ENT_QUOTES, 'UTF-8');
$cfv = htmlspecialchars((string) $cv, ENT_QUOTES, 'UTF-8');

$decoded = json_decode((string) ($cf['prizes_json'] ?? ''), true);

$segments = is_array($decoded) && count($decoded) >= 4 ? array_values(array_map('strval', $decoded)) : [
    '%10 İndirim', '%15 İndirim', '%20 İndirim', '%25 İndirim', 'Ücretsiz Kargo', 'Tekrar Deneyin',
];

$nSeg = max(4, count($segments));

$title = htmlspecialchars((string) ($cf['title'] ?? 'Çarkıfelek'), ENT_QUOTES, 'UTF-8');
$subtitle = htmlspecialchars((string) ($cf['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
$limitDefault = htmlspecialchars(
    trim((string) ($cf['limit_message'] ?? '')) !== ''
        ? (string) $cf['limit_message']
        : 'Günde 1 kez çarkıfelek çevirerek indirim kazanabilirsiniz!',
    ENT_QUOTES,
    'UTF-8'
);

$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$base = $scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.' ? '' : rtrim(str_replace('\\', '/', $scriptDir), '/');

$cfReturnRaw = isset($carkifelek_return_url) && is_string($carkifelek_return_url)
    ? $carkifelek_return_url
    : (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');

$cfRet = htmlspecialchars(carkifelek_sanitize_return_path($cfReturnRaw), ENT_QUOTES, 'UTF-8');

$postHref = htmlspecialchars(($base === '' ? '' : $base) . '/carkifelek_post.php', ENT_QUOTES, 'UTF-8');
$csrfEsc = htmlspecialchars((string) ($_SESSION['carkifelek_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');

$blocked = false;
try {
    $blocked = carkifelek_ip_blocked($pdo, carkifelek_client_ip());
} catch (Throwable $e) {
    $blocked = true;
}

$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
$dbg = (isset($_GET['debug_wheel']) && (string) $_GET['debug_wheel'] === '1') || strpos($qs, 'debug_wheel=1') !== false;

$hadCookie = !empty($_COOKIE['carkifelek_kullanildi']);

$flash = isset($_SESSION['carkifelek_flash']) ? $_SESSION['carkifelek_flash'] : null;
unset($_SESSION['carkifelek_flash']);

$flashLimitMsg = '';

$flashKind = '';

$spinDeg = 0.0;

$wonLabel = '';

if (is_array($flash)) {

    $flashKind = isset($flash['type']) ? (string) $flash['type'] : '';

    if ($flashKind === 'limit') {
        $flashLimitMsg = trim((string) ($flash['message'] ?? ''));

    } elseif ($flashKind === 'won') {
        $spinDeg = (float) ($flash['rotate_deg'] ?? 0);
        $wonLabel = trim((string) ($flash['odul'] ?? ''));

    }

}

$fabEnabled = ! isset($cf['fab_enabled']) || ! empty($cf['fab_enabled']);
$autoPopup = ! empty($cf['auto_popup']);
$eligibleAuto = !$blocked && ($dbg || !$hadCookie);
$overlayOpenPhp = ($autoPopup && $eligibleAuto) || $flashKind === 'won' || $flashKind === 'limit';

/** İki ana renkle sırayla dilimler; ince beyaz çember hatları `coneSepCss`. */
$coneParts = [];

for ($gi = 0; $gi < $nSeg; ++$gi) {
    $col = ($gi % 2 === 0) ? $wheel1 : $wheel2;

    $a0 = ($gi / $nSeg) * 360;

    $a1 = (($gi + 1) / $nSeg) * 360;

    $coneParts[] = sprintf('%s %sdeg %sdeg', $col, rtrim(rtrim(sprintf('%.4f', $a0), '0'), '.'), rtrim(rtrim(sprintf('%.4f', $a1), '0'), '.'));

}

$coneCss = 'conic-gradient(from -90deg, ' . implode(', ', $coneParts) . ')';

$stepDeg = 360 / max(1, $nSeg);

$lineW = max(0.22, min(1.6, $stepDeg * 0.022));

$d0 = rtrim(rtrim(sprintf('%.6f', max(0, $stepDeg - $lineW)), '0'), '.');

$d1 = rtrim(rtrim(sprintf('%.6f', $stepDeg), '0'), '.');

$coneSepCss = sprintf(

    'repeating-conic-gradient(from -90deg, transparent 0deg %sdeg, rgba(255,255,255,.48) %sdeg %sdeg), %s',

    $d0,

    $d0,

    $d1,

    $coneCss

);

$coneCssEsc = htmlspecialchars($coneSepCss, ENT_QUOTES, 'UTF-8');

$doSpinAnim = $flashKind === 'won' && $spinDeg !== 0.0;

$spinEndEsc = htmlspecialchars(number_format((float) $spinDeg, 8, '.', ''), ENT_QUOTES, 'UTF-8');

?>
<style>
#carkifelek-sheet {
    position: fixed;
    left: 0;
    top: 0;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}
.cf-sr-only { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
.cf-host { position:relative; }
#carkifelek-sheet:focus + .cf-fab { outline:2px solid <?= $cfv ?>; outline-offset:2px; }
.cf-fab {
    position:fixed; right:20px; bottom:92px; z-index:99990;
    width:58px; height:58px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer;
    background:linear-gradient(145deg, <?= $cf1 ?>, <?= $cf2 ?>);
    color:#fff; font-size:24px;
    box-shadow:0 8px 28px rgba(15,23,42,.28), 0 0 0 0 <?= $cfv ?>66;
    border:3px solid rgba(255,255,255,.85);
    user-select:none;
    animation: cfFabPulse 2.4s ease-in-out infinite;
}
.cf-fab__icon { position:relative; z-index:2; line-height:1; }
.cf-fab__ring {
    position:absolute; inset:-6px; border-radius:50%;
    border:2px solid <?= $cfv ?>;
    opacity:.55; animation: cfFabRing 2.4s ease-out infinite;
}
@keyframes cfFabPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
@keyframes cfFabRing {
    0% { transform: scale(0.85); opacity:.7; }
    100% { transform: scale(1.35); opacity:0; }
}
.cf-fab:active { transform:scale(0.94); animation:none; }
@media (max-width:480px) {
    .cf-fab { right:14px; bottom:78px; width:52px; height:52px; }
}
.cf-overlay-wrap {
    position:fixed; inset:0; z-index:2147483640;
    min-height:100vh;
    min-height:100dvh;
    display:flex; align-items:center; justify-content:center;
    padding:16px;
    padding:calc(16px + env(safe-area-inset-bottom, 0px));
    background:rgba(2, 6, 23, 0.78);
    backdrop-filter:blur(18px) saturate(1.4);
    -webkit-backdrop-filter:blur(18px) saturate(1.4);
    visibility:hidden; opacity:0; pointer-events:none;
    transition:opacity .3s cubic-bezier(.2,.8,.2,1), visibility .3s;
    isolation:isolate;
}
#carkifelek-sheet:checked ~ .cf-overlay-wrap {
    visibility:visible; opacity:1; pointer-events:auto;
    overscroll-behavior:contain;
}
.cf-backdrop {
    position:absolute; inset:0;
    width:100%; height:100%;
    cursor:pointer;
    z-index:1;
    margin:0;
    padding:0;
    border:0;
    background:transparent;
    appearance:none;
    -webkit-tap-highlight-color:transparent;
    touch-action:none;
}
.cf-backdrop:focus-visible { outline:2px solid <?= $cfv ?>; outline-offset:2px; }
.cf-panel-shell {
    position:relative; z-index:4;
    pointer-events:none;
    max-width:calc(100vw - 32px);
    padding:3px;
    border-radius:30px;
    background:linear-gradient(138deg,
        <?= $cfv ?> 0%,
        <?= $cf1 ?> 48%,
        <?= $cf2 ?> 100%);
    box-shadow:
        0 28px 80px rgba(15,23,42,.4),
        0 14px 40px <?= $cf1 ?>16;
}
.cf-panel {
    position:relative; z-index:4;
    pointer-events:auto;
    width:min(100%, 560px);
    max-height:calc(100vh - 32px);
    max-height:calc(100dvh - 32px);
    overflow:auto;
    -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
    touch-action:auto;
    background:
        radial-gradient(120% 85% at 50% -18%, <?= $cf1 ?>18 0%, transparent 58%),
        linear-gradient(175deg,
            rgba(255,255,255,.985) 0%,
            rgba(252,253,254,.96) 40%,
            rgba(246,249,251,.955) 100%);
    border-radius:27px;
    padding:26px 22px 22px;
    box-shadow:
        0 0 0 1px rgba(255,255,255,.92) inset,
        0 20px 64px rgba(15,23,42,.08) inset,
        0 2px 0 rgba(255,255,255,.55) inset;
    border:none;
    isolation:isolate;
}
.cf-x {
    position:absolute; top:14px; right:14px;
    width:42px; height:42px; border-radius:50%;
    border:1px solid rgba(226,232,240,.9);
    background:linear-gradient(180deg,#fff 0%, #f8fafc 100%);
    display:flex; align-items:center; justify-content:center;
    font-size:22px; line-height:1; color:#475569;
    cursor:pointer; z-index:6;
    box-shadow:0 2px 8px rgba(15,23,42,.08);
}
.cf-wheel-box { position:relative; width:100%; max-width:min(94vw, 420px); margin:14px auto 10px;
    filter:
        drop-shadow(0 2px 0 rgba(255,255,255,.25))
        drop-shadow(0 24px 50px rgba(15,23,42,.42));
}
.cf-pointer-svg {
    position:absolute; left:50%; top:-24px;
    transform:translateX(-50%); z-index:25; pointer-events:none;
    filter:drop-shadow(0 6px 8px rgba(0,0,0,.55));
}
.cf-wheel-disk {
    position:relative; aspect-ratio:1 / 1;
    width:100%;
    border-radius:50%;
    box-sizing:border-box;
    box-shadow:
        0 0 0 3px rgba(255,255,255,.93) inset,
        0 0 0 10px <?= $cf1 ?>26 inset,
        0 0 0 14px <?= $cfv ?>b3 inset,
        0 -20px 44px rgba(0,0,0,.32) inset,
        0 18px 48px rgba(0,0,0,.38);
    background:
        radial-gradient(ellipse 118% 100% at 50% -12%, rgba(255,255,255,.76) 0%, rgba(255,255,255,0) 48%),
        radial-gradient(circle at 50% 50%, rgba(255,255,255,.06) 0%, rgba(0,0,0,.1) 68%, transparent 71%),
        <?= $coneCssEsc ?>;
    overflow:hidden;
}
.cf-wheel-disk::before {
    content:"";
    position:absolute; inset:0;
    border-radius:50%;
    pointer-events:none;
    opacity:.28;
    background:
        radial-gradient(ellipse at 42% 30%, rgba(255,255,255,.45) 0%, rgba(255,255,255,0) 55%),
        linear-gradient(148deg, rgba(255,255,255,.12) 0%, rgba(0,0,0,.04) 100%);
}
.cf-wheel-disk::after {
    content:"";
    position:absolute; inset:7%;
    border-radius:50%;
    border:2px solid rgba(255,255,255,.52);
    box-shadow:
        inset 0 2px 14px rgba(255,255,255,.42),
        inset 0 -8px 20px rgba(15,23,42,.2);
    pointer-events:none;
    z-index:3;
}
.cf-wheel-disk.cf-spinning {
<?php if ($doSpinAnim): ?>
    --spin-end: <?= $spinEndEsc ?>deg;
    animation: cfWheelSpin 4.5s cubic-bezier(0.17, 0.67, 0.12, 0.99) forwards;
<?php endif; ?>
}
@keyframes cfWheelSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(var(--spin-end)); }
}
.cf-title {
    text-align:center; margin:0 0 8px;
    font-weight:900; font-size:1.34rem;
    background:linear-gradient(105deg, <?= $cf1 ?> 0%, <?= $cf2 ?> 52%, <?= $cfv ?> 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent;
}
.cf-sub { text-align:center; color:#334155; font-size:13.5px; margin:0 auto 14px; max-width:34em; line-height:1.52; letter-spacing:.01em; }
.cf-hub {
    position:absolute; left:50%; top:50%;
    transform:translate(-50%, -50%);
    width:76px; height:76px;
    border-radius:50%;
    background:radial-gradient(circle at 32% 24%, rgba(255,255,255,.96) 0%, rgba(241,245,249,.93) 45%, rgba(203,213,225,.94) 100%);
    border:6px solid <?= $cfv ?>;
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.75),
        0 10px 26px rgba(15,23,42,.42);
    display:flex; align-items:center; justify-content:center;
    font-weight:900;
    font-size:9px;
    letter-spacing:.12em;
    color:#334155;
    pointer-events:none; z-index:16;
}
.cf-lbl-wrap { position:absolute; inset:0; border-radius:50%; pointer-events:none; z-index:9; }
.cf-lbl {
    position:absolute;
    left:50%; top:50%;
    width:0; height:0;
    transform-origin:center center;
    font-weight:800;
    font-size:clamp(10px, 2.92vw, 13px);
    color:#fefefe;
    letter-spacing:.025em;
    text-shadow:
        0 0 1px rgba(15,23,42,.98),
        0 1px 2px rgba(0,0,0,.95),
        0 3px 8px rgba(0,0,0,.72),
        0 -1px 0 rgba(0,0,0,.85);
}
.cf-submit {
    appearance:none;
    margin-top:14px;
    width:100%;
    border:none;
    border-radius:999px;
    padding:14px 20px;
    font-weight:800;
    cursor:pointer;
    color:#fff;
    box-shadow:
        0 4px 18px <?= $cf1 ?>55,
        0 1px 0 rgba(255,255,255,.2) inset;
    background:linear-gradient(93deg,
        <?= $cf1 ?> 0%,
        <?= $cfv ?> 48%,
        <?= $cf2 ?> 100%);
}
.cf-submit[disabled] { opacity:.55; cursor:not-allowed; }
.cf-result-win {
    margin-top:12px;
    text-align:center;
    font-weight:800;
    font-size:18px;
    color:#0f766e;
}
.cf-limit-box { text-align:center; padding:8px 4px 0; color:#92400e; }
.cf-limit-detail { font-size:14px; color:#64748b; margin-top:8px; }
</style>
<div class="cf-host">
    <input type="checkbox"
           id="carkifelek-sheet"
           class="cf-sr-only"
           tabindex="-1"
           aria-hidden="true"
           <?= $overlayOpenPhp ? 'checked' : '' ?> />
    <?php if ($fabEnabled): ?>
    <label for="carkifelek-sheet" class="cf-fab" title="Şans çarkı" aria-label="Şans çarkını aç">
        <span class="cf-fab__icon">🎁</span>
        <span class="cf-fab__ring"></span>
    </label>
    <?php endif; ?>
    <div class="cf-overlay-wrap" aria-hidden="<?= $overlayOpenPhp ? 'false' : 'true' ?>">
        <button type="button" class="cf-backdrop" id="cfBackdropClose" aria-label="Kapat"></button>
        <div class="cf-panel-shell">
        <div class="cf-panel">
            <button type="button" class="cf-x" id="cfCloseBtn" aria-label="Kapat">&times;</button>
            <h2 class="cf-title"><?= $title ?></h2>
            <?php if ($subtitle !== ''): ?><p class="cf-sub"><?= nl2br($subtitle) ?></p><?php endif; ?>

            <?php if ($flashKind === 'limit'): ?>
                <div class="cf-limit-box">
                    <div style="font-size:36px;">⚠️</div>
                    <h3 style="margin:8px 0;color:#b45309;font-size:1.1rem;">Şu an çarkı çeviremezsiniz</h3>
                    <p class="cf-limit-detail"><?= $flashLimitMsg !== '' ? nl2br(htmlspecialchars($flashLimitMsg, ENT_QUOTES, 'UTF-8')) : nl2br($limitDefault) ?></p>
                </div>
            <?php elseif ($blocked): ?>
                <div class="cf-limit-box">
                    <div style="font-size:36px;">⚠️</div>
                    <h3 style="margin:8px 0;color:#b45309;font-size:1.1rem;">Şu an çarkı çeviremezsiniz</h3>
                    <p class="cf-limit-detail"><?= nl2br($limitDefault) ?></p>
                </div>
            <?php else: ?>
                <div class="cf-wheel-box">
                    <div class="cf-pointer-svg" aria-hidden="true">
                        <svg width="40" height="50" viewBox="0 0 44 54">
                            <defs>
                                <linearGradient id="cfPtrG" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:<?= $cf1 ?>"/>
                                    <stop offset="100%" style="stop-color:<?= $cf2 ?>"/>
                                </linearGradient>
                            </defs>
                            <path d="M22 54 L2 4 L42 4 Z" fill="url(#cfPtrG)" stroke="#1e293b" stroke-width="1.2"/>
                        </svg>
                    </div>
                    <div class="cf-wheel-disk<?= $doSpinAnim ? ' cf-spinning' : '' ?>">
                        <div class="cf-lbl-wrap">
                            <?php
                            for ($ik = 0; $ik < $nSeg; ++$ik) {
                                $ang = (($ik / $nSeg) * 360) + (360 / $nSeg / 2);

                                $lbl = isset($segments[$ik]) ? (string) $segments[$ik] : '';

                                $lblShow = htmlspecialchars(mb_strlen($lbl) > 22 ? mb_substr($lbl, 0, 21) . '…' : $lbl, ENT_QUOTES, 'UTF-8');

                                echo '<span class="cf-lbl" style="transform: rotate(' . sprintf('%.6f', $ang)
                                    . 'deg) translateY(-178px) rotate(' . sprintf('%.6f', -$ang) . 'deg);">';
                                echo $lblShow;
                                echo '</span>';
                            }
                            ?>
                        </div>
                        <span class="cf-hub">ÇEVİR</span>
                    </div>
                </div>

                <?php if ($flashKind === 'won' && $wonLabel !== ''): ?>
                    <div class="cf-result-win" role="status">
                        TEBRİKLER!<br>
                        <span style="font-size:22px;font-weight:900;"><?= htmlspecialchars($wonLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$blocked && $flashKind !== 'limit'): ?>
                    <form method="post" action="<?= $postHref ?>">
                        <input type="hidden" name="spin" value="1">
                        <input type="hidden" name="cf_return" value="<?= $cfRet ?>">
                        <input type="hidden" name="csrf" value="<?= $csrfEsc ?>">
                        <button type="submit" class="cf-submit"<?= $flashKind === 'won' ? ' disabled' : '' ?>>
                            <?= $flashKind === 'won' ? 'Bugün çevirdiniz' : 'ÇEVİR VE KAZAN' ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        </div>
    </div>
<script>
(function(){
  var cb = document.getElementById('carkifelek-sheet');
  var bd = document.getElementById('cfBackdropClose');
  var closeBtn = document.getElementById('cfCloseBtn');
  if (!cb || !bd) return;
  var host = cb.closest('.cf-host');
  var scrollY = 0;
  var scrollLocked = false;

  function lockScroll() {
    if (scrollLocked) return;
    scrollLocked = true;
    scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + scrollY + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
  }

  function unlockScroll() {
    if (!scrollLocked) return;
    scrollLocked = false;
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    window.scrollTo(0, scrollY);
  }

  function syncAria() {
    var wrap = host ? host.querySelector('.cf-overlay-wrap') : null;
    if (wrap) wrap.setAttribute('aria-hidden', cb.checked ? 'false' : 'true');
  }

  function stopBubbleOnly(e) {
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
  }

  function absorbClick(e) {
    e.preventDefault();
    stopBubbleOnly(e);
  }

  function closeBackdrop() {
    if (!cb.checked) return;
    cb.checked = false;
    unlockScroll();
    syncAria();
    if (document.activeElement === cb) {
      cb.blur();
    }
  }

  ['pointerdown','mousedown','touchstart'].forEach(function (evName) {
    bd.addEventListener(evName, stopBubbleOnly, true);
  });

  bd.addEventListener('click', function (e) {
    absorbClick(e);
    closeBackdrop();
  }, true);

  if (closeBtn) {
    closeBtn.addEventListener('click', function (e) {
      e.preventDefault();
      stopBubbleOnly(e);
      closeBackdrop();
    });
  }

  cb.addEventListener('change', function () {
    if (cb.checked) {
      lockScroll();
    } else {
      unlockScroll();
    }
    syncAria();
  });

  cb.addEventListener('focus', function () {
    cb.blur();
  });

  syncAria();
  if (cb.checked) {
    lockScroll();
  }
})();
</script>
</div>
