<?php

declare(strict_types=1);

/**
 * Sipariş ve bağlı satırları güvenli silme (toplu / tek).
 */
function order_delete_ids(PDO $pdo, array $orderIds): int
{
    $ids = [];
    foreach ($orderIds as $rawId) {
        $id = (int) $rawId;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $childTables = [
        'order_variation_details',
        'order_status_logs',
        'order_items',
    ];

    $pdo->beginTransaction();

    try {
        foreach ($childTables as $table) {
            try {
                $st = $pdo->prepare('DELETE FROM ' . $table . ' WHERE order_id IN (' . $placeholders . ')');
                $st->execute($ids);
            } catch (Throwable $e) {
                /* tablo yoksa veya kolon farklıysa devam */
            }
        }

        $del = $pdo->prepare('DELETE FROM orders WHERE order_id IN (' . $placeholders . ')');
        $del->execute($ids);
        $deleted = $del->rowCount();

        $pdo->commit();

        return $deleted;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
