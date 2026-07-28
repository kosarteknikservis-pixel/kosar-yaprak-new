<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';

info_page_track_view($pdo, 'hakkimizda.php');

$aboutRow = $pdo->query('SELECT * FROM about_us LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
$legacyPairs = [
    ['title_1', 'content_1'],
    ['title_2', 'content_2'],
    ['title_3', 'content_3'],
    ['title_4', 'content_4'],
];
$icons = ['fa-store', 'fa-star', 'fa-truck', 'fa-heart'];
$bodyHtml = info_page_resolve_body(
    $pdo,
    'hakkimizda',
    is_array($aboutRow) ? $aboutRow : null,
    $legacyPairs,
    $icons,
    legal_hakkimizda_html(),
    cms_row_has_text($aboutRow, ['title_1', 'content_1', 'title_2', 'content_2'])
);

$cms = cms_page_fetch($pdo, 'hakkimizda');
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'Hakkımızda';

info_page_render($pdo, [
    'page_file' => 'hakkimizda.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_about',
    'layout' => 'cms',
    'page_class' => 'sss-page-view--content',
    'hero_badge' => 'Mağazamız',
    'hero_badge_icon' => 'fa-info-circle',
    'hero_lead' => 'Kim olduğumuz, nasıl çalıştığımız ve size nasıl hizmet verdiğimiz.',
    'section_icons' => $icons,
    'cta_title' => 'Alışverişe hazır mısınız?',
    'cta_text' => 'Ürünlerimize göz atın veya siparişinizi hemen oluşturun.',
]);
