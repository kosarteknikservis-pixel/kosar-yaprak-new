<?php
declare(strict_types=1);

/**
 * Döviz kuru güncelleme.
 * Kaynak: https://open.er-api.com/v6/latest/TRY (anahtarsız, ücretsiz).
 * Baz = TRY. rate = 1 TRY karşılığı hedef para biriminden kaç birim.
 * İnternet yoksa mevcut (yerel) kurlar korunur; site çalışmaya devam eder.
 */
function currency_update_rates(PDO $pdo, ?string &$msg = null): bool
{
    $url = 'https://open.er-api.com/v6/latest/TRY';
    $ctx = stream_context_create([
        'http' => ['timeout' => 8, 'header' => "User-Agent: 3dhesap/1.0\r\n"],
        'https' => ['timeout' => 8],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        $msg = 'Kur servisine ulaşılamadı (internet yok?). Mevcut yerel kurlar korundu.';
        return false;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['result'] ?? '') !== 'success' || !isset($data['rates']) || !is_array($data['rates'])) {
        $msg = 'Kur servisi beklenmedik yanıt verdi. Mevcut kurlar korundu.';
        return false;
    }
    $rates = $data['rates'];

    $codes = $pdo->query('SELECT code FROM site_currencies')->fetchAll(PDO::FETCH_COLUMN);
    $upd = $pdo->prepare('UPDATE site_currencies SET rate = ?, rate_updated_at = NOW() WHERE code = ?');
    $count = 0;
    foreach ($codes as $code) {
        $code = (string) $code;
        if ($code === 'TRY') {
            $upd->execute([1.0, 'TRY']);
            $count++;
            continue;
        }
        if (isset($rates[$code]) && is_numeric($rates[$code]) && (float) $rates[$code] > 0) {
            $upd->execute([(float) $rates[$code], $code]);
            $count++;
        }
    }
    $msg = $count . ' para birimi güncellendi (kaynak: open.er-api.com).';
    return true;
}
