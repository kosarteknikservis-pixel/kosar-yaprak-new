<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';

info_page_track_view($pdo, 'mesafeli_satis.php');

$defaultHtml = legal_mesafeli_satis_html();
$cms = cms_page_resolve($pdo, 'mesafeli-satis', trim(strip_tags($defaultHtml)) !== '');
$bodyHtml = ($cms && cms_page_content_has_text($cms)) ? (string) $cms['html_content'] : $defaultHtml;
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'Mesafeli Satış Sözleşmesi';

info_page_render($pdo, [
    'page_file' => 'mesafeli_satis.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_mesafeli_satis',
    'layout' => 'legal',
    'hero_badge' => 'Yasal bilgi',
    'hero_badge_icon' => 'fa-file-contract',
    'hero_lead' => 'Mesafeli satış sözleşmesi, tarafların hak ve yükümlülükleri.',
    'trust_pills' => [
        ['icon' => 'fa-scale-balanced', 'label' => '6502 sayılı kanun'],
        ['icon' => 'fa-calendar-days', 'label' => '14 gün cayma'],
        ['icon' => 'fa-shield-halved', 'label' => 'Güvenli alışveriş'],
    ],
    'section_icons' => ['fa-users', 'fa-box', 'fa-tags', 'fa-truck', 'fa-rotate-left', 'fa-credit-card', 'fa-gavel'],
    'cta_title' => 'Sorularınız mı var?',
    'cta_text' => 'Sözleşme veya siparişiniz hakkında destek ekibimize ulaşın.',
]);
