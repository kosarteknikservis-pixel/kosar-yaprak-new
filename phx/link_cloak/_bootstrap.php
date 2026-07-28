<?php
declare(strict_types=1);

require __DIR__ . '/../../db.php';
require __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../includes/link_cloak/LinkCloakService.php';
require_once __DIR__ . '/../../includes/cloaker_helpers.php';

require_once __DIR__ . '/../../includes/admin_paths.php';

function lc_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');

    return admin_url('link_cloak/' . $path);
}

function lc_href(string $path = ''): string
{
    return htmlspecialchars(lc_url($path), ENT_QUOTES, 'UTF-8');
}

function link_cloak_admin_header(string $pageTitle): void
{
    global $page_title;
    $page_title = $pageTitle;
    require __DIR__ . '/../admin_header.php';
}
