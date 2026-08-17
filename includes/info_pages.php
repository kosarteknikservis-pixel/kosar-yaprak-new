<?php

declare(strict_types=1);

require_once __DIR__ . '/sss_faq.php';
require_once __DIR__ . '/cms_helper.php';

/** @return list<array{icon:string,label:string}> */
function info_page_default_trust_pills(): array
{
    return [
        ['icon' => 'fa-truck', 'label' => function_exists('t') ? t('shop.fast_shipping', 'Hızlı kargo') : 'Hızlı kargo'],
        ['icon' => 'fa-shield-halved', 'label' => function_exists('t') ? t('trust.secure_payment', 'Güvenli ödeme') : 'Güvenli ödeme'],
        ['icon' => 'fa-rotate-left', 'label' => function_exists('t') ? t('trust.easy_returns', 'Kolay iade') : 'Kolay iade'],
    ];
}

function info_page_track_view(PDO $pdo, string $pageFile): void
{
    require_once __DIR__.'/attribution_bootstrap.php';

    try {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $stmt = $pdo->prepare('INSERT INTO page_views (page_name, ip_address, visit_time) VALUES (?, ?, ?)');
        $stmt->execute([$pageFile, $ip, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        /* ignore */
    }
}

function info_page_hero_image_html(?string $src, string $alt = '', ?PDO $pdo = null): string
{
    $src = trim((string) $src);
    if ($src === '') {
        return '';
    }
    require_once __DIR__ . '/site_content_urls.php';
    $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $srcEsc = htmlspecialchars(site_media_public_src($src, $pdo), ENT_QUOTES, 'UTF-8');

    return '<div class="sss-page-hero-img"><img src="' . $srcEsc . '" alt="' . $altEsc . '" loading="lazy"></div>';
}

/**
 * @param list<array{0:string,1:string}> $pairs [title_key, content_key]
 * @param list<string> $sectionIcons
 */
function info_page_legacy_blocks_html(?array $row, array $pairs, array $sectionIcons = [], ?string $imagePath = null, ?PDO $pdo = null): string
{
    if (! is_array($row) || $row === []) {
        return '';
    }

    $items = [];
    foreach ($pairs as $i => $pair) {
        [$titleKey, $contentKey] = $pair;
        $q = trim((string) ($row[$titleKey] ?? ''));
        $c = trim((string) ($row[$contentKey] ?? ''));
        if ($q === '' || $c === '') {
            continue;
        }
        $items[] = [
            'q' => $q,
            'a' => nl2br(htmlspecialchars($c, ENT_QUOTES, 'UTF-8')),
            'icon' => $sectionIcons[$i] ?? 'fa-circle-question',
        ];
    }

    if ($items === []) {
        return '';
    }

    $html = info_page_hero_image_html($imagePath, (string) ($items[0]['q'] ?? ''), $pdo);

    return $html . sss_faq_build_html($items, true);
}

function info_page_normalize_html(string $html, array $sectionIcons = [], bool $firstOpen = true): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (str_contains($html, 'sss-faq-list')) {
        return $html;
    }

    $accordion = legal_accordion_normalize_body($html, $sectionIcons, $firstOpen);
    if (str_contains($accordion, 'sss-faq-list')) {
        return $accordion;
    }

    return '<div class="sss-faq-prose">' . $html . '</div>';
}

/**
 * CMS → accordion; yoksa legacy bloklar; yoksa şablon HTML.
 *
 * @param list<array{0:string,1:string}> $legacyPairs
 * @param list<string> $sectionIcons
 */
function info_page_resolve_body(
    PDO $pdo,
    string $cmsSlug,
    ?array $legacyRow,
    array $legacyPairs,
    array $sectionIcons,
    string $fallbackHtml,
    bool $legacyHasContent
): string {
    $cmsRow = cms_page_fetch($pdo, $cmsSlug);
    $cmsHero = null;
    if ($cmsRow) {
        $heroRaw = trim((string) ($cmsRow['hero_image'] ?? ''));
        if ($heroRaw !== '') {
            $cmsHero = $heroRaw;
        }
    }

    $cms = cms_page_resolve($pdo, $cmsSlug, $legacyHasContent);
    if ($cms && (cms_page_content_has_text($cms) || $cmsHero !== null)) {
        $html = (string) ($cms['html_content'] ?? '');
        $hero = info_page_hero_image_html(
            $cmsHero,
            (string) ($cms['title'] ?? ''),
            $pdo
        );

        return $hero . info_page_normalize_html($html, $sectionIcons);
    }

    $legacyImage = is_array($legacyRow) ? trim((string) ($legacyRow['image'] ?? '')) : '';
    if ($cmsHero !== null) {
        $legacyImage = $cmsHero;
    }

    $legacyHtml = info_page_legacy_blocks_html(
        $legacyRow,
        $legacyPairs,
        $sectionIcons,
        $legacyImage !== '' ? $legacyImage : null,
        $pdo
    );
    if ($legacyHtml !== '') {
        return $legacyHtml;
    }

    $fallbackHero = info_page_hero_image_html($cmsHero, '', $pdo);

    return $fallbackHero . info_page_normalize_html($fallbackHtml, $sectionIcons);
}

/** @param array<string,mixed> $config */
function info_page_render(PDO $pdo, array $config): void
{
    require_once __DIR__.'/attribution_bootstrap.php';

    if (! isset($config['trust_pills'])) {
        $config['trust_pills'] = info_page_default_trust_pills();
    }
    if (($config['layout'] ?? '') === 'legal' && ! str_contains((string) ($config['page_class'] ?? ''), 'sss-page-view--legal')) {
        $config['page_class'] = trim((string) ($config['page_class'] ?? '') . ' sss-page-view--legal');
    }

    sss_page_render($pdo, $config);
}
