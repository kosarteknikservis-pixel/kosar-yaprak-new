<?php
declare(strict_types=1);
/**
 * Meta / TikTok / gtag / pa embed — tüm vitrin sayfalarında bir kez basılır.
 * Önkoşul: $pdo (PDO) tanımlı olmalı.
 */
static $siteTrackingHeadRendered = false;
if ($siteTrackingHeadRendered) {
    return;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}
$siteTrackingHeadRendered = true;
require_once __DIR__ . '/conversion_tracking.php';
echo conversion_render_frontend_snippets($pdo);
