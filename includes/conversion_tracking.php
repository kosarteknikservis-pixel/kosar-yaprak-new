<?php
declare(strict_types=1);

function normalize_digits_for_conversion(string $phone): string
{
    $d = preg_replace('/\D+/', '', $phone) ?? '';

    return strlen($d) >= 10 ? substr($d, -10) : $d;
}

/**
 * Meta: _fbc çerezi yoksa fbclid + biçime göre fbc oluştur.
 */
function meta_resolve_fbc_string(array $ctx): ?string
{
    $c = preg_replace('/\s+/', '', (string)($ctx['fbc_cookie'] ?? ''));
    if ($c !== '') {
        return $c;
    }
    $fbclid = trim((string)($ctx['fbclid_session'] ?? ''));
    if ($fbclid === '') {
        return null;
    }
    /** @disregard sunucuda _fbc oluşturma (Pixelsiz senaryoda) subdomainIndex 1 varsayımı */
    return 'fb.1.' . (string)(int)(microtime(true) * 1000) . '.' . $fbclid;
}

/** Meta için telefon hash (TR: 90 ile ve son 10 hane) — ph dizisi */
function meta_phone_hashes(string $digits10OrMore): array
{
    $d = preg_replace('/\D+/', '', $digits10OrMore) ?? '';
    if (strlen($d) < 10) {
        return [];
    }
    $last = substr($d, -10);
    /** @disregard ülke varyantları için çoklu deneme — Meta normalizasyonu */
    $raws = array_unique([
        $last,
        '90' . $last,
        '0' . $last,
    ]);
    $out = [];
    foreach ($raws as $r) {
        $out[] = hash('sha256', strtolower($r));
    }

    return array_values(array_unique($out));
}

/**
 * GA4 _ga çerezi → Measurement Protocol client_id
 */
function ga4_resolve_client_id(array $ctx): string
{
    $from = trim((string)($ctx['ga_client_id'] ?? ''));
    if ($from !== '') {
        return $from;
    }
    $raw = isset($_COOKIE['_ga']) ? (string)$_COOKIE['_ga'] : '';
    $parts = $raw !== '' ? explode('.', $raw) : [];
    /** GA1.{depo}.{id1}.{id2} — ör. GA1.1.abc.def */
    if (count($parts) >= 4 && str_starts_with($parts[0], 'GA')) {
        return $parts[2] . '.' . $parts[3];
    }
    /** fallback rastgele (önceki davranış) */
    return substr(bin2hex(random_bytes(8)), 0, 16) . '.' . substr(bin2hex(random_bytes(8)), 0, 8);
}

/**
 * Meta Pixel (tarayıcı) + CAPI — dedupe için aynı event kimliği.
 */
function conversion_meta_purchase_event_id(string $orderId, string $currency): string
{
    return 'ord-' . $orderId . '-' . $currency;
}

/**
 * TikTok Pixel + Events API — sunucudaki ile aynı şema (event_id çakışmasını önler).
 */
function conversion_tiktok_purchase_event_id(string $orderId): string
{
    return 'ord-' . preg_replace('/\W+/', '', $orderId);
}

/**
 * thankyou Purchase — tarayıcı pikseller + GTM için ortak parametre kümesi.
 *
 * @param list<array<string,mixed>> $orderItemRows product_id, quantity, price, product_name
 *
 * @return array<string,mixed>
 */
