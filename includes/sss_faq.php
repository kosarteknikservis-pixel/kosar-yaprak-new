<?php

declare(strict_types=1);

/**
 * SSS sayfası — accordion FAQ, CMS uyumlu dönüştürme.
 *
 * @return list<array{q: string, a: string, icon: string}>
 */
function sss_faq_default_items(): array
{
    return [
        [
            'q' => 'Siparişimi nasıl verebilirim?',
            'a' => 'Ana sayfadaki ürünlerden birini seçin, <strong>Sipariş Ver</strong> butonuna tıklayın ve iletişim bilgilerinizi eksiksiz doldurun. Onay sonrası siparişiniz işleme alınır.',
            'icon' => 'fa-bag-shopping',
        ],
        [
            'q' => 'Ödeme seçenekleri nelerdir?',
            'a' => 'Sipariş formunda size sunulan ödeme yöntemlerinden birini seçerek siparişinizi tamamlayabilirsiniz. Seçenekler sipariş ekranında listelenir.',
            'icon' => 'fa-credit-card',
        ],
        [
            'q' => 'Kargom ne zaman gelir?',
            'a' => 'Siparişiniz onaylandıktan sonra kargonuz genellikle <strong>1–3 iş günü</strong> içinde kargoya verilir. Teslimat süresi bölgenize göre değişebilir. Detay için <a href="kargo_sureci.php">Kargo Süreci</a> sayfasına bakın.',
            'icon' => 'fa-truck-fast',
        ],
        [
            'q' => 'Siparişimi nasıl takip ederim?',
            'a' => 'Menüden <a href="sorgula.php">Sipariş Sorgula</a> sayfasına gidin; siparişte kullandığınız telefon numarasını girerek durumu anında görüntüleyin.',
            'icon' => 'fa-magnifying-glass',
        ],
        [
            'q' => 'İade veya değişim yapabilir miyim?',
            'a' => 'Cayma hakkınız kapsamında 14 gün içinde iade talep edebilirsiniz. Koşullar ve adımlar için <a href="iade_degisim.php">İade &amp; Değişim</a> sayfamızı inceleyin.',
            'icon' => 'fa-rotate-left',
        ],
        [
            'q' => 'Destek talebi nasıl açılır?',
            'a' => 'Menüden <a href="destek_talebi.php">Destek Talebi</a> formunu doldurun; ekibimiz en kısa sürede size dönüş yapar.',
            'icon' => 'fa-headset',
        ],
        [
            'q' => 'Kişisel verilerim nasıl korunuyor?',
            'a' => 'Verileriniz KVKK kapsamında işlenir. Ayrıntılar <a href="kvkk.php">KVKK Aydınlatma Metni</a> sayfasında yer alır.',
            'icon' => 'fa-shield-halved',
        ],
    ];
}

/**
 * @param list<array{q?: string, a?: string, icon?: string}> $items
 */
function sss_faq_build_html(array $items, bool $firstOpen = true): string
{
    $out = '<div class="sss-faq-list" role="list">';
    $i = 0;
    foreach ($items as $item) {
        $q = trim((string) ($item['q'] ?? ''));
        $a = trim((string) ($item['a'] ?? ''));
        if ($q === '' || $a === '') {
            continue;
        }
        $icon = trim((string) ($item['icon'] ?? 'fa-circle-question'));
        if ($icon !== '' && ! str_starts_with($icon, 'fa-')) {
            $icon = 'fa-' . $icon;
        }
        $open = ($i === 0 && $firstOpen) ? ' open' : '';
        $out .= '<details class="sss-faq-item"' . $open . ' role="listitem">';
        $out .= '<summary class="sss-faq-q">';
        $out .= '<span class="sss-faq-q__icon" aria-hidden="true"><i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></span>';
        $out .= '<span class="sss-faq-q__text">' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<span class="sss-faq-q__chev" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>';
        $out .= '</summary>';
        $out .= '<div class="sss-faq-a"><div class="sss-faq-a__inner">' . $a . '</div></div>';
        $out .= '</details>';
        ++$i;
    }
    $out .= '</div>';

    return $out;
}

function legal_html_to_accordion_items(string $html): array
{
    $html = trim($html);
    if ($html === '') {
        return [];
    }

    $html = preg_replace('/^\s*<h2[^>]*>.*?<\/h2>\s*/is', '', $html) ?? $html;
    $items = [];

    if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>(.*?)(?=<h3|$)/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $q = html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $a = trim((string) $m[2]);
            if ($q !== '' && $a !== '') {
                $items[] = ['q' => $q, 'a' => $a, 'icon' => 'fa-file-lines'];
            }
        }
    }

    return $items;
}

