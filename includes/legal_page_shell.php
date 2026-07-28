<?php
declare(strict_types=1);

/**
 * @deprecated info_pages.php → info_page_render() kullanın
 * @param array{title:string, body_html:string, page_file:string, track_event?:string} $config
 */
function legal_page_render(PDO $pdo, array $config): void
{
    require_once __DIR__ . '/info_pages.php';
    info_page_render($pdo, $config);
}