function conversion_build_purchase_tracking_payload(array $orderItemRows, string $orderId, float $value, string $currency): array
{
    /** @var list<array<string, float|int|string>> $normalized */
    $normalized = [];

    foreach ($orderItemRows as $r) {
        if (!is_array($r)) {
            continue;
        }

        $pid = trim((string) ($r['product_id'] ?? ''));

        if ($pid === '') {
            continue;
        }

        $qty = max(1, (int) ($r['quantity'] ?? 1));
        $lineTotal = isset($r['price']) ? (float) $r['price'] : $value;

        $unitPrice = round($lineTotal / $qty, 2);

        $normalized[] = [
            'product_id' => $pid,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'label' => (string) ($r['product_name'] ?? ''),
        ];
    }

    if ($normalized === []) {
        $fallbackId = preg_replace('/\W/', '', $orderId) ?: 'unknown';

        $normalized[] = [
            'product_id' => $fallbackId,
            'quantity' => 1,
            'unit_price' => round($value, 2),
            'label' => 'Sipariş',
        ];
    }

    $contentIds = [];

    foreach ($normalized as $n) {
        $contentIds[] = (string) $n['product_id'];
    }

    $numItems = 0;

    foreach ($normalized as $n) {
        $numItems += (int) $n['quantity'];
    }

    $metaContents = [];

    foreach ($normalized as $n) {
        $metaContents[] = [
            'id' => (string) $n['product_id'],
            'quantity' => (int) $n['quantity'],
            'item_price' => (float) $n['unit_price'],
        ];

    }

    $ttContents = [];

    foreach ($normalized as $n) {
        $ttContents[] = [
            'content_id' => (string) $n['product_id'],
            'quantity' => (int) $n['quantity'],
            'price' => (float) $n['unit_price'],
        ];

    }

    $gaItems = [];

    foreach ($normalized as $n) {
        $lab = trim((string) $n['label']);

        $gaItems[] = [
            'item_id' => (string) $n['product_id'],
            'item_name' => $lab !== '' ? $lab : (string) $n['product_id'],
            'price' => (float) $n['unit_price'],
            'quantity' => (int) $n['quantity'],
        ];

    }

    return [
        'currency' => $currency,
        'value' => round($value, 2),
        'transaction_id' => (string) $orderId,
        'meta_event_id' => conversion_meta_purchase_event_id($orderId, $currency),
        'tiktok_event_id' => conversion_tiktok_purchase_event_id($orderId),
        'num_items' => $numItems,
        'content_ids' => $contentIds,
        'meta_contents' => $metaContents,
        'tt_contents' => $ttContents,
        'ga_items' => $gaItems,

    ];

}

/**
 * Sipariş onay sayfasında sunucudan gönderilir.
 *
 * @param array<string, mixed> $ctx
 */
