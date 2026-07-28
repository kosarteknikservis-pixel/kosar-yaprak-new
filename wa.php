<?php

declare(strict_types=1);

/**
 * Kısa WhatsApp yönlendirme — SMS’teki kısa link (ör. /wa?r=12).
 */
require 'db.php';
require_once __DIR__ . '/includes/abandoned_recovery.php';

$yarimId = (int) ($_GET['r'] ?? 0);
$ad = '';
$urun = '';

if ($yarimId > 0) {
    try {
        $st = $pdo->prepare('SELECT ad, urun FROM yarim_kalanlar WHERE id = ? LIMIT 1');
        $st->execute([$yarimId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $ad = (string) ($row['ad'] ?? '');
            $urun = (string) ($row['urun'] ?? '');
        }
    } catch (Throwable $e) {
        /* varsayılan metin */
    }
}

$text = $yarimId > 0
    ? abandoned_recovery_whatsapp_prefill($yarimId, $ad, $urun)
    : 'Merhaba, siparisim icin yaziyorum.';

$cfg = abandoned_recovery_settings($pdo);
$digits = abandoned_recovery_whatsapp_digits((string) ($cfg['abandoned_whatsapp_number'] ?? '05527391073'));

header('Location: https://wa.me/' . $digits . '?text=' . rawurlencode($text), true, 302);
exit;
