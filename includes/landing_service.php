<?php

declare(strict_types=1);

require_once __DIR__ . '/site_content_urls.php';
require_once __DIR__ . '/app_url.php';

/**
 * Landing page (açılış sayfası) sistemi: blok tanımları, doğrulama ve vitrin render.
 * Bloklar veritabanında JSON olarak tutulur; her blok { "type": "...", "data": {...} } yapısındadır.
 */

/**
 * Blok kayıt defteri. Her blok tipi için etiket, ikon ve alan tanımları.
 * Alan tipleri: text, textarea, html, image, color, url, number, select, lines, bool
 *
 * @return array<string,array{label:string,icon:string,desc:string,fields:array<int,array<string,mixed>>}>
 */
function landing_block_registry(): array
{
    return [
        'hero' => [
            'label' => 'Hero (kapak)',
            'icon' => 'fa-bolt',
            'desc' => 'Büyük başlık, alt metin, arka plan görseli ve çağrı butonu.',
            'fields' => [
                ['key' => 'badge', 'type' => 'text', 'label' => 'Üst rozet', 'placeholder' => 'Kampanya · %50 indirim'],
                ['key' => 'title', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Ürün adınız burada'],
                ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Alt metin', 'placeholder' => 'Kısa ve vurucu bir açıklama.'],
                ['key' => 'image', 'type' => 'image', 'label' => 'Arka plan / ürün görseli'],
                ['key' => 'cta_text', 'type' => 'text', 'label' => 'Buton metni', 'placeholder' => 'Hemen sipariş ver'],
                ['key' => 'cta_url', 'type' => 'url', 'label' => 'Buton linki', 'placeholder' => 'order.php'],
                ['key' => 'align', 'type' => 'select', 'label' => 'Hizalama', 'options' => ['center' => 'Ortalı', 'left' => 'Sola yaslı']],
                ['key' => 'accent', 'type' => 'color', 'label' => 'Vurgu rengi', 'default' => '#6d28d9'],
            ],
        ],
        'features' => [
            'label' => 'Özellikler',
            'icon' => 'fa-star',
            'desc' => 'İkon + başlık + metin kartları (her satır bir kart).',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Bölüm başlığı', 'placeholder' => 'Neden biz?'],
                ['key' => 'items', 'type' => 'lines', 'label' => 'Kartlar', 'hint' => 'Her satır: ikon | başlık | açıklama  (ör: fa-truck | Hızlı kargo | 1-3 gün)'],
                ['key' => 'columns', 'type' => 'select', 'label' => 'Sütun', 'options' => ['3' => '3 sütun', '2' => '2 sütun', '4' => '4 sütun']],
            ],
        ],
        'product' => [
            'label' => 'Ürün kartı',
            'icon' => 'fa-box',
            'desc' => 'Veritabanından ürün adı, fiyatı ve görseli ile satış kartı.',
            'fields' => [
                ['key' => 'product_id', 'type' => 'product', 'label' => 'Ürün'],
                ['key' => 'cta_text', 'type' => 'text', 'label' => 'Buton metni', 'placeholder' => 'Sepete ekle / Sipariş ver'],
                ['key' => 'cta_url', 'type' => 'url', 'label' => 'Buton linki', 'placeholder' => 'order.php?product_id=...'],
                ['key' => 'show_price', 'type' => 'bool', 'label' => 'Fiyatı göster', 'default' => '1'],
            ],
        ],
        'countdown' => [
            'label' => 'Geri sayım',
            'icon' => 'fa-clock',
            'desc' => 'Aciliyet için canlı geri sayım sayacı.',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Kampanya bitişine'],
                ['key' => 'minutes', 'type' => 'number', 'label' => 'Süre (dakika)', 'placeholder' => '30'],
                ['key' => 'note', 'type' => 'text', 'label' => 'Alt not', 'placeholder' => 'Bu fiyat sadece bugün geçerli!'],
                ['key' => 'accent', 'type' => 'color', 'label' => 'Renk', 'default' => '#e11d48'],
            ],
        ],
        'gallery' => [
            'label' => 'Galeri',
            'icon' => 'fa-images',
            'desc' => 'Görsel ızgarası (her satır bir görsel yolu).',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Ürün görselleri'],
                ['key' => 'images', 'type' => 'lines', 'label' => 'Görseller', 'hint' => 'Her satır bir görsel yolu: uploads/ornek.jpg'],
            ],
        ],
        'reviews' => [
            'label' => 'Yorumlar',
            'icon' => 'fa-comment-dots',
            'desc' => 'Müşteri yorumları (her satır bir yorum).',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Mutlu müşteriler'],
                ['key' => 'items', 'type' => 'lines', 'label' => 'Yorumlar', 'hint' => 'Her satır: isim | yıldız(1-5) | yorum  (ör: Ayşe | 5 | Harika ürün!)'],
            ],
        ],
        'faq' => [
            'label' => 'S.S.S.',
            'icon' => 'fa-circle-question',
            'desc' => 'Soru-cevap akordiyonu (her satır bir soru).',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Sık sorulan sorular'],
                ['key' => 'items', 'type' => 'lines', 'label' => 'Sorular', 'hint' => 'Her satır: soru | cevap'],
            ],
        ],
        'form' => [
            'label' => 'Form',
            'icon' => 'fa-wpforms',
            'desc' => 'Mevcut bir dinamik formu göm.',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Bize ulaşın'],
                ['key' => 'form_id', 'type' => 'form', 'label' => 'Form'],
            ],
        ],
        'rich' => [
            'label' => 'Serbest içerik',
            'icon' => 'fa-align-left',
            'desc' => 'Güvenilir HTML metin bloğu.',
            'fields' => [
                ['key' => 'html', 'type' => 'html', 'label' => 'HTML içerik'],
            ],
        ],
        'video' => [
            'label' => 'Video',
            'icon' => 'fa-play',
            'desc' => 'YouTube / Vimeo gömme.',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Tanıtım videosu'],
                ['key' => 'url', 'type' => 'url', 'label' => 'Video linki', 'placeholder' => 'https://www.youtube.com/watch?v=...'],
            ],
        ],
        'cta' => [
            'label' => 'Çağrı (CTA)',
            'icon' => 'fa-bullhorn',
            'desc' => 'Renkli aksiyon bandı.',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Başlık', 'placeholder' => 'Fırsatı kaçırma!'],
                ['key' => 'text', 'type' => 'textarea', 'label' => 'Metin'],
                ['key' => 'cta_text', 'type' => 'text', 'label' => 'Buton metni', 'placeholder' => 'Hemen sipariş ver'],
                ['key' => 'cta_url', 'type' => 'url', 'label' => 'Buton linki', 'placeholder' => 'order.php'],
                ['key' => 'accent', 'type' => 'color', 'label' => 'Renk', 'default' => '#059669'],
            ],
        ],
        'spacer' => [
            'label' => 'Boşluk',
            'icon' => 'fa-arrows-up-down',
            'desc' => 'Bloklar arasında dikey boşluk.',
            'fields' => [
                ['key' => 'height', 'type' => 'number', 'label' => 'Yükseklik (px)', 'placeholder' => '48'],
            ],
        ],
    ];
}

