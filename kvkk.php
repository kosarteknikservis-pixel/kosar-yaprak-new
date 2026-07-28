<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';
require_once __DIR__ . '/includes/cms_helper.php';

$defaultHtml = legal_kvkk_html();
$cms = cms_page_resolve($pdo, 'kvkk', trim(strip_tags($defaultHtml)) !== '');
$bodyHtml = ($cms && cms_page_content_has_text($cms)) ? (string) $cms['html_content'] : $defaultHtml;
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'KVKK Aydınlatma Metni';

info_page_render($pdo, [
    'page_file' => 'kvkk.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_kvkk',
    'layout' => 'legal',
    'page_class' => 'sss-page-view--legal',
    'hero_badge' => 'Gizlilik & KVKK',
    'hero_badge_icon' => 'fa-shield-halved',
    'hero_lead' => 'Kişisel verilerinizin nasıl işlendiği, saklandığı ve haklarınız — şeffaf ve anlaşılır.',
    'trust_pills' => [
        ['icon' => 'fa-lock', 'label' => 'Veri güvenliği'],
        ['icon' => 'fa-scale-balanced', 'label' => '6698 KVKK'],
        ['icon' => 'fa-user-check', 'label' => 'Haklarınız'],
    ],
    'section_icons' => [
        'fa-building',
        'fa-database',
        'fa-bullseye',
        'fa-share-nodes',
        'fa-clock',
        'fa-gavel',
        'fa-shield',
    ],
    'first_open' => true,
    'cta_title' => 'Verileriniz hakkında sorunuz mu var?',
    'cta_text' => 'KVKK kapsamındaki talepleriniz için destek kanallarımızdan bize ulaşabilirsiniz.',
]);
