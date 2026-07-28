<?php
declare(strict_types=1);

/**
 * @return array{id:int, slug:string, title:string, html_content:?string, hero_image:?string, meta_title:?string, meta_description:?string, prefer_cms:int}|null
 */
function cms_page_fetch(PDO $pdo, string $slug): ?array
{
    $st = $pdo->prepare(
        'SELECT id, slug, title, hero_image, html_content, meta_title, meta_description, prefer_cms, head_extra
        FROM cms_pages WHERE slug = ? AND is_active = 1 LIMIT 1'
    );
    $st->execute([$slug]);
    /** @var array<string,mixed>|false $row */
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) && $row ? $row : null;
}

/** @deprecated use cms_page_resolve */
function cms_page_if_preferred(PDO $pdo, string $slug): ?array
{
    $row = cms_page_fetch($pdo, $slug);
    if (!$row || empty((int) ($row['prefer_cms'] ?? 0))) {
        return null;
    }

    return $row;
}

function cms_page_content_has_text(?array $cmsRow): bool
{
    if (!$cmsRow) {
        return false;
    }
    $html = trim(strip_tags((string) ($cmsRow['html_content'] ?? '')));

    return $html !== '';
}

/** @param array<string,mixed>|null $row */
function cms_row_has_text(?array $row, array $fields): bool
{
    if (!$row) {
        return false;
    }
    foreach ($fields as $field) {
        if (trim((string) ($row[$field] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * CMS tercih edilmişse CMS; eski tablo boşsa ve CMS doluysa CMS; aksi halde null (legacy kullan).
 *
 * @return array<string,mixed>|null
 */
function cms_page_resolve(PDO $pdo, string $slug, bool $legacyHasContent): ?array
{
    $cms = cms_page_fetch($pdo, $slug);
    if (!$cms) {
        return null;
    }
    if (!empty((int) ($cms['prefer_cms'] ?? 0))) {
        return $cms;
    }
    if (!$legacyHasContent && cms_page_content_has_text($cms)) {
        return $cms;
    }

    return null;
}
