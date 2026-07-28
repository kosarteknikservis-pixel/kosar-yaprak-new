<?php
declare(strict_types=1);

/**
 * Üst bildirim şeridi HTML’i — mesajda | varsa iki kademeli (dönem / ana teklif), yoksa tek blok.
 *
 * Örnek panel metni: "EKİM AYI | TEK FİYAT"
 */
function promo_banner_markup(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }

    if (strpos($message, '|') === false) {
        return '<div class="promo-banner promo-banner--plain">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    $parts = explode('|', $message, 2);
    $left = trim((string) ($parts[0] ?? ''));
    $right = trim((string) ($parts[1] ?? ''));

    if ($left === '' && $right === '') {
        return '';
    }

    if ($right === '') {
        return '<div class="promo-banner promo-banner--plain">' . htmlspecialchars($left, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    if ($left === '') {
        return '<div class="promo-banner promo-banner--plain">' . htmlspecialchars($right, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    return '<div class="promo-banner promo-banner--split" role="note">'
        . '<span class="promo-banner__kicker">' . htmlspecialchars($left, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="promo-banner__headline">' . htmlspecialchars($right, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</div>';
}
