<?php

declare(strict_types=1);

/**
 * Online ödeme geçitlerini (PayTR / iyzico) ayarlarla senkronize eder.
 */
function payment_methods_sync_online_gateways(PDO $pdo): void
{
    if (!payment_methods_table_ready($pdo)) {
        return;
    }

    require_once __DIR__ . '/gateways/PaytrGateway.php';
    $paytr = new PaytrGateway($pdo);
    payment_methods_set_gateway_row(
        $pdo,
        'paytr',
        'Kredi / Banka Kartı (Online)',
        5,
        $paytr->configured()
    );

    if (is_file(__DIR__ . '/gateways/IyzicoGateway.php')) {
        require_once __DIR__ . '/gateways/IyzicoGateway.php';
        if (class_exists('IyzicoGateway', false)) {
            $iyz = new IyzicoGateway($pdo);
            $iyzOk = method_exists($iyz, 'configured') && $iyz->configured();
            payment_methods_set_gateway_row(
                $pdo,
                'iyzico',
                'Kredi / Banka Kartı (iyzico)',
                6,
                $iyzOk
            );
        }
    }
}

function payment_methods_table_ready(PDO $pdo): bool
{
    try {
        $q = $pdo->query('SHOW COLUMNS FROM payment_methods LIKE ' . $pdo->quote('gateway_code'));

        return $q instanceof PDOStatement && $q->fetch() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

function payment_methods_set_gateway_row(
    PDO $pdo,
    string $gatewayCode,
    string $defaultName,
    int $sortOrder,
    bool $active
): void {
    $st = $pdo->prepare(
        'SELECT payment_method_id FROM payment_methods WHERE gateway_code = ? ORDER BY payment_method_id ASC LIMIT 1'
    );
    $st->execute([$gatewayCode]);
    $id = (int) ($st->fetchColumn() ?: 0);

    if ($id <= 0) {
        if (! $active) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO payment_methods (method_name, gateway_code, is_active, sort_order) VALUES (?, ?, 1, ?)'
        )->execute([$defaultName, $gatewayCode, $sortOrder]);

        return;
    }

    $pdo->prepare('UPDATE payment_methods SET is_active = ?, sort_order = ? WHERE payment_method_id = ?')
        ->execute([$active ? 1 : 0, $sortOrder, $id]);
}
