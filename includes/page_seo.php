<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';

/** @return array<string, string> */
function page_seo_default_titles(): array
{
    return [
        'index.php' => 'Ana Sayfa',
        'order.php' => 'Sipariş',
        'thankyou.php' => 'Teşekkürler',
        'sorgula.php' => 'Sipariş Sorgula',
        'destek_talebi.php' => 'Destek Talebi',
        'error.php' => 'Hata',
        'hakkimizda.php' => 'Hakkımızda',
        'bayilik.php' => 'Bayilik Başvurusu',
        'kargo_sureci.php' => 'Kargo Süreci',
        'iletisim.php' => 'İletişim',
        'sss.php' => 'Sıkça Sorulan Sorular',
        'kvkk.php' => 'KVKK Aydınlatma Metni',
        'mesafeli_satis.php' => 'Mesafeli Satış Sözleşmesi',
        'iade_degisim.php' => 'İade ve Değişim',
        'dinamik_form.php' => 'Form',
    ];
}

/**
 * @param array<string,mixed>|null $meta
 * @param array<string, string> $overrides
 * @return array{title:string, description:string, keywords:string, canonical:string}
 */
function page_seo_resolve(PDO $pdo, string $pageName, ?array $meta, array $overrides = []): array
{
    $defaults = page_seo_default_titles();
    $baseTitle = trim((string) ($meta['page_title'] ?? ''));
    if ($baseTitle === '') {
        $baseTitle = $defaults[$pageName] ?? 'Online Alışveriş';
    }

    $title = trim((string) ($overrides['title'] ?? $baseTitle));
    $description = trim((string) ($overrides['description'] ?? ($meta['meta_description'] ?? '')));
    $keywords = trim((string) ($overrides['keywords'] ?? ($meta['meta_keywords'] ?? '')));

    $canonicalPath = $pageName;
    if (str_ends_with($canonicalPath, '.php')) {
        $canonicalPath = substr($canonicalPath, 0, -4);
    }
    if ($canonicalPath === 'index') {
        $canonicalPath = '';
    }
    $canonical = app_url($canonicalPath, [], $pdo);

    return [
        'title' => $title,
        'description' => $description,
        'keywords' => $keywords,
        'canonical' => $canonical,
    ];
}

/** @param array<string,mixed>|null $meta */
function page_seo_render(PDO $pdo, string $pageName, ?array $meta, array $overrides = []): void
{
    $seo = page_seo_resolve($pdo, $pageName, $meta, $overrides);
    $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    if ($seo['description'] !== '') {
        echo '<meta name="description" content="' . $esc($seo['description']) . '">' . "\n";
    }
    if ($seo['keywords'] !== '') {
        echo '<meta name="keywords" content="' . $esc($seo['keywords']) . '">' . "\n";
    }
    echo '<meta name="robots" content="' . $esc(trim((string) ($overrides['robots'] ?? 'index, follow'))) . '">' . "\n";
    echo '<link rel="canonical" href="' . $esc($seo['canonical']) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . $esc($seo['title']) . '">' . "\n";
    echo '<meta property="og:url" content="' . $esc($seo['canonical']) . '">' . "\n";
    if ($seo['description'] !== '') {
        echo '<meta property="og:description" content="' . $esc($seo['description']) . '">' . "\n";
    }
    echo '<meta property="og:locale" content="tr_TR">' . "\n";
}