function conversion_send_after_purchase(PDO $pdo, array $ctx): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $row = $pdo->query('SELECT * FROM conversion_api_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $orderId = (string)($ctx['order_id'] ?? '');
    $value = (float)($ctx['value'] ?? 0.0);
    $currency = (string)($ctx['currency'] ?? 'TRY');
    $phoneDigits = normalize_digits_for_conversion((string)($ctx['customer_phone'] ?? ''));
    $ip = (string)($ctx['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    $orderLines = [];

    if (isset($ctx['order_lines']) && is_array($ctx['order_lines'])) {
        foreach ($ctx['order_lines'] as $rowItem) {
            if (is_array($rowItem)) {
                $orderLines[] = $rowItem;
            }
        }
    }

    /** @var list<string|int>|array<int, mixed> $prodRaw */
    $prodRaw = (array) ($ctx['product_ids'] ?? []);
    $productIds = [];
    foreach ($prodRaw as $x) {
        $productIds[] = (string) $x;
    }

    if ($orderLines === [] && $productIds !== []) {
        $n = count($productIds);
        $share = $n > 0 ? round($value / $n, 2) : $value;
        foreach ($productIds as $pid) {
            $pidTrim = trim((string) $pid);

            if ($pidTrim !== '') {
                $orderLines[] = [
                    'product_id' => $pidTrim,
                    'quantity' => 1,
                    'price' => $share,
                    'product_name' => '',
                ];
            }
        }
    }

    $builtPurchase = conversion_build_purchase_tracking_payload($orderLines, $orderId, $value, $currency);
    $ua = (string) ($ctx['client_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $fbcResolved = meta_resolve_fbc_string($ctx);
    $fbpCookie = preg_replace('/\s+/', '', (string)($ctx['fbp_cookie'] ?? ''));
    if ($fbpCookie === '') {
        $fbpCookie = preg_replace('/\s+/', '', (string)($_COOKIE['_fbp'] ?? ''));
    }
    if ($fbcResolved === '') {
        $fbcResolved = null;
    }

    /** @disregard Çok varyantlı ph — boş ise hiçbir şey gönderme */
    $phArr = $phoneDigits !== '' ? meta_phone_hashes($phoneDigits) : [];

    // --- Meta Conversion API ---
    $pixelId = trim((string)($row['meta_pixel_id'] ?? ''));
    $metaToken = trim((string)($row['meta_capi_access_token'] ?? ''));
    if (!empty($row['meta_events_enabled']) && $pixelId !== '' && $metaToken !== '') {
        $evt = [
            'event_name'    => 'Purchase',
            'event_time'    => time(),
            'action_source' => 'website',
            'event_id'      => conversion_meta_purchase_event_id($orderId, $currency),
            'user_data'     => array_filter([
                'client_ip_address' => $ip !== '' ? $ip : null,
                'client_user_agent' => $ua !== '' ? $ua : null,
                'ph' => $phArr !== [] ? $phArr : null,
                'fbc' => $fbcResolved !== null ? $fbcResolved : null,
                'fbp' => $fbpCookie !== '' ? $fbpCookie : null,
            ]),
            'custom_data'   => [
                'currency' => $currency,
                'value'    => round((float) $builtPurchase['value'], 2),
                'order_id' => $orderId,
                'content_ids' => $builtPurchase['content_ids'],
                'content_type' => 'product',
                'num_items' => (int) $builtPurchase['num_items'],
                'contents' => $builtPurchase['meta_contents'],
            ],
        ];
        $data = ['data' => json_encode([$evt]), 'access_token' => $metaToken];
        $test = isset($row['meta_capi_test_code']) ? trim((string)$row['meta_capi_test_code']) : '';
        if ($test !== '') {
            $data['test_event_code'] = $test;
        }
        $api = 'https://graph.facebook.com/v21.0/' . rawurlencode($pixelId) . '/events';
        curl_post_form_conversion($api, $data);
    }

    // --- GA4 Measurement Protocol ---
    $meas = trim((string)($row['ga4_measurement_id'] ?? ''));
    $secret = trim((string)($row['ga4_api_secret'] ?? ''));
    if (!empty($row['ga4_mp_enabled']) && $meas !== '' && $secret !== '') {
        $cid = ga4_resolve_client_id($ctx);
        $gaMpItems = [];
        foreach ($builtPurchase['ga_items'] as $it) {
            if (!is_array($it)) {
                continue;
            }
            $gaMpItems[] = [
                'item_id' => (string) ($it['item_id'] ?? ''),
                'item_name' => (string) ($it['item_name'] ?? ''),
                'price' => round((float) ($it['price'] ?? 0), 2),
                'quantity' => max(1, (int) ($it['quantity'] ?? 1)),
            ];
        }

        $params = [
                    'transaction_id' => $orderId,
                    'currency' => $currency,
                    'value' => round((float) $builtPurchase['value'], 2),
        ];

        if ($gaMpItems !== []) {
            $params['items'] = $gaMpItems;
        }

        $url = 'https://www.google-analytics.com/mp/collect?' . http_build_query([
            'measurement_id' => $meas,
            'api_secret' => $secret,
        ]);
        $body = json_encode([
            'client_id' => $cid,
            'events' => [[
                'name' => 'purchase',
                'params' => $params,
            ],
            ],
        ]);

        curl_post_raw_conversion($url, (string) ($body !== false ? $body : '{"client_id":"","events":[]}'));
    }

    // --- TikTok Events API ---
    $tikId = trim((string)($row['tiktok_pixel_id'] ?? ''));
    $tikTok = trim((string)($row['tiktok_events_api_token'] ?? ''));
    if (!empty($row['tiktok_events_enabled']) && $tikId !== '' && $tikTok !== '') {
        $ttl = trim((string)($ctx['ttclid_session'] ?? ''));
        if ($ttl === '' && isset($_SESSION['attr_ttclid'])) {
            /** @disregard thankyou içinde oturum açılmış olmalı */
            $ttl = (string)$_SESSION['attr_ttclid'];
        }

        /** @disregard TikTok PixelTrackBody: callback = ttclid, properties ayrı üst düzeyde */
        $ctxAd = [];
        if ($ttl !== '') {
            $ctxAd['ad'] = ['callback' => $ttl];
        }
        $payload = [
            'pixel_code' => $tikId,
            'event' => 'CompletePayment',
            'event_id' => conversion_tiktok_purchase_event_id($orderId),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'context' => array_filter(array_merge([
                'ip' => $ip !== '' ? $ip : null,
                'user_agent' => $ua !== '' ? $ua : null,
            ], $ctxAd)),
            'properties' => [
                'currency' => $currency,
                'content_type' => 'product',
                'value' => round((float) $builtPurchase['value'], 2),
                'contents' => $builtPurchase['tt_contents'],
                'order_id' => (string) $orderId,
            ],
        ];
        $tikBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($tikBody !== false) {
            curl_post_headers_conversion(
                'https://business-api.tiktok.com/open_api/v1.3/pixel/track/',
                $tikBody,
                [
                    'Content-Type: application/json',
                    'Access-Token: ' . $tikTok,
                ]
            );
        }
    }
}

/** @param array<string, string|string[]> $fields */
function curl_post_form_conversion(string $url, array $fields): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    @curl_exec($ch);
    @curl_close($ch);
}

function curl_post_raw_conversion(string $url, string $json): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    @curl_exec($ch);
    @curl_close($ch);
}

/** @param list<string> $headers */
function curl_post_headers_conversion(string $url, string $body, array $headers): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    @curl_exec($ch);
    @curl_close($ch);
}

/**
 * Meta / TikTok / Yandex / Clarity — panel ID alanları doluysa otomatik &lt;head&gt; betikleri.
 * head_snippet içinde aynı sağlayıcı zaten varsa tekrarlanmaz.
 *
 * @param array<string, mixed> $row
 */
function conversion_embed_client_pixels(array $row, string $headSnippetBefore): string
{
    $hs = strtolower($headSnippetBefore);
    $chunk = '';

    $mp = preg_replace('/\s+/', '', (string) ($row['meta_pixel_id'] ?? ''));
    if ($mp !== '' && strpos($hs, 'fbevents.js') === false && strpos($hs, 'fbq(') === false) {
        $mpEsc = htmlspecialchars($mp, ENT_QUOTES, 'UTF-8');
        $chunk .= <<<HTML

<!-- Meta Pixel (Dönüşüm API sayfası — Pixel ID) -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$mpEsc}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={$mpEsc}&amp;ev=PageView&amp;noscript=1" alt="" /></noscript>
HTML;
    }

    $tik = preg_replace('/[^A-Za-z0-9]/', '', (string) ($row['tiktok_pixel_id'] ?? ''));
    if ($tik !== '' && strpos($hs, 'analytics.tiktok.com') === false && strpos($hs, 'ttq.load') === false) {
        $tikEsc = htmlspecialchars($tik, ENT_QUOTES, 'UTF-8');
        $chunk .= <<<HTML

<!-- TikTok Pixel -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};

ttq.load('{$tikEsc}');
  ttq.page();
}(window, document, 'ttq');
</script>
HTML;
    }

    $ym = preg_replace('/\D+/', '', (string) ($row['yandex_metrica_counter_id'] ?? ''));
    if ($ym !== '' && strpos($hs, 'mc.yandex.ru') === false && strpos($hs, 'yandexmetrica') === false) {
        $ymJs = htmlspecialchars($ym, ENT_QUOTES, 'UTF-8');
        $chunk .= <<<HTML

<!-- Yandex.Metrica -->
<script type="text/javascript">
   (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
   m[i].l=1*new Date();
   for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
   k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
   (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

   ym({$ymJs}, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true,
        ecommerce:"dataLayer"
   });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/{$ymJs}" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
HTML;
    }

    $cl = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($row['microsoft_clarity_project_id'] ?? ''));
    if ($cl !== '' && strpos($hs, 'clarity.ms') === false) {
        $clEsc = htmlspecialchars($cl, ENT_QUOTES, 'UTF-8');
        $chunk .= <<<HTML

<!-- Microsoft Clarity -->
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "{$clEsc}");
</script>
HTML;
    }

    return $chunk;
}

