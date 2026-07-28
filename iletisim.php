<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';

info_page_track_view($pdo, 'iletisim.php');

$contactRow = $pdo->query('SELECT * FROM contact_info LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
$legacyPairs = [
    ['title_1', 'content_1'],
    ['title_2', 'content_2'],
];
$icons = ['fa-headset', 'fa-search', 'fa-clock'];
$bodyHtml = info_page_resolve_body(
    $pdo,
    'iletisim',
    is_array($contactRow) ? $contactRow : null,
    $legacyPairs,
    $icons,
    legal_iletisim_html(),
    cms_row_has_text($contactRow, ['title_1', 'content_1', 'title_2', 'content_2'])
);

$cms = cms_page_fetch($pdo, 'iletisim');
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'Bize Ulaşın';

info_page_render($pdo, [
    'page_file' => 'iletisim.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_contact',
    'layout' => 'cms',
    'page_class' => 'sss-page-view--content',
    'hero_badge' => 'İletişim',
    'hero_badge_icon' => 'fa-envelope',
    'hero_lead' => 'Sorularınız ve talepleriniz için doğru kanallar — hızlı dönüş.',
    'section_icons' => $icons,
    'cta_title' => 'Hemen yazın',
    'cta_text' => 'Destek talebi açın veya siparişinizi sorgulayın.',
]);