/** @return array<string,array{label:string,vars:array<string,string>}> */
function landing_theme_registry(): array
{
    return [
        'aurora' => ['label' => 'Aurora (mor)', 'vars' => [
            '--lp-bg' => '#0b1020', '--lp-surface' => '#141b2e', '--lp-text' => '#e7ebf5',
            '--lp-muted' => '#9aa6c4', '--lp-accent' => '#7c3aed', '--lp-accent2' => '#22d3ee',
        ]],
        'sunset' => ['label' => 'Gün batımı', 'vars' => [
            '--lp-bg' => '#1a1013', '--lp-surface' => '#26171b', '--lp-text' => '#fdeee6',
            '--lp-muted' => '#d3a99a', '--lp-accent' => '#f97316', '--lp-accent2' => '#ec4899',
        ]],
        'mint' => ['label' => 'Aydınlık nane', 'vars' => [
            '--lp-bg' => '#f6fdfb', '--lp-surface' => '#ffffff', '--lp-text' => '#0f2f27',
            '--lp-muted' => '#5b7a72', '--lp-accent' => '#059669', '--lp-accent2' => '#0ea5e9',
        ]],
        'clean' => ['label' => 'Aydınlık sade', 'vars' => [
            '--lp-bg' => '#f8fafc', '--lp-surface' => '#ffffff', '--lp-text' => '#0f172a',
            '--lp-muted' => '#64748b', '--lp-accent' => '#4f46e5', '--lp-accent2' => '#f59e0b',
        ]],
    ];
}

