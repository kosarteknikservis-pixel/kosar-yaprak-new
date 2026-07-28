<?php
declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/app_url.php';
require_once dirname(__DIR__) . '/includes/attribution_helpers.php';
require_once dirname(__DIR__) . '/includes/abandoned_capture.php';
require_once dirname(__DIR__) . '/includes/abandoned_recovery.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function abandoned_debug_log(string $message, array $context = []): void
{
    $line = '['.date('Y-m-d H:i:s').'] [abandoned] '.$message;
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $line .= ' '.$json;
        }
    }
    $line .= "\n";
    @file_put_contents(dirname(__DIR__).'/hata_loglari.log', $line, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'method']);
    exit;
}

/**
 * @return array<string, bool>
 */
function yarim_kalan_columns(PDO $pdo): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $q = $pdo->query('SHOW COLUMNS FROM yarim_kalanlar');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = (string) ($row['Field'] ?? '');
        if ($name !== '') {
            $cache[$name] = true;
        }
    }

    return $cache;
}

try {
    $captureSettings = abandoned_capture_settings($pdo);

    $ad = trim((string)($_POST['ad'] ?? ''));
    $tel = trim((string)($_POST['tel'] ?? ''));
    $urun = substr(trim((string)($_POST['urun'] ?? '')), 0, 700);
    $fiyat = substr(trim((string)($_POST['fiyat'] ?? '')), 0, 64);
    $productId = isset($_POST['product_id']) ? max(0, (int)$_POST['product_id']) : 0;

    $hasContact = $ad !== '' || $tel !== '';
    $hasProduct = $productId > 0 || $urun !== '';
    $allowProductOnly = ! empty($captureSettings['enabled']) && ! empty($captureSettings['product_only']);

    if (! $hasContact && ! ($allowProductOnly && $hasProduct)) {
        echo json_encode(['ok' => true]);
        exit;
    }

    $cols = yarim_kalan_columns($pdo);

    $ip = app_client_ip();
    $telNorm = preg_replace('/\D+/', '', $tel);
    $telEnd = mb_strlen((string)$telNorm) >= 10 ? mb_substr($telNorm, -10) : $telNorm;

    // Not: aynı gün sipariş verilmiş olsa bile yarım kalan kaydı tutulur.
    // Dönüşüm sonrası kayıtlar is_converted ile işaretlenir.

    $sess = $_COOKIE['yarim_kalan_sid'] ?? null;
    if ($sess !== null && !preg_match('/^[a-zA-Z0-9_-]{16,96}$/', (string)$sess)) {
        $sess = null;
    }
    if (!$sess) {
        $sess = bin2hex(random_bytes(16));
        setcookie('yarim_kalan_sid', $sess, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $activeClause = isset($cols['is_converted']) ? ' AND IFNULL(is_converted, 0) = 0' : '';

    $rid = null;
    if ($sess !== null && isset($cols['session_id'])) {
        $find3 = $pdo->prepare('SELECT id FROM yarim_kalanlar WHERE session_id = ?'.$activeClause.' ORDER BY id DESC LIMIT 1');
        $find3->execute([$sess]);
        $rid = $find3->fetchColumn() ?: null;
    }
    if (!$rid && $telEnd !== '') {
        $find2 = $pdo->prepare('SELECT id FROM yarim_kalanlar WHERE tel LIKE ?'.$activeClause.' ORDER BY id DESC LIMIT 1');
        $find2->execute(['%' . $telEnd]);
        $rid = $find2->fetchColumn() ?: null;
    }
    if (!$rid) {
        $find = $pdo->prepare('SELECT id FROM yarim_kalanlar WHERE ip = ?'.$activeClause.' ORDER BY id DESC LIMIT 1');
        $find->execute([$ip]);
        $rid = $find->fetchColumn() ?: null;
    }

    $existing = [];
    $hadTelBefore = false;
    if ($rid) {
        $exStmt = $pdo->prepare('SELECT ad, tel, urun, fiyat, product_id FROM yarim_kalanlar WHERE id = ? LIMIT 1');
        $exStmt->execute([(int) $rid]);
        $existing = $exStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $hadTelBefore = trim((string) ($existing['tel'] ?? '')) !== '';
    }

    if ($ad === '' && ! empty($existing['ad'])) {
        $ad = trim((string) $existing['ad']);
    }
    if ($tel === '' && ! empty($existing['tel'])) {
        $tel = trim((string) $existing['tel']);
    }
    if ($urun === '' && ! empty($existing['urun'])) {
        $urun = substr(trim((string) $existing['urun']), 0, 700);
    }
    if ($fiyat === '' && ! empty($existing['fiyat'])) {
        $fiyat = substr(trim((string) $existing['fiyat']), 0, 64);
    }
    if ($productId <= 0 && ! empty($existing['product_id'])) {
        $productId = (int) $existing['product_id'];
    }

    $attr = attribution_abandoned_slice();
    $adSource = $attr['ad_source'];
    $utmSource = $attr['utm_source'];
    $utmMedium = $attr['utm_medium'];
    $utmCampaign = $attr['utm_campaign'];
    $utmContent = $attr['utm_content'];
    $utmTerm = $attr['utm_term'];

    $values = [
        'ad' => mb_substr($ad, 0, 254),
        'tel' => mb_substr($tel, 0, 39),
        'urun' => $urun,
        'fiyat' => $fiyat,
        'session_id' => $sess,
        'product_id' => $productId > 0 ? $productId : null,
        'ad_source' => $adSource,
        'utm_source' => $utmSource,
        'utm_medium' => $utmMedium,
        'utm_campaign' => $utmCampaign,
        'utm_content' => $utmContent,
        'utm_term' => $utmTerm,
        'is_converted' => 0,
        'converted_at' => null,
        'converted_order_id' => null,
    ];

    if ($rid) {
        $setParts = [];
        $setParams = [];
        $skipOnUpdate = ['is_converted', 'converted_at', 'converted_order_id'];
        foreach ($values as $column => $value) {
            if (in_array($column, $skipOnUpdate, true)) {
                continue;
            }
            if (isset($cols[$column])) {
                $setParts[] = "{$column} = ?";
                $setParams[] = $value;
            }
        }

        if ($setParts !== []) {
            $setParams[] = (int) $rid;
            $up = $pdo->prepare('UPDATE yarim_kalanlar SET '.implode(', ', $setParts).' WHERE id = ?');
            $up->execute($setParams);
        }
    } else {
        $insertValues = array_merge(['ip' => $ip], $values);
        $insertCols = [];
        $placeholders = [];
        $insertParams = [];

        foreach ($insertValues as $column => $value) {
            if (isset($cols[$column])) {
                $insertCols[] = $column;
                $placeholders[] = '?';
                $insertParams[] = $value;
            }
        }

        if ($insertCols === []) {
            throw new RuntimeException('yarim_kalanlar tablosunda yazılabilir kolon bulunamadı.');
        }

        $ins = $pdo->prepare(
            'INSERT INTO yarim_kalanlar ('.implode(', ', $insertCols).') VALUES ('.implode(', ', $placeholders).')'
        );
        $ins->execute($insertParams);
        $rid = (int) $pdo->lastInsertId();
    }

    $savedId = $rid ? (int) $rid : 0;
    // SMS yalnızca telefon ilk kez yakalandığında (form her güncellemede tekrar gitmez).
    if ($savedId > 0 && $tel !== '' && ! $hadTelBefore) {
        try {
            abandoned_recovery_send_sms($pdo, $savedId);
        } catch (Throwable $smsEx) {
            abandoned_debug_log('recovery_sms_error', ['id' => $savedId, 'error' => $smsEx->getMessage()]);
        }
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    abandoned_debug_log('save_failed', ['error' => $e->getMessage()]);
    error_log('abandoned_save failed: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'err' => 'save_failed']);
}
