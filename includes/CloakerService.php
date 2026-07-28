<?php
declare(strict_types=1);

/**
 * Cloaker — basit UA kuralları (eski davranış) + isteğe bağlı gelişmiş mod (panel cloaker v2 ile uyumlu).
 */
final class CloakerService
{
    private const SESS_REF = '_cloaker_ref_ok';
    private const SESS_JS_GRACE = '_cloaker_js_grace_at';
    private const COOKIE_REF = '_cl_rf';
    private const COOKIE_JS = '_cl_tk';

    /** @return list<string> */
    private static function defaultExemptBasenames(): array
    {
        return ['get_districts.php', 'safe-page.php', 'go.php', 'lc.php'];
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__);
    }

    /** Sunucunun projeyi güvenilir şekilde tanıması için kullanılır — X-Forwarded-For vb. doğrulanmıyor. */
    private static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return mb_substr($ip, 0, 64);
    }

    private static function currentUri(): string
    {
        $u = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return mb_substr($u, 0, 1024);
    }

    private static function referer(): string
    {
        $r = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        return mb_substr($r, 0, 512);
    }

    private static function ua(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1200);
    }

    public static function isEligibleRequest(): bool
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return false;
        }
        $sf = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $root = str_replace('\\', '/', dirname(__DIR__));
        if ($sf === '' || $root === '') {
            return true;
        }
        if (str_starts_with($sf, $root)) {
            $rel = substr($sf, strlen($root));
            if (str_starts_with((string) $rel, '/admin/') || str_starts_with((string) $rel, '/phx/')) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public static function loadSettings(PDO $pdo): array
    {
        try {
            $st = $pdo->query('SELECT * FROM cloaker_settings WHERE id = 1');
            $row = $st instanceof PDOStatement ? $st->fetch(PDO::FETCH_ASSOC) : false;

            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function ensureSessionIfNeeded(array $cfg): void
    {
        $adv = !empty((int) ($cfg['advanced_cloaker'] ?? 0));
        if (!$adv || session_status() !== PHP_SESSION_NONE) {
            return;
        }
        @session_start();
    }

    private static function scriptBasename(): string
    {
        $n = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));

        return $n !== '' ? $n : 'index.php';
    }

    /** @param array<string, mixed> $cfg */
    private static function exemptBasenamesList(array $cfg): array
    {
        $raw = trim((string) ($cfg['cloaker_exempt_basenames'] ?? ''));
        if ($raw === '') {
            return self::defaultExemptBasenames();
        }
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ln) {
            $ln = trim($ln);
            if ($ln !== '') {
                $out[] = $ln;
            }
        }

        return $out === [] ? self::defaultExemptBasenames() : $out;
    }

    /** @param array<string, mixed> $cfg */
    private static function isExemptBasename(array $cfg, string $script): bool
    {
        foreach (self::exemptBasenamesList($cfg) as $b) {
            if (strcasecmp($script, trim($b)) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $cfg */
    private static function safePageBasename(array $cfg): string
    {
        $t = trim((string) ($cfg['safe_page_target'] ?? 'safe-page.php'));
        if ($t === '') {
            return 'safe-page.php';
        }
        if (filter_var($t, FILTER_VALIDATE_URL)) {
            $path = parse_url($t, PHP_URL_PATH);

            return $path !== null && $path !== '' ? basename($path) : 'safe-page.php';
        }

        return basename(str_replace('\\', '/', $t));
    }

    /** @param array<string, mixed> $cfg */
    private static function onSafePage(array $cfg, string $script): bool
    {
        $want = strtolower(self::safePageBasename($cfg));

        return $want !== '' && strcasecmp($script, $want) === 0;
    }

    private static function isBenignBot(string $uaLower): bool
    {
        $needles = [
            'googlebot', 'bingbot', 'duckduckbot', 'yandex', 'baiduspider', 'applebot',
            'facebookexternalhit', 'facebot', 'twitterbot', 'linkedinbot', 'slackbot', 'telegrambot',
            'discordbot', 'whatsapp', 'pinterest', 'embedly',
        ];
        foreach ($needles as $n) {
            if (str_contains($uaLower, $n)) {
                return true;
            }
        }

        return false;
    }

    /** Küçük harf UA ile eşleşir */
    private static function scraperNeedles(): array
    {
        return [
            'curl/', 'wget/', 'python-requests', 'scrapy/', 'scrapy-', 'java/', 'apache-httpclient',
            'go-http-client', ' okhttp/', ' okhttp', 'postman/', 'axios/', 'libwww-perl',
            'httpclient', 'urllib', 'powershell', 'masscan',
        ];
    }

    private static function guessVendorLabel(string $uaLower): string
    {
        if ($uaLower === '') {
            return '';
        }
        if (preg_match('/facebook|meta-external|facebot|instagrambot|threadsbot|fbcrawl|whatsapp|\\binstagram\\//', $uaLower)) {
            return 'meta';
        }
        if (preg_match('/googlebot|adsbot|google-inspection|feedfetcher|mediapartners-google|googleproducer|google web preview/', $uaLower)) {
            return 'google';
        }
        if (str_contains($uaLower, 'bingbot') || str_contains($uaLower, 'msnbot')) {
            return 'microsoft';
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function knownBotAgents(): array
    {
        return [
            'facebookexternalhit', 'facebot', 'facebookbot', 'facebookcatalog',
            'meta-externalagent', 'meta-externalfetcher', 'meta-webindexer',
            'instagrambot', 'threadsbot',
            'googlebot', 'adsbot', 'google-inspectiontool', 'feedfetcher-google',
            'mediapartners-google', 'storebot-google', 'googleproducer',
            'twitterbot', 'linkedinbot', 'bingbot', 'slackbot', 'discordbot',
            'embedly', 'quora link preview', 'pinterestbot', 'tiktokspider', 'bytespider',
            'curl/', 'wget', 'python-requests', 'libwww-perl',
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{blocked: bool, reason: string|null, benign: bool}
     */
    public static function evaluate(array $cfg, string $ua, bool $enforceBlocking): array
    {
        $uaLower = mb_strtolower($ua, 'UTF-8');
        $respect = !empty((int) ($cfg['respect_benign_bots'] ?? 1));
        if ($respect && self::isBenignBot($uaLower)) {
            return ['blocked' => false, 'reason' => null, 'benign' => true];
        }
        if (!$enforceBlocking) {
            return ['blocked' => false, 'reason' => null, 'benign' => false];
        }
        if (!empty((int) ($cfg['block_empty_ua'] ?? 1)) && trim($ua) === '') {
            return ['blocked' => true, 'reason' => 'boş UA', 'benign' => false];
        }
        if (!empty((int) ($cfg['block_empty_ua'] ?? 1))) {
            $len = mb_strlen(trim($ua), 'UTF-8');
            if ($len > 0 && $len < 12) {
                return ['blocked' => true, 'reason' => 'çok kısa UA', 'benign' => false];
            }
        }
        if (!empty((int) ($cfg['block_scraper_ua'] ?? 1))) {
            foreach (self::scraperNeedles() as $n) {
                if (str_contains($uaLower, $n)) {
                    return ['blocked' => true, 'reason' => 'şüpheli UA: ' . $n, 'benign' => false];
                }
            }
            $extra = (string) ($cfg['extra_block_substrings'] ?? '');
            foreach (preg_split('/\r\n|\r|\n/', $extra, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
                $line = mb_strtolower(trim($line), 'UTF-8');
                if ($line !== '' && str_contains($uaLower, $line)) {
                    return ['blocked' => true, 'reason' => 'özel liste: ' . mb_substr($line, 0, 80), 'benign' => false];
                }
            }
        }

        return ['blocked' => false, 'reason' => null, 'benign' => false];
    }

    private static function ipCIDRCheck(string $ip, string $cidr): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        if (strpos($cidr, '/') === false) {
            return hash_equals($ip, trim($cidr));
        }
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0] ?? '';
        $mask = isset($parts[1]) ? (int) $parts[1] : 0;
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        if ($ip_long === false || $subnet_long === false) {
            return false;
        }
        $mask_long = -1 << (32 - max(0, min(32, $mask)));

        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * @return list<string>
     */
    private static function loadBlockedIpFile(string $path): array
    {
        static $cache = [];
        $mtime = @filemtime($path);
        $key = $path . '|' . (string) $mtime;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $rules = [];
        if (is_readable($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $ln) {
                    $ln = trim((string) $ln);
                    if ($ln === '' || (isset($ln[0]) && $ln[0] === '#')) {
                        continue;
                    }
                    $rules[] = $ln;
                }
            }
        }
        $cache[$key] = $rules;

        return $rules;
    }

    /** @param list<string> $lines */
    private static function ipMatchesList(string $ip, array $lines): bool
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }
            if (strpos($line, '/') !== false) {
                if (self::ipCIDRCheck($ip, $line)) {
                    return true;
                }
            } elseif ($ip === $line) {
                return true;
            }
        }

        return false;
    }

    private static function getHostnameWithTimeoutIpv4(string $ip): string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return '';
        }
        $ptr = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
        $host_record = @dns_get_record($ptr, DNS_PTR);

        return ($host_record !== false && isset($host_record[0]['target'])) ? (string) $host_record[0]['target'] : '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function geoCountryCode(array $cfg, string $ip): ?string
    {
        $rel = trim(str_replace('\\', '/', (string) ($cfg['geoip_mmdb_path'] ?? '')));
        if ($rel === '') {
            return null;
        }
        $path = self::projectRoot() . '/' . ltrim($rel, '/');
        if (!is_readable($path)) {
            return null;
        }
        $readerInc = __DIR__ . '/cloaker/mmdb_reader.php';
        if (!is_readable($readerInc)) {
            return null;
        }
        require_once $readerInc;

        try {
            $reader = new CloakerMMDBReader($path);
            /** @var array<string,mixed>|null $record */
            $record = $reader->get($ip);

            /** @phpstan-ignore-next-line */
            if (!is_array($record)) {
                return null;
            }
            $country = $record['country'] ?? null;

            return is_array($country) && isset($country['iso_code']) ? (string) $country['iso_code'] : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function refGateOk(array $cfg): bool
    {
        if (empty((int) ($cfg['require_ref'] ?? 0))) {
            return true;
        }
        $key = trim((string) ($cfg['ref_key'] ?? ''));
        if ($key === '') {
            return true;
        }
        if (!empty($_SESSION[self::SESS_REF])) {
            return true;
        }
        if (!empty($_COOKIE[self::COOKIE_REF]) && (string) $_COOKIE[self::COOKIE_REF] === '1') {
            $_SESSION[self::SESS_REF] = true;

            return true;
        }
        $got = isset($_GET['ref']) && is_scalar($_GET['ref'])
            ? trim((string) $_GET['ref']) : '';
        if ($got !== '' && hash_equals($key, $got)) {
            $_SESSION[self::SESS_REF] = true;
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie(self::COOKIE_REF, '1', time() + 86400 * 30, '/', '', $secure, true);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{threat: bool, reason: string, benign_trust: bool}
     */
    private static function evaluateAdvancedThreat(
        array $cfg,
        string $ip,
        string $ua,
        string $uaLower,
        bool $benignTrust
    ): array {
        if (self::ipMatchesList($ip, preg_split('/\r\n|\r|\n/', (string) ($cfg['ip_whitelist'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])) {
            return ['threat' => false, 'reason' => '', 'benign_trust' => $benignTrust];
        }
        if (!self::refGateOk($cfg)) {
            return ['threat' => true, 'reason' => 'Missing/Wrong ref key', 'benign_trust' => false];
        }
        if (!empty((int) ($cfg['geoip_on'] ?? 0))) {
            $cc = self::geoCountryCode($cfg, $ip);
            $allowed = array_map(
                static fn (string $x) => strtoupper(trim($x)),
                explode(',', (string) ($cfg['allowed_countries'] ?? 'TR'))
            );
            $allowed = array_filter($allowed, static fn (string $x) => $x !== '');
            if ($cc !== null && $allowed !== [] && !in_array(strtoupper($cc), $allowed, true)) {
                return ['threat' => true, 'reason' => 'GeoIP: ' . $cc . ' not allowed', 'benign_trust' => false];
            }
            if ($cc === null && ($cfg['dns_mode'] ?? 'esnek') === 'agresif') {
                return ['threat' => true, 'reason' => 'GeoIP lookup failed (agresif)', 'benign_trust' => false];
            }
        }
        $file = __DIR__ . '/cloaker/blocked_ips.txt';
        if (self::ipMatchesList($ip, self::loadBlockedIpFile($file))) {
            return ['threat' => true, 'reason' => 'IP blocklist (file)', 'benign_trust' => false];
        }
        $blText = trim((string) ($cfg['ip_blacklist'] ?? ''));
        if ($blText !== '' && self::ipMatchesList($ip, preg_split('/\r\n|\r|\n/', $blText, -1, PREG_SPLIT_NO_EMPTY) ?: [])) {
            return ['threat' => true, 'reason' => 'IP blocklist (panel)', 'benign_trust' => false];
        }
        if (!$benignTrust && !empty((int) ($cfg['block_known_bots'] ?? 1))) {
            foreach (self::knownBotAgents() as $agent) {
                if (strpos($uaLower, $agent) !== false) {
                    return ['threat' => true, 'reason' => 'Bot UA: ' . $agent, 'benign_trust' => false];
                }
            }
        }
        if (
            !$benignTrust
            && ($cfg['dns_mode'] ?? 'esnek') === 'agresif'
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            $hostname = strtolower(self::getHostnameWithTimeoutIpv4($ip));
            $suspicious_hosts = ['amazonaws', 'googleusercontent', 'facebook.com', 'fbcdn', 'linode', 'digitalocean', 'datacenter', 'hosting', 'cloud'];
            foreach ($suspicious_hosts as $shost) {
                if ($hostname !== '' && strpos($hostname, $shost) !== false) {
                    return ['threat' => true, 'reason' => 'Host: ' . $shost, 'benign_trust' => false];
                }
            }
        }
        if (
            !$benignTrust
            && ($cfg['js_mode'] ?? 'esnek') === 'agresif'
        ) {
            $hasJs = isset($_COOKIE[self::COOKIE_JS]) && (string) $_COOKIE[self::COOKIE_JS] === 'active';
            if (!$hasJs) {
                if (!isset($_SESSION[self::SESS_JS_GRACE])) {
                    $_SESSION[self::SESS_JS_GRACE] = time();
                } else {
                    $graceAge = time() - (int) $_SESSION[self::SESS_JS_GRACE];
                    if ($graceAge >= 3) {
                        return ['threat' => true, 'reason' => 'JS token missing', 'benign_trust' => false];
                    }
                }
            } else {
                unset($_SESSION[self::SESS_JS_GRACE]);
            }
        }

        return ['threat' => false, 'reason' => '', 'benign_trust' => $benignTrust];
    }

    /** @param array<string, mixed> $cfg */
    private static function bumpStats(PDO $pdo, bool $blocked): void
    {
        try {
            if ($blocked) {
                $pdo->exec('UPDATE cloaker_settings SET stat_total = stat_total + 1, stat_blocked = stat_blocked + 1 WHERE id = 1');
            } else {
                $pdo->exec('UPDATE cloaker_settings SET stat_total = stat_total + 1, stat_passed = stat_passed + 1 WHERE id = 1');
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function insertTraffic(
        PDO $pdo,
        array $cfg,
        string $verdict,
        ?string $blockReason,
        string $vendorLabel
    ): void {
        $isBlockish = in_array($verdict, ['block', 'cloak'], true);
        if (!$isBlockish && $verdict !== 'log_only' && $verdict !== 'allow_bot' && !self::shouldLogAllow($cfg)) {
            return;
        }
        if (!$isBlockish && $verdict === 'allow' && !self::shouldLogAllow($cfg)) {
            return;
        }
        try {
            $st = $pdo->prepare(
                'INSERT INTO cloaker_traffic (ip, user_agent, vendor_label, request_uri, referer, verdict, block_reason) VALUES (?,?,?,?,?,?,?)'
            );
            $st->execute([
                self::clientIp(),
                mb_substr(self::ua(), 0, 512),
                mb_substr($vendorLabel, 0, 32),
                self::currentUri(),
                self::referer(),
                mb_substr($verdict, 0, 24),
                $blockReason !== null ? mb_substr($blockReason, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            if (isset($_SERVER['HTTP_HOST'])) {
                error_log('cloaker_log: ' . $e->getMessage());
            }
        }
    }

    /** @param array<string, mixed> $cfg */
    private static function shouldLogAllow(array $cfg): bool
    {
        return !empty((int) ($cfg['traffic_log_enabled'] ?? 1));
    }

    /**
     * Şüpheli sınıflandırma sonrası yanıt. `log_only` için true döner (sayfa normal devam).
     *
     * @param array<string, mixed> $cfg
     */
    private static function respondAdvancedThreat(PDO $pdo, array $cfg, string $reason, string $vendorLabel): bool
    {
        $mode = (string) ($cfg['threat_handling'] ?? 'safe_shadow');
        $verdictTraffic = ($mode === 'http_403') ? 'block' : ($mode === 'log_only' ? 'log_only' : 'cloak');
        self::insertTraffic($pdo, $cfg, $verdictTraffic, $reason, $vendorLabel);
        if ($mode === 'log_only') {
            return true;
        }
        self::bumpStats($pdo, true);
        if ($mode === 'http_403') {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erişim reddedildi.';

            exit;
        }
        $target = trim((string) ($cfg['safe_page_target'] ?? 'safe-page.php'));
        $cloakMethod = (int) ($cfg['cloak_method'] ?? 2);
        $forceRedirect = $mode === 'safe_redirect';
        $useRedirect = $forceRedirect || ($mode === 'safe_shadow' && $cloakMethod === 1);

        if (filter_var($target, FILTER_VALIDATE_URL)) {
            header('Location: ' . $target);
            exit;
        }
        $base = self::storefrontRedirectBase($pdo, $cfg);
        $file = basename(str_replace('\\', '/', $target));
        if ($file === '') {
            $file = 'safe-page.php';
        }
        $abs = self::projectRoot() . DIRECTORY_SEPARATOR . $file;
        if ($useRedirect || !is_readable($abs)) {
            header('Location: ' . rtrim($base, '/') . '/' . $file);
            exit;
        }
        /** @phpstan-ignore-next-line */
        include $abs;
        exit;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function storefrontRedirectBase(PDO $pdo, array $cfg): string
    {
        require_once __DIR__ . '/cloaker_helpers.php';

        return rtrim(cloaker_resolve_public_base($pdo, $cfg), '/');
    }

    private static function publicBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $dir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = str_replace('\\', '/', $dir);
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            $dir = '';
        }

        return ($https ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
    }

    public static function run(PDO $pdo): void
    {
        if (!self::isEligibleRequest()) {
            return;
        }
        $cfg = self::loadSettings($pdo);
        if ($cfg === []) {
            return;
        }
        if (empty((int) ($cfg['cloaker_enabled'] ?? 1))) {
            return;
        }

        self::ensureSessionIfNeeded($cfg);
        $script = self::scriptBasename();

        if (self::isExemptBasename($cfg, $script)) {
            return;
        }
        if (self::onSafePage($cfg, $script)) {
            return;
        }

        $advanced = !empty((int) ($cfg['advanced_cloaker'] ?? 0));
        $trafficLog = !empty((int) ($cfg['traffic_log_enabled'] ?? 1));
        $legacyBlocking = !empty((int) ($cfg['blocking_enabled'] ?? 0));

        $ua = self::ua();
        $uaLower = mb_strtolower($ua, 'UTF-8');
        $vendor = self::guessVendorLabel($uaLower);
        $respect = !empty((int) ($cfg['respect_benign_bots'] ?? 1));
        $benignTrust = $respect && self::isBenignBot($uaLower);

        if ($advanced) {
            $t = self::evaluateAdvancedThreat($cfg, self::clientIp(), $ua, $uaLower, $benignTrust);
            $legacyEval = self::evaluate($cfg, $ua, $legacyBlocking);
            $extraThreat = $legacyEval['blocked'] ?? false;

            $suppressPassTrafficLog = false;
            if ($t['threat']) {
                $reason = $t['reason'] !== '' ? $t['reason'] : 'threat';
                $continuePage = self::respondAdvancedThreat($pdo, $cfg, $reason, $vendor);
                if (!$continuePage) {
                    return;
                }
                if ((string) ($cfg['threat_handling'] ?? '') === 'log_only') {
                    $suppressPassTrafficLog = true;
                }
            }
            if ($extraThreat) {
                $lr = (string) ($legacyEval['reason'] ?? 'legacy UA rules');
                $continuePage = self::respondAdvancedThreat($pdo, $cfg, $lr, $vendor);
                if (!$continuePage) {
                    return;
                }
                if ((string) ($cfg['threat_handling'] ?? '') === 'log_only') {
                    $suppressPassTrafficLog = true;
                }
            }

            self::bumpStats($pdo, false);
            $finalVerdict = ($benignTrust || !empty($legacyEval['benign'])) ? 'allow_bot' : 'allow';
            if (!$suppressPassTrafficLog) {
                self::insertTraffic($pdo, $cfg, $finalVerdict, null, $vendor);
            }
            if (($cfg['js_mode'] ?? 'esnek') === 'agresif') {
                if (!headers_sent()) {
                    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                    setcookie(self::COOKIE_JS, 'active', time() + 86400, '/', '', $secure, false);
                }
            }

            return;
        }

        if (!$trafficLog && !$legacyBlocking) {
            return;
        }

        $eval = self::evaluate($cfg, $ua, $legacyBlocking);
        if ($legacyBlocking && !empty($eval['blocked'])) {
            self::insertTraffic($pdo, $cfg, 'block', $eval['reason'] ?? 'engellendi', $vendor);
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erişim reddedildi.';

            exit;
        }
        if ($trafficLog) {
            $verdict = !empty($eval['benign']) ? 'allow_bot' : 'allow';
            self::insertTraffic($pdo, $cfg, $verdict, null, $vendor);
        }
    }

    /**
     * Reklam geçidi (go.php): bot → güvenli sayfa, gerçek kullanıcı → vitrin kampanya URL.
     */
    public static function runGateway(PDO $pdo, string $token): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }
        $cfg = self::loadSettings($pdo);
        if ($cfg === [] || empty((int) ($cfg['cloaker_enabled'] ?? 1))) {
            self::gatewayNotFound();

            return;
        }
        if (empty((int) ($cfg['gateway_enabled'] ?? 0))) {
            self::gatewayNotFound();

            return;
        }
        $expected = trim((string) ($cfg['gateway_token'] ?? ''));
        $token = trim($token);
        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            self::gatewayNotFound();

            return;
        }

        self::ensureSessionIfNeeded($cfg);
        $ua = self::ua();
        $uaLower = mb_strtolower($ua, 'UTF-8');
        $vendor = self::guessVendorLabel($uaLower);
        $respect = !empty((int) ($cfg['respect_benign_bots'] ?? 1));
        $benignTrust = $respect && self::isBenignBot($uaLower);
        $advanced = !empty((int) ($cfg['advanced_cloaker'] ?? 0));
        $legacyBlocking = !empty((int) ($cfg['blocking_enabled'] ?? 0));

        /** Geçit token geçerliyse ref zorunluluğu burada aranmaz; hedef vitrinde ref eklenir. */
        $cfgEval = $cfg;
        $cfgEval['require_ref'] = 0;
        $mmdbRel = trim(str_replace('\\', '/', (string) ($cfg['geoip_mmdb_path'] ?? '')));
        $mmdbAbs = $mmdbRel !== '' ? self::projectRoot() . '/' . ltrim($mmdbRel, '/') : '';
        if (!empty((int) ($cfgEval['geoip_on'] ?? 0)) && !is_readable($mmdbAbs)) {
            $cfgEval['geoip_on'] = 0;
        }

        $threat = false;
        $reason = '';
        if ($advanced) {
            $t = self::evaluateAdvancedThreat($cfgEval, self::clientIp(), $ua, $uaLower, $benignTrust);
            $threat = $t['threat'];
            $reason = $t['reason'];
            if (!$threat) {
                $legacyEval = self::evaluate($cfgEval, $ua, $legacyBlocking);
                if (!empty($legacyEval['blocked'])) {
                    $threat = true;
                    $reason = (string) ($legacyEval['reason'] ?? 'legacy UA');
                }
            }
        } else {
            $eval = self::evaluate($cfgEval, $ua, $legacyBlocking || !empty((int) ($cfg['block_known_bots'] ?? 1)));
            if (!empty($eval['blocked'])) {
                $threat = true;
                $reason = (string) ($eval['reason'] ?? 'UA');
            }
        }

        if ($threat) {
            $continuePage = self::respondAdvancedThreat($pdo, $cfg, $reason !== '' ? $reason : 'gateway threat', $vendor);
            if (!$continuePage) {
                return;
            }
        }

        require_once __DIR__ . '/cloaker_helpers.php';
        self::insertTraffic($pdo, $cfg, $benignTrust ? 'allow_bot' : 'allow', null, $vendor);
        self::bumpStats($pdo, false);
        $dest = cloaker_campaign_url($pdo, $cfg);
        header('Location: ' . $dest, true, 302);
        exit;
    }

    private static function gatewayNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Sayfa bulunamadı.';
        exit;
    }
}
