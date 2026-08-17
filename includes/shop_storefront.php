<?php
declare(strict_types=1);

/**
 * Vitrin fiyat / kargo / taksit gösterimi — mevcut kart düzenini bozmaz.
 */

function shop_use_save_badge(?PDO $pdo = null): bool
{
    $lang = function_exists('current_lang') ? current_lang($pdo) : 'tr';
    $code = function_exists('current_currency_code') ? current_currency_code($pdo) : 'TRY';

    return $lang !== 'tr' || in_array($code, ['AUD', 'USD', 'GBP', 'NZD', 'CAD'], true);
}

function shop_heading_localized(string $field, string $original, string $fallbackKey, string $fallbackText): string
{
    $original = trim($original);
    $out = function_exists('content_t') ? content_t('homepage_section', 1, $field, $original) : $original;
    $lang = function_exists('current_lang') ? current_lang() : 'tr';
    if ($lang !== 'tr' && ($out === '' || $out === $original)) {
        return function_exists('t') ? t($fallbackKey, $fallbackText) : $fallbackText;
    }

    return $out !== '' ? $out : $fallbackText;
}

function shop_paytr_max_installment(?PDO $pdo = null): int
{
    $pdo = $pdo ?? (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : null);
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $paytr = $pdo->query("SELECT COUNT(*) FROM payment_methods WHERE is_active = 1 AND gateway_code = 'paytr'")->fetchColumn();
        if ((int) $paytr < 1) {
            return 0;
        }
        $cfg = $pdo->query('SELECT no_installment, max_installment FROM paytr_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return 0;
    }
    if (!is_array($cfg) || !empty($cfg['no_installment'])) {
        return 0;
    }
    $max = (int) ($cfg['max_installment'] ?? 0);

    return $max >= 2 ? $max : 0;
}

function shop_interest_free_label(float $saleTry, ?PDO $pdo = null): string
{
    $n = shop_paytr_max_installment($pdo);
    if ($n < 2 || $saleTry <= 0) {
        return '';
    }
    $each = $saleTry / $n;
    $amount = function_exists('money') ? money($each) : number_format($each, 2, ',', '.') . ' TL';
    $tpl = function_exists('t')
        ? t('shop.interest_free', '{n} interest-free payments of {amount}')
        : '{n} interest-free payments of {amount}';

    return str_replace(['{n}', '{amount}'], [(string) $n, $amount], $tpl);
}

function shop_time_label_localized(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || !function_exists('current_lang') || current_lang() === 'tr') {
        return $raw;
    }
    $lower = mb_strtolower($raw, 'UTF-8');
    if (in_array($lower, ['az önce', 'biraz önce', 'az once', 'biraz once'], true)) {
        return function_exists('t') ? t('shop.just_now', 'just now') : 'just now';
    }
    if (preg_match('/^(\d+)\s*dk\s*önce$/u', $lower, $m)) {
        $n = (int) $m[1];
        $tpl = function_exists('t') ? t('shop.mins_ago', '{n} min ago') : '{n} min ago';

        return str_replace('{n}', (string) $n, $tpl);
    }

    return $raw;
}

function shop_ui_norm(string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    $s = str_replace(['İ', 'I'], ['i', 'ı'], $s);

    return mb_strtolower($s, 'UTF-8');
}

function shop_ui_text(string $original): string
{
    $original = trim($original);
    if ($original === '' || !function_exists('current_lang') || current_lang() === 'tr' || !function_exists('t')) {
        return $original;
    }
    if (str_contains($original, '|')) {
        $parts = array_map('trim', explode('|', $original, 2));

        return implode(' | ', array_map('shop_ui_text', $parts));
    }
    static $map = null;
    if ($map === null) {
        $map = [
            'sepetiniz' => ['shop.your_cart', 'YOUR CART'],
            'kargonuz ilk iş günü içerisinde kargoya verilecektir.' => ['shop.ship_first_day', 'Your order will be shipped on the first business day.'],
            'kargonuz 1–3 iş günü içinde kargoya verilir.' => ['shop.ship_1_3', 'Your order will be shipped within 1–3 business days.'],
            'kargonuz 1-3 iş günü içinde kargoya verilir.' => ['shop.ship_1_3', 'Your order will be shipped within 1–3 business days.'],
            'siparişinizle ilgili notlarınızı buraya ekleyebilirsiniz.' => ['order.note_placeholder', 'Add a note about your order if needed.'],
            'ağustos ayı tek fiyat' => ['shop.promo_august', 'AUGUST SINGLE PRICE'],
            'yaz için son indirim bugün' => ['shop.promo_summer', 'LAST SUMMER DISCOUNT TODAY'],
            'ekim ayı tek fiyat' => ['shop.promo_month', 'THIS MONTH SINGLE PRICE'],
            'tek fiyat' => ['shop.promo_single', 'SINGLE PRICE'],
            'kapıda nakit ödeme' => ['trust.cod_cash', 'Cash on Delivery'],
            'kapıda kredi/banka kartı ile ödeme' => ['trust.cod_card', 'Card on Delivery'],
            'kapıda kredi kartı ile ödeme' => ['trust.cod_card', 'Card on Delivery'],
            'kredi kartı (paytr)' => ['trust.online_card', 'Online Credit Card'],
            'online kredi kartı' => ['trust.online_card', 'Online Credit Card'],
            'havale / eft' => ['trust.bank', 'Bank Transfer'],
            'bu web sitesi sertifikalı güvenlidir' => ['shop.certified_secure', 'This website is certified secure'],
            'bilgileriniz saklanmaz ve 3. kişiler ile kesinlikle paylaşılmaz' => ['shop.privacy_note', 'Your information is not stored and is never shared with third parties'],
            'indirim süresi dolmak üzere!' => ['cd.soon', 'Sale ending soon!'],
            'süre dolmak üzere!' => ['cd.ending', 'Ending soon!'],
            'fırsat' => ['cd.deal', 'DEAL'],
            'ücretsiz kargo' => ['perk.free_shipping', 'Free Shipping'],
        ];
    }
    $key = shop_ui_norm($original);
    if (isset($map[$key])) {
        [$tk, $fb] = $map[$key];

        return t($tk, $fb);
    }

    return $original;
}

