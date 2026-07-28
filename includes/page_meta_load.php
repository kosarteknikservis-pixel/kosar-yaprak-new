<?php
declare(strict_types=1);

/**
 * Load page_meta row for given page name.
 * Tries exact match first; if not found, tries without ".php".
 */
function page_meta_load(PDO $pdo, string $pageName): ?array
{
    $pageName = trim($pageName);
    if ($pageName === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM page_meta WHERE page_name = ? LIMIT 1');
    $stmt->execute([$pageName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && $row) {
        return $row;
    }

    if (str_ends_with($pageName, '.php')) {
        $alt = substr($pageName, 0, -4);
        if ($alt !== '') {
            $stmt->execute([$alt]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && $row) {
                return $row;
            }
        }
    }

    return null;
}