/**
 * @param list<string> $sectionIcons
 */
function legal_accordion_normalize_body(string $html, array $sectionIcons = [], bool $firstOpen = true): string
{
    $html = trim($html);
    if ($html === '' || str_contains($html, 'sss-faq-list')) {
        return $html;
    }

    $items = legal_html_to_accordion_items($html);
    if ($items === []) {
        return $html;
    }

    foreach ($items as $i => &$item) {
        if (isset($sectionIcons[$i])) {
            $item['icon'] = $sectionIcons[$i];
        }
    }
    unset($item);

    return sss_faq_build_html($items, $firstOpen);
}

function sss_faq_normalize_body(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return sss_faq_build_html(sss_faq_default_items());
    }
    if (str_contains($html, 'sss-faq-list')) {
        return $html;
    }

    $items = [];
    if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>\s*(?:<p[^>]*>(.*?)<\/p>|<div[^>]*>(.*?)<\/div>)/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $q = html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $a = trim((string) ($m[2] !== '' ? $m[2] : ($m[3] ?? '')));
            if ($q !== '' && $a !== '') {
                $items[] = ['q' => $q, 'a' => $a, 'icon' => 'fa-circle-question'];
            }
        }
    }

    if ($items === []) {
        return sss_faq_build_html(sss_faq_default_items());
    }

    return sss_faq_build_html($items);
}

/**
 * @param array{title: string, body_html: string, page_file: string, track_event?: string} $config
 */
