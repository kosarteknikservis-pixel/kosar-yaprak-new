<?php
require 'db.php';
require_once __DIR__ . '/includes/legal_templates.php';
require_once __DIR__ . '/includes/info_pages.php';
require_once __DIR__ . '/includes/cms_helper.php';

$defaultHtml = legal_sss_html();
$cms = cms_page_resolve($pdo, 'sss', trim(strip_tags($defaultHtml)) !== '');
$bodyHtml = ($cms && cms_page_content_has_text($cms)) ? (string) $cms['html_content'] : $defaultHtml;
$pageTitle = ($cms && trim((string) ($cms['title'] ?? '')) !== '') ? (string) $cms['title'] : 'Sıkça Sorulan Sorular';

info_page_render($pdo, [
    'page_file' => 'sss.php',
    'title' => $pageTitle,
    'body_html' => $bodyHtml,
    'track_event' => 'page_sss',
]);
