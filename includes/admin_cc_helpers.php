<?php
declare(strict_types=1);

/** Telefon → sadece rakam (son 10 hane için arama) */
function cc_phone_digits(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';

    return $d;
}

function cc_phone_tail10(string $raw): string
{
    $d = cc_phone_digits($raw);

    return strlen($d) >= 10 ? substr($d, -10) : $d;
}

/** WhatsApp wa.me linki */
function cc_whatsapp_href(string $raw, string $text = ''): string
{
    $d = cc_phone_digits($raw);
    if ($d === '') {
        return '#';
    }
    if (str_starts_with($d, '0')) {
        $d = substr($d, 1);
    }
    if (!str_starts_with($d, '90')) {
        $d = '90' . $d;
    }

    $url = 'https://wa.me/' . $d;
    if ($text !== '') {
        $url .= '?text=' . rawurlencode($text);
    }

    return $url;
}

function cc_tel_href(string $raw): string
{
    $d = cc_phone_digits($raw);

    return $d !== '' ? 'tel:+' . ltrim($d, '+') : '#';
}

/**
 * @return array{class: string, label: string}
 */
function cc_status_badge(string $status): array
{
    $s = mb_strtolower(trim($status), 'UTF-8');

    return match (true) {
        str_contains($s, 'onay') => ['cc-badge--ok', $status],
        str_contains($s, 'kargo') || str_contains($s, 'teslim') => ['cc-badge--ship', $status],
        str_contains($s, 'bekle') => ['cc-badge--wait', $status],
        str_contains($s, 'aran') || str_contains($s, 'randevu') => ['cc-badge--call', $status],
        str_contains($s, 'ulaş') || str_contains($s, 'iptal') || str_contains($s, 'iade') || str_contains($s, 'mükerrer') => ['cc-badge--bad', $status],
        default => ['cc-badge--muted', $status],
    };
}

/** Form payload içinden telefon alanı bul */
function cc_extract_phone_from_payload(array $payload): string
{
    foreach (['telefon', 'tel', 'phone', 'gsm', 'customer_phone', 'cep'] as $key) {
        if (!empty($payload[$key]) && is_scalar($payload[$key])) {
            return trim((string) $payload[$key]);
        }
    }
    foreach ($payload as $k => $v) {
        if (!is_scalar($v)) {
            continue;
        }
        $lk = mb_strtolower((string) $k, 'UTF-8');
        if (str_contains($lk, 'tel') || str_contains($lk, 'phone') || str_contains($lk, 'gsm')) {
            return trim((string) $v);
        }
    }

    return '';
}

function cc_phone_actions_html(string $phone, bool $compact = false, string $whatsappText = ''): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '<span class="text-muted">—</span>';
    }

    $wa = htmlspecialchars(cc_whatsapp_href($phone, $whatsappText), ENT_QUOTES, 'UTF-8');
    $tel = htmlspecialchars(cc_tel_href($phone), ENT_QUOTES, 'UTF-8');
    $disp = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    $cls = $compact ? ' cc-phone-actions--compact' : '';

    return '<div class="cc-phone-actions' . $cls . '">'
        . '<a class="cc-phone-actions__num" href="' . $tel . '">' . $disp . '</a>'
        . '<a class="cc-phone-actions__btn cc-phone-actions__btn--call" href="' . $tel . '" title="Ara"><i class="fas fa-phone"></i></a>'
        . '<a class="cc-phone-actions__btn cc-phone-actions__btn--wa" href="' . $wa . '" target="_blank" rel="noopener" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>'
        . '</div>';
}