/**
 * Sipariş onay sayfası için head/snippet gömüsü ve gtag.
 */
function conversion_render_frontend_snippets(PDO $pdo): string
{
    $row = $pdo->query('SELECT * FROM conversion_api_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '';
    }

    $out = '';
    $headSnippet = (string) ($row['head_snippet'] ?? '');
    if ($headSnippet !== '') {
        $out .= "\n" . $headSnippet;
    }

    $out .= conversion_embed_client_pixels($row, $headSnippet);

    $mid = trim((string) ($row['google_gtag_measurement_id'] ?? ''));
    if ($mid !== '') {
        $midEsc = htmlspecialchars($mid, ENT_QUOTES, 'UTF-8');
        $out .= <<<HTML

<script async src="https://www.googletagmanager.com/gtag/js?id={$midEsc}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$midEsc}');
</script>
HTML;
    }

    $aw = trim((string)($row['google_ads_conversion_id'] ?? ''));
    $awBare = preg_replace('/^AW-/i', '', $aw);
    if ($awBare !== '') {
        $bare = htmlspecialchars($awBare, ENT_QUOTES, 'UTF-8');
        $out .= <<<HTML

<script async src="https://www.googletagmanager.com/gtag/js?id=AW-{$bare}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'AW-{$bare}');
</script>
HTML;
    }

    if (!empty($row['body_snippet'])) {
        $out .= "\n" . (string)$row['body_snippet'];
    }

    $out .= conversion_render_yandex_ecommerce_helper_script();
    $out .= conversion_render_pa_helper_script();

    return $out;
}

