<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';

info_page_track_view($pdo, 'kargo_sureci.php');

$shippingRow = $pdo->query('SELECT * FROM shipping_process LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
$legacyPairs = [
    ['title_1', 'content_1'],
    ['title_2', 'content_2'],
    ['title_3', 'content_3'],
];
$icons = ['fa-clipboard-check', 'fa-box', 'fa-truck-fast', 'fa-search'];
$bodyHtml = info_page_resolve_body(
    $pdo,
    'kargo-sureci',
    is_array($shippingRow) ? $shippingRow : null,
    $legacyPairs,
    $icons,
    legal_kargo_sureci_html(),
    cms_row_has_text($shippingRow, ['title_1', 'content_1', 'title_2', 'content_2'])
);

$cms = cms_page_fetch($pdo, 'kargo-sureci');
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'Kargo Süreci';

info_page_render($pdo, [
    'page_file' => 'kargo_sureci.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_shipping',
    'layout' => 'cms',
    'page_class' => 'sss-page-view--content',
    'hero_badge' => 'Teslimat',
    'hero_badge_icon' => 'fa-shipping-fast',
    'hero_lead' => 'Siparişiniz onaylandıktan sonra kargoya veriliş ve teslimat adımları.',
    'trust_pills' => [
        ['icon' => 'fa-truck', 'label' => '1–3 iş günü kargo'],
        ['icon' => 'fa-location-dot', 'label' => 'Adrese teslim'],
        ['icon' => 'fa-search', 'label' => 'Sipariş takibi'],
    ],
    'section_icons' => $icons,
    'cta_title' => 'Siparişinizi takip edin',
    'cta_text' => 'Telefon numaranızla anlık durum sorgulayın veya yeni sipariş verin.',
]);
