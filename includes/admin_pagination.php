<?php
declare(strict_types=1);

/**
 * Sayfa numarası penceresi (taşmayı önlemek için sınırlı görünür sayfa).
 *
 * @return array{0:int,1:int} [start, end]
 */
function admin_pagination_window(int $page, int $totalPages, int $maxVisible = 5): array
{
    if ($totalPages < 1) {
        return [1, 1];
    }

    $page = max(1, min($page, $totalPages));
    $maxVisible = max(3, $maxVisible);
    $start = max(1, $page - (int) floor($maxVisible / 2));
    $end = min($totalPages, $start + $maxVisible - 1);

    if ($end - $start + 1 < $maxVisible) {
        $start = max(1, $end - $maxVisible + 1);
    }

    return [$start, $end];
}

/**
 * Standart admin sayfalandırma (İlk / « / numaralar / » / Son).
 *
 * @param callable(int):string $pageUrl Sayfa numarası → tam href
 */
function admin_render_pagination(int $page, int $totalPages, callable $pageUrl, int $maxVisible = 5): void
{
    if ($totalPages <= 1) {
        return;
    }

    $page = max(1, min($page, $totalPages));
    [$start, $end] = admin_pagination_window($page, $totalPages, $maxVisible);

    echo '<nav class="admin-pager" aria-label="Sayfa navigasyonu">';
    echo '<div class="admin-pager__meta">Sayfa ' . (int) $page . ' / ' . (int) $totalPages . '</div>';
    echo '<ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">';

    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl(1), ENT_QUOTES, 'UTF-8') . '">İlk</a></li>';
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl($page - 1), ENT_QUOTES, 'UTF-8') . '">«</a></li>';
    }

    for ($i = $start; $i <= $end; ++$i) {
        $active = $i === $page ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="' . htmlspecialchars($pageUrl($i), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
    }

    if ($page < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl($page + 1), ENT_QUOTES, 'UTF-8') . '">»</a></li>';
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl($totalPages), ENT_QUOTES, 'UTF-8') . '">Son</a></li>';
    }

    echo '</ul></nav>';
}
