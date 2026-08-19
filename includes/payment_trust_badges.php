<?php

declare(strict_types=1);

/**
 * Aktif ödeme yöntemlerinden vitrin güven rozetleri üretir.
 *
 * @return list<array{icon:string,label:string,tone:string}>
 */
function payment_trust_badges_collect(PDO $pdo): array
{
    if (is_file(__DIR__ . '/payment_methods_sync.php')) {
        require_once __DIR__ . '/payment_methods_sync.php';
        payment_methods_sync_online_gateways($pdo);
    }

    try {
        $stmt = $pdo->query(
            'SELECT payment_method_id, method_name, gateway_code FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, method_name'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }

    $badges = [];
    $seen = [];

    foreach ($rows as $row) {
        $gw = trim((string) ($row['gateway_code'] ?? 'cod'));
        $name = trim((string) ($row['method_name'] ?? ''));
        $nameLower = mb_strtolower($name, 'UTF-8');

        if ($gw === 'paytr') {
            if (! isset($seen['online_card'])) {
                $badges[] = ['icon' => 'fa-credit-card', 'label' => function_exists('t') ? t('trust.online_card', 'Online Kredi Kartı') : 'Online Kredi Kartı', 'tone' => 'blue'];
                $seen['online_card'] = true;
            }
            continue;
        }

        if ($gw === 'iyzico' || $gw === 'nkolay') {
            if (! isset($seen['online_card'])) {
                $badges[] = ['icon' => 'fa-credit-card', 'label' => function_exists('t') ? t('trust.online_card', 'Online Kredi Kartı') : 'Online Kredi Kartı', 'tone' => 'blue'];
                $seen['online_card'] = true;
            }
            continue;
        }

        if ($gw === 'bank_transfer') {
            $badges[] = [
                'icon' => 'fa-building-columns',
                'label' => $name !== ''
                    ? (function_exists('content_t') ? content_t('payment_method', (int) ($row['payment_method_id'] ?? 0), 'name', $name) : $name)
                    : (function_exists('t') ? t('trust.bank', 'Havale / EFT') : 'Havale / EFT'),
                'tone' => 'slate',
            ];
            continue;
        }

        if (str_contains($nameLower, 'nakit')) {
            if (! isset($seen['cod_cash'])) {
                $badges[] = ['icon' => 'fa-money-bill-wave', 'label' => function_exists('t') ? t('trust.cod_cash', 'Kapıda Nakit Ödeme') : 'Kapıda Nakit Ödeme', 'tone' => 'green'];
                $seen['cod_cash'] = true;
            }
            continue;
        }

        if (str_contains($nameLower, 'kart') || str_contains($nameLower, 'kredi') || str_contains($nameLower, 'banka')) {
            if (! isset($seen['cod_card'])) {
                $badges[] = ['icon' => 'fa-credit-card', 'label' => function_exists('t') ? t('trust.cod_card', 'Kapıda Kart ile Ödeme') : 'Kapıda Kart ile Ödeme', 'tone' => 'teal'];
                $seen['cod_card'] = true;
            }
            continue;
        }

        $badges[] = [
            'icon' => 'fa-hand-holding-dollar',
            'label' => $name !== ''
                ? (function_exists('content_t') ? content_t('payment_method', (int) ($row['payment_method_id'] ?? 0), 'name', $name) : $name)
                : (function_exists('t') ? t('trust.cod', 'Kapıda Ödeme') : 'Kapıda Ödeme'),
            'tone' => 'green',
        ];
    }

    $installLabel = payment_trust_paytr_installment_label($pdo, $rows);
    if ($installLabel !== null) {
        $badges[] = ['icon' => 'fa-calendar-check', 'label' => $installLabel, 'tone' => 'orange'];
    }

    return $badges;
}

/**
 * @param list<array<string,mixed>> $activeMethods
 */
function payment_trust_paytr_installment_label(PDO $pdo, array $activeMethods): ?string
{
    $paytrActive = false;
    foreach ($activeMethods as $row) {
        if (($row['gateway_code'] ?? '') === 'paytr') {
            $paytrActive = true;
            break;
        }
    }
    if (! $paytrActive) {
        return null;
    }

    try {
        $cfg = $pdo->query('SELECT no_installment, max_installment FROM paytr_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
    if (! is_array($cfg) || ! empty($cfg['no_installment'])) {
        return null;
    }

    $max = (int) ($cfg['max_installment'] ?? 0);
    if ($max === 3) {
        return function_exists('t') ? t('trust.installment_3', '3 taksit imkânı (kartla online)') : '3 taksit imkânı (kartla online)';
    }
    if ($max >= 2) {
        $tpl = function_exists('t') ? t('trust.installment_n', '{n} taksit imkânı (kartla online)') : '{n} taksit imkânı (kartla online)';

        return str_replace('{n}', (string) $max, $tpl);
    }

    return function_exists('t') ? t('trust.installment', 'Taksitli ödeme (kartla online)') : 'Taksitli ödeme (kartla online)';
}

/**
 * @param list<array{icon:string,label:string,tone:string}> $badges
 */
function payment_trust_render(array $badges, string $wrapperClass = 'payment-trust'): void
{
    if ($badges === []) {
        return;
    }

    echo '<div class="' . htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') . '" role="list" aria-label="' . (function_exists('te') ? te('shop.payment_options', 'Ödeme seçenekleri') : 'Ödeme seçenekleri') . '">';
    foreach ($badges as $badge) {
        $tone = (string) ($badge['tone'] ?? 'slate');
        $icon = (string) ($badge['icon'] ?? 'fa-circle-check');
        $label = (string) ($badge['label'] ?? '');
        if ($label === '') {
            continue;
        }
        echo '<span class="payment-trust__pill payment-trust__pill--' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') . '" role="listitem">';
        echo '<i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
        echo '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</span>';
    }
    echo '</div>';
}