function landing_slugify(string $raw): string
{
    $raw = trim(mb_strtolower($raw, 'UTF-8'));
    $map = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u'];
    $raw = strtr($raw, $map);
    $raw = preg_replace('/[^a-z0-9]+/', '-', $raw) ?? '';
    $raw = trim($raw, '-');
    if ($raw === '') {
        $raw = 'sayfa-' . substr((string) time(), -6);
    }
    return substr($raw, 0, 150);
}

/**
 * Editörden gelen ham blok dizisini güvenli, normalize edilmiş bir yapıya çevirir.
 *
 * @param mixed $raw
 * @return array<int,array{type:string,data:array<string,string>}>
 */
function landing_sanitize_blocks($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $registry = landing_block_registry();
    $out = [];
    foreach ($raw as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = (string) ($block['type'] ?? '');
        if (!isset($registry[$type])) {
            continue;
        }
        $data = [];
        foreach ($registry[$type]['fields'] as $field) {
            $key = (string) $field['key'];
            $val = $block['data'][$key] ?? ($block[$key] ?? '');
            if (is_array($val)) {
                $val = '';
            }
            $val = (string) $val;
            if (($field['type'] ?? '') === 'bool') {
                $val = ($val === '1' || $val === 'on' || $val === 'true') ? '1' : '0';
            }
            $data[$key] = $val;
        }
        $out[] = ['type' => $type, 'data' => $data];
    }
    return $out;
}

/** @return array<int,array{type:string,data:array<string,string>}> */
function landing_decode_blocks(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return landing_sanitize_blocks($decoded);
}

/** @return array<int,array{type:string,data:array<string,string>}> */
function landing_starter_blocks(): array
{
    return landing_sanitize_blocks([
        ['type' => 'hero', 'data' => [
            'badge' => 'Yeni kampanya',
            'title' => 'Ürününüzü buraya yazın',
            'subtitle' => 'Kısa, net ve ikna edici bir açıklama ekleyin. Ziyaretçiyi aksiyona yönlendirin.',
            'cta_text' => 'Hemen sipariş ver',
            'cta_url' => 'order.php',
            'align' => 'center',
            'accent' => '#7c3aed',
        ]],
        ['type' => 'features', 'data' => [
            'heading' => 'Neden biz?',
            'items' => "fa-truck | Hızlı kargo | 1-3 iş günü içinde kapınızda\nfa-shield-halved | Güvenli alışveriş | Kapıda ödeme imkanı\nfa-headset | 7/24 destek | Her an yanınızdayız",
            'columns' => '3',
        ]],
        ['type' => 'cta', 'data' => [
            'heading' => 'Fırsatı kaçırmayın',
            'text' => 'Bugüne özel indirimli fiyat.',
            'cta_text' => 'Sipariş ver',
            'cta_url' => 'order.php',
            'accent' => '#059669',
        ]],
    ]);
}

/** Bir "lines" alanını ayrıştırır: her satır | ile bölünür. @return array<int,array<int,string>> */
function landing_parse_lines(string $raw, int $cols): array
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        while (count($parts) < $cols) {
            $parts[] = '';
        }
        $rows[] = $parts;
    }
    return $rows;
}

function landing_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Buton/link URL'ini güvenli hale getirir (yalnız http(s), göreli yol veya boş). */
function landing_safe_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
        return '';
    }
    return $url;
}

