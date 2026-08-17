<?php
declare(strict_types=1);

require_once __DIR__ . '/footer_constants.php';

/** @return array{display_mode:string, images:array<int, array<string,mixed>>} */
function site_footer_load(PDO $pdo): array
{
    $mode = 'image';
    try {
        $st = $pdo->prepare('SELECT footer_display_mode FROM footer_images WHERE id = ? LIMIT 1');
        $st->execute([FOOTER_DISPLAY_ROW_ID]);
        $raw = $st->fetchColumn();
        if (is_string($raw) && in_array($raw, ['image', 'legal', 'both'], true)) {
            $mode = $raw;
        }
    } catch (Throwable $e) {
        $mode = 'image';
    }

    $images = [];
    if ($mode === 'image' || $mode === 'both') {
        $ids = footer_reserved_row_ids();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, image_path, created_at FROM footer_images
            WHERE id NOT IN ({$placeholders})
            AND image_path IS NOT NULL AND image_path != ''
            ORDER BY created_at DESC";
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $images = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return ['display_mode' => $mode, 'images' => $images];
}

function site_footer_image_src(string $imagePath): string
{
    $path = str_replace('\\', '/', $imagePath);
    if (str_starts_with($path, '../')) {
        $path = substr($path, 3);
    }
    if (str_starts_with($path, 'uploads/')) {
        return $path;
    }

    return 'uploads/' . ltrim($path, '/');
}

function site_footer_render(PDO $pdo): void
{
    $footer = site_footer_load($pdo);
    $mode = $footer['display_mode'];
    $showImages = ($mode === 'image' || $mode === 'both') && $footer['images'] !== [];
    $showLegal = $mode === 'legal' || $mode === 'both';

    if (!$showImages && !$showLegal) {
        return;
    }

    echo '<footer class="site-footer" role="contentinfo">';

    if ($showImages) {
        echo '<div class="site-footer__images">';
        foreach ($footer['images'] as $image) {
            $src = site_footer_image_src((string) ($image['image_path'] ?? ''));
            echo '<div class="site-footer__image">';
            echo '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" class="d-block w-100" alt="" loading="lazy" onerror="this.style.display=\'none\'">';
            echo '</div>';
        }
        echo '</div>';
    }

    if ($showLegal) {
        echo '<nav class="site-footer__legal" aria-label="' . htmlspecialchars(function_exists('t') ? t('footer.legal', 'Yasal bilgiler') : 'Yasal bilgiler', ENT_QUOTES, 'UTF-8') . '">';
        echo '<a href="kvkk.php">' . htmlspecialchars(function_exists('t') ? t('footer.kvkk', 'KVKK') : 'KVKK', ENT_QUOTES, 'UTF-8') . '</a>';
        echo '<a href="mesafeli_satis.php">' . htmlspecialchars(function_exists('t') ? t('footer.distance', 'Mesafeli Satış') : 'Mesafeli Satış', ENT_QUOTES, 'UTF-8') . '</a>';
        echo '<a href="iade_degisim.php">' . htmlspecialchars(function_exists('t') ? t('footer.returns', 'İade & Değişim') : 'İade & Değişim', ENT_QUOTES, 'UTF-8') . '</a>';
        echo '<a href="sss.php">' . htmlspecialchars(function_exists('t') ? t('menu.faq', 'Sıkça Sorulan Sorular') : 'Sıkça Sorulan Sorular', ENT_QUOTES, 'UTF-8') . '</a>';
        echo '</nav>';
    }

    echo '</footer>';
}