/**
 * Yandex.Metrica e-ticaret — UA Enhanced Ecommerce şeması (dataLayer taşıyıcı).
 */
function conversion_render_yandex_ecommerce_helper_script(): string
{
    return <<<'HTML'

<script>
(function (w) {
    if (w.ymEcomDataLayerPush) {
        return;
    }
    w.ymEcomProduct = function (id, name, price, quantity) {
        return {
            id: String(id || 'product'),
            name: String(name || 'Ürün'),
            price: Number(price) || 0,
            quantity: Math.max(1, parseInt(quantity, 10) || 1)
        };
    };
    w.ymEcomFromGaItems = function (gaItems) {
        var products = [];
        var list = gaItems || [];
        for (var i = 0; i < list.length; i++) {
            var it = list[i] || {};
            products.push(w.ymEcomProduct(it.item_id, it.item_name, it.price, it.quantity));
        }
        return products;
    };
    w.ymEcomDataLayerPush = function (block) {
        if (!block || typeof block !== 'object') {
            return;
        }
        w.dataLayer = w.dataLayer || [];
        w.dataLayer.push({ ecommerce: null });
        var payload = { currencyCode: block.currencyCode || 'TRY' };
        if (block.impressions) {
            payload.impressions = block.impressions;
        }
        if (block.detail) {
            payload.detail = block.detail;
        }
        if (block.checkout) {
            payload.checkout = block.checkout;
        }
        if (block.purchase) {
            payload.purchase = block.purchase;
        }
        w.dataLayer.push({ ecommerce: payload });
    };
}(window));
</script>
HTML;
}

/**
 * Analitik pa.track — snake_case isim + currency; script yüklenene kadar bekler.
 */
function conversion_render_pa_helper_script(): string
{
    return <<<'HTML'

<script>
(function (w) {
    if (w.paTrackSafe) {
        return;
    }
    w.paTrackSafe = function (name, extra) {
        extra = extra || {};
        if (typeof name !== 'string' || !/^[a-z0-9_]{1,64}$/.test(name)) {
            return;
        }
        var payload = { name: name };
        if (extra.value != null && extra.value !== '' && !isNaN(Number(extra.value))) {
            payload.value = Number(extra.value);
        }
        if (extra.currency && typeof extra.currency === 'string') {
            payload.currency = String(extra.currency).toUpperCase().slice(0, 3);
        }
        var tries = 0;
        (function tick() {
            if (typeof w.pa !== 'undefined' && typeof w.pa.track === 'function') {
                try {
                    w.pa.track(payload);
                } catch (e) {}
                return;
            }
            if (++tries < 120) {
                setTimeout(tick, 100);
            }
        })();
    };
})(window);
</script>
HTML;
}

/**
 * Ödül / satın alma (Google Ads dönüşüm).
 */
