<?php
declare(strict_types=1);

/** Türkiye paneli — para birimi, ondalık ve tarih biçimleri */
function admin_tr_money(float|int|string|null $amount, int $decimals = 2): string
{
    $n = is_numeric($amount) ? (float) $amount : 0.0;

    return number_format($n, $decimals, ',', '.') . ' TL';
}

function admin_tr_number(float|int|string|null $value, int $decimals = 0): string
{
    $n = is_numeric($value) ? (float) $value : 0.0;

    return number_format($n, $decimals, ',', '.');
}

function admin_tr_percent(float|int|string|null $value, int $decimals = 1): string
{
    return '%' . admin_tr_number($value, $decimals);
}

function admin_tr_datetime(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d.m.Y H:i', $ts) : $raw;
}

function admin_tr_date(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d.m.Y', $ts) : $raw;
}

/** Meta / dönüşüm API — Türkçe etiket sözlüğü */
function admin_tr_conversion_label(string $key): string
{
    return match ($key) {
        'purchase' => 'Satın alma (purchase)',
        'view_item' => 'Ürün görüntüleme',
        'begin_checkout' => 'Ödeme başlangıcı',
        'complete_payment' => 'Ödeme tamamlandı',
        default => $key,
    };
}