function sss_page_render(PDO $pdo, array $config): void
{
    if (is_file(__DIR__ . '/i18n.php')) {
        require_once __DIR__ . '/i18n.php';
        i18n_boot($pdo);
    }
    require_once __DIR__ . '/page_meta_load.php';
    require_once __DIR__ . '/page_seo.php';

    $pageName = $config['page_file'];
    $meta = page_meta_load($pdo, $pageName) ?? [];
    $seo = page_seo_resolve($pdo, $pageName, $meta, ['title' => $config['title']]);
    $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $layout = (string) ($config['layout'] ?? 'faq');
    $bodyHtml = (string) $config['body_html'];
    if ($layout === 'legal' || $layout === 'cms') {
        $sectionIcons = is_array($config['section_icons'] ?? null) ? $config['section_icons'] : [];
        $faqHtml = legal_accordion_normalize_body($bodyHtml, $sectionIcons, (bool) ($config['first_open'] ?? true));
        if ($faqHtml === $bodyHtml && trim($bodyHtml) !== '' && ! str_contains($bodyHtml, 'sss-faq-list')) {
            $faqHtml = '<div class="sss-faq-prose">' . $bodyHtml . '</div>';
        }
    } else {
        $faqHtml = sss_faq_normalize_body($bodyHtml);
    }
    $heroBadge = trim((string) ($config['hero_badge'] ?? (function_exists('t') ? t('menu.faq', 'Sıkça Sorulan Sorular') : 'Yardım merkezi')));
    $heroBadgeIcon = trim((string) ($config['hero_badge_icon'] ?? 'fa-circle-question'));
    $heroLead = trim((string) ($config['hero_lead'] ?? (function_exists('t') ? t('query.hero_lead', 'Telefon numaranızla sipariş durumunuzu anında görüntüleyin.') : 'Sipariş, ödeme ve teslimat hakkında merak ettikleriniz — tek dokunuşla cevap.')));
    $trustPills = is_array($config['trust_pills'] ?? null) ? $config['trust_pills'] : (function_exists('info_page_default_trust_pills') ? info_page_default_trust_pills() : [
        ['icon' => 'fa-truck', 'label' => '1–3 iş günü kargo'],
        ['icon' => 'fa-shield-halved', 'label' => 'Güvenli alışveriş'],
        ['icon' => 'fa-headset', 'label' => 'Destek'],
    ]);
    $pageExtraClass = trim((string) ($config['page_class'] ?? ''));
    $showCta = ($config['show_cta'] ?? true) !== false;
    $ctaTitle = trim((string) ($config['cta_title'] ?? (function_exists('t') ? t('query.cta_title', 'Yeni sipariş mi vereceksiniz?') : 'Hâlâ sorunuz mu var?')));
    $ctaText = trim((string) ($config['cta_text'] ?? (function_exists('t') ? t('query.cta_text', 'Ürünlerimize göz atın veya destek talebi oluşturun.') : 'Ekibimiz size yardımcı olmaya hazır — hemen iletişime geçin veya siparişinizi sorgulayın.')));
    $slotHtml = (string) ($config['slot_html'] ?? '');
    $showFaq = ($config['show_faq'] ?? true) !== false && trim($faqHtml) !== '';
    $extraScripts = (string) ($config['extra_scripts'] ?? '');
    $extraHead = (string) ($config['extra_head'] ?? '');
    $afterShellHtml = (string) ($config['after_shell_html'] ?? '');
    ?>
<!DOCTYPE html>
<html <?= function_exists('i18n_html_attrs') ? i18n_html_attrs($pdo) : 'lang="tr" dir="ltr"' ?>>
<head>
    <meta charset="UTF-8">
    <title><?= $esc($seo['title']) ?></title>
    <?php page_seo_render($pdo, $pageName, $meta, ['title' => $config['title']]); ?>
    <?php if (!empty($meta['head_content'])): ?>
        <?= $meta['head_content'] ?>
    <?php endif; ?>
    <?php require __DIR__ . '/site_tracking_head.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/info-pages.css">
    <link rel="stylesheet" href="css/sss-faq.css">
    <link rel="stylesheet" href="css/site-footer.css">
    <?= $extraHead ?>
</head>
<body class="site-shell-app info-page-view sss-page-view<?= $pageExtraClass !== '' ? ' ' . $esc($pageExtraClass) : '' ?>">
    <?php require dirname(__DIR__) . '/menu.php'; ?>
    <?php if (!empty($meta['body_content'])): ?>
        <?= $meta['body_content'] ?>
    <?php endif; ?>

    <div class="sss-page-shell">
        <header class="sss-hero">
            <div class="sss-hero__badge"><i class="fas <?= $esc($heroBadgeIcon) ?>"></i> <?= $esc($heroBadge) ?></div>
            <h1 class="sss-hero__title"><?= $esc($config['title']) ?></h1>
            <p class="sss-hero__lead"><?= $esc($heroLead) ?></p>
            <div class="sss-trust-row">
                <?php foreach ($trustPills as $pill): ?>
                    <?php
                    $pIcon = trim((string) ($pill['icon'] ?? 'fa-check'));
                    $pLabel = trim((string) ($pill['label'] ?? ''));
                    if ($pLabel === '') {
                        continue;
                    }
                    ?>
                    <span class="sss-trust-pill"><i class="fas <?= $esc($pIcon) ?>"></i> <?= $esc($pLabel) ?></span>
                <?php endforeach; ?>
            </div>
        </header>

        <?php if ($showFaq): ?>
        <section class="sss-faq-card" data-sss-faq>
            <?= $faqHtml ?>
        </section>
        <?php endif; ?>

        <?php if ($slotHtml !== ''): ?>
        <section class="sss-faq-card sss-slot-card">
            <?= $slotHtml ?>
        </section>
        <?php endif; ?>

        <?php if ($showCta): ?>
        <section class="sss-cta-band">
            <h2 class="sss-cta-band__title"><?= $esc($ctaTitle) ?></h2>
            <p class="sss-cta-band__text"><?= $esc($ctaText) ?></p>
            <div class="sss-cta-band__actions">
                <a href="index.php#products-heading" class="sss-cta-btn sss-cta-btn--primary"><i class="fas fa-bag-shopping"></i> Alışverişe başla</a>
                <a href="sorgula.php" class="sss-cta-btn sss-cta-btn--ghost"><i class="fas fa-search"></i> Sipariş sorgula</a>
                <a href="destek_talebi.php" class="sss-cta-btn sss-cta-btn--ghost"><i class="fas fa-headset"></i> Destek talebi</a>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <?= $afterShellHtml ?>

    <?php
    require_once __DIR__ . '/site_footer.php';
    site_footer_render($pdo);
    include dirname(__DIR__) . '/social_buttons.php';
    if (!empty($config['track_event'])) {
        echo '<script>if(typeof window.paTrackSafe==="function"){window.paTrackSafe(' . json_encode($config['track_event'], JSON_HEX_TAG | JSON_HEX_AMP) . ');}</script>';
    }
    echo $extraScripts;
    ?>
<script>
(function () {
    document.querySelectorAll('[data-sss-faq]').forEach(function (root) {
        var items = root.querySelectorAll('details.sss-faq-item');
        items.forEach(function (detail) {
            detail.addEventListener('toggle', function () {
                if (!detail.open) return;
                items.forEach(function (other) {
                    if (other !== detail) other.open = false;
                });
            });
        });
    });
})();
</script>
</body>
</html>
    <?php
}