function shop_panel_text(string $original, string $entityType = '', int $entityId = 0, string $field = ''): string
{
    $original = trim($original);
    if ($original === '') {
        return '';
    }
    if ($entityType !== '' && $entityId > 0 && function_exists('content_t')) {
        $via = content_t($entityType, $entityId, $field, $original);
        if ($via !== '' && $via !== $original) {
            return $via;
        }
        if (function_exists('current_lang') && current_lang() === 'tr') {
            return $original;
        }
    }

    return shop_ui_text($original);
}

function payment_method_label(int $id, string $name): string
{
    $name = trim($name);
    if ($id > 0 && function_exists('content_t')) {
        $via = content_t('payment_method', $id, 'name', $name);
        if ($via !== '' && $via !== $name) {
            return $via;
        }
    }
    if (function_exists('current_lang') && current_lang() === 'tr') {
        return $name;
    }
    $lower = shop_ui_norm($name);
    if (str_contains($lower, 'nakit')) {
        return function_exists('t') ? t('trust.cod_cash', 'Cash on Delivery') : 'Cash on Delivery';
    }
    if (str_contains($lower, 'paytr') || str_contains($lower, 'iyzico') || str_contains($lower, 'online')) {
        return function_exists('t') ? t('trust.online_card', 'Online Credit Card') : 'Online Credit Card';
    }
    if (str_contains($lower, 'havale') || str_contains($lower, 'eft')) {
        return function_exists('t') ? t('trust.bank', 'Bank Transfer') : 'Bank Transfer';
    }
    if (str_contains($lower, 'kart')) {
        return function_exists('t') ? t('trust.cod_card', 'Card on Delivery') : 'Card on Delivery';
    }

    return shop_ui_text($name);
}

/**
 * @return list<array{icon:string,title:string,text:string,tone:string}>
 */
function shop_footer_info_tiles(): array
{
    return [
        ['icon' => 'fa-money-bill-wave', 'title' => function_exists('t') ? t('trust.cod_cash', 'Kapıda Nakit Ödeme') : 'Kapıda Nakit Ödeme', 'text' => function_exists('t') ? t('footer.tile_cod_cash', 'Pay in cash when your order arrives.') : 'Pay in cash when your order arrives.', 'tone' => 'green'],
        ['icon' => 'fa-credit-card', 'title' => function_exists('t') ? t('trust.cod_card', 'Kapıda Kart ile Ödeme') : 'Kapıda Kart ile Ödeme', 'text' => function_exists('t') ? t('footer.tile_cod_card', 'Pay by card at the door.') : 'Pay by card at the door.', 'tone' => 'teal'],
        ['icon' => 'fa-truck-fast', 'title' => function_exists('t') ? t('shop.fast_shipping', 'Hızlı kargo') : 'Hızlı kargo', 'text' => function_exists('t') ? t('footer.tile_ship', 'Orders are dispatched the same day.') : 'Orders are dispatched the same day.', 'tone' => 'orange'],
        ['icon' => 'fa-shield-halved', 'title' => function_exists('t') ? t('shop.safe_shopping', 'Güvenli alışveriş') : 'Güvenli alışveriş', 'text' => function_exists('t') ? t('footer.tile_ssl', '256-bit SSL encrypted checkout.') : '256-bit SSL encrypted checkout.', 'tone' => 'blue'],
    ];
}

function shop_fake_alerts_localized(array $alerts): array
{
    $out = [];
    foreach ($alerts as $row) {
        $out[] = [
            'customer_name' => (string) ($row['customer_name'] ?? ''),
            'city_name' => (string) ($row['city_name'] ?? ''),
            'time_label' => shop_time_label_localized((string) ($row['time_label'] ?? '')),
        ];
    }

    return $out;
}
