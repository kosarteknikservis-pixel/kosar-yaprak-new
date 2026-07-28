<?php
declare(strict_types=1);

require_once __DIR__ . '/LinkCloakBotClassifier.php';
require_once __DIR__ . '/../app_url.php';

final class LinkCloakService
{
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** @return array<string, mixed>|null */
    public static function campaignByToken(PDO $pdo, string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^[a-f0-9]{16}$/', $token)) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM link_cloak_campaigns WHERE token = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function gatewayUrl(PDO $pdo, string $token): string
    {
        return app_url('lc.php', ['c' => $token], $pdo);
    }

    /** @return list<string> */
    public static function parseUrlList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $line)) {
                $line = 'https://' . ltrim($line, '/');
            }
            $out[] = mb_substr($line, 0, 512);
        }

        return $out;
    }

    /** @param array<string, mixed> $campaign */
    public static function allMoneyUrls(array $campaign): array
    {
        $primary = trim((string) ($campaign['money_url'] ?? ''));
        $list = [$primary];
        $raw = trim((string) ($campaign['money_urls_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $u) {
                    $u = trim((string) $u);
                    if ($u !== '' && ! in_array($u, $list, true)) {
                        $list[] = $u;
                    }
                }
            }
        }

        return array_values(array_filter($list, static fn (string $u) => $u !== ''));
    }

    /** @param array<string, mixed> $campaign */
    public static function currentMoneyUrl(array $campaign): string
    {
        $urls = self::allMoneyUrls($campaign);
        $idx = max(0, (int) ($campaign['rotation_index'] ?? 0));
        if ($urls === []) {
            return '';
        }
        if ($idx >= count($urls)) {
            $idx = 0;
        }

        return $urls[$idx];
    }

    /**
     * Geçit isteği — lc.php giriş noktası.
     */
    public static function handle(PDO $pdo, string $token): void
    {
        $campaign = self::campaignByToken($pdo, $token);
        if ($campaign === null || empty((int) ($campaign['is_enabled'] ?? 0))) {
            self::notFound();

            return;
        }

        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        $ip = self::clientIp();
        $referer = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);
        $uri = mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 512);
        $strict = ! empty((int) ($campaign['strict_filter'] ?? 1));
        $class = LinkCloakBotClassifier::classify($ua, $strict);
        $isBot = $class['is_bot'];

        $status = (string) ($campaign['run_status'] ?? 'passive');
        $totalBefore = (int) ($campaign['total_hits'] ?? 0);
        $warmupLimit = max(0, (int) ($campaign['warmup_paravan_hits'] ?? 50));
        $inWarmup = $warmupLimit > 0 && $totalBefore < $warmupLimit;
        $forceParavan = $isBot || $status === 'passive' || $inWarmup;

        self::bumpTotals($pdo, (int) $campaign['id'], $isBot);

        if ($forceParavan) {
            $reason = $class['reason'];
            if ($reason === '' && $status === 'passive') {
                $reason = 'pasif mod';
            } elseif ($reason === '' && $inWarmup) {
                $reason = 'ısınma (' . ($totalBefore + 1) . '/' . $warmupLimit . ')';
            }
            $verdict = $isBot ? 'bot' : 'human';
            self::logHit($pdo, (int) $campaign['id'], $ip, $ua, $class['vendor'], $uri, $referer, $verdict, $reason !== '' ? $reason : null);
            self::respondParavan($pdo, $campaign);

            return;
        }

        $campaign = self::campaignByToken($pdo, $token) ?? $campaign;
        $dest = self::applyRotationIfNeeded($pdo, $campaign);
        if ($dest === '') {
            self::notFound();

            return;
        }

        self::logHit($pdo, (int) $campaign['id'], $ip, $ua, $class['vendor'], $uri, $referer, 'human', null);
        header('Location: ' . $dest, true, 302);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        exit;
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string) ($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $raw = trim(explode(',', $raw)[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return mb_substr($raw, 0, 64);
            }
        }

        return '';
    }

    private static function bumpTotals(PDO $pdo, int $id, bool $isBot): void
    {
        if ($isBot) {
            $pdo->exec('UPDATE link_cloak_campaigns SET total_hits = total_hits + 1, bot_hits = bot_hits + 1 WHERE id = ' . (int) $id);
        } else {
            $pdo->exec('UPDATE link_cloak_campaigns SET total_hits = total_hits + 1, human_hits = human_hits + 1, clicks_since_rotate = clicks_since_rotate + 1 WHERE id = ' . (int) $id);
        }
    }

    /** @param array<string, mixed> $campaign */
    private static function applyRotationIfNeeded(PDO $pdo, array $campaign): string
    {
        $id = (int) $campaign['id'];
        $threshold = max(1, (int) ($campaign['rotate_after_clicks'] ?? 25));
        $clicksSince = (int) ($campaign['clicks_since_rotate'] ?? 0);
        $urls = self::allMoneyUrls($campaign);
        if ($urls === []) {
            return '';
        }
        $idx = max(0, (int) ($campaign['rotation_index'] ?? 0));
        if ($idx >= count($urls)) {
            $idx = 0;
        }

        if (count($urls) > 1 && $clicksSince >= $threshold) {
            $idx = ($idx + 1) % count($urls);
            $newUrl = $urls[$idx];
            $pdo->prepare('UPDATE link_cloak_campaigns SET rotation_index = ?, clicks_since_rotate = 0, money_url = ? WHERE id = ?')
                ->execute([$idx, $newUrl, $id]);

            return $newUrl;
        }

        return $urls[$idx] !== '' ? $urls[$idx] : ($urls[0] ?? '');
    }

    /** @param array<string, mixed> $campaign */
    private static function respondParavan(PDO $pdo, array $campaign): void
    {
        $paravan = trim((string) ($campaign['paravan_url'] ?? ''));
        if ($paravan !== '' && filter_var($paravan, FILTER_VALIDATE_URL)) {
            header('Location: ' . $paravan, true, 302);
            exit;
        }
        if (! class_exists('SafePageService', false)) {
            require_once __DIR__ . '/../safe_page_service.php';
        }
        SafePageService::render($pdo);
        exit;
    }

    private static function logHit(
        PDO $pdo,
        int $campaignId,
        string $ip,
        string $ua,
        string $vendor,
        string $uri,
        string $referer,
        string $verdict,
        ?string $reason
    ): void {
        try {
            $st = $pdo->prepare(
                'INSERT INTO link_cloak_traffic (campaign_id, ip, user_agent, vendor_label, request_uri, referer, verdict, block_reason)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $campaignId,
                $ip,
                $ua,
                mb_substr($vendor, 0, 32),
                $uri,
                $referer,
                mb_substr($verdict, 0, 16),
                $reason !== null ? mb_substr($reason, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('link_cloak_log: ' . $e->getMessage());
            }
        }
    }

    private static function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Link bulunamadı.';
        exit;
    }
}
