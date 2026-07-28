<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/site_helpers.php';

$page_title = 'SSS — Destek & Güvenlik';

$ops = [
    'orders_today' => 0,
    'orders_total' => 0,
    'guard_server' => 0,
    'guard_cookie' => 0,
    'cloaker_on' => 0,
    'support_open' => 0,
];
try {
    $ops['orders_today'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE() AND order_status_id != 19"
    )->fetchColumn();
    $ops['orders_total'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM orders WHERE order_status_id != 19'
    )->fetchColumn();
    $g = $pdo->query(
        'SELECT COALESCE(order_dupe_server_enabled,1) AS s, COALESCE(order_cookie_gate_enabled,1) AS c
         FROM checkout_module_settings WHERE id = 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($g) {
        $ops['guard_server'] = (int) $g['s'];
        $ops['guard_cookie'] = (int) $g['c'];
    }
    $ops['cloaker_on'] = (int) $pdo->query(
        'SELECT COALESCE(cloaker_enabled,0) FROM cloaker_settings WHERE id = 1'
    )->fetchColumn();
    $ops['support_open'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM support_requests WHERE status IS NULL OR status = '' OR status = 'Beklemede' OR status = 'Yeni'"
    )->fetchColumn();
} catch (Throwable $e) {
    /* stats optional */
}

$admin_vitrin_href = site_public_vitrin_href($pdo);
$shieldScore = min(100, 40 + ($ops['guard_server'] ? 25 : 0) + ($ops['guard_cookie'] ? 20 : 0) + ($ops['cloaker_on'] ? 15 : 0));

include 'admin_header.php';
?>

<div class="container-fluid py-3 admin-sss-page">
    <div class="admin-page-intro admin-sss-page__intro">
        <div class="admin-sss-page__intro-main">
            <div class="admin-sss-page__status-row">
                <span class="admin-sss-page__pulse" aria-hidden="true"></span>
                <span class="admin-sss-page__status-label">Sistem durumu: koruma aktif</span>
                <span class="brand-bounty-badge"><i class="fas fa-shield-virus"></i> Bug Bounty</span>
            </div>
            <h1><i class="fas fa-user-shield text-warning"></i> SSS — Destek &amp; güvenlik protokolü</h1>
            <p class="lead">phxcore0 panel lisansı, destek kapsamı ve müdahale kuralları. Kod bütünlüğü ihlali tespit edilirse destek ve iade süreçleri otomatik olarak sonlandırılır.</p>
        </div>
        <div class="admin-sss-page__intro-badges">
            <span class="admin-sss-pill admin-sss-pill--danger"><i class="fas fa-lock"></i> Hash korumalı</span>
            <span class="admin-sss-pill admin-sss-pill--warn"><i class="fas fa-eye"></i> İzleme açık</span>
            <span class="admin-sss-pill admin-sss-pill--ok"><i class="fas fa-headset"></i> Destek aktif</span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="admin-section-stack">
                <section class="admin-sss-threat admin-sss-threat--critical">
                    <div class="admin-sss-threat__head">
                        <span class="admin-sss-threat__level">SEVİYE 01 · KRİTİK</span>
                        <h2><i class="fas fa-shield-alt"></i> Güvenlik ve lisans</h2>
                    </div>
                    <div class="admin-sss-threat__body">
                        <div class="admin-sss-alert admin-sss-alert--info">
                            <div class="admin-sss-alert__icon"><i class="fas fa-fingerprint"></i></div>
                            <div>
                                <strong>Özel geliştirme</strong>
                                <p>Kendi rakipleri içerisinde en üst düzey olan bu yazılım, tarafımca özel ve özenli olarak geliştirilmiş olup, ileri düzey güvenlik önlemleri ile korunmaktadır.</p>
                            </div>
                        </div>
                        <div class="admin-sss-alert admin-sss-alert--neutral">
                            <div class="admin-sss-alert__icon"><i class="fas fa-code-branch"></i></div>
                            <div>
                                <strong>Kod bütünlüğü</strong>
                                <p>Layout şifreleme ve hash algoritmaları ile güvence altındadır. Düzenleme veya müdahale girişimi, yazılımın kalbi olan fonksiyon dosyasını şifreleyerek işlevsiz hale getirebilir.</p>
                                <code class="admin-sss-code">INTEGRITY :: TAMPER_DETECT → LOCKDOWN</code>
                            </div>
                        </div>
                        <div class="admin-sss-alert admin-sss-alert--danger">
                            <div class="admin-sss-alert__icon"><i class="fas fa-skull-crossbones"></i></div>
                            <div>
                                <strong>Müdahale uyarısı</strong>
                                <p>Bu tarz bir girişim paneli sattığım kişi veya kişiler tarafından yapıldığını tespit edersem: destek verilmez, ücret iadesi yapılmaz, silinen siparişler beni alakadar etmez ve iletişim kesilir.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-sss-threat admin-sss-threat--support">
                    <div class="admin-sss-threat__head">
                        <span class="admin-sss-threat__level admin-sss-threat__level--ok">SEVİYE 02 · DESTEK</span>
                        <h2><i class="fas fa-life-ring"></i> Destek ve hizmetler</h2>
                    </div>
                    <div class="admin-sss-threat__body">
                        <div class="admin-sss-grid-3">
                            <div class="admin-sss-mini-card">
                                <i class="fas fa-check-double"></i>
                                <strong>Ücretsiz teknik destek</strong>
                                <span>Tüm teknik sorunlar ve basit seviye özel istekler.</span>
                            </div>
                            <div class="admin-sss-mini-card">
                                <i class="fas fa-server"></i>
                                <strong>Planlı bakım</strong>
                                <span>Sorgular ayda bir veya yılda 2–3 kez, birkaç günlüğüne bakıma girebilir.</span>
                            </div>
                            <div class="admin-sss-mini-card">
                                <i class="fas fa-moon"></i>
                                <strong>Gece hattı</strong>
                                <span>Genellikle gece çevrimiçiyim; sosyal kanallardan ulaşabilirsiniz.</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-sss-rules">
                    <div class="admin-sss-rules__head"><i class="fas fa-list-check"></i> Hızlı kurallar</div>
                    <ul class="admin-sss-rules__list">
                        <li><span class="ok"><i class="fas fa-check"></i></span> Teknik sorunlar için ücretsiz destek</li>
                        <li><span class="ok"><i class="fas fa-check"></i></span> Basit özel istekler ücretsiz</li>
                        <li><span class="ok"><i class="fas fa-check"></i></span> Gece saatlerinde aktif destek hattı</li>
                        <li><span class="no"><i class="fas fa-ban"></i></span> Çekirdek kodda izinsiz değişiklik yapmayın</li>
                        <li><span class="no"><i class="fas fa-ban"></i></span> Hash / lisans bypass denemesi yapmayın</li>
                    </ul>
                </section>
            </div>
        </div>

        <div class="col-xl-4">
            <aside class="admin-sss-ops">
                <div class="admin-sss-ops__head">
                    <div class="admin-sss-ops__head-top">
                        <span class="admin-sss-ops__live"><span class="admin-sss-ops__live-dot"></span> CANLI</span>
                        <span class="admin-sss-ops__chip">phxcore0 ops</span>
                    </div>
                    <h2><i class="fas fa-microchip"></i> Mission control</h2>
                    <p>Panel nabzı — anlık özet, kısayollar ve koruma skoru.</p>
                </div>

                <div class="admin-sss-ops__shield">
                    <div class="admin-sss-ops__shield-ring" style="--shield-pct: <?= (int) $shieldScore ?>">
                        <span class="admin-sss-ops__shield-val"><?= (int) $shieldScore ?></span>
                        <span class="admin-sss-ops__shield-lbl">KORUMA</span>
                    </div>
                    <div class="admin-sss-ops__shield-meta">
                        <strong>Shield index</strong>
                        <span>Sipariş koruması + cloaker durumuna göre hesaplanır.</span>
                    </div>
                </div>

                <div class="admin-sss-ops__stats">
                    <div class="admin-sss-ops__stat">
                        <span class="admin-sss-ops__stat-val"><?= number_format($ops['orders_today'], 0, ',', '.') ?></span>
                        <span class="admin-sss-ops__stat-lbl">Bugün sipariş</span>
                    </div>
                    <div class="admin-sss-ops__stat">
                        <span class="admin-sss-ops__stat-val"><?= number_format($ops['orders_total'], 0, ',', '.') ?></span>
                        <span class="admin-sss-ops__stat-lbl">Toplam sipariş</span>
                    </div>
                    <div class="admin-sss-ops__stat">
                        <span class="admin-sss-ops__stat-val"><?= $ops['support_open'] ?></span>
                        <span class="admin-sss-ops__stat-lbl">Açık destek</span>
                    </div>
                </div>

                <div class="admin-sss-ops__matrix" aria-label="Koruma matrisi">
                    <div class="admin-sss-ops__matrix-item<?= $ops['guard_server'] ? ' is-on' : '' ?>">
                        <i class="fas fa-server"></i><span>Sunucu dupe</span>
                    </div>
                    <div class="admin-sss-ops__matrix-item<?= $ops['guard_cookie'] ? ' is-on' : '' ?>">
                        <i class="fas fa-cookie-bite"></i><span>Çerez gate</span>
                    </div>
                    <div class="admin-sss-ops__matrix-item<?= $ops['cloaker_on'] ? ' is-on' : '' ?>">
                        <i class="fas fa-user-secret"></i><span>Cloaker</span>
                    </div>
                    <div class="admin-sss-ops__matrix-item is-on">
                        <i class="fas fa-bug"></i><span>Bug Bounty</span>
                    </div>
                </div>

                <div class="admin-sss-ops__launch">
                    <span class="admin-sss-ops__launch-label">Hızlı atlama</span>
                    <div class="admin-sss-ops__launch-grid">
                        <a href="index.php" class="admin-sss-ops__jump"><i class="fas fa-gauge-high"></i> Dashboard</a>
                        <a href="orders.php" class="admin-sss-ops__jump"><i class="fas fa-cart-shopping"></i> Siparişler</a>
                        <a href="cloaker_settings.php" class="admin-sss-ops__jump"><i class="fas fa-mask"></i> Cloaker</a>
                        <a href="<?= htmlspecialchars($admin_vitrin_href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="admin-sss-ops__jump admin-sss-ops__jump--accent"><i class="fas fa-store"></i> Vitrin</a>
                    </div>
                </div>

                <div class="admin-sss-ops__bounty">
                    <div class="admin-sss-ops__bounty-glow"></div>
                    <span class="brand-bounty-badge"><i class="fas fa-shield-virus"></i> Bug Bounty</span>
                    <p>Güvenlik açığı bulursan raporla — panel sağlığı için teşekkür ederiz.</p>
                </div>
            </aside>

            <div class="admin-sss-terminal mt-3">
                <div class="admin-sss-terminal__bar">
                    <span></span><span></span><span></span>
                    <code>phxcore0@panel — audit.log</code>
                </div>
                <pre class="admin-sss-terminal__body"><span class="t-dim">[OK]</span> lisans doğrulandı
<span class="t-dim">[OK]</span> panel şeması güncel
<span class="t-ok">[>>]</span> bugün <?= (int) $ops['orders_today'] ?> sipariş · shield <?= (int) $shieldScore ?>
<span class="t-warn">[!!]</span> çekirdek müdahale = kilitleme
<span class="t-dim">[--]</span> destek: teknik + basit istekler açık</pre>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer_common.php'; ?>
