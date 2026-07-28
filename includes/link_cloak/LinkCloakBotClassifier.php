<?php
declare(strict_types=1);

/**
 * Link geçidi bot sınıflandırması — Geçit Merkezi (site cloaker’dan bağımsız).
 */
final class LinkCloakBotClassifier
{
    /** @return array{is_bot: bool, reason: string, vendor: string} */
    public static function classify(string $ua, bool $strict = true): array
    {
        $uaLower = mb_strtolower(trim($ua), 'UTF-8');
        $vendor = self::guessVendor($uaLower);

        if ($strict && $uaLower === '') {
            return ['is_bot' => true, 'reason' => 'boş UA', 'vendor' => $vendor];
        }
        if ($strict && mb_strlen($uaLower, 'UTF-8') > 0 && mb_strlen($uaLower, 'UTF-8') < 12) {
            return ['is_bot' => true, 'reason' => 'kısa UA', 'vendor' => $vendor];
        }

        foreach (self::botNeedles() as $needle) {
            if (str_contains($uaLower, $needle)) {
                return ['is_bot' => true, 'reason' => 'bot: ' . $needle, 'vendor' => $vendor];
            }
        }
        foreach (self::scraperNeedles() as $needle) {
            if (str_contains($uaLower, $needle)) {
                return ['is_bot' => true, 'reason' => 'scraper: ' . $needle, 'vendor' => $vendor];
            }
        }

        return ['is_bot' => false, 'reason' => '', 'vendor' => $vendor];
    }

    private static function guessVendor(string $uaLower): string
    {
        if (preg_match('/facebook|meta-external|facebot|instagrambot|whatsapp/', $uaLower)) {
            return 'meta';
        }
        if (preg_match('/googlebot|adsbot|google-inspection|mediapartners-google/', $uaLower)) {
            return 'google';
        }
        if (str_contains($uaLower, 'bingbot')) {
            return 'microsoft';
        }

        return '';
    }

    /** @return list<string> */
    private static function botNeedles(): array
    {
        return [
            'facebookexternalhit', 'facebot', 'meta-externalads', 'meta-externalagent', 'meta-externalfetcher',
            'meta-webindexer', 'instagrambot', 'googlebot', 'adsbot-google', 'google-inspectiontool',
            'bingbot', 'twitterbot', 'linkedinbot', 'slackbot', 'telegrambot',
            'pinterestbot', 'tiktokspider', 'bytespider', 'embedly', 'whatsapp',
        ];
    }

    /** @return list<string> */
    private static function scraperNeedles(): array
    {
        return [
            'curl/', 'wget/', 'python-requests', 'scrapy/', 'headlesschrome',
            'phantomjs', 'selenium', 'puppeteer', 'libwww-perl', 'go-http-client',
        ];
    }
}