/** YouTube/Vimeo linkini gömme (embed) adresine çevirir. */
function landing_video_embed(string $url): string
{
    $url = trim($url);
    if (preg_match('#youtube\.com/watch\?v=([\w-]+)#i', $url, $m) || preg_match('#youtu\.be/([\w-]+)#i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#vimeo\.com/(\d+)#i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return $url;
}

/**
 * Tüm blokları vitrin HTML'ine dönüştürür.
 *
 * @param array<int,array{type:string,data:array<string,string>}> $blocks
 */
function landing_render_blocks(PDO $pdo, array $blocks): string
{
    $out = '';
    foreach ($blocks as $block) {
        $out .= landing_render_block($pdo, (string) $block['type'], $block['data']);
    }
    return $out;
}

/** @param array<string,string> $d */
function landing_render_block(PDO $pdo, string $type, array $d): string
{
    switch ($type) {
        case 'hero':
            $align = ($d['align'] ?? 'center') === 'left' ? 'lp-hero--left' : 'lp-hero--center';
            $accent = landing_e($d['accent'] ?? '#7c3aed');
            $img = trim($d['image'] ?? '');
            $style = 'style="--lp-block-accent:' . $accent . ';"';
            $bg = '';
            if ($img !== '') {
                $bg = '<div class="lp-hero__bg" style="background-image:url(' . landing_e(site_media_public_src($img, $pdo)) . ')"></div>';
            }
            $badge = trim($d['badge'] ?? '') !== '' ? '<span class="lp-hero__badge">' . landing_e($d['badge']) . '</span>' : '';
            $sub = trim($d['subtitle'] ?? '') !== '' ? '<p class="lp-hero__sub">' . nl2br(landing_e($d['subtitle'])) . '</p>' : '';
            $cta = landing_button($d['cta_text'] ?? '', $d['cta_url'] ?? '', 'lp-btn lp-btn--primary lp-btn--lg');
            return '<section class="lp-hero ' . $align . '" ' . $style . '>' . $bg
                . '<div class="lp-hero__inner lp-wrap">' . $badge
                . '<h1 class="lp-hero__title">' . landing_e($d['title'] ?? '') . '</h1>'
                . $sub . $cta . '</div></section>';

        case 'features':
            $cols = (int) ($d['columns'] ?? 3);
            $cols = in_array($cols, [2, 3, 4], true) ? $cols : 3;
            $items = landing_parse_lines($d['items'] ?? '', 3);
            $cards = '';
            foreach ($items as $it) {
                $icon = $it[0] !== '' ? $it[0] : 'fa-check';
                if (strpos($icon, 'fa-') === 0) {
                    $icon = 'fas ' . $icon;
                }
                $cards .= '<div class="lp-feature"><span class="lp-feature__ic"><i class="' . landing_e($icon) . '"></i></span>'
                    . '<h3>' . landing_e($it[1]) . '</h3><p>' . landing_e($it[2]) . '</p></div>';
            }
            return '<section class="lp-section lp-wrap">' . landing_heading($d['heading'] ?? '')
                . '<div class="lp-grid lp-grid--' . $cols . '">' . $cards . '</div></section>';

        case 'product':
            return landing_render_product($pdo, $d);

        case 'countdown':
            $mins = max(1, (int) ($d['minutes'] ?? 30));
            $accent = landing_e($d['accent'] ?? '#e11d48');
            $id = 'lpcd' . substr(md5(uniqid('', true)), 0, 6);
            $note = trim($d['note'] ?? '') !== '' ? '<div class="lp-cd__note">' . landing_e($d['note']) . '</div>' : '';
            $html = '<section class="lp-section lp-wrap"><div class="lp-cd" style="--lp-block-accent:' . $accent . '">'
                . landing_heading($d['heading'] ?? '')
                . '<div class="lp-cd__timer" id="' . $id . '" data-mins="' . $mins . '">'
                . '<div class="lp-cd__cell"><span data-h>00</span><small>saat</small></div>'
                . '<div class="lp-cd__cell"><span data-m>00</span><small>dakika</small></div>'
                . '<div class="lp-cd__cell"><span data-s>00</span><small>saniye</small></div></div>'
                . $note . '</div></section>';
            $html .= '<script>(function(){var el=document.getElementById("' . $id . '");if(!el)return;'
                . 'var end=Date.now()+(parseInt(el.dataset.mins,10)||30)*60000;'
                . 'function p(n){return (n<10?"0":"")+n;}'
                . 'function t(){var r=Math.max(0,end-Date.now());var s=Math.floor(r/1000);'
                . 'el.querySelector("[data-h]").textContent=p(Math.floor(s/3600));'
                . 'el.querySelector("[data-m]").textContent=p(Math.floor((s%3600)/60));'
                . 'el.querySelector("[data-s]").textContent=p(s%60);if(r>0)requestAnimationFrame(function(){setTimeout(t,250);});}'
                . 't();})();</script>';
            return $html;

        case 'gallery':
            $imgs = landing_parse_lines($d['images'] ?? '', 1);
            $cells = '';
            foreach ($imgs as $row) {
                $src = trim($row[0]);
                if ($src === '') {
                    continue;
                }
                $cells .= '<figure class="lp-gallery__cell"><img src="' . landing_e(site_media_public_src($src, $pdo)) . '" alt="" loading="lazy"></figure>';
            }
            return '<section class="lp-section lp-wrap">' . landing_heading($d['heading'] ?? '')
                . '<div class="lp-gallery">' . $cells . '</div></section>';

        case 'reviews':
            $items = landing_parse_lines($d['items'] ?? '', 3);
            $cards = '';
            foreach ($items as $it) {
                $stars = max(1, min(5, (int) ($it[1] !== '' ? $it[1] : 5)));
                $starHtml = str_repeat('<i class="fas fa-star"></i>', $stars) . str_repeat('<i class="far fa-star"></i>', 5 - $stars);
                $cards .= '<div class="lp-review"><div class="lp-review__stars">' . $starHtml . '</div>'
                    . '<p>' . landing_e($it[2]) . '</p><div class="lp-review__name">' . landing_e($it[0]) . '</div></div>';
            }
            return '<section class="lp-section lp-wrap">' . landing_heading($d['heading'] ?? '')
                . '<div class="lp-grid lp-grid--3">' . $cards . '</div></section>';

        case 'faq':
            $items = landing_parse_lines($d['items'] ?? '', 2);
            $acc = '';
            foreach ($items as $it) {
                $acc .= '<details class="lp-faq__item"><summary>' . landing_e($it[0]) . '</summary><div class="lp-faq__a">' . nl2br(landing_e($it[1])) . '</div></details>';
            }
            return '<section class="lp-section lp-wrap lp-wrap--narrow">' . landing_heading($d['heading'] ?? '')
                . '<div class="lp-faq">' . $acc . '</div></section>';

        case 'form':
            return landing_render_form($pdo, $d);

        case 'rich':
            return '<section class="lp-section lp-wrap lp-prose">' . ($d['html'] ?? '') . '</section>';

        case 'video':
            $embed = landing_video_embed($d['url'] ?? '');
            if ($embed === '') {
                return '';
            }
            return '<section class="lp-section lp-wrap">' . landing_heading($d['heading'] ?? '')
                . '<div class="lp-video"><iframe src="' . landing_e($embed) . '" title="video" loading="lazy" allowfullscreen></iframe></div></section>';

        case 'cta':
            $accent = landing_e($d['accent'] ?? '#059669');
            return '<section class="lp-cta" style="--lp-block-accent:' . $accent . '"><div class="lp-wrap lp-cta__inner">'
                . '<div><h2>' . landing_e($d['heading'] ?? '') . '</h2>'
                . (trim($d['text'] ?? '') !== '' ? '<p>' . nl2br(landing_e($d['text'])) . '</p>' : '') . '</div>'
                . landing_button($d['cta_text'] ?? '', $d['cta_url'] ?? '', 'lp-btn lp-btn--light lp-btn--lg') . '</div></section>';

        case 'spacer':
            $h = max(0, min(400, (int) ($d['height'] ?? 48)));
            return '<div class="lp-spacer" style="height:' . $h . 'px"></div>';
    }
    return '';
}

function landing_heading(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    return '<h2 class="lp-section__title">' . landing_e($text) . '</h2>';
}

function landing_button(string $text, string $url, string $cls): string
{
    $text = trim($text);
    $url = landing_safe_url($url);
    if ($text === '' || $url === '') {
        return '';
    }
    return '<a class="' . landing_e($cls) . '" href="' . landing_e($url) . '">' . landing_e($text) . '</a>';
}

/** @param array<string,string> $d */
function landing_render_product(PDO $pdo, array $d): string
{
    $pid = (int) ($d['product_id'] ?? 0);
    if ($pid <= 0) {
        return '';
    }
    try {
        $st = $pdo->prepare('SELECT product_id, product_name, product_price, product_image FROM products WHERE product_id = ? LIMIT 1');
        $st->execute([$pid]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $p = null;
    }
    if (!$p) {
        return '';
    }
    $img = trim((string) ($p['product_image'] ?? ''));
    $pName = function_exists('content_t') ? content_t('product', (int) $p['product_id'], 'name', (string) $p['product_name']) : (string) $p['product_name'];
    $imgHtml = $img !== '' ? '<div class="lp-product__img"><img src="' . landing_e(site_media_public_src($img, $pdo)) . '" alt="' . landing_e($pName) . '" loading="lazy"></div>' : '';
    $price = '';
    if (($d['show_price'] ?? '1') === '1') {
        $priceStr = function_exists('money')
            ? money((float) $p['product_price'])
            : number_format((float) $p['product_price'], 2, ',', '.') . ' TL';
        $price = '<div class="lp-product__price price">' . landing_e($priceStr) . '</div>';
    }
    $ctaUrl = trim($d['cta_url'] ?? '');
    if ($ctaUrl === '') {
        $ctaUrl = 'order.php?product_id=' . $pid;
    }
    $cta = landing_button($d['cta_text'] ?? 'Sipariş ver', $ctaUrl, 'lp-btn lp-btn--primary lp-btn--lg');
    return '<section class="lp-section lp-wrap"><div class="lp-product">' . $imgHtml
        . '<div class="lp-product__body"><h2>' . landing_e($pName) . '</h2>'
        . $price . $cta . '</div></div></section>';
}

/** @param array<string,string> $d */
function landing_render_form(PDO $pdo, array $d): string
{
    $fid = (int) ($d['form_id'] ?? 0);
    if ($fid <= 0) {
        return '';
    }
    try {
        $st = $pdo->prepare('SELECT * FROM custom_forms WHERE id = ? AND is_active = 1 LIMIT 1');
        $st->execute([$fid]);
        $form = $st->fetch(PDO::FETCH_ASSOC);
        if (!$form) {
            return '';
        }
        $fs = $pdo->prepare('SELECT * FROM custom_form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC');
        $fs->execute([$fid]);
        $fields = $fs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return '';
    }
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['csrf_cf'])) {
        $_SESSION['csrf_cf'] = bin2hex(random_bytes(16));
    }
    $csrf = (string) $_SESSION['csrf_cf'];

    $inner = '';
    foreach ($fields as $f) {
        $label = landing_e((string) ($f['label'] ?? ''));
        $key = landing_e((string) ($f['field_key'] ?? ''));
        $reqAttr = !empty($f['is_required']) ? ' required' : '';
        $type = (string) ($f['field_type'] ?? 'text');
        $name = 'f[' . $key . ']';
        if ($type === 'textarea') {
            $control = '<textarea name="' . $name . '" rows="4"' . $reqAttr . '></textarea>';
        } elseif ($type === 'select') {
            $opts = '<option value="">Seçin</option>';
            foreach (preg_split('/\r\n|\r|\n/', (string) ($f['options_text'] ?? '')) ?: [] as $o) {
                $o = trim((string) $o);
                if ($o !== '') {
                    $opts .= '<option>' . landing_e($o) . '</option>';
                }
            }
            $control = '<select name="' . $name . '"' . $reqAttr . '>' . $opts . '</select>';
        } elseif ($type === 'checkbox') {
            $control = '<span class="lp-form__check"><input type="checkbox" name="' . $name . '" value="1"' . $reqAttr . '> Evet</span>';
        } else {
            $itype = $type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : ($type === 'number' ? 'number' : 'text'));
            $control = '<input type="' . $itype . '" name="' . $name . '"' . $reqAttr . '>';
        }
        $inner .= '<label class="lp-form__row"><span>' . $label . (!empty($f['is_required']) ? ' *' : '') . '</span>' . $control . '</label>';
    }
    $heading = trim($d['heading'] ?? '') !== '' ? $d['heading'] : (string) $form['title'];
    return '<section class="lp-section lp-wrap lp-wrap--narrow">' . landing_heading($heading)
        . '<form class="lp-form" method="post" action="' . landing_e(app_url('dinamik_form.php')) . '">'
        . '<input type="hidden" name="csrf" value="' . landing_e($csrf) . '">'
        . '<input type="hidden" name="slug" value="' . landing_e((string) $form['slug']) . '">'
        . $inner
        . '<button type="submit" class="lp-btn lp-btn--primary lp-btn--lg">Gönder</button></form></section>';
}
