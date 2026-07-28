<?php
declare(strict_types=1);

require_once __DIR__ . '/site_helpers.php';

final class SafePageService
{
    /** @return list<array{title:string,category:string,date:string,excerpt:string}> */
    public static function defaultPosts(): array
    {
        return [
            [
                'title' => 'Günlük yürüyüşün kalp sağlığına etkisi',
                'category' => 'Sağlık',
                'date' => '2026-03-12',
                'excerpt' => 'Düzenli tempolu yürüyüş, kan basıncını dengelemeye ve stres hormonlarını azaltmaya yardımcı olabilir. Uzmanlar günde otuz dakikalık hafif aktiviteyi öneriyor.',
            ],
            [
                'title' => 'Evde dengeli beslenme için beş pratik ipucu',
                'category' => 'Beslenme',
                'date' => '2026-03-08',
                'excerpt' => 'Tabağınızın yarısını sebze ve meyve ile doldurmak, porsiyon kontrolünü kolaylaştırır. Şekerli içecekler yerine su ve bitki çayları tercih edilebilir.',
            ],
            [
                'title' => 'Uyku düzeni neden önemli?',
                'category' => 'Yaşam',
                'date' => '2026-03-01',
                'excerpt' => 'Yeterli ve düzenli uyku, konsantrasyonu ve bağışıklık sistemini destekler. Yatmadan önce ekran süresini kısaltmak uykuya geçişi hızlandırır.',
            ],
            [
                'title' => 'Mevsim geçişlerinde bağışıklığı desteklemek',
                'category' => 'Sağlık',
                'date' => '2026-02-22',
                'excerpt' => 'C vitamini açısından zengin besinler, lifli gıdalar ve bol sıvı tüketimi günlük rutinin parçası olabilir. Aşı takviminizi hekiminizle gözden geçirin.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultConfig(PDO $pdo): array
    {
        $siteName = 'Yaşam Rehberi';
        try {
            $sn = trim((string) $pdo->query('SELECT site_name FROM settings WHERE id = 1 LIMIT 1')->fetchColumn());
            if ($sn !== '') {
                $siteName = $sn;
            }
        } catch (Throwable $e) {
        }

        return [
            'brand_name' => $siteName,
            'meta_title' => $siteName . ' — Sağlık ve yaşam yazıları',
            'meta_description' => 'Günlük yaşam, beslenme ve sağlıklı alışkanlıklar üzerine sade, bilgilendirici içerikler.',
            'hero_title' => 'Sağlıklı yaşam notları',
            'hero_subtitle' => 'Beslenme, hareket ve günlük rutinler hakkında okunabilir, güncel yazılar.',
            'posts_json' => json_encode(self::defaultPosts(), JSON_UNESCAPED_UNICODE),
        ];
    }

    /** @return array<string, mixed> */
    public static function load(PDO $pdo): array
    {
        $defaults = self::defaultConfig($pdo);
        try {
            $row = $pdo->query(
                'SELECT safe_page_brand_name, safe_page_meta_title, safe_page_meta_description,
                        safe_page_hero_title, safe_page_hero_subtitle, safe_page_posts_json
                 FROM cloaker_settings WHERE id = 1 LIMIT 1'
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $row = false;
        }
        if (! is_array($row)) {
            return $defaults;
        }

        $cfg = $defaults;
        foreach ([
            'brand_name' => 'safe_page_brand_name',
            'meta_title' => 'safe_page_meta_title',
            'meta_description' => 'safe_page_meta_description',
            'hero_title' => 'safe_page_hero_title',
            'hero_subtitle' => 'safe_page_hero_subtitle',
        ] as $k => $col) {
            $v = trim((string) ($row[$col] ?? ''));
            if ($v !== '') {
                $cfg[$k] = $v;
            }
        }
        $rawPosts = trim((string) ($row['safe_page_posts_json'] ?? ''));
        if ($rawPosts !== '') {
            $decoded = json_decode($rawPosts, true);
            if (is_array($decoded) && $decoded !== []) {
                $cfg['posts_json'] = $rawPosts;
            }
        }

        return $cfg;
    }

    /** @return list<array{title:string,category:string,date:string,excerpt:string}> */
    public static function postsFromConfig(array $cfg): array
    {
        $raw = (string) ($cfg['posts_json'] ?? '');
        $list = json_decode($raw, true);
        if (! is_array($list)) {
            return self::defaultPosts();
        }
        $out = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out[] = [
                'title' => mb_substr($title, 0, 200),
                'category' => mb_substr(trim((string) ($item['category'] ?? 'Genel')), 0, 64),
                'date' => mb_substr(trim((string) ($item['date'] ?? '')), 0, 16),
                'excerpt' => mb_substr(trim((string) ($item['excerpt'] ?? '')), 0, 600),
            ];
        }

        return $out !== [] ? $out : self::defaultPosts();
    }

    private static function pageUrl(): string
    {
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/safe-page.php');
        $uri = strtok($uri, '?') ?: '/safe-page.php';

        return ($https ? 'https' : 'http') . '://' . $host . $uri;
    }

    private static function formatDate(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        $ts = strtotime($iso);

        return $ts !== false ? date('d.m.Y', $ts) : $iso;
    }

    public static function render(PDO $pdo): void
    {
        $cfg = self::load($pdo);
        $posts = self::postsFromConfig($cfg);
        $brand = htmlspecialchars((string) $cfg['brand_name'], ENT_QUOTES, 'UTF-8');
        $metaTitle = htmlspecialchars((string) $cfg['meta_title'], ENT_QUOTES, 'UTF-8');
        $metaDesc = htmlspecialchars((string) $cfg['meta_description'], ENT_QUOTES, 'UTF-8');
        $heroTitle = htmlspecialchars((string) $cfg['hero_title'], ENT_QUOTES, 'UTF-8');
        $heroSub = htmlspecialchars((string) $cfg['hero_subtitle'], ENT_QUOTES, 'UTF-8');
        $pageUrl = htmlspecialchars(self::pageUrl(), ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html lang="tr"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>', $metaTitle, '</title>';
        echo '<meta name="description" content="', $metaDesc, '">';
        echo '<meta name="robots" content="index,follow">';
        echo '<link rel="canonical" href="', $pageUrl, '">';
        echo '<meta property="og:type" content="website">';
        echo '<meta property="og:locale" content="tr_TR">';
        echo '<meta property="og:title" content="', $metaTitle, '">';
        echo '<meta property="og:description" content="', $metaDesc, '">';
        echo '<meta property="og:url" content="', $pageUrl, '">';
        echo '<meta property="og:site_name" content="', $brand, '">';
        echo '<meta name="twitter:card" content="summary_large_image">';
        echo '<meta name="twitter:title" content="', $metaTitle, '">';
        echo '<meta name="twitter:description" content="', $metaDesc, '">';
        echo '<style>';
        include __DIR__ . '/safe_page_styles.inc.php';
        echo '</style></head><body>';

        echo '<header class="sp-header"><div class="sp-container sp-header__inner">';
        echo '<a class="sp-logo" href="#">', $brand, '</a>';
        echo '<nav class="sp-nav" aria-label="Ana menü"><a href="#">Ana sayfa</a><a href="#">Yazılar</a><a href="#">Hakkında</a></nav>';
        echo '</div></header>';

        echo '<section class="sp-hero"><div class="sp-container">';
        echo '<p class="sp-hero__eyebrow">Blog</p>';
        echo '<h1 class="sp-hero__title">', $heroTitle, '</h1>';
        echo '<p class="sp-hero__lead">', $heroSub, '</p>';
        echo '</div></section>';

        echo '<main class="sp-main"><div class="sp-container"><div class="sp-grid">';
        foreach ($posts as $i => $post) {
            $t = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
            $cat = htmlspecialchars($post['category'] !== '' ? $post['category'] : 'Genel', ENT_QUOTES, 'UTF-8');
            $ex = htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8');
            $dt = htmlspecialchars(self::formatDate($post['date']), ENT_QUOTES, 'UTF-8');
            $delay = ($i % 3) + 1;
            echo '<article class="sp-card" style="--delay:', (int) $delay, '">';
            echo '<div class="sp-card__thumb" aria-hidden="true"></div>';
            echo '<div class="sp-card__body">';
            echo '<div class="sp-card__meta"><span class="sp-pill">', $cat, '</span>';
            if ($dt !== '') {
                echo '<time datetime="', htmlspecialchars($post['date'], ENT_QUOTES, 'UTF-8'), '">', $dt, '</time>';
            }
            echo '</div><h2 class="sp-card__title"><a href="#">', $t, '</a></h2>';
            echo '<p class="sp-card__excerpt">', $ex, '</p>';
            echo '<a class="sp-card__link" href="#">Devamını oku →</a>';
            echo '</div></article>';
        }
        echo '</div></div></main>';

        echo '<footer class="sp-footer"><div class="sp-container sp-footer__inner">';
        echo '<span>© ', $year, ' ', $brand, '</span>';
        echo '<span class="sp-footer__muted">Bilgilendirme amaçlı içerikler</span>';
        echo '</div></footer></body></html>';
    }
}
