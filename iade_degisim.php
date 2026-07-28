<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';
require_once __DIR__ . '/includes/cms_helper.php';

$defaultHtml = legal_iade_degisim_html();
$cms = cms_page_resolve($pdo, 'iade-degisim', trim(strip_tags($defaultHtml)) !== '');
$bodyHtml = ($cms && cms_page_content_has_text($cms)) ? (string) $cms['html_content'] : $defaultHtml;
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'İade ve Değişim';

info_page_render($pdo, [
    'page_file' => 'iade_degisim.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_iade_degisim',
    'layout' => 'legal',
    'page_class' => 'sss-page-view--legal',
    'hero_badge' => 'İade & değişim',
    'hero_badge_icon' => 'fa-rotate-left',
    'hero_lead' => 'Cayma hakkı, iade adımları ve değişim koşulları — madde madde, tek dokunuşla.',
    'trust_pills' => [
        ['icon' => 'fa-calendar-check', 'label' => '14 gün cayma'],
        ['icon' => 'fa-truck', 'label' => '1–3 iş günü kargo'],
        ['icon' => 'fa-headset', 'label' => 'Destek hattı'],
    ],
    'section_icons' => [
        'fa-calendar-days',
        'fa-list-ol',
        'fa-arrows-rotate',
        'fa-ban',
        'fa-box',
    ],
    'cta_title' => 'İade veya değişim mi istiyorsunuz?',
    'cta_text' => 'Destek formundan talebinizi iletin; ekibimiz süreci sizinle birlikte yürütür.',
]);