function conversion_render_google_ads_purchase_script(PDO $pdo, float $value, string $currency = 'TRY', ?string $transactionId = null): string
{
    $row = $pdo->query('SELECT google_ads_conversion_enabled, google_ads_conversion_id, google_ads_conversion_label FROM conversion_api_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['google_ads_conversion_enabled'])) {
        return '';
    }
    $aw = preg_replace('/^AW-/i', '', trim((string)($row['google_ads_conversion_id'] ?? '')));
    $lab = trim((string)($row['google_ads_conversion_label'] ?? ''));
    if ($aw === '' || $lab === '') {
        return '';
    }

    $vJs = htmlspecialchars(number_format(round($value, 2), 4, '.', ''), ENT_QUOTES, 'UTF-8');
    $sendToJs = json_encode('AW-' . $aw . '/' . $lab, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
    $currencyJs = json_encode($currency, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
    $tid = $transactionId !== null ? trim($transactionId) : '';
    $txnLine = '';
    if ($tid !== '') {
        $txnLine = ",\n    transaction_id: " . json_encode($tid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
    }

    return <<<HTML
<script>
(function(){
  if (typeof window.gtag !== 'function') { return; }
  window.gtag('event', 'conversion', {
    send_to: {$sendToJs},
    value: {$vJs},
    currency: {$currencyJs}{$txnLine}
  });
})();
</script>
HTML;
}

function conversion_social_click_event_id(string $channel, string $ip): string
{
    $channel = preg_replace('/[^a-z]/', '', strtolower($channel)) ?: 'social';

    return 'soc-' . $channel . '-' . substr(hash('sha256', $ip . gmdate('Y-m-d H:i')), 0, 20);
}

/**
 * WhatsApp / Instagram sabit buton — sunucu CAPI (social_go.php).
 */
function conversion_send_social_click(PDO $pdo, string $channel): void
{
    $channel = strtolower(trim($channel));
    if ($channel !== 'whatsapp' && $channel !== 'instagram') {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $row = $pdo->query('SELECT * FROM conversion_api_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $eventId = conversion_social_click_event_id($channel, $ip);
    $label = $channel === 'whatsapp' ? 'WhatsApp' : 'Instagram';

    $fbcResolved = meta_resolve_fbc_string([
        'fbc_cookie' => (string) ($_COOKIE['_fbc'] ?? ''),
        'fbclid_session' => (string) ($_SESSION['attr_fbclid'] ?? ''),
    ]);
    $fbpCookie = preg_replace('/\s+/', '', (string) ($_COOKIE['_fbp'] ?? ''));

    $pixelId = trim((string) ($row['meta_pixel_id'] ?? ''));
    $metaToken = trim((string) ($row['meta_capi_access_token'] ?? ''));
    if (! empty($row['meta_events_enabled']) && $pixelId !== '' && $metaToken !== '') {
        $evt = [
            'event_name' => 'Contact',
            'event_time' => time(),
            'action_source' => 'website',
            'event_id' => $eventId,
            'user_data' => array_filter([
                'client_ip_address' => $ip !== '' ? $ip : null,
                'client_user_agent' => $ua !== '' ? $ua : null,
                'fbc' => $fbcResolved !== null && $fbcResolved !== '' ? $fbcResolved : null,
                'fbp' => $fbpCookie !== '' ? $fbpCookie : null,
            ]),
            'custom_data' => [
                'content_name' => $label,
                'content_category' => 'social_button',
            ],
        ];
        $data = ['data' => json_encode([$evt]), 'access_token' => $metaToken];
        $test = trim((string) ($row['meta_capi_test_code'] ?? ''));
        if ($test !== '') {
            $data['test_event_code'] = $test;
        }
        curl_post_form_conversion(
            'https://graph.facebook.com/v21.0/' . rawurlencode($pixelId) . '/events',
            $data
        );
    }

    $meas = trim((string) ($row['ga4_measurement_id'] ?? ''));
    $secret = trim((string) ($row['ga4_api_secret'] ?? ''));
    if (! empty($row['ga4_mp_enabled']) && $meas !== '' && $secret !== '') {
        $cid = ga4_resolve_client_id(['ga_client_id' => '']);
        $url = 'https://www.google-analytics.com/mp/collect?' . http_build_query([
            'measurement_id' => $meas,
            'api_secret' => $secret,
        ]);
        $body = json_encode([
            'client_id' => $cid,
            'events' => [[
                'name' => 'generate_lead',
                'params' => [
                    'method' => $channel,
                    'event_category' => 'social',
                    'content_type' => $label,
                ],
            ]],
        ]);
        curl_post_raw_conversion($url, (string) ($body !== false ? $body : '{}'));
    }

    $tikId = trim((string) ($row['tiktok_pixel_id'] ?? ''));
    $tikTok = trim((string) ($row['tiktok_events_api_token'] ?? ''));
    if (! empty($row['tiktok_events_enabled']) && $tikId !== '' && $tikTok !== '') {
        $ttl = trim((string) ($_SESSION['attr_ttclid'] ?? ''));
        $ctxAd = $ttl !== '' ? ['ad' => ['callback' => $ttl]] : [];
        $payload = [
            'pixel_code' => $tikId,
            'event' => 'Contact',
            'event_id' => $eventId,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'context' => array_filter(array_merge([
                'ip' => $ip !== '' ? $ip : null,
                'user_agent' => $ua !== '' ? $ua : null,
            ], $ctxAd)),
            'properties' => [
                'content_type' => 'social_button',
                'description' => $label,
            ],
        ];
        curl_post_headers_conversion(
            'https://business-api.tiktok.com/open_api/v1.3/pixel/track/',
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
            ['Content-Type: application/json', 'Access-Token: ' . $tikTok]
        );
    }
}

/**
 * Vitrin sosyal buton tıklaması — tarayıcı pikselleri (aktifse).
 */
function conversion_render_social_click_tracker_script(PDO $pdo): string
{
    $row = $pdo->query(
        'SELECT meta_pixel_id, tiktok_pixel_id, google_gtag_measurement_id, yandex_metrica_counter_id
         FROM conversion_api_settings WHERE id = 1'
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $cfg = [
        'meta' => trim((string) ($row['meta_pixel_id'] ?? '')) !== '',
        'tiktok' => trim((string) ($row['tiktok_pixel_id'] ?? '')) !== '',
        'gtag' => trim((string) ($row['google_gtag_measurement_id'] ?? '')) !== '',
        'yandex' => preg_replace('/\D+/', '', (string) ($row['yandex_metrica_counter_id'] ?? '')),
    ];
    $cfgJson = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    return <<<HTML
<script>
(function (w, cfg) {
    if (w.trackSocialClick) {
        return;
    }
    w.trackSocialClick = function (channel) {
        channel = String(channel || '').toLowerCase();
        if (channel !== 'whatsapp' && channel !== 'instagram') {
            return;
        }
        var label = channel === 'whatsapp' ? 'WhatsApp' : 'Instagram';
        var goal = channel + '_click';
        var eventId = 'soc-' + channel + '-' + Date.now();

        w.dataLayer = w.dataLayer || [];
        w.dataLayer.push({
            event: 'social_click',
            social_channel: channel,
            event_category: 'social',
            event_action: 'click',
            event_label: label
        });

        if (cfg.meta && typeof w.fbq === 'function') {
            try {
                w.fbq('track', 'Contact', {
                    content_name: label,
                    content_category: 'social_button'
                }, { eventID: eventId });
            } catch (e) {}
        }

        if (cfg.tiktok && typeof w.ttq !== 'undefined' && typeof w.ttq.track === 'function') {
            try {
                w.ttq.track('Contact', {
                    content_type: 'social_button',
                    description: label
                });
            } catch (e) {}
        }

        if (cfg.gtag && typeof w.gtag === 'function') {
            try {
                w.gtag('event', 'generate_lead', {
                    method: channel,
                    event_category: 'social',
                    content_type: label
                });
            } catch (e) {}
        }

        if (cfg.yandex && typeof w.ym === 'function') {
            try {
                w.ym(Number(cfg.yandex), 'reachGoal', goal);
                w.ym(Number(cfg.yandex), 'params', {
                    social_click: channel,
                    social_label: label
                });
            } catch (e) {}
        }

        if (typeof w.paTrackSafe === 'function') {
            w.paTrackSafe(goal, { currency: 'TRY' });
        }
    };

    w.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.social-buttons a[data-social-channel]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var ch = a.getAttribute('data-social-channel') || '';
                var href = a.getAttribute('href') || '';
                w.trackSocialClick(ch);
                setTimeout(function () {
                    w.open(href, '_blank', 'noopener,noreferrer');
                }, 150);
            });
        });
    });
})(window, {$cfgJson});
</script>
HTML;
}
