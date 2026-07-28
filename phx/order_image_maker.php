<?php
declare(strict_types=1);

require '../db.php';
require 'auth.php';
require_once __DIR__ . '/../includes/order_image_payload.php';

// Tarayıcı bu sayfanın eski (önbellekteki) sürümünü göstermesin — her zaman güncel kod.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$oim_order_init = null;
$oim_factory_mode = isset($_GET['factory']) && (string) $_GET['factory'] !== '0';
$oim_factory_order_ids = [];
if (isset($_GET['order_ids'])) {
    foreach (preg_split('/[\s,;]+/', (string) $_GET['order_ids']) as $chunk) {
        $id = (int) $chunk;
        if ($id > 0) {
            $oim_factory_order_ids[$id] = $id;
        }
    }
    $oim_factory_order_ids = array_values($oim_factory_order_ids);
}
if (isset($_GET['order_id'])) {
    $oimOid = max(1, (int) $_GET['order_id']);
    $oim_order_init = order_image_build_payload($pdo, $oimOid);
    if ($oim_factory_order_ids === []) {
        $oim_factory_order_ids = [$oimOid];
    }
}

$oim_status_names = [];
try {
    require_once __DIR__ . '/../includes/orders/order_list_service.php';
    $oim_status_names = OrderListService::statusNames($pdo);
} catch (Throwable $e) {
    $oim_status_names = ['Beklemede', 'Onaylandı', 'Kargoya Verildi'];
}

$page_title = 'Sipariş görseli fabrikası';
include 'admin_header.php';
?>
<script>
window.OIM_ORDER_INIT = <?= json_encode($oim_order_init, JSON_UNESCAPED_UNICODE); ?>;
window.OIM_FACTORY_BOOT = <?= json_encode([
    'factoryMode' => $oim_factory_mode,
    'preselectIds' => $oim_factory_order_ids,
    'statusNames' => $oim_status_names,
    'apiUrl' => admin_href('order_image_factory_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
    .order-image-maker-page {
        --oim-bg: #eef2f7;
        --oim-surface: #ffffff;
        --oim-surface2: #f8fafc;
        --oim-border: rgba(15, 23, 42, 0.1);
        --oim-text: #0f172a;
        --oim-muted: #64748b;
        --oim-accent: #6366f1;
        --oim-accent2: #0891b2;
        font-family: 'Inter', system-ui, sans-serif;
        background: linear-gradient(180deg, #e2e8f0 0%, #f1f5f9 38%, #f8fafc 100%);
        min-height: calc(100vh - 56px);
        padding: 1.25rem 1rem 2.5rem;
        color: var(--oim-text);
    }
    .order-image-maker-page .wrap { max-width: 1540px; margin: 0 auto; padding: 0 0.5rem; }
    .oim-hero {
        margin-bottom: 1.5rem;
    }
    .oim-hero h1 {
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin: 0 0 0.35rem;
        color: #0f172a;
    }
    .oim-hero p { margin: 0; color: var(--oim-muted); font-size: 0.9rem; }
    .oim-stepper {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-bottom: 1.35rem;
    }
    .oim-step {
        position: relative;
        flex: 1 1 0;
        min-width: 152px;
        border: 1px solid var(--oim-border);
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        border-radius: 16px;
        padding: 0.8rem 0.95rem;
        cursor: pointer;
        overflow: hidden;
        transition: border-color 0.25s, box-shadow 0.3s, transform 0.2s, background 0.3s;
        display: flex;
        align-items: center;
        gap: 0.7rem;
        text-align: left;
    }
    /* Sol vurgu çizgisi (aktifte belirir) */
    .oim-step::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--oim-accent) 0%, var(--oim-accent2) 100%);
        transform: scaleY(0);
        transform-origin: top center;
        transition: transform 0.32s ease;
    }
    .oim-step:hover {
        border-color: rgba(99, 102, 241, 0.4);
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.09);
    }
    .oim-step.active {
        border-color: transparent;
        background: linear-gradient(135deg, #eef2ff 0%, #ffffff 62%);
        box-shadow: 0 14px 32px rgba(99, 102, 241, 0.2);
    }
    .oim-step.active::before { transform: scaleY(1); }
    .oim-step.done {
        border-color: rgba(16, 185, 129, 0.28);
        background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 62%);
    }
    /* Soluk dekoratif arka plan ikonu */
    .oim-step-bgicon {
        position: absolute;
        right: -4px;
        bottom: -10px;
        font-size: 3.1rem;
        color: rgba(99, 102, 241, 0.06);
        pointer-events: none;
        transition: color 0.3s, transform 0.3s;
    }
    .oim-step.active .oim-step-bgicon {
        color: rgba(99, 102, 241, 0.14);
        transform: scale(1.08) rotate(-6deg);
    }
    .oim-step.done .oim-step-bgicon { color: rgba(16, 185, 129, 0.12); }
    .oim-step-num {
        position: relative;
        width: 34px;
        height: 34px;
        border-radius: 11px;
        background: #eef2f7;
        color: var(--oim-muted);
        font-weight: 800;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.3s, color 0.3s, box-shadow 0.3s, transform 0.3s;
    }
    .oim-step.active .oim-step-num {
        background: linear-gradient(135deg, var(--oim-accent) 0%, #818cf8 100%);
        color: #fff;
        box-shadow: 0 6px 14px rgba(99, 102, 241, 0.42);
        transform: scale(1.06);
    }
    .oim-step.done .oim-step-num {
        background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
        color: #fff;
        box-shadow: 0 6px 14px rgba(16, 185, 129, 0.35);
    }
    .oim-step-check { display: none; font-size: 0.85rem; }
    .oim-step.done .oim-step-check { display: block; }
    .oim-step.done .oim-step-digit { display: none; }
    .oim-step-body { display: flex; flex-direction: column; min-width: 0; }
    .oim-step-title {
        font-weight: 700;
        font-size: 0.86rem;
        line-height: 1.2;
        color: #0f172a;
        transition: color 0.25s;
    }
    .oim-step.active .oim-step-title { color: var(--oim-accent); }
    .oim-step.done .oim-step-title { color: #047857; }
    .oim-step-desc { font-size: 0.72rem; color: var(--oim-muted); margin-top: 2px; }
    /* Kontrol paneli başlığı (bölüm rozeti + sıfırla butonları) */
    .oim-panel-head {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        padding: 0.55rem 0.7rem;
        margin-bottom: 1rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border: 1px solid rgba(99, 102, 241, 0.14);
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    }
    .oim-ph-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--oim-accent) 0%, #818cf8 100%);
        color: #fff;
        font-size: 0.92rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }
    .oim-ph-titles { display: flex; flex-direction: column; min-width: 0; margin-right: auto; }
    .oim-ph-title { font-weight: 700; font-size: 0.92rem; color: #0f172a; line-height: 1.15; }
    .oim-ph-sub { font-size: 0.7rem; color: var(--oim-muted); }
    .oim-ph-actions { display: flex; gap: 0.4rem; flex-shrink: 0; flex-wrap: wrap; }
    .oim-reset-btn {
        border-radius: 999px !important;
        font-size: 0.74rem !important;
        font-weight: 600 !important;
        padding: 0.32rem 0.72rem !important;
        display: inline-flex !important;
        align-items: center;
        transition: transform 0.18s, box-shadow 0.2s, background 0.2s;
    }
    .oim-reset-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220, 53, 69, 0.22); }
    .oim-reset-btn[data-reset="all"]:hover { box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4); }
    /* Önce geniş önizleme, sonra dar kontrol kolonu (eskiden ~440px max ile tuval küçülüyordu) */
    .oim-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
    @media (min-width: 992px) {
        .oim-grid {
            grid-template-columns: minmax(0, 1fr) minmax(340px, 400px);
            align-items: start;
        }
        /* Sağ kontrol kolonu: ekrana sabit, kutular uzasa da kendi içinde kayar */
        .oim-card-controls {
            position: sticky;
            top: 72px;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 88px);
        }
        .oim-card-controls .oim-controls-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1 1 auto;
            scrollbar-width: thin;
            scrollbar-color: #c7cbd4 transparent;
            overscroll-behavior: contain;
        }
        .oim-card-controls .oim-controls-scroll::-webkit-scrollbar { width: 8px; }
        .oim-card-controls .oim-controls-scroll::-webkit-scrollbar-thumb {
            background: #cbd2df;
            border-radius: 8px;
        }
        .oim-card-controls .oim-controls-scroll::-webkit-scrollbar-thumb:hover { background: #a9b2c4; }
        /* İçerideki adım butonları alt kenarda takılı kalsın */
        .oim-card-controls .oim-nav-btns {
            position: sticky;
            bottom: -1px;
            background: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, #ffffff 40%);
            margin-top: 0.75rem;
            z-index: 4;
        }
    }
    .oim-card {
        background: var(--oim-surface);
        border: 1px solid var(--oim-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    }
    .oim-card-h {
        padding: 0.85rem 1.1rem;
        font-weight: 700;
        font-size: 0.88rem;
        border-bottom: 1px solid var(--oim-border);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(180deg, var(--oim-surface2) 0%, #ffffff 100%);
        color: var(--oim-text);
    }
    .oim-card-h i { color: var(--oim-accent2); }
    .oim-card-b { padding: 1rem 1.1rem; }
    .preview-area {
        background:
            radial-gradient(ellipse 90% 70% at 50% 0%, rgba(99, 102, 241, 0.08) 0%, transparent 52%),
            linear-gradient(180deg, #fafbfc 0%, #f1f5f9 50%, #eef2f7 100%);
        border-top: 1px solid rgba(15, 23, 42, 0.06);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: clamp(14px, 2.2vw, 28px);
        min-height: min(560px, 58vh);
    }
    .oim-design-stage {
        width: 100%;
        max-width: min(1180px, 100%);
        position: relative;
    }
    .oim-design-viewport {
        width: 100%;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        border-radius: 18px;
        box-shadow:
            inset 0 0 0 1px rgba(15, 23, 42, 0.06),
            0 12px 32px rgba(15, 23, 42, 0.08);
        background: #ffffff;
    }
    .oim-design-clip {
        flex-shrink: 0;
        overflow: hidden;
        margin: 0 auto;
        border-radius: 16px;
        line-height: 0;
    }
    .oim-design-scaler {
        width: 900px;
        height: 450px;
        transform-origin: top left;
        will-change: transform;
        position: relative;
    }
    .oim-safe-overlay {
        position: absolute;
        left: 0;
        top: 0;
        width: 900px;
        height: 450px;
        z-index: 50;
        pointer-events: none;
        box-sizing: border-box;
    }
    .oim-safe-overlay .oim-safe-frame {
        position: absolute;
        left: 24px;
        top: 24px;
        right: 24px;
        bottom: 24px;
        border: 2px dashed rgba(99, 102, 241, 0.45);
        border-radius: 10px;
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.35) inset;
    }
    .oim-cta-item {
        position: absolute;
        left: 24px;
        bottom: 28px;
        display: inline-block;
        padding: 12px 28px;
        font-weight: 800;
        font-size: 18px;
        border-radius: 999px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #ffffff;
        z-index: 6;
        box-shadow: 0 10px 28px rgba(79, 70, 229, 0.28);
        text-decoration: none;
        cursor: grab;
        line-height: 1.25;
        border: none;
        outline: none;
    }
    .oim-cta-item:active { cursor: grabbing; }
    #design[data-cta-hide="1"] .oim-cta-item { display: none !important; }
    .design [data-oim-locked="1"].draggable { cursor: not-allowed !important; opacity: 0.92; outline-style: dotted; }
    .oim-design-caption {
        text-align: center;
        margin-top: 0.65rem;
        font-size: 0.75rem;
        color: #64748b;
        letter-spacing: 0.02em;
    }
    .design {
        width: 900px;
        height: 450px;
        border-radius: 16px;
        position: relative;
        padding: 24px;
        box-sizing: border-box;
        background: linear-gradient(135deg, #ffffff 0%, #f1f5ff 100%);
        font-family: 'Inter', Arial, Helvetica, sans-serif;
        color: #283458;
        overflow: hidden;
    }
    .draggable { cursor: grab; }
    .draggable:active { cursor: grabbing; }
    #oimResizeHud {
        position: absolute;
        left: 0;
        top: 0;
        width: 0;
        height: 0;
        z-index: 10010;
        pointer-events: none;
        display: none;
    }
    #oimResizeHud .oim-resize-grip {
        pointer-events: auto;
        box-sizing: border-box;
        width: 13px;
        height: 13px;
        margin: 0;
        padding: 0;
        border: 2px solid #7c3aed;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 3px;
        cursor: nwse-resize;
        transform: translate(-50%, -50%);
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.2);
    }
    #oimResizeHud .oim-resize-grip:focus-visible {
        outline: 2px solid #6366f1;
        outline-offset: 1px;
    }
    .selected { outline: 2px dashed #8b5cf6; outline-offset: 3px; }
    .badge-rozet {
        position: absolute;
        left: 24px;
        top: 24px;
        background: #2ecc71;
        color: #fff;
        border-radius: 40px;
        padding: 10px 16px;
        font-weight: 700;
        z-index: 1;
        white-space: nowrap;
        display: inline-block;
    }
    .h1 { position: absolute; left: 24px; top: 60px; font-size: 46px; font-weight: 800; margin: 0; }
    .h2 { position: absolute; left: 24px; top: 116px; font-size: 36px; font-weight: 800; color: #e74c3c; margin: 0; }
    .h3 { position: absolute; left: 24px; top: 168px; font-size: 28px; font-weight: 700; margin: 0; }
    .bullets { position: absolute; left: 24px; top: 220px; font-size: 19px; line-height: 1.6; }
    .prodimg { position: absolute; right: 140px; bottom: 40px; max-height: 240px; max-width: 300px; }
    /* Ctrl + köşe: width/height + object-fit fill (inline) — oran kırık gerçek germe */
    img.prodimg[data-oim-img-stretch='1'] { max-width: none !important; max-height: none !important; }
    .iconitem { position: absolute; left: 24px; top: 24px; color: #283458; font-size: 48px; }
    #titlesContainer { position: relative; z-index: 5; }
    .theme-1 { background: linear-gradient(135deg, #ffffff 0%, #f1f5ff 100%); }
    .theme-2 { background: linear-gradient(135deg, #fef3f3 0%, #fff7e5 100%); }
    .theme-3 { background: linear-gradient(135deg, #ecfff4 0%, #e7f0ff 100%); }
    .theme-4 { background: linear-gradient(135deg, #f6f9fc 0%, #dde7ff 100%); }
    .theme-5 { background: #ffffff; }
    .theme-6 { background: #f8f8f8; }
    .theme-7 { background: linear-gradient(135deg, #ffe29f 0%, #ffa99f 48%, #ff719a 100%); }
    .theme-8 { background: linear-gradient(135deg, #c2ffd8 0%, #465efb 100%); }
    .theme-9 { background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); }
    .theme-10 { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
    .theme-11 { background: linear-gradient(135deg, #141e30 0%, #243b55 100%); }
    .theme-12 { background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%); }
    .theme-13 {
        background-color: #fafcff;
        background-image:
            linear-gradient(rgba(99, 102, 241, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(99, 102, 241, 0.05) 1px, transparent 1px);
        background-size: 22px 22px;
    }
    .theme-14 {
        background: linear-gradient(118deg, #f8fafc 0%, #eef2ff 42%, #ffffff 100%);
    }
    .design.theme-14::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 10px;
        background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 55%, #22d3ee 100%);
        border-radius: 16px 0 0 16px;
        z-index: 0;
        pointer-events: none;
    }
    .theme-15 {
        background: linear-gradient(125deg, #fff7ed 0%, #ffedd5 35%, #fef3c7 72%, #fff 100%);
    }
    .theme-16 {
        background:
            radial-gradient(circle at 88% 12%, rgba(16, 185, 129, 0.18) 0%, rgba(16, 185, 129, 0) 60%),
            radial-gradient(circle at 8% 90%, rgba(6, 182, 212, 0.12) 0%, rgba(6, 182, 212, 0) 55%),
            linear-gradient(180deg, #f0fdf9 0%, #ffffff 100%);
    }
    .theme-17 {
        background: linear-gradient(160deg, #0f172a 0%, #1e293b 55%, #334155 100%);
    }
    .design.theme-17,
    .design.theme-17 .bullets div { color: #e2e8f0 !important; }
    .design.theme-17 .h1, .design.theme-17 .h3 { color: #f8fafc !important; }
    .design.theme-17 .h2 { color: #fcd34d !important; }
    .design.theme-17 .iconitem { color: #94a3b8 !important; }
    .theme-18 {
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.94) 0%, rgba(241, 245, 255, 0.98) 100%),
            linear-gradient(218deg, #e0f2fe 0%, transparent 42%),
            linear-gradient(32deg, #ede9fe 0%, transparent 38%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85), 0 32px 64px rgba(79, 70, 229, 0.08);
    }
    .theme-19 {
        background: radial-gradient(circle at 88% 15%, rgba(244, 114, 182, 0.22) 0%, rgba(244, 114, 182, 0) 55%),
            linear-gradient(125deg, #fff1f8 0%, #ffe4ec 42%, #ffffff 100%);
    }
    .theme-20 {
        background: linear-gradient(145deg, #0f172a 0%, #0e7490 45%, #134e4a 100%);
    }
    .design.theme-20,
    .design.theme-20 .bullets div { color: #ecfeff !important; }
    .design.theme-20 .h1, .design.theme-20 .h3 { color: #f8fafc !important; }
    .design.theme-20 .h2 { color: #67e8f9 !important; }
    .design.theme-20 .iconitem { color: #94a3b8 !important; }
    .design.theme-20 .oim-cta-item { box-shadow: 0 14px 32px rgba(0, 0, 0, 0.35); }
    .theme-21 {
        background:
            radial-gradient(circle at 12% 20%, rgba(251, 191, 36, 0.2) 0%, rgba(251, 191, 36, 0) 60%),
            linear-gradient(168deg, #fffbeb 0%, #ffedd5 35%, #fff7ed 78%, #fff 100%);
    }
    .theme-22 {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 62%, #e2e8f0 100%);
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .theme-23 {
        background: linear-gradient(135deg, #1c1410 0%, #292524 38%, #44403c 100%);
    }
    .design.theme-23,
    .design.theme-23 .bullets div { color: #fafaf9 !important; }
    .design.theme-23 .h1, .design.theme-23 .h3 { color: #fefce8 !important; }
    .design.theme-23 .h2 { color: #fbbf24 !important; }
    .design.theme-23 .iconitem { color: #a8a29e !important; }
    .theme-24 {
        background:
            radial-gradient(circle at 18% 30%, rgba(254, 240, 138, 0.55) 0%, rgba(254, 240, 138, 0) 60%),
            radial-gradient(circle at 92% 12%, rgba(248, 113, 113, 0.25) 0%, rgba(248, 113, 113, 0) 55%),
            linear-gradient(118deg, #fef2f2 0%, #fff 55%, #fffef0 100%);
    }
    .design.theme-11 .bullets div { color: #f1f5f9 !important; opacity: 0.95; }
    .design.theme-11 .h1,
    .design.theme-11 .h3 { color: #f1f5f9 !important; }
    .design.theme-11 .h2 { color: #fbbf24 !important; }
    .design.theme-11 .iconitem { color: #cbd5e1 !important; }
    /* —— Premium temalar (25-30) —— */
    .theme-25 {
        background:
            radial-gradient(circle at 15% 20%, rgba(168, 85, 247, 0.35) 0%, rgba(168, 85, 247, 0) 45%),
            radial-gradient(circle at 88% 85%, rgba(34, 211, 238, 0.28) 0%, rgba(34, 211, 238, 0) 42%),
            linear-gradient(150deg, #0b0f1a 0%, #16112e 55%, #0a1024 100%);
    }
    .design.theme-25,
    .design.theme-25 .bullets div { color: #e9d5ff !important; }
    .design.theme-25 .h1, .design.theme-25 .h3 { color: #f5f3ff !important; text-shadow: 0 0 18px rgba(168,85,247,0.55); }
    .design.theme-25 .h2 { color: #22d3ee !important; text-shadow: 0 0 20px rgba(34,211,238,0.6); }
    .design.theme-25 .iconitem { color: #c4b5fd !important; }
    .theme-26 {
        background:
            linear-gradient(135deg, rgba(255,255,255,0.72) 0%, rgba(224,231,255,0.55) 100%),
            radial-gradient(circle at 82% 18%, rgba(56, 189, 248, 0.4) 0%, rgba(56, 189, 248, 0) 48%),
            radial-gradient(circle at 12% 88%, rgba(129, 140, 248, 0.4) 0%, rgba(129, 140, 248, 0) 46%),
            #eef2ff;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.9);
    }
    .theme-27 {
        background:
            radial-gradient(circle at 78% 12%, rgba(250, 204, 21, 0.28) 0%, rgba(250, 204, 21, 0) 55%),
            linear-gradient(140deg, #1a1206 0%, #2c1e0a 42%, #3f2d10 100%);
    }
    .design.theme-27,
    .design.theme-27 .bullets div { color: #fef9c3 !important; }
    .design.theme-27 .h1, .design.theme-27 .h3 { color: #fffbeb !important; }
    .design.theme-27 .h2 {
        color: #facc15 !important;
        background: linear-gradient(92deg, #fde68a 0%, #f59e0b 50%, #fde68a 100%);
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .design.theme-27 .iconitem { color: #eab308 !important; }
    .theme-28 {
        background: linear-gradient(125deg, #ff9a9e 0%, #fecfef 42%, #fad0c4 100%);
    }
    .theme-29 {
        background:
            radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.4) 0%, rgba(99, 102, 241, 0) 55%),
            linear-gradient(180deg, #020617 0%, #1e1b4b 60%, #312e81 100%);
    }
    .design.theme-29,
    .design.theme-29 .bullets div { color: #e0e7ff !important; }
    .design.theme-29 .h1, .design.theme-29 .h3 { color: #ffffff !important; }
    .design.theme-29 .h2 { color: #a5b4fc !important; }
    .design.theme-29 .iconitem { color: #818cf8 !important; }
    .theme-30 {
        background:
            radial-gradient(circle at 20% 12%, rgba(45, 212, 191, 0.28) 0%, rgba(45, 212, 191, 0) 60%),
            radial-gradient(circle at 90% 92%, rgba(132, 204, 22, 0.22) 0%, rgba(132, 204, 22, 0) 55%),
            linear-gradient(160deg, #ecfeff 0%, #f0fdf4 55%, #ffffff 100%);
    }
    .theme-swatches { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .theme-swatch { width: 40px; height: 26px; border-radius: 8px; border: 2px solid transparent; cursor: pointer; transition: transform 0.15s; }
    .theme-swatch:hover { transform: scale(1.06); }
    .theme-swatch.active { outline: 2px solid #8b5cf6; border-color: rgba(15, 23, 42, 0.1); }
    .order-image-maker-page .input-group-append { display: contents; }
    #layerList .badge { cursor: default; }
    .oim-panel { display: none; animation: oimIn 0.22s ease; }
    .oim-panel.active { display: block; }
    @keyframes oimIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    .oim-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--oim-muted); font-weight: 600; margin-bottom: 0.35rem; }
    .order-image-maker-page .form-control,
    .order-image-maker-page .custom-select,
    .order-image-maker-page select.form-control {
        background: #ffffff;
        border: 1px solid var(--oim-border);
        color: var(--oim-text);
        border-radius: 10px;
        font-size: 0.875rem;
    }
    .order-image-maker-page .form-control:focus,
    .order-image-maker-page select:focus {
        border-color: rgba(99, 102, 241, 0.45);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        background: #ffffff;
        color: var(--oim-text);
    }
    .order-image-maker-page .btn { border-radius: 10px; font-weight: 600; }
    .order-image-maker-page .btn-primary {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        border: none;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    }
    .order-image-maker-page .btn-primary:hover { filter: brightness(1.08); }
    .oim-nav-btns { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--oim-border); }
    .oim-section {
        position: relative;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        border: 1px solid var(--oim-border);
        border-radius: 14px;
        padding: 0.95rem 1.05rem;
        margin-bottom: 0.9rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.25s, border-color 0.25s, transform 0.2s;
    }
    .oim-section:hover {
        border-color: rgba(99, 102, 241, 0.28);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.07);
    }
    .oim-section > .d-flex strong { font-size: 0.9rem; }
    /* Bölüm başlıkları — küçük renkli işaretle (d-block !important ile uyumlu) */
    .oim-section > strong:first-child {
        font-size: 0.92rem;
        font-weight: 700;
        color: #0f172a;
    }
    .oim-section > strong:first-child::before {
        content: "";
        display: inline-block;
        vertical-align: -2px;
        width: 4px;
        height: 15px;
        border-radius: 999px;
        margin-right: 0.5rem;
        background: linear-gradient(180deg, var(--oim-accent) 0%, var(--oim-accent2) 100%);
    }
    /* Etiketler */
    .oim-card-controls label {
        font-size: 0.76rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.3rem;
    }
    /* Inputlar biraz daha ferah */
    .order-image-maker-page .form-control:not([type="color"]):not(.form-control-file) {
        padding: 0.46rem 0.7rem;
        height: auto;
    }
    .order-image-maker-page input[type="color"].form-control {
        padding: 0.15rem;
        height: 38px;
        cursor: pointer;
        border-radius: 10px;
    }
    /* Kontrol panelindeki küçük "hap" butonları */
    .oim-card-controls .btn-sm.btn-outline-secondary {
        border-radius: 999px;
        border-color: var(--oim-border);
        color: #475569;
        background: #fff;
        font-weight: 600;
        font-size: 0.78rem;
        padding: 0.34rem 0.78rem;
        transition: all 0.18s;
    }
    .oim-card-controls .btn-sm.btn-outline-secondary:hover {
        border-color: rgba(99, 102, 241, 0.5);
        color: var(--oim-accent);
        background: rgba(99, 102, 241, 0.06);
        transform: translateY(-1px);
    }
    .oim-card-controls .btn-sm.btn-outline-secondary.active,
    .oim-card-controls .btn-sm.btn-outline-secondary:active {
        border-color: transparent !important;
        color: #fff !important;
        background: linear-gradient(135deg, var(--oim-accent) 0%, #4f46e5 100%) !important;
        box-shadow: 0 5px 14px rgba(79, 70, 229, 0.32);
    }
    /* Tema swatch'ları biraz daha büyük ve şık */
    .theme-swatch {
        width: 42px;
        height: 30px;
        border-radius: 9px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
    }
    .theme-swatch:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18); }
    .theme-swatch.active {
        outline: 2px solid var(--oim-accent);
        outline-offset: 1px;
        transform: scale(1.05);
    }
    @media (max-width: 991.98px) {
        .order-image-maker-page { padding: 0.75rem 0.5rem 2rem; }
        .order-image-maker-page .wrap { padding: 0; }
        .oim-card.oim-card-preview { position: static !important; top: auto !important; }
        .oim-stepper { gap: 0.35rem; }
        .oim-step { min-width: calc(50% - 0.2rem); flex: 1 1 calc(50% - 0.2rem); }
        .preview-area { min-height: 280px; padding: 12px; }
        .oim-nav-btns { position: sticky; bottom: 0; background: #fff; z-index: 20; padding-bottom: 0.5rem; }
        .oim-design-caption { font-size: 0.68rem; }
        }
    /* —— Premium hero —— */
    .oim-hero-pro {
        position: relative;
        overflow: hidden;
        border-radius: 20px;
        padding: 1.4rem 1.5rem;
        margin-bottom: 1.25rem;
        color: #f8fafc;
        background:
            radial-gradient(circle at 12% 15%, rgba(168, 85, 247, 0.45), transparent 45%),
            radial-gradient(circle at 88% 90%, rgba(34, 211, 238, 0.4), transparent 46%),
            linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #0b1120 100%);
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.28);
    }
    .oim-hero-pro::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
        background-size: 26px 26px;
        pointer-events: none;
        opacity: 0.6;
    }
    .oim-hero-pro > * { position: relative; z-index: 1; }
    .oim-hero-pro h1 {
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin: 0 0 0.35rem;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .oim-hero-pro p { margin: 0; color: #c7d2fe; font-size: 0.9rem; max-width: 760px; }
    .oim-hero-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.9rem; }
    .oim-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.74rem;
        font-weight: 700;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: #e0e7ff;
        backdrop-filter: blur(4px);
    }
    .oim-hero-badge i { color: #a5b4fc; }
    .oim-pro-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        color: #78350f;
        background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%);
        box-shadow: 0 3px 10px rgba(245, 158, 11, 0.35);
    }
    .oim-hero-title-pill {
        font-size: 0.6rem;
        vertical-align: middle;
    }
    /* —— Mod sekmeleri (segmented) —— */
    .oim-mode-tabs {
        display: inline-flex;
        gap: 0.35rem;
        margin-bottom: 1.1rem;
        padding: 0.3rem;
        background: var(--oim-surface);
        border: 1px solid var(--oim-border);
        border-radius: 14px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
    }
    .oim-mode-tab {
        border: none;
        background: transparent;
        border-radius: 10px;
        padding: 0.5rem 1.15rem;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--oim-muted);
        cursor: pointer;
        transition: color 0.2s, background 0.2s, box-shadow 0.2s;
    }
    .oim-mode-tab:hover { color: var(--oim-text); }
    .oim-mode-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.32);
    }
    /* —— Şablon galerisi —— */
    .oim-preset-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
        gap: 0.55rem;
    }
    .oim-preset-tile {
        position: relative;
        border: 1px solid var(--oim-border);
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        background: #fff;
        padding: 0;
        transition: transform 0.15s, box-shadow 0.2s, border-color 0.2s;
        text-align: left;
    }
    .oim-preset-tile:hover {
        transform: translateY(-2px);
        border-color: rgba(99, 102, 241, 0.5);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
    }
    .oim-preset-tile.active { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.35); }
    .oim-preset-tile__thumb {
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.9);
        font-size: 1rem;
    }
    .oim-preset-tile__label {
        display: block;
        padding: 0.35rem 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--oim-text);
        line-height: 1.2;
    }
    .oim-preset-tile__badge {
        position: absolute;
        top: 4px;
        right: 4px;
        font-size: 0.55rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        padding: 0.08rem 0.34rem;
        border-radius: 999px;
        color: #78350f;
        background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%);
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4);
    }
    /* —— Studio hızlı efekt paketleri —— */
    .oim-fx-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .oim-fx-btn {
        border: 1px solid var(--oim-border);
        background: #fff;
        border-radius: 10px;
        padding: 0.4rem 0.7rem;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
    }
    .oim-fx-btn:hover { border-color: rgba(99,102,241,0.5); background: #f5f3ff; }
    .oim-factory-card {
        background: var(--oim-surface);
        border: 1px solid var(--oim-border);
        border-radius: 14px;
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
    }
    .oim-factory-table-wrap {
        max-height: 420px;
        overflow: auto;
        border: 1px solid var(--oim-border);
        border-radius: 10px;
    }
    .oim-factory-table { margin: 0; font-size: 0.82rem; }
    .oim-factory-table th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        z-index: 1;
    }
    .oim-factory-progress {
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .oim-factory-progress > span {
        display: block;
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #6366f1, #0891b2);
        transition: width 0.25s ease;
    }
    .oim-factory-log {
        max-height: 140px;
        overflow: auto;
        font-size: 0.78rem;
        background: #f8fafc;
        border-radius: 8px;
        padding: 0.5rem 0.65rem;
        color: var(--oim-muted);
    }
    .oim-factory-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.6rem;
        margin-top: 0.75rem;
    }
    .oim-factory-thumb {
        border: 1px solid var(--oim-border);
        border-radius: 10px;
        overflow: hidden;
        background: #f8fafc;
    }
    .oim-factory-thumb img { display: block; width: 100%; height: 78px; object-fit: cover; background: #fff; }
    .oim-factory-thumb figcaption {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 4px;
        padding: 4px 6px;
        font-size: 0.68rem;
        color: var(--oim-muted);
    }
    .oim-factory-thumb a { font-weight: 700; color: var(--oim-accent); text-decoration: none; }
    .oim-factory-stat-row { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
    .oim-factory-stat {
        flex: 1;
        min-width: 88px;
        border: 1px solid var(--oim-border);
        border-radius: 12px;
        padding: 0.55rem 0.7rem;
        background: linear-gradient(180deg, #f8fafc, #fff);
    }
    .oim-factory-stat b { display: block; font-size: 1.15rem; font-weight: 800; color: var(--oim-text); line-height: 1.1; }
    .oim-factory-stat span { font-size: 0.68rem; color: var(--oim-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    </style>

<div class="order-image-maker-page">
<div class="wrap">
    <div class="oim-hero-pro">
        <h1>
            <i class="fas fa-industry"></i>
            Sipariş Görseli Stüdyosu &amp; Fabrikası
            <span class="oim-pro-pill oim-hero-title-pill"><i class="fas fa-crown"></i> Pro</span>
        </h1>
        <p>Şablon + sipariş verisiyle tek tıkla veya toplu profesyonel pazarlama görseli üretin. Stüdyoda tasarlayın, fabrikada seri üretin, doğrudan siparişe kaydedin.</p>
        <div class="oim-hero-badges">
            <span class="oim-hero-badge"><i class="fas fa-swatchbook"></i> 30 tema</span>
            <span class="oim-hero-badge"><i class="fas fa-layer-group"></i> 15 hazır şablon</span>
            <span class="oim-hero-badge"><i class="fas fa-bolt"></i> Toplu üretim</span>
            <span class="oim-hero-badge"><i class="fas fa-magic"></i> Efekt paketleri</span>
            <span class="oim-hero-badge"><i class="fas fa-cloud-upload-alt"></i> Sunucuya kayıt</span>
        </div>
        <div id="oimOrderBanner" class="alert alert-warning border-0 shadow-sm py-2 small mb-0 mt-3 d-none" role="status"></div>
    </div>

    <div class="oim-mode-tabs" role="tablist">
        <button type="button" class="oim-mode-tab active" data-oim-mode="studio" id="oimModeStudio">
            <i class="fas fa-palette me-1"></i> Stüdyo
        </button>
        <button type="button" class="oim-mode-tab" data-oim-mode="factory" id="oimModeFactory">
            <i class="fas fa-cogs me-1"></i> Fabrika (toplu)
        </button>
    </div>

    <div id="oimStudioWrap">
    <div class="oim-stepper" id="oimStepper" role="tablist">
        <button type="button" class="oim-step active" data-step="1" aria-selected="true">
            <span class="oim-step-num"><i class="fas fa-check oim-step-check"></i><span class="oim-step-digit">1</span></span>
            <span class="oim-step-body"><span class="oim-step-title">Görünüm</span><span class="oim-step-desc">Tuval, tema ve zemin</span></span>
            <i class="fas fa-palette oim-step-bgicon"></i>
        </button>
        <button type="button" class="oim-step" data-step="2" aria-selected="false">
            <span class="oim-step-num"><i class="fas fa-check oim-step-check"></i><span class="oim-step-digit">2</span></span>
            <span class="oim-step-body"><span class="oim-step-title">Metinler</span><span class="oim-step-desc">Başlık, rozet, maddeler</span></span>
            <i class="fas fa-font oim-step-bgicon"></i>
        </button>
        <button type="button" class="oim-step" data-step="3" aria-selected="false">
            <span class="oim-step-num"><i class="fas fa-check oim-step-check"></i><span class="oim-step-digit">3</span></span>
            <span class="oim-step-body"><span class="oim-step-title">Görseller</span><span class="oim-step-desc">Ürün ve ikon</span></span>
            <i class="fas fa-image oim-step-bgicon"></i>
        </button>
        <button type="button" class="oim-step" data-step="4" aria-selected="false">
            <span class="oim-step-num"><i class="fas fa-check oim-step-check"></i><span class="oim-step-digit">4</span></span>
            <span class="oim-step-body"><span class="oim-step-title">Düzen &amp; indir</span><span class="oim-step-desc">Katman ve PNG</span></span>
            <i class="fas fa-layer-group oim-step-bgicon"></i>
        </button>
    </div>

    <div class="oim-grid">
        <div class="oim-card oim-card-preview" style="position: sticky; top: 72px;">
            <div id="oimPreviewCardHeading" class="oim-card-h"><i class="fas fa-eye"></i> Canlı önizleme · 900 × 450 px</div>
            <div class="card-body preview-area p-0">
                <div class="oim-design-stage">
                    <div class="oim-design-viewport" id="oimDesignViewport">
                        <div class="oim-design-clip" id="oimDesignClip">
                            <div class="oim-design-scaler" id="oimDesignScaler">
            <div id="design" class="design theme-1">
                <div id="rozet" class="badge-rozet rozet-item draggable">Özel Fırsat</div>
                <div id="titlesContainer">
                    <div class="h1 draggable title-item" data-idx="1" id="title1">Lorem Ipsum</div>
                    <div class="h2 draggable title-item" data-idx="2" id="title2">Dolor Sit</div>
                    <div class="h3 draggable title-item" data-idx="3" id="subtitle">Amet</div>
                </div>
                                    <div class="bullets draggable" id="bullets"></div>
                <img id="prodimg" class="prodimg draggable" src="" alt="" style="display:none;">
                <i id="icon1" class="iconitem draggable" style="display:none;"></i>
                                    <button type="button" id="cta1" class="oim-cta-item draggable">Şimdi al</button>
                                    <div id="oimResizeHud" class="oim-resize-hud" aria-hidden="true">
                                        <button type="button" class="oim-resize-grip" tabindex="-1" title="Boyut: köşeden sürükle — Shift oran korur · Ctrl ürün görselinde X/Y bağımsız germe (object-fit: fill)"></button>
                                    </div>
                                </div>
                                <div id="oimSafeOverlay" class="oim-safe-overlay d-none"><div class="oim-safe-frame"></div></div>
                            </div>
                        </div>
                    </div>
                    <div id="oimDesignCaptionLine" class="oim-design-caption">Tuval <strong id="oimCanvasDimsStr">900×450 px</strong> — dosya ile aynı boyut. Köşeden sürükleyerek boyut: <kbd title="Oran sabit">Shift</kbd> oran korur; <kbd title="Yalnızca ürün görseli: gerçek XY germe">Ctrl</kbd> ürün görselinde genişlik/yükseklik ayrı (görsel gerilir). Güvenlik çerçevesi yalnızca önizlemede.</div>
            </div>
        </div>
    </div>

        <div class="oim-card oim-card-controls">
            <div class="oim-card-h"><i class="fas fa-sliders-h"></i> Adım kontrolleri</div>
            <div class="oim-card-b oim-controls-scroll">

                <div class="oim-panel active" data-step="1" role="tabpanel">
                    <div class="oim-panel-head">
                        <span class="oim-ph-icon"><i class="fas fa-palette"></i></span>
                        <span class="oim-ph-titles"><span class="oim-ph-title">Görünüm</span><span class="oim-ph-sub">Tuval, tema ve zemin</span></span>
                        <span class="oim-ph-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger oim-reset-btn" data-reset="appearance" data-reset-label="Görünüm (tema & arka plan)"><i class="fas fa-rotate-left me-1"></i>Bölümü sıfırla</button>
                        </span>
                    </div>
                    <div class="oim-section mb-3">
                        <strong class="d-block mb-1">Tuval boyutu (px)</strong>
                        <p class="small text-muted mb-2">Boyut tasarımla aynıdır; indirdiğiniz görsel tam bu ebattadır (yüksek çözünürlük seçeneği yalnızca piksel yoğunluğunu artırır).</p>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label class="small">Genişlik</label>
                                <input type="number" id="oimCanvasW" class="form-control" value="900" min="200" max="3840" step="1">
                            </div>
                            <div class="form-group col-6">
                                <label class="small">Yükseklik</label>
                                <input type="number" id="oimCanvasH" class="form-control" value="450" min="200" max="3840" step="1">
                            </div>
                        </div>
                        <label class="small d-block mb-1">Ölçülü sıçra</label>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-canvas-preset" data-w="900" data-h="450">900×450</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-canvas-preset" data-w="1200" data-h="628">1200×628 OG</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-canvas-preset" data-w="1080" data-h="1080">1080²</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-canvas-preset" data-w="1080" data-h="1920">1080×1920 story</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-canvas-preset" data-w="1920" data-h="1080">1920×1080</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-canvas-preset" data-w="800" data-h="418">Twitter 800×418</button>
                        </div>
                        <button type="button" id="btnApplyCanvasDims" class="btn btn-sm btn-primary">Boyutu tuvale uygula</button>
                    </div>
                    <div class="oim-label">Tema</div>
                    <div class="form-group">
                        <select id="i_theme" class="form-control">
                            <option value="theme-1" selected>Tema 1 — Buzlu mavi</option>
                            <option value="theme-2">Tema 2 — Sıcak</option>
                            <option value="theme-3">Tema 3 — Yeşil / mavi</option>
                            <option value="theme-4">Tema 4 — Kurumsal</option>
                            <option value="theme-5">Tema 5 — Beyaz</option>
                            <option value="theme-6">Tema 6 — Açık gri</option>
                            <option value="theme-7">Tema 7 — Sunset</option>
                            <option value="theme-8">Tema 8 — Mint / indigo</option>
                            <option value="theme-9">Tema 9 — Soft gray</option>
                            <option value="theme-10">Tema 10 — Mor / pembe</option>
                            <option value="theme-11">Tema 11 — Gece</option>
                            <option value="theme-12">Tema 12 — Okyanus</option>
                            <option value="theme-13">Tema 13 — Landing · Grid</option>
                            <option value="theme-14">Tema 14 — Landing · Çizgi</option>
                            <option value="theme-15">Tema 15 — Landing · Gün batımı</option>
                            <option value="theme-16">Tema 16 — Landing · Yeşil</option>
                            <option value="theme-17">Tema 17 — Landing · Koyu</option>
                            <option value="theme-18">Tema 18 — Landing · Cam</option>
                            <option value="theme-19">Tema 19 — Moda</option>
                            <option value="theme-20">Tema 20 — Teknoloji</option>
                            <option value="theme-21">Tema 21 — Gıda</option>
                            <option value="theme-22">Tema 22 — Minimal B2B</option>
                            <option value="theme-23">Tema 23 — Premium altın</option>
                            <option value="theme-24">Tema 24 — Flaş kampanya</option>
                            <option value="theme-25">Tema 25 — ✦ Neon gece</option>
                            <option value="theme-26">Tema 26 — ✦ Cam (glass)</option>
                            <option value="theme-27">Tema 27 — ✦ Altın lüks</option>
                            <option value="theme-28">Tema 28 — ✦ Mercan</option>
                            <option value="theme-29">Tema 29 — ✦ Derin uzay</option>
                            <option value="theme-30">Tema 30 — ✦ Aurora</option>
                        </select>
                        <div class="theme-swatches mt-2">
                            <div class="theme-swatch" data-theme="theme-1" style="background:linear-gradient(135deg,#ffffff 0%,#f1f5ff 100%)" title="1"></div>
                            <div class="theme-swatch" data-theme="theme-2" style="background:linear-gradient(135deg,#fef3f3 0%,#fff7e5 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-3" style="background:linear-gradient(135deg,#ecfff4 0%,#e7f0ff 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-4" style="background:linear-gradient(135deg,#f6f9fc 0%,#dde7ff 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-5" style="background:#ffffff"></div>
                            <div class="theme-swatch" data-theme="theme-6" style="background:#f8f8f8"></div>
                            <div class="theme-swatch" data-theme="theme-7" style="background:linear-gradient(135deg,#ffe29f 0%,#ffa99f 48%,#ff719a 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-8" style="background:linear-gradient(135deg,#c2ffd8 0%,#465efb 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-9" style="background:linear-gradient(135deg,#fdfbfb 0%,#ebedee 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-10" style="background:linear-gradient(135deg,#a18cd1 0%,#fbc2eb 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-11" style="background:linear-gradient(135deg,#141e30 0%,#243b55 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-12" style="background:linear-gradient(135deg,#00c6ff 0%,#0072ff 100%)"></div>
                            <div class="theme-swatch" data-theme="theme-13" style="background:#fafcff;box-shadow:inset 0 0 0 1px rgba(99,102,241,0.2)" title="13"></div>
                            <div class="theme-swatch" data-theme="theme-14" style="background:linear-gradient(118deg,#f8fafc 0%,#eef2ff 100%)" title="14"></div>
                            <div class="theme-swatch" data-theme="theme-15" style="background:linear-gradient(125deg,#fff7ed 0%,#fef3c7 100%)" title="15"></div>
                            <div class="theme-swatch" data-theme="theme-16" style="background:linear-gradient(180deg,#f0fdf9 0%,#fff 100%)" title="16"></div>
                            <div class="theme-swatch" data-theme="theme-17" style="background:linear-gradient(160deg,#0f172a 0%,#334155 100%)" title="17"></div>
                            <div class="theme-swatch" data-theme="theme-18" style="background:linear-gradient(135deg,#fff 0%,#e0e7ff 100%)" title="18"></div>
                            <div class="theme-swatch" data-theme="theme-19" style="background:linear-gradient(125deg,#fff1f8 0%,#ffe4ec 100%)" title="19"></div>
                            <div class="theme-swatch" data-theme="theme-20" style="background:linear-gradient(145deg,#0f172a 0%,#134e4a 100%)" title="20"></div>
                            <div class="theme-swatch" data-theme="theme-21" style="background:linear-gradient(168deg,#fffbeb 0%,#fff7ed 100%)" title="21"></div>
                            <div class="theme-swatch" data-theme="theme-22" style="background:#f8fafc;box-shadow:inset 0 0 0 1px #e2e8f0" title="22"></div>
                            <div class="theme-swatch" data-theme="theme-23" style="background:linear-gradient(135deg,#1c1410 0%,#44403c 100%)" title="23"></div>
                            <div class="theme-swatch" data-theme="theme-24" style="background:linear-gradient(118deg,#fef2f2 0%,#fffef0 100%)" title="24"></div>
                            <div class="theme-swatch" data-theme="theme-25" style="background:linear-gradient(150deg,#16112e 0%,#0a1024 100%)" title="25 · Neon gece"></div>
                            <div class="theme-swatch" data-theme="theme-26" style="background:linear-gradient(135deg,#dbeafe 0%,#c7d2fe 100%)" title="26 · Cam"></div>
                            <div class="theme-swatch" data-theme="theme-27" style="background:linear-gradient(140deg,#2c1e0a 0%,#3f2d10 100%)" title="27 · Altın lüks"></div>
                            <div class="theme-swatch" data-theme="theme-28" style="background:linear-gradient(125deg,#ff9a9e 0%,#fad0c4 100%)" title="28 · Mercan"></div>
                            <div class="theme-swatch" data-theme="theme-29" style="background:linear-gradient(180deg,#1e1b4b 0%,#312e81 100%)" title="29 · Derin uzay"></div>
                            <div class="theme-swatch" data-theme="theme-30" style="background:linear-gradient(160deg,#ccfbf1 0%,#f0fdf4 100%)" title="30 · Aurora"></div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-3 mb-1">
                            <div class="oim-label mb-0">Hazır içerik şablonları</div>
                            <span class="oim-pro-pill"><i class="fas fa-crown"></i> Premium</span>
                        </div>
                        <p class="small text-muted mb-2">Tek tıkla tema + başlık + madde uygulanır. Sonra istediğinizi değiştirin.</p>
                        <div id="oimPresetGallery" class="oim-preset-gallery"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Arka plan</label>
                            <select id="bgType" class="form-control">
                                <option value="theme" selected>Tema (hazır)</option>
                                <option value="grad">Özel gradyan</option>
                                <option value="solid">Düz renk</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4"><label>Renk 1</label><input type="color" id="bg1" value="#ffffff" class="form-control"></div>
                        <div class="form-group col-md-4"><label>Renk 2</label><input type="color" id="bg2" value="#f1f5ff" class="form-control"></div>
                    </div>
                </div>

                <div class="oim-panel" data-step="2" role="tabpanel">
                    <div class="oim-panel-head">
                        <span class="oim-ph-icon"><i class="fas fa-font"></i></span>
                        <span class="oim-ph-titles"><span class="oim-ph-title">Metinler</span><span class="oim-ph-sub">Başlık, rozet, maddeler</span></span>
                        <span class="oim-ph-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger oim-reset-btn" data-reset="text" data-reset-label="Metinler (başlık, rozet, maddeler)"><i class="fas fa-rotate-left me-1"></i>Bölümü sıfırla</button>
                        </span>
                    </div>
                    <div class="oim-section">
                        <strong class="d-block mb-2"><i class="fas fa-box-open me-1 text-primary"></i>Siparişten getir</strong>
                        <p class="text-muted small mb-2">Sipariş numarasını yaz, ürün adı · fiyat · görsel otomatik gelsin.</p>
                        <div class="input-group input-group-sm">
                            <input type="number" min="1" step="1" id="oimOrderIdInput" class="form-control" placeholder="Sipariş No (örn. 2)">
                            <button type="button" id="btnLoadOrderById" class="btn btn-primary"><i class="fas fa-download me-1"></i>Yükle</button>
                        </div>
                        <div id="oimOrderLoadMsg" class="small mt-2 mb-0"></div>
                    </div>
                    <div class="oim-section">
                        <strong class="d-block mb-2">Tipografi ölçeği</strong>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-typo" data-typo="compact">Kompakt</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-typo" data-typo="balanced">Standart</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary oim-typo" data-typo="dramatic">Dramatik</button>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                            <strong class="mb-0">Başlık efekti</strong>
                            <span class="oim-pro-pill"><i class="fas fa-crown"></i> Premium</span>
                        </div>
                        <div class="oim-fx-row">
                            <button type="button" class="oim-fx-btn oim-fx" data-fx="none">Düz</button>
                            <button type="button" class="oim-fx-btn oim-fx" data-fx="gold"><i class="fas fa-crown me-1" style="color:#f59e0b"></i>Altın</button>
                            <button type="button" class="oim-fx-btn oim-fx" data-fx="neon"><i class="fas fa-bolt me-1" style="color:#22d3ee"></i>Neon</button>
                            <button type="button" class="oim-fx-btn oim-fx" data-fx="glow"><i class="fas fa-sun me-1" style="color:#6366f1"></i>Işıltı</button>
                            <button type="button" class="oim-fx-btn oim-fx" data-fx="outline"><i class="far fa-square me-1"></i>Kontur</button>
                        </div>
                        <div id="oimContrastHint" class="small mt-2 mb-0 text-muted"></div>
                    </div>
                    <div class="oim-section">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Başlıklar</strong>
                            <button id="addTitle" type="button" class="btn btn-sm btn-outline-secondary">+ Başlık</button>
                        </div>
                        <div id="titlesWrap">
                            <div class="input-group mb-2 title-row" data-target="title2">
                                <input type="text" class="form-control ti-text" value="Dolor Sit">
                                <div class="input-group-append">
                                    <input type="color" class="form-control ti-color" value="#e74c3c" style="width:56px">
                                    <select class="form-control ti-weight" style="width:88px">
                                        <option value="800" selected>Extra</option>
                                        <option value="700">Bold</option>
                                        <option value="600">Semibold</option>
                                    </select>
                                    <input type="number" class="form-control ti-size" value="36" min="16" max="96" style="width:72px">
                                    <button class="btn btn-outline-secondary btn-sm rm-title" type="button" title="Kaldır">−</button>
                                </div>
                            </div>
                            <div class="input-group mb-2 title-row" data-target="subtitle">
                                <input type="text" class="form-control ti-text" value="Amet">
                                <div class="input-group-append">
                                    <input type="color" class="form-control ti-color" value="#283458" style="width:56px">
                                    <select class="form-control ti-weight" style="width:88px">
                                        <option value="700" selected>Bold</option>
                                        <option value="600">Semibold</option>
                                        <option value="400">Normal</option>
                                    </select>
                                    <input type="number" class="form-control ti-size" value="28" min="16" max="96" style="width:72px">
                                    <button class="btn btn-outline-secondary btn-sm rm-title" type="button">−</button>
                                </div>
                            </div>
                            <div class="input-group mb-2 title-row" data-target="title1">
                                <input type="text" class="form-control ti-text" value="Lorem Ipsum">
                                <div class="input-group-append">
                                    <input type="color" class="form-control ti-color" value="#283458" style="width:56px">
                                    <select class="form-control ti-weight" style="width:88px">
                                        <option value="700" selected>Bold</option>
                                        <option value="600">Semibold</option>
                                        <option value="400">Normal</option>
                                    </select>
                                    <input type="number" class="form-control ti-size" value="46" min="16" max="96" style="width:72px">
                                    <button class="btn btn-outline-secondary btn-sm rm-title" type="button">−</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row mt-2">
                            <div class="form-group col-md-8">
                                <label>Başlık yazı tipi</label>
                                <select id="titles_font" class="form-control">
                                    <option value="Inter,Arial,Helvetica,sans-serif" selected>Inter</option>
                                    <option value="Arial,Helvetica,sans-serif">Arial</option>
                                    <option value="Helvetica,Arial,sans-serif">Helvetica</option>
                                    <option value="'Times New Roman',serif">Times New Roman</option>
                                    <option value="Georgia,serif">Georgia</option>
                                    <option value="Tahoma,sans-serif">Tahoma</option>
                                    <option value="Verdana,sans-serif">Verdana</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Gölge</label>
                                <div class="d-flex align-items-center flex-wrap">
                                    <input id="titles_shadow" type="checkbox" class="me-2">
                                    <input id="titles_shadow_color" type="color" value="#000000" style="width:40px;height:34px;padding:0;border:none;cursor:pointer">
                                    <input id="titles_shadow_blur" type="range" min="0" max="10" value="2" class="ms-2">
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="oim-section">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Rozet</strong>
                            <button id="btnRozetAdd" type="button" class="btn btn-sm btn-outline-success">+ Rozet</button>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label>Metin</label><input id="i_rozet" type="text" class="form-control" value="Özel Fırsat"></div>
                            <div class="form-group col-md-3"><label>Yazı</label><input id="rozet_color" type="color" class="form-control" value="#ffffff"></div>
                            <div class="form-group col-md-3"><label>Arka plan</label><input id="rozet_bg" type="color" class="form-control" value="#2ecc71"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3"><label>Boyut</label><input id="rozet_size" type="range" class="form-control-range" min="12" max="48" value="16"></div>
                            <div class="form-group col-md-3"><label>Köşe</label><input id="rozet_radius" type="range" class="form-control-range" min="0" max="50" value="40"></div>
                            <div class="form-group col-md-3"><label>Font</label>
                                <select id="rozet_font" class="form-control">
                                    <option value="Inter,Arial,Helvetica,sans-serif" selected>Inter</option>
                                    <option value="Arial,Helvetica,sans-serif">Arial</option>
                                    <option value="Georgia,serif">Georgia</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3"><label>Kalınlık</label>
                                <select id="rozet_weight" class="form-control">
                                    <option value="400">Normal</option>
                                    <option value="600">Semibold</option>
                                    <option value="700" selected>Bold</option>
                                    <option value="800">Extra</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label>Gölge (rozet)</label>
                            <div class="d-flex align-items-center">
                                <input id="rozet_shadow" type="checkbox" class="me-2">
                                <input id="rozet_shadow_color" type="color" value="#000000" class="form-control" style="width:56px">
                                <input id="rozet_shadow_blur" type="range" min="0" max="10" value="2" class="ms-2">
                            </div>
                        </div>
                    </div>

                    <div class="oim-section">
                        <strong class="d-block mb-2">CTA düğmesi</strong>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="oimCtaShow" checked>
                            <label class="form-check-label small" for="oimCtaShow">Tuval üzerinde CTA göster</label>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2">
                                <label class="small">Metin</label>
                                <input type="text" id="oimCtaText" class="form-control" value="Şimdi al" maxlength="40">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small">Yazı rengi</label>
                                <input type="color" id="oimCtaColor" value="#ffffff" class="form-control">
                        </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small">Arka plan</label>
                                <input type="color" id="oimCtaBg" value="#4f46e5" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-0">
                                <label class="small">Boyut (px)</label>
                                <input type="range" id="oimCtaFont" class="form-range" min="12" max="32" value="18">
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label class="small">Köşe (≤500, 999 = hap)</label>
                                <input type="number" id="oimCtaRadius" class="form-control" min="4" max="999" value="999" step="1">
                        </div>
                    </div>
                </div>

                    <div class="oim-section mb-0">
                        <strong class="d-block mb-2">Madde listesi</strong>
                        <div id="bulletsWrap">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control bullet-item" value="• Kaliteli malzeme ve şık tasarım">
                                <div class="input-group-append"><button class="btn btn-outline-secondary btn-sm rm-bullet" type="button">−</button></div>
                        </div>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control bullet-item" value="• Hızlı teslimat ve güvenli alışveriş">
                                <div class="input-group-append"><button class="btn btn-outline-secondary btn-sm rm-bullet" type="button">−</button></div>
                    </div>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control bullet-item" value="• Uygun fiyat, yüksek performans">
                                <div class="input-group-append"><button class="btn btn-outline-secondary btn-sm rm-bullet" type="button">−</button></div>
                            </div>
                        </div>
                        <button id="addBullet" type="button" class="btn btn-sm btn-outline-secondary mt-2">+ Madde</button>
                        <div class="form-row mt-3">
                            <div class="form-group col-md-6">
                                <label>Madde fontu</label>
                            <select id="bullets_font" class="form-control">
                                <option value="Inter,Arial,Helvetica,sans-serif" selected>Inter</option>
                                <option value="Arial,Helvetica,sans-serif">Arial</option>
                                <option value="Georgia,serif">Georgia</option>
                            </select>
                        </div>
                            <div class="form-group col-md-3"><label>Kalınlık</label>
                                <select id="bullets_weight" class="form-control">
                                    <option value="400" selected>Normal</option><option value="600">Semibold</option><option value="700">Bold</option>
                                </select>
                        </div>
                            <div class="form-group col-md-3"><label>Pt</label>
                            <input id="bullets_size" type="number" class="form-control" value="19" min="10" max="40">
                        </div>
                    </div>
                        <div class="form-group mb-0">
                            <label>Gölge (maddeler)</label>
                            <div class="d-flex align-items-center">
                                <input id="bullets_shadow" type="checkbox" class="me-2">
                                <input id="bullets_shadow_color" type="color" value="#000000" class="form-control" style="width:56px">
                                <input id="bullets_shadow_blur" type="range" min="0" max="10" value="2" class="ms-2">
                    </div>
                        </div>
                    </div>
                </div>

                <div class="oim-panel" data-step="3" role="tabpanel">
                    <div class="oim-panel-head">
                        <span class="oim-ph-icon"><i class="fas fa-image"></i></span>
                        <span class="oim-ph-titles"><span class="oim-ph-title">Görseller</span><span class="oim-ph-sub">Ürün ve ikon</span></span>
                        <span class="oim-ph-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger oim-reset-btn" data-reset="images" data-reset-label="Görseller (ürün & ikon)"><i class="fas fa-rotate-left me-1"></i>Bölümü sıfırla</button>
                        </span>
                    </div>
                    <div class="form-group">
                        <label>Ürün görselleri (çoklu)</label>
                        <input type="file" id="i_img" class="form-control-file" accept="image/png,image/jpeg,image/webp,image/gif" multiple style="font-size:.85rem">
                    </div>
                    <div id="imgsList" class="mb-3 small text-muted"></div>

                    <div class="oim-section mb-0">
                        <strong class="d-block mb-2">İkon</strong>
                    <div class="form-row">
                            <div class="form-group col-md-6">
                            <select id="i_icon_class" class="form-control">
                                <option value="fas fa-star">fas fa-star</option>
                                <option value="fas fa-check-circle">fas fa-check-circle</option>
                                <option value="fas fa-bolt">fas fa-bolt</option>
                                <option value="fas fa-certificate">fas fa-certificate</option>
                                <option value="fas fa-fire">fas fa-fire</option>
                                <option value="fas fa-heart">fas fa-heart</option>
                                <option value="fas fa-shield-alt">fas fa-shield-alt</option>
                                <option value="fas fa-truck">fas fa-truck</option>
                                <option value="fas fa-leaf">fas fa-leaf</option>
                                <option value="fas fa-gem">fas fa-gem</option>
                                <option value="fas fa-box">fas fa-box</option>
                                <option value="fas fa-paint-brush">fas fa-paint-brush</option>
                                <option value="fas fa-thumbs-up">fas fa-thumbs-up</option>
                                <option value="fas fa-crown">fas fa-crown</option>
                                <option value="fas fa-gift">fas fa-gift</option>
                                <option value="fas fa-shipping-fast">fas fa-shipping-fast</option>
                                <option value="fas fa-medal">fas fa-medal</option>
                                <option value="fas fa-award">fas fa-award</option>
                            </select>
                        </div>
                            <div class="form-group col-md-3"><label>Renk</label><input id="i_icon_color" type="color" class="form-control" value="#283458"></div>
                            <div class="form-group col-md-3"><label>px</label><input id="i_icon_size" type="number" class="form-control" value="48" min="16" max="120"></div>
                    </div>
                        <button id="btnIconAdd" type="button" class="btn btn-sm btn-outline-secondary">İkon ekle</button>
                </div>
            </div>

                <div class="oim-panel" data-step="4" role="tabpanel">
                    <div class="oim-panel-head">
                        <span class="oim-ph-icon"><i class="fas fa-layer-group"></i></span>
                        <span class="oim-ph-titles"><span class="oim-ph-title">Düzen &amp; indir</span><span class="oim-ph-sub">Katman ve PNG</span></span>
                        <span class="oim-ph-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger oim-reset-btn" data-reset="layout" data-reset-label="Düzen &amp; indir ayarları"><i class="fas fa-rotate-left me-1"></i>Bölümü sıfırla</button>
                            <button type="button" class="btn btn-sm btn-danger oim-reset-btn" data-reset="all" data-reset-label="TÜM tasarım"><i class="fas fa-trash-arrow-up me-1"></i>Tümünü sıfırla</button>
                        </span>
                    </div>
                    <p class="text-muted small mb-2">Klavye: seçili katman için ok tuşları <kbd>±1 px</kbd>, <kbd>Shift</kbd>+ok <kbd>±10 px</kbd>, silmek için <kbd>Del</kbd> / <kbd>Backspace</kbd> (katman); <kbd>Ctrl+Z</kbd> geri · <kbd>Ctrl+Y</kbd> / <kbd>Ctrl+Shift+Z</kbd> yinele. Mor tutamak: <kbd>Shift</kbd> oran korur; <kbd>Ctrl</kbd> yalnızca <strong>ürün görselinde</strong> genişlik ve yüksekliği bağımsız ayarlar (içerik gerçekten gerilir/sıkıştırılır). Form alanındayken kısayol devre dışıdır.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" id="btnUndoOim" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo"></i></button>
                        <button type="button" id="btnRedoOim" class="btn btn-sm btn-outline-secondary"><i class="fas fa-redo"></i></button>
                        <button type="button" id="btnSaveDraft" class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i>Taslağı kaydet</button>
                        <button type="button" id="btnLoadDraft" class="btn btn-sm btn-outline-primary">Taslağı yükle</button>
                        <label class="btn btn-sm btn-outline-secondary mb-0"><i class="fas fa-file-import me-1"></i><input type="file" id="oimDraftFile" accept="application/json,.json" class="d-none">JSON yükle</label>
                        <button type="button" id="btnExportDraft" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>JSON çıkar</button>
                        <button type="button" id="btnHydrateOrder" class="btn btn-sm btn-outline-info d-none">Sipariş verisini uygula</button>
                    </div>
                    <div class="oim-section mb-3">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="oimShowSafe">
                            <label class="form-check-label small" for="oimShowSafe">Güvenli alan çerçevesini önizlemede göster (24 px iç kenar)</label>
                        </div>
                        <div class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="oimAutosaveDraft" checked>
                            <label class="form-check-label small" for="oimAutosaveDraft">Taslağı yerelde otomatik kaydet (~25 sn)</label>
                        </div>
                    </div>
                    <div class="oim-section mb-3">
                        <strong class="d-block mb-2">Hizalama &amp; araçlar</strong>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" id="btnAlignCenterH" class="btn btn-sm btn-outline-secondary py-1">⟷ Ortala</button>
                            <button type="button" id="btnAlignCenterV" class="btn btn-sm btn-outline-secondary py-1">⟱ Dikey ortala</button>
                            <button type="button" id="btnSnapLeft" class="btn btn-sm btn-outline-secondary py-1">Sol 24px</button>
                            <button type="button" id="btnSnapRight" class="btn btn-sm btn-outline-secondary py-1">Sağ 24px</button>
                            <button type="button" id="btnSnapTop" class="btn btn-sm btn-outline-secondary py-1">Üst 24px</button>
                            <button type="button" id="btnSnapBottom" class="btn btn-sm btn-outline-secondary py-1">Alt 24px</button>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" id="btnDuplicateLayer" class="btn btn-sm btn-outline-success py-1">Çoğalt</button>
                            <button type="button" id="btnToggleLock" class="btn btn-sm btn-outline-warning py-1">Katman kilidi</button>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">Katman seçin veya tuval üzerinden tıklayın.</p>
                    <div class="oim-section">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                            <strong>Katman</strong>
                            <span id="layerInfo" class="badge text-bg-secondary">Seçili: —</span>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Öğe</label>
                                <select id="layerTarget" class="form-control"></select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Sıra (z-index)</label>
                                <div>
                                    <button type="button" id="btnToFront" class="btn btn-sm btn-outline-secondary me-1 mb-1">En üst</button>
                                    <button type="button" id="btnToBack" class="btn btn-sm btn-outline-secondary me-1 mb-1">En alt</button>
                                    <button type="button" id="btnUp" class="btn btn-sm btn-outline-success me-1 mb-1">+1</button>
                                    <button type="button" id="btnDown" class="btn btn-sm btn-outline-danger me-1 mb-1">−1</button>
                                    <button type="button" id="btnHideShow" class="btn btn-sm btn-outline-warning me-1 mb-1">Gizle</button>
                                    <button type="button" id="btnDeleteSel" class="btn btn-sm btn-danger mb-1">Sil</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label>Katman listesi</label>
                            <div id="layerList" class="small" style="max-height:220px;overflow:auto"></div>
                        </div>
                        <div class="form-group mb-0 mt-2">
                            <label>Boyut (seçili görsel / ikon)</label>
                            <input type="range" id="sizeSlider" min="40" max="800" value="300" class="form-control-range">
                        </div>
                    </div>

                    <div class="oim-section mb-3">
                        <strong class="d-block mb-2">Watermark (logo köşesi)</strong>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="oimWmEnable">
                            <label class="form-check-label small" for="oimWmEnable">İndirirken tuval köşesine logo ekle</label>
                        </div>
                        <input type="file" id="oimWmFile" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm mb-2">
                        <div class="form-row mb-2">
                            <div class="form-group col-6">
                                <label class="small">Opaklık</label>
                                <input type="range" id="oimWmOpacity" class="form-range" min="0.2" max="1" step="0.05" value="0.85">
                            </div>
                            <div class="form-group col-6">
                                <label class="small">Genişlik (tuval yüzdesi)</label>
                                <input type="range" id="oimWmScale" class="form-range" min="6" max="28" step="1" value="14">
                            </div>
                        </div>
                        <small class="text-muted">Watermark yalnızca indirirken uygulanır; öne izlemede gösterilmez.</small>
                    </div>

                    <div class="oim-section mb-3">
                        <strong class="d-block mb-2">Çıktı dosyası</strong>
                        <p class="small text-muted mb-2">Dosya ebadi 1. adımdaki tuvalle aynıdır; alta yalnızca biçim ve (isteğe bağlı) 2× piksel yoğunluğu seçilir.</p>
                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label class="small">Biçim</label>
                                <select id="oimExportFmt" class="form-control form-control-sm">
                                    <option value="png">PNG</option>
                                    <option value="jpeg">JPEG</option>
                                    <option value="webp">WebP</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="small">JPEG/WebP kalite</label>
                                <input type="range" id="oimExportQuality" class="form-range" min="0.5" max="1" step="0.02" value="0.92">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small" title="Dosya ebadi = tuval × bu çarpı">Çözünürlük çarpanı</label>
                                <select id="oimExportPxScale" class="form-control form-control-sm">
                                    <option value="1" selected>1× (tam boy)</option>
                                    <option value="2">2× (keskin yüksek çözünürlük)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="oimPngTransparent">
                            <label class="form-check-label small" for="oimPngTransparent">PNG için şeffaf arka plan (tema düz tonu ve özel zemin bağlamasında)</label>
                        </div>
                    </div>

                    <button type="button" id="btnDownload" class="btn btn-primary btn-lg btn-block mt-2">
                        <i class="fas fa-file-download me-2"></i>Görseli indir
                    </button>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" id="btnCopyImage" class="btn btn-outline-primary btn-sm flex-fill">
                            <i class="far fa-copy me-1"></i>Panoya kopyala
                        </button>
                        <button type="button" id="btnPreviewNewTab" class="btn btn-outline-secondary btn-sm flex-fill">
                            <i class="fas fa-external-link-alt me-1"></i>Yeni sekmede aç
                        </button>
                    </div>
                    <div id="saveResult" class="text-center small mt-2" style="min-height:1.25rem;color:var(--oim-muted)"></div>
                </div>

                <div class="oim-nav-btns">
                    <button type="button" class="btn btn-outline-secondary" id="oimPrev" disabled><i class="fas fa-arrow-left me-1"></i> Geri</button>
                    <button type="button" class="btn btn-light text-dark fw-semibold ms-auto" id="oimNext">
                        İleri <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /oimStudioWrap -->

    <div id="oimFactoryWrap" class="d-none">
        <div class="oim-factory-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <strong class="d-block">Fabrika şablonu</strong>
                    <span class="small text-muted">Stüdyodaki mevcut tuval düzeni şablon olarak kullanılır.</span>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnFactorySaveTemplate">
                        <i class="fas fa-save me-1"></i>Mevcut tuvali şablon yap
                    </button>
                    <select id="oimFactoryPresetPick" class="form-control form-control-sm" style="width:auto;min-width:170px">
                        <option value="">Hazır şablon seç…</option>
                    </select>
                </div>
            </div>
            <div id="oimFactoryTemplateStatus" class="small text-muted mb-0">Kayıtlı şablon: kontrol ediliyor…</div>
            <div id="oimFactoryTemplatePreview" class="mt-2 d-none">
                <span class="small text-muted d-block mb-1">Şablon önizleme</span>
                <img id="oimFactoryTemplateImg" alt="Şablon önizleme" style="max-width:260px;width:100%;border:1px solid var(--oim-border);border-radius:10px">
            </div>
        </div>

        <div class="oim-factory-card">
            <strong class="d-block mb-2">Sipariş kuyruğu</strong>
            <div class="form-row mb-2">
                <div class="form-group col-md-3">
                    <label class="small">Durum</label>
                    <select id="oimFactoryStatus" class="form-control form-control-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($oim_status_names as $sn): ?>
                        <option value="<?= htmlspecialchars($sn, ENT_QUOTES, 'UTF-8') ?>"<?= $sn === 'Beklemede' ? ' selected' : '' ?>><?= htmlspecialchars($sn, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label class="small">Arama</label>
                    <input type="text" id="oimFactorySearch" class="form-control form-control-sm" placeholder="ID, isim, telefon">
                </div>
                <div class="form-group col-md-2">
                    <label class="small">Limit</label>
                    <select id="oimFactoryLimit" class="form-control form-control-sm">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnFactoryLoadOrders">
                        <i class="fas fa-sync me-1"></i>Listeyi yükle
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFactorySelectAll">Tümünü seç</button>
                </div>
            </div>
            <div class="oim-factory-table-wrap mb-2">
                <table class="table table-sm table-hover oim-factory-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="oimFactoryCheckAll" title="Tümü"></th>
                            <th>#</th>
                            <th>Müşteri</th>
                            <th>Durum</th>
                            <th>Tutar</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody id="oimFactoryOrderBody">
                        <tr><td colspan="6" class="text-muted text-center py-3">Listeyi yükleyin veya URL ile sipariş seçin.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="small text-muted" id="oimFactoryOrderMeta">0 sipariş</div>
        </div>

        <div class="oim-factory-card">
            <strong class="d-block mb-2">Üretim ayarları</strong>
            <div class="form-row mb-3">
                <div class="form-group col-md-3">
                    <label class="small">Çıktı</label>
                    <select id="oimFactoryFmt" class="form-control form-control-sm">
                        <option value="png">PNG</option>
                        <option value="jpeg">JPEG</option>
                        <option value="webp">WebP</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label class="small">Ürün görseli</label>
                    <select id="oimFactoryUseImage" class="form-control form-control-sm">
                        <option value="1" selected>Siparişten otomatik ekle</option>
                        <option value="0">Şablondaki görseli koru</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label class="small">Teslim</label>
                    <select id="oimFactoryDelivery" class="form-control form-control-sm">
                        <option value="download" selected>İndir (tek tek)</option>
                        <option value="server">Sunucuya kaydet</option>
                        <option value="both">İkisi birden</option>
                    </select>
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="oimFactoryAutoHydrate" checked>
                        <label class="form-check-label small" for="oimFactoryAutoHydrate">Sipariş metinlerini otomatik yaz</label>
                    </div>
                </div>
            </div>
            <div class="oim-factory-stat-row">
                <div class="oim-factory-stat"><b id="oimFactoryStatSel">0</b><span>Seçili</span></div>
                <div class="oim-factory-stat"><b id="oimFactoryStatOk">0</b><span>Üretilen</span></div>
                <div class="oim-factory-stat"><b id="oimFactoryStatErr">0</b><span>Hata</span></div>
            </div>
            <div class="oim-factory-progress mb-2"><span id="oimFactoryProgressBar"></span></div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <button type="button" class="btn btn-primary" id="btnFactoryRun" disabled>
                    <i class="fas fa-play me-1"></i>Seçilenleri üret
                </button>
                <button type="button" class="btn btn-outline-danger d-none" id="btnFactoryStop">
                    <i class="fas fa-stop me-1"></i>Durdur
                </button>
                <button type="button" class="btn btn-outline-primary" id="btnFactoryEditStudio">
                    <i class="fas fa-palette me-1"></i>Stüdyoda düzenle
                </button>
                <span class="small text-muted" id="oimFactoryRunStatus">Hazır</span>
            </div>
            <div class="oim-factory-log" id="oimFactoryLog">Fabrika kuyruğu boş.</div>
            <div id="oimFactoryGallery" class="oim-factory-gallery"></div>
        </div>
    </div>

        </div>
    </div>

<script>
(function () {
    'use strict';

    var OIM_STEP = 1;
    var OIM_MAX = 4;
    var imgCounter = 1;
    var rozetCounter = 1;
    var iconCounter = 1;
    var ctaCounter = 1;
    var wmStore = { dataURL: '', img: null };
    var undoHistory = [];
    var undoPtr = -1;
    var MAX_HIST = 40;
    var oimAutosaveIv = null;
    var OIM_DEFAULT_STATE = null;
    var undoDragTimer = null;
    var IGNORE_UNDO_GUARD = false;

    function applyTextShadow(enabled, color, blur) {
        return enabled ? ('1px 1px ' + (blur || 2) + 'px ' + (color || '#000')) : 'none';
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** Tuval ebadi kullanıcıya aittir (#oimCanvasW / #oimCanvasH). */
    function clampInt(n, lo, hi) {
        n = Math.round(Number(n));
        if (isNaN(n)) return lo;
        return Math.min(hi, Math.max(lo, n));
    }

    function getCanvasW() {
        var el = document.getElementById('oimCanvasW');
        return clampInt(el ? parseInt(String(el.value || '900'), 10) : 900, 200, 3840);
    }

    function getCanvasH() {
        var el = document.getElementById('oimCanvasH');
        return clampInt(el ? parseInt(String(el.value || '450'), 10) : 450, 200, 3840);
    }

    function updateCanvasDimsHeadings() {
        var w = getCanvasW();
        var h = getCanvasH();
        var head = document.getElementById('oimPreviewCardHeading');
        if (head) {
            head.innerHTML = '<i class="fas fa-eye"></i> Canlı önizleme · ' + w + ' × ' + h + ' px';
        }
        var dstr = document.getElementById('oimCanvasDimsStr');
        if (dstr) {
            dstr.textContent = w + '×' + h + ' px';
        }
    }

    function syncDesignDimsFromInputs() {
        var cw = getCanvasW();
        var ch = getCanvasH();
        var design = document.getElementById('design');
        var scaler = document.getElementById('oimDesignScaler');
        var ov = document.getElementById('oimSafeOverlay');
        if (design) {
            design.style.width = cw + 'px';
            design.style.height = ch + 'px';
        }
        if (scaler) {
            scaler.style.width = cw + 'px';
            scaler.style.height = ch + 'px';
        }
        if (ov) {
            ov.style.width = cw + 'px';
            ov.style.height = ch + 'px';
        }
        updateCanvasDimsHeadings();
        fitOimDesignPreview();
        positionResizeHud();
    }

    var OIM_THEME_CLASSES = [
        'theme-1', 'theme-2', 'theme-3', 'theme-4', 'theme-5', 'theme-6', 'theme-7', 'theme-8',
        'theme-9', 'theme-10', 'theme-11', 'theme-12', 'theme-13', 'theme-14', 'theme-15', 'theme-16',
        'theme-17', 'theme-18', 'theme-19', 'theme-20', 'theme-21', 'theme-22', 'theme-23', 'theme-24',
        'theme-25', 'theme-26', 'theme-27', 'theme-28', 'theme-29', 'theme-30',
    ];
    var OIM_FORM_IDS_LIKE = [
        'i_theme', 'bgType', 'bg1', 'bg2', 'titles_font', 'titles_shadow_color', 'titles_shadow_blur', 'titles_shadow',
        'i_rozet', 'rozet_color', 'rozet_bg', 'rozet_size', 'rozet_radius', 'rozet_font', 'rozet_weight',
        'rozet_shadow', 'rozet_shadow_color', 'rozet_shadow_blur',
        'bullets_font', 'bullets_weight', 'bullets_size', 'bullets_shadow', 'bullets_shadow_color', 'bullets_shadow_blur',
        'oimCtaText', 'oimCtaColor', 'oimCtaBg', 'oimCtaFont', 'oimCtaRadius',
        'i_icon_class', 'i_icon_color', 'i_icon_size',
        'oimExportFmt', 'oimExportQuality', 'oimCanvasW', 'oimCanvasH', 'oimExportPxScale', 'oimPngTransparent', 'oimCtaShow', 'oimShowSafe',
        'oimWmOpacity', 'oimWmScale',
    ];

    var OIM_PRESETS = {
        ecom: {
            theme: 'theme-13',
            bgType: 'theme',
            rozet: 'Yeni koleksiyon',
            rozet_bg: '#10b981',
            rozet_color: '#ffffff',
            title2: { text: 'Premium kalite', color: '#6366f1', weight: '800', size: '36' },
            subtitle: { text: 'Ücretsiz kargo fırsatı', color: '#475569', weight: '600', size: '26' },
            title1: { text: 'Seçilmiş ürün vitrinleri', color: '#0f172a', weight: '800', size: '44' },
            bullets: [
                '• Stoktan hızlı gönderim',
                '• Orijinal ürün, faturalı satış',
                '• Güvenli ödeme seçenekleri',
            ],
        },
        campaign: {
            theme: 'theme-15',
            bgType: 'theme',
            rozet: '%30\u2019a varan',
            rozet_bg: '#ea580c',
            rozet_color: '#fffbeb',
            title1: { text: 'FLASH KAMPANYA', color: '#9a3412', weight: '800', size: '40' },
            title2: { text: 'Bugün son gün!', color: '#dc2626', weight: '800', size: '38' },
            subtitle: { text: 'Sepette ekstra avantajlar', color: '#713f12', weight: '600', size: '24' },
            bullets: ['• Süre dolmadan yakalayın', '• Kampanya kodu gerektirmez', '• Fiyat garantisi'],
        },
        corporate: {
            theme: 'theme-14',
            bgType: 'theme',
            rozet: 'Kurumsal',
            rozet_bg: '#1e293b',
            rozet_color: '#f8fafc',
            title1: { text: 'Güvenilir çözüm ortağınız', color: '#0f172a', weight: '800', size: '38' },
            title2: { text: 'Profesyonel hizmet', color: '#4f46e5', weight: '800', size: '32' },
            subtitle: { text: 'Uzun süreçlerde bile tutarlı kalite', color: '#334155', weight: '600', size: '24' },
            bullets: ['• Deneyimli ekip desteği', '• SLA odaklı operasyon', '• Ölçeklenebilir altyapı'],
        },
        fashion: {
            theme: 'theme-19',
            bgType: 'theme',
            rozet: 'Yeni sezon',
            rozet_bg: '#db2777',
            rozet_color: '#fff1f2',
            title1: { text: 'Koleksiyon ön siparişte', color: '#9d174d', weight: '800', size: '40' },
            title2: { text: 'Şık kombinler', color: '#be185d', weight: '800', size: '34' },
            subtitle: { text: 'Sınırlı stok · hızlı kargo', color: '#831843', weight: '600', size: '24' },
            bullets: ['• Premium kumaş seçenekleri', '• Beden rehberi desteği', '• Kolay değişim'],
        },
        tech: {
            theme: 'theme-20',
            bgType: 'theme',
            rozet: 'Teknoloji',
            rozet_bg: '#0891b2',
            rozet_color: '#ecfeff',
            title1: { text: 'Güç · hız · güven', color: '#f1f5f9', weight: '800', size: '42' },
            title2: { text: 'Yeni nesil donanım', color: '#67e8f9', weight: '800', size: '36' },
            subtitle: { text: 'Uzun ömürlü performans garantisi', color: '#cbd5e1', weight: '600', size: '24' },
            bullets: ['• Fabrika garantili ürün', '• Güvenli teslimat', '• Uzman teknik destek'],
        },
        food: {
            theme: 'theme-21',
            bgType: 'theme',
            rozet: 'Tazelik',
            rozet_bg: '#d97706',
            rozet_color: '#fffbeb',
            title1: { text: 'Lezzet bugün masada', color: '#92400e', weight: '800', size: '40' },
            title2: { text: 'Şef menüsü seçkisi', color: '#b45309', weight: '800', size: '34' },
            subtitle: { text: 'Hijyen ve kalite bir arada', color: '#78350f', weight: '600', size: '24' },
            bullets: ['• Kontrollü üretim süreci', '• Hızlı servis', '• Diyet dostu seçenekler'],
        },
        b2b: {
            theme: 'theme-22',
            bgType: 'theme',
            rozet: 'İş ortağınız',
            rozet_bg: '#334155',
            rozet_color: '#f8fafc',
            title1: { text: 'Minimal · net · ölçeklenebilir', color: '#0f172a', weight: '800', size: '36' },
            title2: { text: 'B2B süreçler için', color: '#475569', weight: '800', size: '30' },
            subtitle: { text: 'Şeffaf fiyatlandırma ve raporlama', color: '#64748b', weight: '600', size: '24' },
            bullets: ['• Sözleşmeli tedarik', '• SLA ve performans raporu', '• API / entegrasyon'],
        },
        premium: {
            theme: 'theme-23',
            bgType: 'theme',
            rozet: 'VIP',
            rozet_bg: '#b45309',
            rozet_color: '#fffbeb',
            title1: { text: 'Premium deneyim', color: '#fafaf9', weight: '800', size: '40' },
            title2: { text: 'Ayrıcalıklı koleksiyon', color: '#fbbf24', weight: '800', size: '36' },
            subtitle: { text: 'Sınırlı sayıda · el işçiliği', color: '#e7e5e4', weight: '600', size: '24' },
            bullets: ['• İmza paketleme', '• Özel danışmanlık', '• Genişletilmiş garanti'],
        },
        flash: {
            theme: 'theme-24',
            bgType: 'theme',
            rozet: 'SON 24 SAAT',
            rozet_bg: '#dc2626',
            rozet_color: '#fef2f2',
            title1: { text: 'FLAŞ İNDİRİM', color: '#991b1b', weight: '800', size: '40' },
            title2: { text: 'Stoklar tükenmeden', color: '#dc2626', weight: '800', size: '36' },
            subtitle: { text: 'Otomatik indirim · kupon gerekmez', color: '#b91c1c', weight: '600', size: '22' },
            bullets: ['• Hızlı teslimat', '• İade kolaylığı', '• Güvenli ödeme'],
        },
        neon: {
            theme: 'theme-25',
            bgType: 'theme',
            rozet: 'YENİ',
            rozet_bg: '#a855f7',
            rozet_color: '#faf5ff',
            title1: { text: 'GELECEK ŞİMDİ BAŞLIYOR', color: '#f5f3ff', weight: '800', size: '40' },
            title2: { text: 'Neon seri · sınırlı üretim', color: '#22d3ee', weight: '800', size: '32' },
            subtitle: { text: 'Işıltılı tasarım, güçlü performans', color: '#e9d5ff', weight: '600', size: '24' },
            bullets: ['• Öne çıkan tasarım', '• Premium malzeme', '• Hızlı kargo'],
        },
        luxury: {
            theme: 'theme-27',
            bgType: 'theme',
            rozet: 'LUXURY',
            rozet_bg: '#a16207',
            rozet_color: '#fffbeb',
            title1: { text: 'Zarafetin yeni tanımı', color: '#fffbeb', weight: '800', size: '40' },
            title2: { text: 'El işçiliği · altın detay', color: '#facc15', weight: '800', size: '34' },
            subtitle: { text: 'Ayrıcalıklı bir deneyim sizi bekliyor', color: '#fef9c3', weight: '600', size: '23' },
            bullets: ['• Sınırlı sayıda üretim', '• Sertifikalı kalite', '• Özel kutu & kart'],
        },
        minimal: {
            theme: 'theme-26',
            bgType: 'theme',
            rozet: 'MINIMAL',
            rozet_bg: '#4f46e5',
            rozet_color: '#ffffff',
            title1: { text: 'Sadelik en yüksek zarafet', color: '#0f172a', weight: '800', size: '42' },
            title2: { text: 'Daha az, ama daha iyi', color: '#4f46e5', weight: '700', size: '30' },
            subtitle: { text: 'Fonksiyonel ve şık', color: '#334155', weight: '600', size: '24' },
            bullets: ['• Zamansız tasarım', '• Dayanıklı yapı', '• Her ortama uyum'],
        },
        blackfriday: {
            theme: 'theme-29',
            bgType: 'theme',
            rozet: 'BLACK FRIDAY',
            rozet_bg: '#0f172a',
            rozet_color: '#f8fafc',
            title1: { text: 'YILIN EN BÜYÜK İNDİRİMİ', color: '#ffffff', weight: '800', size: '38' },
            title2: { text: '%70’e varan fırsatlar', color: '#a5b4fc', weight: '800', size: '36' },
            subtitle: { text: 'Stoklarla sınırlı — kaçırma', color: '#e0e7ff', weight: '600', size: '24' },
            bullets: ['• Ekstra sepet indirimi', '• Ücretsiz kargo', '• Taksit imkânı'],
        },
        story: {
            theme: 'theme-28',
            bgType: 'theme',
            rozet: 'HİKAYE',
            rozet_bg: '#db2777',
            rozet_color: '#fff1f2',
            title1: { text: 'Kaydırma yukarı', color: '#831843', weight: '800', size: '44' },
            title2: { text: 'Bugüne özel', color: '#be185d', weight: '800', size: '34' },
            subtitle: { text: 'Sınırlı süre fırsatı', color: '#9d174d', weight: '600', size: '26' },
            bullets: ['• Hemen keşfet', '• Kolay sipariş', '• Güvenli ödeme'],
        },
        welcome: {
            theme: 'theme-30',
            bgType: 'theme',
            rozet: 'HOŞ GELDİN',
            rozet_bg: '#0d9488',
            rozet_color: '#f0fdfa',
            title1: { text: 'İlk siparişine özel', color: '#0f766e', weight: '800', size: '40' },
            title2: { text: 'Sana özel indirim kodu', color: '#0891b2', weight: '800', size: '32' },
            subtitle: { text: 'Aramıza katıldığın için teşekkürler', color: '#115e59', weight: '600', size: '24' },
            bullets: ['• Hoş geldin hediyesi', '• Öncelikli destek', '• Hızlı teslimat'],
        },
    };

    var OIM_PRESET_META = {
        ecom: { label: 'Ürün vitrin', grad: 'linear-gradient(135deg,#e0f2fe,#eef2ff)', badge: '' },
        campaign: { label: 'Kampanya', grad: 'linear-gradient(135deg,#fff7ed,#fef3c7)', badge: '' },
        corporate: { label: 'Kurumsal', grad: 'linear-gradient(135deg,#f8fafc,#e2e8f0)', badge: '' },
        fashion: { label: 'Moda', grad: 'linear-gradient(135deg,#fff1f8,#ffe4ec)', badge: '' },
        tech: { label: 'Teknoloji', grad: 'linear-gradient(135deg,#0f172a,#134e4a)', badge: 'dark' },
        food: { label: 'Gıda', grad: 'linear-gradient(135deg,#fffbeb,#fff7ed)', badge: '' },
        b2b: { label: 'B2B minimal', grad: 'linear-gradient(135deg,#f8fafc,#e2e8f0)', badge: '' },
        flash: { label: 'İndirim flaşı', grad: 'linear-gradient(135deg,#fef2f2,#fffef0)', badge: '' },
        neon: { label: 'Neon gece', grad: 'linear-gradient(150deg,#16112e,#0a1024)', badge: 'pro' },
        luxury: { label: 'Altın lüks', grad: 'linear-gradient(140deg,#2c1e0a,#3f2d10)', badge: 'pro' },
        minimal: { label: 'Minimal', grad: 'linear-gradient(135deg,#dbeafe,#c7d2fe)', badge: 'pro' },
        premium: { label: 'Premium', grad: 'linear-gradient(135deg,#1c1410,#44403c)', badge: 'pro' },
        blackfriday: { label: 'Black Friday', grad: 'linear-gradient(180deg,#1e1b4b,#312e81)', badge: 'pro' },
        story: { label: 'Story / reels', grad: 'linear-gradient(125deg,#ff9a9e,#fad0c4)', badge: 'pro' },
        welcome: { label: 'Hoş geldin', grad: 'linear-gradient(160deg,#ccfbf1,#f0fdf4)', badge: 'pro' },
    };

    function setTitleRowInputs(target, spec) {
        if (!spec) return;
        document.querySelectorAll('#titlesWrap .title-row[data-target="' + target + '"]').forEach(function (row) {
            var inp = row.querySelector('.ti-text');
            var c = row.querySelector('.ti-color');
            var w = row.querySelector('.ti-weight');
            var s = row.querySelector('.ti-size');
            if (inp) inp.value = spec.text;
            if (c && spec.color) c.value = spec.color;
            if (w && spec.weight) w.value = spec.weight;
            if (s && spec.size) s.value = spec.size;
        });
    }

    function fillBulletsWrap(lines) {
        var wrap = document.getElementById('bulletsWrap');
        if (!wrap) return;
        var html = '';
        lines.forEach(function (line) {
            html +=
                '<div class="input-group mb-2"><input type="text" class="form-control bullet-item" value="' +
                escapeHtml(line) +
                '"><div class="input-group-append"><button class="btn btn-outline-secondary btn-sm rm-bullet" type="button">−</button></div></div>';
        });
        wrap.innerHTML = html;
    }

    function applyOimPreset(key) {
        var p = OIM_PRESETS[key];
        if (!p) return;
        document.getElementById('i_theme').value = p.theme;
        document.getElementById('bgType').value = p.bgType || 'theme';
        if (p.rozet_bg) document.getElementById('rozet_bg').value = p.rozet_bg;
        if (p.rozet_color) document.getElementById('rozet_color').value = p.rozet_color;
        document.getElementById('i_rozet').value = p.rozet;
        setTitleRowInputs('title1', p.title1);
        setTitleRowInputs('title2', p.title2);
        setTitleRowInputs('subtitle', p.subtitle);
        fillBulletsWrap(p.bullets);
        syncTitlesFromUI();
        updatePreview();
        refreshLayerList();
        captureHistoryImmediate();
        document.querySelectorAll('.oim-preset-tile').forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-preset') === key);
        });
        document.querySelectorAll('.theme-swatch').forEach(function (sw) {
            sw.classList.toggle('active', sw.getAttribute('data-theme') === p.theme);
        });
    }

    function renderPresetGallery() {
        var host = document.getElementById('oimPresetGallery');
        if (!host || typeof OIM_PRESET_META === 'undefined') return;
        var html = '';
        Object.keys(OIM_PRESET_META).forEach(function (key) {
            var m = OIM_PRESET_META[key];
            var badge = m.badge === 'pro'
                ? '<span class="oim-preset-tile__badge">PRO</span>'
                : '';
            var icon = m.badge === 'pro' ? 'fa-crown' : 'fa-wand-magic-sparkles';
            html +=
                '<button type="button" class="oim-preset-tile" data-preset="' + key + '">' +
                badge +
                '<span class="oim-preset-tile__thumb" style="background:' + m.grad + '"><i class="fas ' + icon + '"></i></span>' +
                '<span class="oim-preset-tile__label">' + escapeHtml(m.label) + '</span>' +
                '</button>';
        });
        host.innerHTML = html;
        host.querySelectorAll('.oim-preset-tile').forEach(function (tile) {
            tile.addEventListener('click', function () {
                applyOimPreset(tile.getAttribute('data-preset') || '');
            });
        });
    }

    function darkThemeGuess() {
        var th = document.getElementById('i_theme').value;
        if (['theme-11', 'theme-17', 'theme-20', 'theme-23', 'theme-25', 'theme-27', 'theme-29'].indexOf(th) >= 0) {
            return true;
        }
        return false;
    }

    function applyOimTextFx(fx) {
        var color, weight, shadowOn, shadowColor, shadowBlur;
        if (fx === 'gold') {
            color = '#f59e0b'; weight = '800'; shadowOn = true; shadowColor = '#78350f'; shadowBlur = '3';
        } else if (fx === 'neon') {
            color = '#22d3ee'; weight = '800'; shadowOn = true; shadowColor = '#0891b2'; shadowBlur = '9';
        } else if (fx === 'glow') {
            color = '#ffffff'; weight = '800'; shadowOn = true; shadowColor = '#6366f1'; shadowBlur = '8';
        } else if (fx === 'outline') {
            color = '#0f172a'; weight = '800'; shadowOn = true; shadowColor = '#ffffff'; shadowBlur = '2';
        } else {
            color = null; weight = '800'; shadowOn = false; shadowColor = '#000000'; shadowBlur = '2';
        }
        ['title1', 'title2', 'subtitle'].forEach(function (t) {
            document.querySelectorAll('#titlesWrap .title-row[data-target="' + t + '"]').forEach(function (row) {
                var c = row.querySelector('.ti-color');
                var w = row.querySelector('.ti-weight');
                if (c && color) c.value = color;
                if (w && weight) w.value = weight;
            });
        });
        var sc = document.getElementById('titles_shadow');
        if (sc) sc.checked = shadowOn;
        var scc = document.getElementById('titles_shadow_color');
        if (scc) scc.value = shadowColor;
        var scb = document.getElementById('titles_shadow_blur');
        if (scb) scb.value = shadowBlur;
        syncTitlesFromUI();
        updatePreview();
        captureHistoryImmediate();
    }

    function collectFormVals() {
        var o = {};
        OIM_FORM_IDS_LIKE.forEach(function (id) {
            var n = document.getElementById(id);
            if (!n) return;
            if (n.type === 'checkbox') {
                o[id] = n.checked ? '1' : '';
            } else {
                o[id] = String(n.value);
            }
        });
        ['oimWmEnable', 'oimAutosaveDraft'].forEach(function (id) {
            var n = document.getElementById(id);
            if (n && n.type === 'checkbox') o[id] = n.checked ? '1' : '';
        });
        return o;
    }

    function applyFormVals(vals) {
        if (!vals) return;
        OIM_FORM_IDS_LIKE.forEach(function (id) {
            if (!(id in vals)) return;
            var n = document.getElementById(id);
            if (!n) return;
            if (n.type === 'checkbox') {
                n.checked = vals[id] === '1';
            } else {
                n.value = vals[id];
            }
        });
        if ('oimWmEnable' in vals) {
            var w = document.getElementById('oimWmEnable');
            if (w && w.type === 'checkbox') w.checked = vals.oimWmEnable === '1';
        }
        if ('oimAutosaveDraft' in vals) {
            var a = document.getElementById('oimAutosaveDraft');
            if (a && a.type === 'checkbox') a.checked = vals.oimAutosaveDraft === '1';
        }
        syncSafeOverlayVisibility();
    }

    function serializeFullState() {
        var d = document.getElementById('design');
        var hudPrev = document.getElementById('oimResizeHud');
        try {
            if (hudPrev && hudPrev.parentNode) hudPrev.parentNode.removeChild(hudPrev);
            return {
                v: 2,
                designHTML: d ? d.innerHTML : '',
                titlesWrap: document.getElementById('titlesWrap') ? document.getElementById('titlesWrap').innerHTML : '',
                bulletsWrap: document.getElementById('bulletsWrap') ? document.getElementById('bulletsWrap').innerHTML : '',
                imgsListHTML: document.getElementById('imgsList') ? document.getElementById('imgsList').innerHTML : '',
                vals: collectFormVals(),
                wmDataURL: wmStore.dataURL || '',
            };
        } finally {
            ensureResizeHudInDesign();
            positionResizeHud();
        }
    }

    function serializeForStorage() {
        var s = serializeFullState();
        return JSON.stringify(s);
    }

    function applyFullStateSilent(p) {
        if (!p || !document.getElementById('design')) return;
        IGNORE_UNDO_GUARD = true;
        try {
            document.getElementById('design').innerHTML = p.designHTML || '';
            ensureResizeHudInDesign();
            if (document.getElementById('titlesWrap')) {
                document.getElementById('titlesWrap').innerHTML = p.titlesWrap || '';
            }
            if (document.getElementById('bulletsWrap')) {
                document.getElementById('bulletsWrap').innerHTML = p.bulletsWrap || '';
            }
            if (document.getElementById('imgsList')) {
                document.getElementById('imgsList').innerHTML = p.imgsListHTML || '';
            }
            var vals = {};
            Object.assign(vals, p.vals || {});
            if (
                (!(vals.oimCanvasW !== undefined && String(vals.oimCanvasW).trim() !== '') || !(vals.oimCanvasH !== undefined && String(vals.oimCanvasH).trim() !== '')) &&
                (vals.oimExportW !== undefined || vals.oimExportH !== undefined)
            ) {
                if (vals.oimExportW !== undefined && String(vals.oimExportW).trim() !== '') {
                    vals.oimCanvasW = vals.oimExportW;
                }
                if (vals.oimExportH !== undefined && String(vals.oimExportH).trim() !== '') {
                    vals.oimCanvasH = vals.oimExportH;
                }
            }
            applyFormVals(vals);
            if (p.wmDataURL && typeof p.wmDataURL === 'string') {
                setWatermarkFromDataUrl(p.wmDataURL);
            } else if (!p.wmDataURL && wmStore) {
                wmStore.dataURL = '';
                wmStore.img = null;
            }
            reinstateDesignBindings();
            syncTitlesFromUI();
            renderBullets();
            updatePreview();
            refreshLayerList();
            syncDesignDimsFromInputs();
        } finally {
            IGNORE_UNDO_GUARD = false;
        }
    }

    /* —— Sıfırlama (genel + bölüm bölüm) —— */
    var OIM_RESET_SECTION_IDS = {
        appearance: ['i_theme', 'bgType', 'bg1', 'bg2'],
        text: [
            'titles_font', 'titles_shadow_color', 'titles_shadow_blur', 'titles_shadow',
            'i_rozet', 'rozet_color', 'rozet_bg', 'rozet_size', 'rozet_radius', 'rozet_font', 'rozet_weight',
            'rozet_shadow', 'rozet_shadow_color', 'rozet_shadow_blur',
            'bullets_font', 'bullets_weight', 'bullets_size', 'bullets_shadow', 'bullets_shadow_color', 'bullets_shadow_blur',
            'oimCtaText', 'oimCtaColor', 'oimCtaBg', 'oimCtaFont', 'oimCtaRadius', 'oimCtaShow',
        ],
        images: ['i_icon_class', 'i_icon_color', 'i_icon_size'],
        layout: [
            'oimExportFmt', 'oimExportQuality', 'oimCanvasW', 'oimCanvasH', 'oimExportPxScale',
            'oimPngTransparent', 'oimShowSafe', 'oimWmOpacity', 'oimWmScale',
        ],
    };

    function oimApplyDefaultVals(ids) {
        if (!OIM_DEFAULT_STATE || !OIM_DEFAULT_STATE.vals) return;
        var subset = {};
        ids.forEach(function (id) {
            if (id in OIM_DEFAULT_STATE.vals) subset[id] = OIM_DEFAULT_STATE.vals[id];
        });
        applyFormVals(subset);
    }

    function oimToast(msg, kind) {
        var host = document.getElementById('oimToastHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'oimToastHost';
            host.style.cssText =
                'position:fixed;right:16px;bottom:16px;z-index:99999;display:flex;flex-direction:column;gap:8px;max-width:340px;';
            document.body.appendChild(host);
        }
        var el = document.createElement('div');
        var bg = kind === 'error' ? '#dc3545' : (kind === 'warn' ? '#f59e0b' : '#198754');
        el.style.cssText =
            'background:' + bg + ';color:#fff;padding:10px 14px;border-radius:10px;' +
            'box-shadow:0 8px 24px rgba(0,0,0,.25);font-size:.9rem;line-height:1.35;' +
            'opacity:0;transform:translateY(8px);transition:opacity .2s,transform .2s;';
        el.textContent = msg;
        host.appendChild(el);
        requestAnimationFrame(function () {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transform = 'translateY(8px)';
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 250);
        }, 3200);
    }

    function oimResetSection(section) {
        if (!OIM_DEFAULT_STATE) {
            oimToast('Sıfırlama için varsayılan durum yakalanamadı. Sayfayı yenileyin (Ctrl+F5).', 'error');
            return;
        }
        if (section === 'all') {
            applyFullStateSilent(OIM_DEFAULT_STATE);
            captureHistoryImmediate();
            return;
        }
        if (section === 'appearance') {
            oimApplyDefaultVals(OIM_RESET_SECTION_IDS.appearance);
            document.querySelectorAll('.oim-preset-tile').forEach(function (t) {
                t.classList.remove('active');
            });
        } else if (section === 'text') {
            var tw = document.getElementById('titlesWrap');
            var bw = document.getElementById('bulletsWrap');
            if (tw) tw.innerHTML = OIM_DEFAULT_STATE.titlesWrap || '';
            if (bw) bw.innerHTML = OIM_DEFAULT_STATE.bulletsWrap || '';
            oimApplyDefaultVals(OIM_RESET_SECTION_IDS.text);
            if (typeof applyOimTextFx === 'function') applyOimTextFx('none');
            syncTitlesFromUI();
            renderBullets();
        } else if (section === 'images') {
            document.querySelectorAll('#design .prodimg').forEach(function (img) {
                if (img.id === 'prodimg') {
                    img.style.display = 'none';
                    img.src = '';
                } else if (img.parentNode) {
                    img.parentNode.removeChild(img);
                }
            });
            var icon = document.getElementById('icon1');
            if (icon) icon.style.display = 'none';
            var imgsList = document.getElementById('imgsList');
            if (imgsList) imgsList.innerHTML = OIM_DEFAULT_STATE.imgsListHTML || '';
            oimApplyDefaultVals(OIM_RESET_SECTION_IDS.images);
        } else if (section === 'layout') {
            oimApplyDefaultVals(OIM_RESET_SECTION_IDS.layout);
            if (wmStore) {
                wmStore.dataURL = '';
                wmStore.img = null;
            }
            var wmEnable = document.getElementById('oimWmEnable');
            if (wmEnable) wmEnable.checked = OIM_DEFAULT_STATE.vals.oimWmEnable === '1';
        }
        updatePreview();
        refreshLayerList();
        captureHistoryImmediate();
    }



    function captureHistoryImmediate() {
        if (IGNORE_UNDO_GUARD) return;
        try {
            var snap = serializeForStorage();
            if (undoPtr >= 0 && undoHistory[undoPtr] === snap) {
                return;
            }
            undoHistory = undoHistory.slice(0, undoPtr + 1);
            undoHistory.push(snap);
            undoPtr = undoHistory.length - 1;
            while (undoHistory.length > MAX_HIST) {
                undoHistory.shift();
                undoPtr--;
            }
        } catch (e) {
            console.warn(e);
        }
    }

    function scheduleCaptureAfterDrag() {
        if (IGNORE_UNDO_GUARD) return;
        clearTimeout(undoDragTimer);
        undoDragTimer = setTimeout(function () {
            captureHistoryImmediate();
        }, 400);
    }

    function undoOimAct() {
        if (undoPtr <= 0) return;
        undoPtr -= 1;
        try {
            var obj = JSON.parse(undoHistory[undoPtr]);
            applyFullStateSilent(obj);
        } catch (e) {
            console.error(e);
        }
    }

    function redoOimAct() {
        if (undoPtr >= undoHistory.length - 1) return;
        undoPtr += 1;
        try {
            var obj = JSON.parse(undoHistory[undoPtr]);
            applyFullStateSilent(obj);
        } catch (e) {
            console.error(e);
        }
    }

    function ensureResizeHudInDesign() {
        var design = document.getElementById('design');
        if (!design || document.getElementById('oimResizeHud')) return;
        var hud = document.createElement('div');
        hud.id = 'oimResizeHud';
        hud.className = 'oim-resize-hud';
        hud.setAttribute('aria-hidden', 'true');
        hud.innerHTML =
            '<button type="button" class="oim-resize-grip" tabindex="-1" title="Boyut: köşeden sürükle — Shift oran korur · Ctrl ürün görselinde X/Y bağımsız germe (object-fit: fill)"></button>';
        design.appendChild(hud);
    }

    function reinstateDesignBindings() {
        document.querySelectorAll('#design .draggable').forEach(function (node) {
            delete node.dataset.oimDragBound;
            enableDrag(node);
        });
        wireDesignDelegationOnce();
    }

    var designDelegationWired = false;
    function wireDesignDelegationOnce() {
        if (designDelegationWired) return;
        var design = document.getElementById('design');
        if (!design) return;
        designDelegationWired = true;
        design.addEventListener('mousedown', function (ev) {
            if (ev.target && ev.target.classList && ev.target.classList.contains('oim-resize-grip')) {
                var hx = document.getElementById(document.getElementById('layerTarget').value);
                if (hx) {
                    beginOimResizeFromGrip(ev, hx);
                }
                ev.preventDefault();
                ev.stopPropagation();
                return;
            }
            var t = ev.target.closest(
                '.title-item, .rozet-item, #bullets.draggable, img.prodimg, .iconitem, .oim-cta-item'
            );
            if (!t || !t.id) return;
            setSelected(t.id);
        });
        design.addEventListener(
            'touchstart',
            function (ev) {
                if (ev.target && ev.target.classList && ev.target.classList.contains('oim-resize-grip')) {
                    var hy = document.getElementById(document.getElementById('layerTarget').value);
                    if (hy) beginOimResizeFromGrip(ev, hy);
                    ev.preventDefault();
                    return;
                }
                var t = ev.target.closest(
                    '.title-item, .rozet-item, #bullets.draggable, img.prodimg, .iconitem, .oim-cta-item'
                );
                if (!t || !t.id) return;
                setSelected(t.id);
            },
            { passive: false }
        );
    }

    function applyCtaFromControls() {
        var d = document.getElementById('design');
        if (!d) return;
        var showCk = document.getElementById('oimCtaShow');
        d.setAttribute('data-cta-hide', showCk && showCk.checked ? '0' : '1');
        document.querySelectorAll('.oim-cta-item').forEach(function (el) {
            el.textContent = document.getElementById('oimCtaText').value || 'CTA';
            el.style.color = document.getElementById('oimCtaColor').value;
            el.style.background = document.getElementById('oimCtaBg').value;
            el.style.backgroundImage = 'none';
            var mul = parseFloat(String(el.getAttribute('data-oim-scale') || '1'));
            if (!(mul > 0.05)) mul = 1;
            var fz = parseFloat(document.getElementById('oimCtaFont').value || '18') * mul;
            el.style.fontSize = fz + 'px';
            el.style.padding = 12 * mul + 'px ' + 28 * mul + 'px';
            var rad = parseInt(document.getElementById('oimCtaRadius').value, 10);
            el.style.borderRadius = (rad >= 200 ? 999 : Math.max(4, rad)) + 'px';
        });
    }

    function hexToRgb(hex) {
        var h = (hex || '').replace('#', '');
        if (h.length === 3) {
            h = h.split('').map(function (ch) {
                return ch + ch;
            }).join('');
        }
        var n = parseInt(h, 16);
        if (isNaN(n) || h.length !== 6) return { r: 40, g: 50, b: 70 };
        return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
    }

    function relLum(rgb) {
        function f(c) {
            var v = c / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        }
        var R = f(rgb.r);
        var G = f(rgb.g);
        var B = f(rgb.b);
        return 0.2126 * R + 0.7152 * G + 0.0722 * B;
    }

    function contrastRatio(c1, c2) {
        var L1 = relLum(hexToRgb(c1));
        var L2 = relLum(hexToRgb(c2));
        var hi = Math.max(L1, L2);
        var lo = Math.min(L1, L2);
        return (hi + 0.05) / (lo + 0.05);
    }

    function updateContrastHint() {
        var out = document.getElementById('oimContrastHint');
        if (!out) return;
        var row = document.querySelector('#titlesWrap .title-row[data-target="title1"] .ti-color');
        var fh = row ? row.value : '#283458';
        var bgGuess = darkThemeGuess() ? '#0f172a' : '#f8fafc';
        var ratio = contrastRatio(fh, bgGuess);
        if (ratio >= 4.5) {
            out.textContent = 'Başlık / arka plan kontrast tahmini uygun (~' + ratio.toFixed(1) + ':1).';
            out.className = 'small mt-2 mb-0 text-muted';
        } else {
            out.innerHTML =
                '<span class="text-warning-emphasis fw-semibold">Düşük kontrast (~' +
                ratio.toFixed(1) +
                ':1).</span> Koyu tema için daha açık yazı seçin veya zemini güncelleyin.';
            out.className = 'small mt-2 mb-0';
        }
    }

    function syncSafeOverlayVisibility() {
        var cb = document.getElementById('oimShowSafe');
        var ov = document.getElementById('oimSafeOverlay');
        if (!ov) return;
        if (cb && cb.checked) {
            ov.classList.remove('d-none');
            ov.style.display = 'block';
            ov.style.visibility = '';
        } else {
            ov.classList.add('d-none');
            ov.style.display = 'none';
            ov.style.visibility = 'hidden';
        }
    }

    function setWatermarkFromDataUrl(url) {
        wmStore.dataURL = url || '';
        wmStore.img = null;
        if (!wmStore.dataURL) return;
        var im = new Image();
        im.crossOrigin = 'anonymous';
        im.onload = function () {
            wmStore.img = im;
        };
        im.src = wmStore.dataURL;
    }

    function flattenCanvasWithPad(srcCanvas, padHex) {
        if (!srcCanvas || !padHex) return srcCanvas;
        var out = document.createElement('canvas');
        out.width = srcCanvas.width;
        out.height = srcCanvas.height;
        var ctx = out.getContext('2d');
        ctx.fillStyle = padHex;
        ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(srcCanvas, 0, 0);
        return out;
    }

    function finalizeExportBlob(srcCanvas) {
        var transp =
            document.getElementById('oimPngTransparent') &&
            document.getElementById('oimPngTransparent').checked;
        var fmt = document.getElementById('oimExportFmt').value || 'png';
        var qual = parseFloat(document.getElementById('oimExportQuality').value);
        var padHex = transp && fmt === 'png' ? null : '#ffffff';
        if (fmt === 'jpeg') {
            padHex = '#ffffff';
        }
        if (fmt === 'png' && !transp && document.getElementById('bgType').value !== 'solid') {
            padHex = '#ffffff';
        }
        var canvas = flattenCanvasWithPad(srcCanvas, padHex);
        var wmImg = wmStore.img;
        if (wmImg && wmImg.complete && document.getElementById('oimWmEnable') && document.getElementById('oimWmEnable').checked) {
            var ctx2 = canvas.getContext('2d');
            var pct = parseInt(document.getElementById('oimWmScale').value, 10) / 100;
            var mW = Math.min(canvas.width * Math.max(pct, 0.06), canvas.width * 0.42);
            var scaleWM = mW / wmImg.width;
            var mH = wmImg.height * scaleWM;
            var opac = parseFloat(document.getElementById('oimWmOpacity').value);
            ctx2.globalAlpha = isNaN(opac) ? 0.85 : opac;
            ctx2.drawImage(wmImg, canvas.width - mW - 16, canvas.height - mH - 16, mW, mH);
            ctx2.globalAlpha = 1;
        }
        function tryBlob(cv, mime, q) {
            return new Promise(function (resolve, reject) {
                if (cv.toBlob) {
                    cv.toBlob(
                        function (b) {
                            if (b) resolve(b);
                            else reject(new Error('toBlob'));
                        },
                        mime,
                        typeof q === 'number' ? q : undefined
                    );
                } else {
                    reject(new Error('no toBlob'));
                }
            });
        }
        var mime = 'image/png';
        var qArg = undefined;
        if (fmt === 'jpeg') {
            mime = 'image/jpeg';
            qArg = isNaN(qual) ? 0.92 : qual;
        } else if (fmt === 'webp') {
            mime = 'image/webp';
            qArg = isNaN(qual) ? 0.92 : qual;
        } else if (fmt === 'png') {
            mime = 'image/png';
        }
        var ext = fmt === 'jpeg' ? 'jpg' : fmt === 'webp' ? 'webp' : 'png';
        var fileBase = 'siparis_gorseli_' + Date.now();
        return tryBlob(canvas, mime, qArg)
            .then(function (blob) {
                return { blob: blob, ext: ext, fileBase: fileBase };
            })
            .catch(function () {
                var url =
                    mime === 'image/png'
                        ? canvas.toDataURL('image/png')
                        : canvas.toDataURL(mime, qArg);
                return fetch(url)
                    .then(function (r) {
                        return r.blob();
                    })
                    .then(function (blob2) {
                        return { blob: blob2, ext: ext, fileBase: fileBase };
                    });
            });
    }

    function downloadBlob(blob, name) {
        var a = document.createElement('a');
        var u = URL.createObjectURL(blob);
        a.download = name;
        a.href = u;
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(u);
        }, 1500);
    }

    function moveSelectedLayerPx(dx, dy) {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!el || el.getAttribute('data-oim-locked') === '1') return;
        var dw = getCanvasW();
        var dh = getCanvasH();
        var left = parseFloat(String(el.style.left || '').replace('px', ''));
        if (isNaN(left)) left = el.offsetLeft;
        var top = parseFloat(String(el.style.top || '').replace('px', ''));
        if (isNaN(top)) top = el.offsetTop;
        el.style.right = '';
        el.style.bottom = '';
        var nw = Math.max(0, dw - el.offsetWidth);
        var nh = Math.max(0, dh - el.offsetHeight);
        el.style.left = Math.max(0, Math.min(left + dx, nw)) + 'px';
        el.style.top = Math.max(0, Math.min(top + dy, nh)) + 'px';
        positionResizeHud();
    }

    function fitOimDesignPreview() {
        var vp = document.getElementById('oimDesignViewport');
        var clip = document.getElementById('oimDesignClip');
        var scaler = document.getElementById('oimDesignScaler');
        var design = document.getElementById('design');
        if (!vp || !clip || !scaler || !design) return;
        var cw = Math.max(1, design.offsetWidth || getCanvasW());
        var ch = Math.max(1, design.offsetHeight || getCanvasH());
        var pad = 4;
        var avail = Math.max(280, vp.clientWidth - pad);
        var scale = Math.min(1, avail / cw);
        design.style.transform = '';
        scaler.style.width = cw + 'px';
        scaler.style.height = ch + 'px';
        scaler.style.transformOrigin = 'top left';
        scaler.style.transform = 'scale(' + scale + ')';
        clip.style.width = cw * scale + 'px';
        clip.style.height = ch * scale + 'px';
        positionResizeHud();
    }

    function alignSelected(mode) {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!el || el.getAttribute('data-oim-locked') === '1') return;
        var DES_W = getCanvasW();
        var DES_H = getCanvasH();
        var m = 24;
        el.style.right = '';
        el.style.bottom = '';
        var w = el.offsetWidth;
        var h = el.offsetHeight;
        if (mode === 'h') {
            el.style.left = Math.max(0, (DES_W - w) / 2) + 'px';
        } else if (mode === 'v') {
            el.style.top = Math.max(0, (DES_H - h) / 2) + 'px';
        } else if (mode === 'l') {
            el.style.left = m + 'px';
        } else if (mode === 'r') {
            el.style.left = Math.max(m, DES_W - w - m) + 'px';
        } else if (mode === 't') {
            el.style.top = m + 'px';
        } else if (mode === 'b') {
            el.style.top = '';
            el.style.bottom = m + 'px';
        }
        refreshLayerList();
        captureHistoryImmediate();
    }

    function duplicateSelectedLayer() {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!el || id === 'bullets') return;
        if (el.classList.contains('title-item')) {
            var nid = 'title' + Date.now();
            var d = el.cloneNode(true);
            d.id = nid;
            d.removeAttribute('data-oim-drag-bound');
            d.removeAttribute('data-oim-locked');
            d.style.left =
                parseInt(String(el.style.left || '').replace('px', ''), 10) + 12 + 'px';
            d.style.top = parseInt(String(el.style.top || '').replace('px', ''), 10) + 12 + 'px';
            document.getElementById('titlesContainer').appendChild(d);
            var srcRow = document.querySelector('#titlesWrap .title-row[data-target="' + id + '"]');
            if (srcRow) {
                var row = srcRow.cloneNode(true);
                row.setAttribute('data-target', nid);
                document.getElementById('titlesWrap').appendChild(row);
            }
            syncTitlesFromUI();
            enableDrag(d);
            setSelected(nid);
        } else if (el.classList.contains('rozet-item')) {
            rozetCounter += 1;
            var r = el.cloneNode(true);
            r.id = 'rozet' + rozetCounter;
            r.removeAttribute('data-oim-drag-bound');
            r.style.left = parseInt(String(el.style.left || '').replace('px', ''), 10) + 10 + 'px';
            r.style.top = parseInt(String(el.style.top || '').replace('px', ''), 10) + 6 + 'px';
            document.getElementById('design').appendChild(r);
            applyRozetStyles(r);
            enableDrag(r);
            setSelected(r.id);
        } else if (el.classList.contains('prodimg')) {
            var im = el.cloneNode(true);
            im.id = 'prodimg' + (++imgCounter);
            im.removeAttribute('data-oim-drag-bound');
            im.style.left = parseInt(String(el.style.left || '').replace('px', ''), 10) + 16 + 'px';
            im.style.top = parseInt(String(el.style.top || '').replace('px', ''), 10) + 8 + 'px';
            document.getElementById('design').appendChild(im);
            enableDrag(im);
            setSelected(im.id);
        } else if (el.classList.contains('iconitem')) {
            var ic = el.cloneNode(true);
            ic.id = 'icon' + (++iconCounter);
            ic.removeAttribute('data-oim-drag-bound');
            ic.style.left = parseInt(String(el.style.left || '').replace('px', ''), 10) + 8 + 'px';
            ic.style.top = parseInt(String(el.style.top || '').replace('px', ''), 10) + 8 + 'px';
            document.getElementById('design').appendChild(ic);
            enableDrag(ic);
            setSelected(ic.id);
        } else if (el.classList.contains('oim-cta-item')) {
            ctaCounter += 1;
            var cta = el.cloneNode(true);
            cta.id = 'cta' + ctaCounter;
            cta.removeAttribute('data-oim-drag-bound');
            cta.style.right = '';
            cta.style.left = parseInt(String(el.style.left || '').replace('px', ''), 10) + 12 + 'px';
            var ty = parseInt(String(el.style.top || '').replace('px', ''), 10);
            if (!isNaN(ty)) {
                cta.style.top = ty + 10 + 'px';
            }
            document.getElementById('design').appendChild(cta);
            enableDrag(cta);
            setSelected(cta.id);
            updatePreview();
        }
        refreshLayerList();
        captureHistoryImmediate();
    }

    function toggleSelectedLock() {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!el || id === 'bullets') return;
        var on = el.getAttribute('data-oim-locked') === '1';
        el.setAttribute('data-oim-locked', on ? '0' : '1');
        refreshLayerList();
        updateLayerInfo();
        captureHistoryImmediate();
        positionResizeHud();
    }

    function applyTypoPack(key) {
        var map =
            key === 'compact'
                ? { title1: 34, title2: 26, subtitle: 18, bullets: 16 }
                : key === 'dramatic'
                    ? { title1: 52, title2: 42, subtitle: 32, bullets: 22 }
                    : { title1: 44, title2: 34, subtitle: 26, bullets: 19 };
        [['title1', map.title1], ['title2', map.title2], ['subtitle', map.subtitle]].forEach(function (pair) {
            document.querySelectorAll('#titlesWrap .title-row[data-target="' + pair[0] + '"] .ti-size').forEach(function (inp) {
                inp.value = pair[1];
            });
        });
        var b = document.getElementById('bullets_size');
        if (b) b.value = map.bullets;
        syncTitlesFromUI();
        updatePreview();
        captureHistoryImmediate();
    }

    function ensureWatermarkLoaded() {
        return new Promise(function (resolve) {
            if (!document.getElementById('oimWmEnable').checked || !wmStore.dataURL) {
                resolve();
                return;
            }
            var t0 = Date.now();
            (function poll() {
                if (wmStore.img && wmStore.img.complete) resolve();
                else if (Date.now() - t0 > 4500) resolve();
                else setTimeout(poll, 48);
            })();
        });
    }

    function hydrateOrderPayload(ob) {
        if (!ob) return;
        setTitleRowInputs('title1', {
            text: ob.titleMain || 'Ürün',
            color: '#0f172a',
            weight: '800',
            size: '44',
        });
        setTitleRowInputs('title2', {
            text: 'Sipariş #' + ob.order_id,
            color: '#6366f1',
            weight: '800',
            size: '32',
        });
        setTitleRowInputs('subtitle', {
            text: String(ob.priceLine || ob.productLine || '').slice(0, 140),
            color: '#475569',
            weight: '600',
            size: '24',
        });
        fillBulletsWrap([
            '• Ürün: ' + String(ob.productLine || '').slice(0, 100),
            '• ' + String(ob.priceLine || ''),
            '• Sipariş #' + ob.order_id,
        ]);
        document.getElementById('i_rozet').value = 'Sip #' + ob.order_id;
        syncTitlesFromUI();
        renderBullets();
        updatePreview();
        refreshLayerList();
        captureHistoryImmediate();
    }

    function preloadImageUrl(url) {
        return new Promise(function (resolve) {
            if (!url) {
                resolve(false);
                return;
            }
            var im = new Image();
            im.crossOrigin = 'anonymous';
            im.onload = function () {
                resolve(true);
            };
            im.onerror = function () {
                resolve(false);
            };
            im.src = url;
        });
    }

    function setPrimaryProductImage(url) {
        return preloadImageUrl(url).then(function (ok) {
            if (!ok || !url) return false;
            var img = document.getElementById('prodimg');
            if (!img) return false;
            img.src = url;
            img.style.display = 'block';
            img.style.left = img.style.left || '500px';
            img.style.top = img.style.top || '120px';
            img.style.maxWidth = img.style.maxWidth || '300px';
            img.style.maxHeight = img.style.maxHeight || '240px';
            if (!img.getAttribute('data-oim-drag-bound')) {
                enableDrag(img);
            }
            updatePreview();
            refreshLayerList();
            return true;
        });
    }

    function exportCurrentDesign() {
        var design = document.getElementById('design');
        var scaler = document.getElementById('oimDesignScaler');
        var clip = document.getElementById('oimDesignClip');
        var bgTransparentEl = document.getElementById('oimPngTransparent');

        // Fabrika modunda stüdyo tuvali gizli (d-none) olabilir → 0 boyut → html2canvas
        // 0x0 tuval üretir. Export süresince stüdyoyu ekran dışında görünür kıl.
        var studioWrap = document.getElementById('oimStudioWrap');
        var studioWasHidden = studioWrap && studioWrap.classList.contains('d-none');
        var studioPrevCss = studioWrap ? studioWrap.getAttribute('style') : null;
        if (studioWasHidden) {
            studioWrap.classList.remove('d-none');
            studioWrap.style.position = 'fixed';
            studioWrap.style.left = '-100000px';
            studioWrap.style.top = '0';
            studioWrap.style.width = Math.max(320, getCanvasW() + 80) + 'px';
            studioWrap.style.zIndex = '-1';
            studioWrap.style.opacity = '1';
            studioWrap.style.pointerEvents = 'none';
        }

        var cw = Math.max(1, design.offsetWidth || getCanvasW());
        var ch = Math.max(1, design.offsetHeight || getCanvasH());
        var hud = document.getElementById('oimResizeHud');
        var hudPrevDisp = hud ? hud.style.display : '';
        if (hud) hud.style.display = 'none';
        var prevScale = scaler ? scaler.style.transform : '';
        var prevClipW = clip ? clip.style.width : '';
        var prevClipH = clip ? clip.style.height : '';
        if (scaler) scaler.style.transform = 'none';
        if (clip) {
            clip.style.width = cw + 'px';
            clip.style.height = ch + 'px';
        }
        function restoreCanvasView() {
            if (hud) hud.style.display = hudPrevDisp || '';
            if (scaler) scaler.style.transform = prevScale;
            if (clip) {
                clip.style.width = prevClipW;
                clip.style.height = prevClipH;
            }
            if (studioWasHidden && studioWrap) {
                if (studioPrevCss === null) {
                    studioWrap.removeAttribute('style');
                } else {
                    studioWrap.setAttribute('style', studioPrevCss);
                }
                studioWrap.classList.add('d-none');
            }
            fitOimDesignPreview();
            positionResizeHud();
        }
        var bgTransparent = bgTransparentEl && bgTransparentEl.checked;
        var bgCanvas = bgTransparent ? 'rgba(0,0,0,0)' : '#ffffff';
        var pxSc = parseInt(String(document.getElementById('oimExportPxScale').value || '1'), 10);
        if (pxSc !== 2) pxSc = 1;

        function h2cRun(flatten) {
            var opts = {
                backgroundColor: bgCanvas,
                scale: pxSc,
                useCORS: true,
                logging: false,
            };
            if (flatten) {
                opts.onclone = function (clonedDoc) {
                    oimFlattenGradients(clonedDoc);
                };
            }
            return html2canvas(design, opts);
        }

        return ensureWatermarkLoaded()
            .then(function () {
                return h2cRun(false).catch(function (err) {
                    // html2canvas gradyan hatası (addColorStop non-finite) için güvenlik ağı:
                    // TÜM gradyanları düz renge indirip tekrar dene — garanti çözüm.
                    if (err && /addColorStop|non-finite/i.test(String(err.message || err))) {
                        if (typeof oimFactoryLog === 'function') {
                            oimFactoryLog('Gradyan uyumsuzluğu — düz arka planla yeniden deneniyor…');
                        }
                        return h2cRun(true);
                    }
                    throw err;
                });
            })
            .then(function (canvas) {
                return finalizeExportBlob(canvas);
            })
            .then(function (pack) {
                restoreCanvasView();
                return pack;
            })
            .catch(function (err) {
                restoreCanvasView();
                throw err;
            });
    }

    function oimSplitTopLevel(s) {
        var out = [];
        var depth = 0;
        var cur = '';
        for (var i = 0; i < s.length; i++) {
            var c = s[i];
            if (c === '(') depth++;
            else if (c === ')') depth--;
            if (c === ',' && depth === 0) {
                out.push(cur);
                cur = '';
            } else {
                cur += c;
            }
        }
        if (cur.trim() !== '') out.push(cur);
        return out;
    }

    function oimFirstColor(s) {
        var m = s.match(/rgba?\([^)]*\)|#[0-9a-fA-F]{3,8}/);
        return m ? m[0] : null;
    }

    function oimFlattenGradients(clonedDoc) {
        try {
            var root = clonedDoc.getElementById('design');
            if (!root) return;
            var view = clonedDoc.defaultView || window;
            var els = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));
            els.forEach(function (el) {
                var cs;
                try {
                    cs = view.getComputedStyle(el);
                } catch (e) {
                    return;
                }
                var bi = cs.backgroundImage || '';
                if (!bi || bi.indexOf('gradient') < 0) return;
                var bc = cs.backgroundColor || '';
                var hasSolid = bc && bc !== 'rgba(0, 0, 0, 0)' && bc !== 'transparent';
                if (!hasSolid) {
                    var fc = oimFirstColor(bi);
                    if (fc) el.style.backgroundColor = fc;
                }
                el.style.backgroundImage = 'none';
                // background-clip:text + transparent dolgu varsa metni görünür kıl
                if ((cs.webkitTextFillColor === 'rgba(0, 0, 0, 0)' ||
                     cs.webkitTextFillColor === 'transparent') && cs.color) {
                    el.style.webkitTextFillColor = cs.color;
                    el.style.color = cs.color;
                }
            });
        } catch (e) {
            /* sanitizasyon başarısız olsa da render denensin */
        }
    }

    var capInputTimer = null;
    function scheduleDraftInputCapture() {
        clearTimeout(capInputTimer);
        capInputTimer = setTimeout(captureHistoryImmediate, 1200);
    }

    function applyRozetStyles(el) {
        if (!el) return;
        var mul = parseFloat(String(el.getAttribute('data-oim-scale') || '1'));
        if (!(mul > 0.05)) mul = 1;
    el.innerText = document.getElementById('i_rozet').value;
        el.style.backgroundColor = document.getElementById('rozet_bg').value;
        el.style.color = document.getElementById('rozet_color').value;
        el.style.fontSize = parseFloat(document.getElementById('rozet_size').value || '16') * mul + 'px';
        el.style.borderRadius = parseFloat(document.getElementById('rozet_radius').value || '40') * mul + 'px';
        el.style.fontFamily = document.getElementById('rozet_font').value;
        el.style.fontWeight = document.getElementById('rozet_weight').value;
        el.style.padding = 10 * mul + 'px ' + 16 * mul + 'px';
        el.style.textShadow = applyTextShadow(
            document.getElementById('rozet_shadow').checked,
            document.getElementById('rozet_shadow_color').value,
            document.getElementById('rozet_shadow_blur').value
        );
    }

    /** XSS-safe madde satırları */
    function renderBullets() {
    var bs = document.querySelectorAll('.bullet-item');
        var bulletsEl = document.getElementById('bullets');
        bulletsEl.textContent = '';
        bs.forEach(function (inp) {
            var t = (inp.value || '').trim();
            if (!t) return;
            var line = document.createElement('div');
            line.textContent = t;
            bulletsEl.appendChild(line);
        });
    bulletsEl.style.fontFamily = document.getElementById('bullets_font').value;
    bulletsEl.style.fontWeight = document.getElementById('bullets_weight').value;
        bulletsEl.style.fontSize = document.getElementById('bullets_size').value + 'px';
        bulletsEl.style.textShadow = applyTextShadow(
            document.getElementById('bullets_shadow').checked,
            document.getElementById('bullets_shadow_color').value,
            document.getElementById('bullets_shadow_blur').value
        );
    }

    function updatePreview() {
        document.querySelectorAll('.rozet-item').forEach(applyRozetStyles);
        renderBullets();
        applyCtaFromControls();
    var d = document.getElementById('design');
        OIM_THEME_CLASSES.forEach(function (cls) {
            d.classList.remove(cls);
        });
        var th = document.getElementById('i_theme').value;
        d.classList.add(th);
        document.querySelectorAll('.theme-swatch').forEach(function (sw) {
            sw.classList.toggle('active', sw.getAttribute('data-theme') === th);
        });
        var c1 = document.getElementById('bg1').value;
        var c2 = document.getElementById('bg2').value;
        var bt = document.getElementById('bgType').value;
        if (bt === 'grad') {
            d.style.background = 'linear-gradient(135deg,' + c1 + ' 0%,' + c2 + ' 100%)';
        } else if (bt === 'solid') {
            d.style.background = c1;
        } else {
            d.style.background = '';
        }
        var tf = document.getElementById('titles_font').value;
        var tsh = document.getElementById('titles_shadow').checked;
        var tshc = document.getElementById('titles_shadow_color').value;
        var tshb = document.getElementById('titles_shadow_blur').value;
        document.querySelectorAll('#titlesContainer .title-item').forEach(function (t) {
            t.style.fontFamily = tf;
            t.style.textShadow = applyTextShadow(tsh, tshc, tshb);
        });
        updateContrastHint();
        syncSafeOverlayVisibility();
        positionResizeHud();
    }

    function syncLayerSelect() {
        var sel = document.getElementById('layerTarget');
        var prev = sel.value;
        var opts = [];
        document.querySelectorAll('.rozet-item').forEach(function (el) {
            if (el.style.display === 'none') return;
            opts.push({ v: el.id, t: 'Rozet (' + el.id + ')' });
        });
        document.querySelectorAll('#titlesContainer .title-item').forEach(function (el) {
            if (el.style.display === 'none') return;
            opts.push({ v: el.id, t: 'Başlık: ' + el.id });
        });
        var b = document.getElementById('bullets');
        if (b && b.style.display !== 'none') {
            opts.push({ v: 'bullets', t: 'Madde listesi' });
        }
        document.querySelectorAll('img.prodimg').forEach(function (el) {
            if (el.style.display === 'none') return;
            if (!el.getAttribute('src')) return;
            opts.push({ v: el.id, t: 'Görsel: ' + el.id });
        });
        document.querySelectorAll('.iconitem').forEach(function (el) {
            if (el.id === 'icon1' && el.style.display === 'none') return;
            if (el.style.display === 'none') return;
            opts.push({ v: el.id, t: 'İkon: ' + el.id });
        });
        document.querySelectorAll('.oim-cta-item').forEach(function (el) {
            if (el.style.display === 'none') return;
            opts.push({ v: el.id, t: 'CTA: ' + el.id });
        });
        sel.innerHTML = '';
        opts.forEach(function (o) {
            var op = document.createElement('option');
            op.value = o.v;
            op.textContent = o.t;
            sel.appendChild(op);
        });
        var ok = false;
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === prev) {
                ok = true;
                break;
            }
        }
        if (ok) sel.value = prev;
        else if (sel.options.length) sel.value = sel.options[0].value;
        document.querySelectorAll('#design .selected').forEach(function (n) {
            n.classList.remove('selected');
        });
        var cur = document.getElementById(sel.value);
        if (cur) cur.classList.add('selected');
        updateLayerInfo();
        updateSizeSliderForSelection();
        positionResizeHud();
    }

    function refreshLayerList() {
        var list = document.getElementById('layerList');
        list.innerHTML = '';
        function row(html) {
            var w = document.createElement('div');
            w.className = 'mb-1 d-flex flex-wrap align-items-center';
            w.style.gap = '6px';
            w.innerHTML = html;
            list.appendChild(w);
        }
        document.querySelectorAll('.rozet-item').forEach(function (el) {
            var id = el.id;
            if (el.style.display === 'none') return;
            row('<span class="badge text-bg-warning text-dark">' + escapeHtml(id) + '</span><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-secondary selL py-0 px-2">Seç</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-warning togL py-0 px-2">Gizle</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-danger delL py-0 px-2">Sil</button>');
        });
        document.querySelectorAll('#titlesContainer .title-item').forEach(function (el) {
            var id = el.id;
            if (el.style.display === 'none') return;
            row('<span class="badge text-bg-light text-dark border">' + escapeHtml(id) + '</span><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-secondary selL py-0 px-2">Seç</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-warning togL py-0 px-2">Gizle</button>');
        });
        var bel = document.getElementById('bullets');
        if (bel && bel.style.display !== 'none') {
            row('<span class="badge text-bg-info">' + escapeHtml('bullets') + '</span><button type="button" data-id="bullets" class="btn btn-sm btn-outline-secondary selL py-0 px-2">Seç</button><button type="button" data-id="bullets" class="btn btn-sm btn-outline-warning togL py-0 px-2">Gizle</button>');
        }
        document.querySelectorAll('img.prodimg').forEach(function (el) {
            var id = el.id;
            if (el.style.display === 'none') return;
            if (!el.getAttribute('src')) return;
            row('<span class="badge text-bg-primary">' + escapeHtml(id) + '</span><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-secondary selL py-0 px-2">Seç</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-warning togL py-0 px-2">Gizle</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-danger delL py-0 px-2">Sil</button>');
        });
        document.querySelectorAll('.iconitem').forEach(function (el) {
            var id = el.id;
            if (id === 'icon1' && el.style.display === 'none') return;
            if (el.style.display === 'none') return;
            row('<span class="badge text-bg-dark">' + escapeHtml(id) + '</span><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-secondary selL py-0 px-2">Seç</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-warning togL py-0 px-2">Gizle</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-danger delL py-0 px-2">Sil</button>');
        });
        document.querySelectorAll('.oim-cta-item').forEach(function (el) {
            var id = el.id;
            if (el.style.display === 'none') return;
            row('<span class="badge text-bg-secondary">' + escapeHtml(id) + '</span><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-secondary selL py-0 px-2">Seç</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-warning togL py-0 px-2">Gizle</button><button type="button" data-id="' + escapeHtml(id) + '" class="btn btn-sm btn-outline-danger delL py-0 px-2">Sil</button>');
        });
        syncLayerSelect();
    }

    function setSelected(id) {
        syncLayerSelect();
        var tg = document.getElementById('layerTarget');
        var found = false;
        for (var j = 0; j < tg.options.length; j++) {
            if (tg.options[j].value === id) {
                found = true;
                break;
            }
        }
        if (found) tg.value = id;
        document.querySelectorAll('#design .selected').forEach(function (n) {
            n.classList.remove('selected');
        });
        var el = document.getElementById(id);
        if (el) el.classList.add('selected');
        updateLayerInfo();
        updateSizeSliderForSelection();
        positionResizeHud();
    }

    function z(el) {
        var zi = parseInt(el.style.zIndex || '0', 10);
        return isNaN(zi) ? 0 : zi;
    }

    function updateLayerInfo() {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        var badge = document.getElementById('layerInfo');
        if (!el) {
            badge.textContent = 'Seçili: —';
            return;
        }
        var vis = el.style.display === 'none' ? 'Gizli' : 'Görünür';
        badge.textContent = 'Seçili: ' + id + ' | ' + vis + ' | z:' + z(el);
    }

    function updateSizeSliderForSelection() {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        var slider = document.getElementById('sizeSlider');
        if (!el) {
            slider.disabled = true;
            return;
        }
        if (el.classList.contains('prodimg')) {
            var w =
                el.getAttribute('data-oim-img-stretch') === '1'
                    ? Math.max(40, el.offsetWidth)
                    : parseInt((el.style.maxWidth || '300').replace('px', ''), 10);
            slider.min = 40;
            slider.max = 800;
            slider.value = isNaN(w) ? 300 : w;
            slider.disabled = false;
        } else if (el.classList.contains('iconitem')) {
            var fs = parseInt((el.style.fontSize || '48').replace('px', ''), 10);
            slider.min = 16;
            slider.max = 160;
            slider.value = isNaN(fs) ? 48 : fs;
            slider.disabled = false;
        } else {
            slider.disabled = true;
        }
    }

    function oimReadProdimgBox(el) {
        if (!el || !el.classList.contains('prodimg')) {
            return { mw: 300, mh: 240 };
        }
        if (el.getAttribute('data-oim-img-stretch') === '1') {
            return {
                mw: Math.max(24, el.offsetWidth || 300),
                mh: Math.max(24, el.offsetHeight || 240),
            };
        }
        var mw = parseInt(String(el.style.maxWidth || '300').replace(/px/, ''), 10) || 300;
        var mh =
            parseInt(String(el.style.maxHeight || '').replace(/px/, ''), 10) ||
            Math.round((mw * 240) / 300);
        return { mw: mw, mh: mh };
    }

    function oimSetProdimgStretchBox(el, wPx, hPx) {
        el.setAttribute('data-oim-img-stretch', '1');
        el.style.maxWidth = 'none';
        el.style.maxHeight = 'none';
        el.style.width = Math.round(wPx) + 'px';
        el.style.height = Math.round(hPx) + 'px';
        el.style.objectFit = 'fill';
    }

    function oimSetProdimgContainBox(el, mwPx, mhPx) {
        el.removeAttribute('data-oim-img-stretch');
        el.style.width = '';
        el.style.height = '';
        el.style.objectFit = '';
        el.style.maxWidth = Math.round(mwPx) + 'px';
        el.style.maxHeight = Math.round(mhPx) + 'px';
    }

    function positionResizeHud() {
        var hud = document.getElementById('oimResizeHud');
        if (!hud) return;
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!id || id === 'bullets' || !el || el.getAttribute('data-oim-locked') === '1') {
            hud.style.display = 'none';
            return;
        }
        var ok =
            el.classList.contains('prodimg') ||
            el.classList.contains('iconitem') ||
            el.classList.contains('title-item') ||
            el.classList.contains('rozet-item') ||
            el.classList.contains('oim-cta-item');
        if (!ok) {
            hud.style.display = 'none';
            return;
        }
        hud.style.display = 'block';
        var brX = el.offsetLeft + el.offsetWidth;
        var brY = el.offsetTop + el.offsetHeight;
        hud.style.left = brX + 'px';
        hud.style.top = brY + 'px';
    }

    function oimPreserveAspectModifiers(shiftDown, ctrlDown) {
        return shiftDown ? true : !ctrlDown;
    }

    function beginOimResizeFromGrip(ev, el) {
        if (!ev || !el || el.getAttribute('data-oim-locked') === '1') return;
        var preserveAspect = oimPreserveAspectModifiers(!!ev.shiftKey, !!ev.ctrlKey);

        function designMetrics() {
            var d = document.getElementById('design');
            var r = d.getBoundingClientRect();
            var s = d.offsetWidth > 0 ? r.width / d.offsetWidth : 1;
            return { d: d, r: r, s: isNaN(s) || !s ? 1 : s };
        }
        function ptOf(evMove) {
            var m = designMetrics();
            var e = evMove.touches ? evMove.touches[0] : evMove;
            return {
                x: (e.clientX - m.r.left) / m.s,
                y: (e.clientY - m.r.top) / m.s,
            };
        }
        function clamp(n, lo, hi) {
            return Math.max(lo, Math.min(hi, n));
        }

        var p0 = ptOf(ev);
        var left =
            parseFloat(String(el.style.left || '').replace(/px/, '').trim()) ||
            el.offsetLeft;
        var top = parseFloat(String(el.style.top || '').replace(/px/, '').trim()) || el.offsetTop;
        var sw0 = Math.max(12, el.offsetWidth);
        var sh0 = Math.max(12, el.offsetHeight);

        var prod0 = el.classList.contains('prodimg') ? oimReadProdimgBox(el) : null;
        var startMw = prod0 ? prod0.mw : 300;
        var startMh = prod0 ? prod0.mh : 240;

        var titleRow =
            el.classList.contains('title-item')
                ? document.querySelector('#titlesWrap .title-row[data-target="' + el.id + '"] .ti-size')
                : null;
        var titleFs0 = titleRow ? parseFloat(String(titleRow.value || '46'), 10) || 46 : 46;

        var iconFs0 =
            parseFloat(String(el.style.fontSize || '48').replace(/px/, '')) || 48;

        var scaleMul0 =
            parseFloat(String(el.getAttribute('data-oim-scale') || '1'), 10) || 1;

        function move(ev2) {
            var p = ptOf(ev2);
            var dw = getCanvasW();
            var dh = getCanvasH();
            var maxWRight = Math.max(24, dw - left);
            var maxHBottom = Math.max(24, dh - top);
            var nw = clamp(sw0 + (p.x - p0.x), 24, maxWRight);
            var nh = clamp(sh0 + (p.y - p0.y), 24, maxHBottom);
            var sx = nw / sw0;
            var sy = nh / sh0;

            if (el.classList.contains('prodimg')) {
                var mwN;
                var mhN;
                if (preserveAspect) {
                    var sfp = Math.min(sx, sy);
                    mwN = Math.round(clamp(startMw * sfp, 48, dw + 600));
                    mhN = Math.round(clamp(startMh * sfp, 48, dh + 600));
                    oimSetProdimgContainBox(el, mwN, mhN);
                } else {
                    mwN = Math.round(clamp(startMw * sx, 48, dw + 600));
                    mhN = Math.round(clamp(startMh * sy, 48, dh + 600));
                    oimSetProdimgStretchBox(el, mwN, mhN);
                }
            } else if (el.classList.contains('iconitem')) {
                var sfi = preserveAspect ? Math.min(sx, sy) : sx;
                el.style.fontSize = clamp(iconFs0 * sfi, 12, 220) + 'px';
            } else if (el.classList.contains('title-item') && titleRow) {
                var sft = preserveAspect ? Math.min(sx, sy) : sx;
                titleRow.value = String(Math.round(clamp(titleFs0 * sft, 12, 200)));
                syncTitlesFromUI();
            } else if (el.classList.contains('rozet-item')) {
                var sfr = preserveAspect ? Math.min(sx, sy) : Math.min(Math.max(sx, sy), (sx + sy) / 2);
                el.setAttribute('data-oim-scale', clamp(scaleMul0 * sfr, 0.35, 3.5).toFixed(3));
                applyRozetStyles(el);
            } else if (el.classList.contains('oim-cta-item')) {
                var sfc = preserveAspect ? Math.min(sx, sy) : Math.min(Math.max(sx, sy), (sx + sy) / 2);
                el.setAttribute('data-oim-scale', clamp(scaleMul0 * sfc, 0.35, 3.5).toFixed(3));
                applyCtaFromControls();
            }

            positionResizeHud();
            if (ev2.preventDefault) ev2.preventDefault();
        }

        function up() {
            document.removeEventListener('mousemove', move);
            document.removeEventListener('touchmove', move);
            document.removeEventListener('mouseup', up);
            document.removeEventListener('touchend', up);
            updateSizeSliderForSelection();
            scheduleCaptureAfterDrag();
        }

        document.addEventListener('mousemove', move);
        document.addEventListener('touchmove', move, { passive: false });
        document.addEventListener('mouseup', up);
        document.addEventListener('touchend', up);
    }

    function enableDrag(el) {
        if (!el || el.dataset.oimDragBound === '1') return;
        el.dataset.oimDragBound = '1';
        var pos = null;
        function designMetrics() {
            var d = document.getElementById('design');
            var r = d.getBoundingClientRect();
            var s = d.offsetWidth > 0 ? r.width / d.offsetWidth : 1;
            return { d: d, r: r, s: isNaN(s) || !s ? 1 : s };
        }
        function pointInDesign(ev) {
            var m = designMetrics();
            var e = ev.touches ? ev.touches[0] : ev;
            return {
                x: (e.clientX - m.r.left) / m.s,
                y: (e.clientY - m.r.top) / m.s,
            };
        }
        el.addEventListener('mousedown', start);
        el.addEventListener('touchstart', start, { passive: false });
        function start(ev) {
            if (el.getAttribute('data-oim-locked') === '1') {
                return;
            }
            ev.preventDefault();
            var pt = pointInDesign(ev);
            var left = parseFloat(String(el.style.left || '').replace('px', '')) || el.offsetLeft;
            var top = parseFloat(String(el.style.top || '').replace('px', '')) || el.offsetTop;
            pos = { ox: pt.x - left, oy: pt.y - top };
            el.style.right = '';
            el.style.bottom = '';
            document.addEventListener('mousemove', move);
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('mouseup', stop);
            document.addEventListener('touchend', stop);
        }
        function move(ev) {
            var pt = pointInDesign(ev);
            var x = pt.x - pos.ox;
            var y = pt.y - pos.oy;
            var dEl = document.getElementById('design');
            var dw = dEl.offsetWidth;
            var dh = dEl.offsetHeight;
            x = Math.max(0, Math.min(x, dw - el.offsetWidth));
            y = Math.max(0, Math.min(y, dh - el.offsetHeight));
            el.style.left = x + 'px';
            el.style.top = y + 'px';
            if (ev.preventDefault) ev.preventDefault();
        }
        function stop() {
            document.removeEventListener('mousemove', move);
            document.removeEventListener('touchmove', move);
            document.removeEventListener('mouseup', stop);
            document.removeEventListener('touchend', stop);
            scheduleCaptureAfterDrag();
        }
    }

    function goStep(n) {
        OIM_STEP = Math.max(1, Math.min(OIM_MAX, n));
        document.querySelectorAll('.oim-step').forEach(function (b) {
            var sn = parseInt(b.getAttribute('data-step'), 10);
            var on = sn === OIM_STEP;
            b.classList.toggle('active', on);
            b.classList.toggle('done', sn < OIM_STEP);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.oim-panel').forEach(function (p) {
            p.classList.toggle('active', parseInt(p.getAttribute('data-step'), 10) === OIM_STEP);
        });
        document.getElementById('oimPrev').disabled = OIM_STEP <= 1;
        var nx = document.getElementById('oimNext');
        if (OIM_STEP >= OIM_MAX) {
            nx.className = 'btn btn-outline-secondary ms-auto fw-semibold';
            nx.innerHTML = 'Başa dön <i class="fas fa-redo ms-1"></i>';
        } else {
            nx.className = 'btn btn-light text-dark fw-semibold ms-auto';
            nx.innerHTML = 'İleri <i class="fas fa-arrow-right ms-1"></i>';
        }
    }

    /* Basit adım geçişi: tek seferlik listener yerine delegated */
    document.getElementById('oimStepper').addEventListener('click', function (e) {
        var btn = e.target.closest('.oim-step');
        if (!btn) return;
        var s = parseInt(btn.getAttribute('data-step'), 10);
        if (!isNaN(s)) goStep(s);
    });

    document.getElementById('oimPrev').addEventListener('click', function () {
        goStep(OIM_STEP - 1);
    });

    document.getElementById('oimNext').addEventListener('click', function () {
        if (OIM_STEP >= OIM_MAX) {
            goStep(1);
        } else {
            goStep(OIM_STEP + 1);
        }
    });

    ['i_rozet', 'rozet_bg', 'rozet_color', 'rozet_size', 'rozet_radius', 'rozet_font', 'rozet_weight', 'rozet_shadow', 'rozet_shadow_color', 'rozet_shadow_blur', 'i_theme', 'bg1', 'bg2', 'bgType', 'titles_font', 'titles_shadow', 'titles_shadow_color', 'titles_shadow_blur', 'bullets_font', 'bullets_weight', 'bullets_size', 'bullets_shadow', 'bullets_shadow_color', 'bullets_shadow_blur'].forEach(function (id) {
        var node = document.getElementById(id);
        if (!node) return;
        node.addEventListener('input', updatePreview);
        node.addEventListener('change', updatePreview);
    });

    document.querySelectorAll('.theme-swatch').forEach(function (sw) {
        sw.addEventListener('click', function () {
            document.getElementById('i_theme').value = sw.getAttribute('data-theme');
            updatePreview();
        });
    });

    document.querySelectorAll('.oim-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyOimPreset(btn.getAttribute('data-preset') || '');
        });
    });
    renderPresetGallery();

    function syncTitlesFromUI() {
        document.querySelectorAll('#titlesWrap .title-row').forEach(function (row) {
            var target = row.getAttribute('data-target');
            var el = document.getElementById(target);
            if (!el) return;
            el.innerText = row.querySelector('.ti-text').value;
            el.style.color = row.querySelector('.ti-color').value;
            el.style.fontSize = row.querySelector('.ti-size').value + 'px';
            el.style.fontWeight = row.querySelector('.ti-weight').value;
        });
    }

    document.getElementById('titlesWrap').addEventListener('input', function () {
        syncTitlesFromUI();
    });
    document.getElementById('titlesWrap').addEventListener('change', function () {
        syncTitlesFromUI();
    });

    document.getElementById('addTitle').addEventListener('click', function () {
        var idx = Date.now();
        var id = 'title' + idx;
        var d = document.createElement('div');
        d.className = 'h3 draggable title-item';
        d.id = id;
        d.setAttribute('data-idx', String(idx));
        d.style.left = '24px';
        d.style.top = '260px';
        d.innerText = 'Yeni başlık';
        document.getElementById('titlesContainer').appendChild(d);
        enableDrag(d);
        setSelected(id);
        var row = document.createElement('div');
        row.className = 'input-group mb-2 title-row';
        row.setAttribute('data-target', id);
        row.innerHTML = '<input type="text" class="form-control ti-text" value="Yeni başlık"><div class="input-group-append"><input type="color" class="form-control ti-color" value="#283458" style="width:56px"><select class="form-control ti-weight" style="width:88px"><option value="700">Bold</option><option value="600" selected>Semibold</option><option value="400">Normal</option></select><input type="number" class="form-control ti-size" value="28" min="16" max="96" style="width:72px"><button class="btn btn-outline-secondary btn-sm rm-title" type="button">−</button></div>';
        document.getElementById('titlesWrap').appendChild(row);
        syncTitlesFromUI();
        updatePreview();
            refreshLayerList();
        captureHistoryImmediate();
    });

    document.getElementById('titlesWrap').addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('rm-title')) {
            var row = e.target.closest('.title-row');
            var target = row.getAttribute('data-target');
            var el = document.getElementById(target);
            if (el) el.remove();
            row.remove();
            refreshLayerList();
            captureHistoryImmediate();
        }
    });

    document.getElementById('i_img').addEventListener('change', function (e) {
        var files = Array.prototype.slice.call(e.target.files || []);
        if (!files.length) return;
        files.forEach(function (f) {
            imgCounter += 1;
            var id = 'prodimg' + imgCounter;
            var r = new FileReader();
            r.onload = function () {
                var img = document.createElement('img');
                img.id = id;
                img.className = 'prodimg draggable';
                img.src = r.result;
                img.style.display = 'block';
                img.style.left = '500px';
                img.style.top = '120px';
                img.style.maxWidth = '300px';
                img.style.maxHeight = '240px';
                document.getElementById('design').appendChild(img);
                enableDrag(img);
                setSelected(id);
                var l = document.getElementById('imgsList');
                var a = document.createElement('div');
                a.className = 'mb-1 d-flex align-items-center flex-wrap';
                a.style.gap = '6px';
                var sp = document.createElement('span');
                sp.textContent = f.name;
                a.appendChild(sp);
                function mkBtn(txt, cls) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.setAttribute('data-id', id);
                    b.className = 'btn btn-sm btn-outline-secondary py-0 px-2 ' + cls;
                    b.textContent = txt;
                    return b;
                }
                a.appendChild(mkBtn('+', 'zoom-plus'));
                a.appendChild(mkBtn('−', 'zoom-minus'));
                a.appendChild(mkBtn('Sil', 'del-'));
                l.appendChild(a);
    refreshLayerList();
                captureHistoryImmediate();
            };
            r.readAsDataURL(f);
        });
        e.target.value = '';
    });

    document.getElementById('btnRozetAdd').addEventListener('click', function () {
        rozetCounter += 1;
        var id = 'rozet' + rozetCounter;
        var el = document.createElement('div');
        el.id = id;
        el.className = 'badge-rozet rozet-item draggable';
        el.style.top = '24px';
        el.style.left = (24 + rozetCounter * 12) + 'px';
        el.style.right = '';
        el.textContent = document.getElementById('i_rozet').value || 'Rozet';
        document.getElementById('design').appendChild(el);
        enableDrag(el);
        setSelected(id);
        applyRozetStyles(el);
        refreshLayerList();
        captureHistoryImmediate();
    });

    document.getElementById('imgsList').addEventListener('click', function (e) {
        var t = e.target;
        if (!t.getAttribute || !t.getAttribute('data-id')) return;
        var id = t.getAttribute('data-id');
        if (t.classList.contains('del-')) {
            var el = document.getElementById(id);
            if (el) el.remove();
            t.closest('div.mb-1').remove();
            refreshLayerList();
        }
        if (t.classList.contains('zoom-plus')) {
            var img = document.getElementById(id);
            if (img) {
                var box = oimReadProdimgBox(img);
                var w = box.mw;
                var nw = Math.min(w + 30, 800);
                oimSetProdimgContainBox(img, nw, nw * (240 / 300));
                if (document.getElementById('layerTarget').value === id) {
                    document.getElementById('sizeSlider').value = nw;
                }
            }
        }
        if (t.classList.contains('zoom-minus')) {
            var img2 = document.getElementById(id);
            if (img2) {
                var box2 = oimReadProdimgBox(img2);
                var w2 = box2.mw;
                var nw2 = Math.max(w2 - 30, 40);
                oimSetProdimgContainBox(img2, nw2, nw2 * (240 / 300));
                if (document.getElementById('layerTarget').value === id) {
                    document.getElementById('sizeSlider').value = nw2;
                }
            }
        }
    });

    document.getElementById('addBullet').addEventListener('click', function () {
        var wrap = document.getElementById('bulletsWrap');
        var div = document.createElement('div');
        div.className = 'input-group mb-2';
        div.innerHTML = '<input type="text" class="form-control bullet-item" value="• "><div class="input-group-append"><button class="btn btn-outline-secondary btn-sm rm-bullet" type="button">−</button></div>';
        wrap.appendChild(div);
        updatePreview();
        captureHistoryImmediate();
    });

    document.getElementById('bulletsWrap').addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('rm-bullet')) {
            e.target.closest('.input-group').remove();
            updatePreview();
            refreshLayerList();
            captureHistoryImmediate();
        }
    });

    document.getElementById('bulletsWrap').addEventListener('input', updatePreview);

    document.getElementById('btnIconAdd').addEventListener('click', function () {
        var cls = document.getElementById('i_icon_class').value.trim();
        if (!cls) return;
        var ok = /^fa[srlb]?\s+fa-[\w-]+$/.test(cls);
        if (!ok) return;
        iconCounter += 1;
        var id = 'icon' + iconCounter;
        var el = document.createElement('i');
        el.id = id;
        el.className = 'iconitem draggable ' + cls;
        el.style.color = document.getElementById('i_icon_color').value;
        el.style.fontSize = document.getElementById('i_icon_size').value + 'px';
        el.style.left = (24 + iconCounter * 8) + 'px';
        el.style.top = (80 + iconCounter * 4) + 'px';
        document.getElementById('design').appendChild(el);
        enableDrag(el);
        setSelected(id);
    refreshLayerList();
        captureHistoryImmediate();
    });

    document.getElementById('layerList').addEventListener('click', function (e) {
        var t = e.target;
        if (!t.getAttribute) return;
        var id = t.getAttribute('data-id');
        if (!id) return;
        if (t.classList.contains('selL')) setSelected(id);
        if (t.classList.contains('delL')) {
            var el = document.getElementById(id);
            if (!el || id === 'cta1') {
                return;
            }
            el.remove();
            if (id.indexOf('prodimg') === 0 && id !== 'prodimg') {
                document.querySelectorAll('#imgsList .del-').forEach(function (btn) {
                    if (btn.getAttribute('data-id') === id) {
                        var row = btn.closest('div.mb-1');
                        if (row) row.remove();
                    }
                });
            }
            refreshLayerList();
        }
        if (t.classList.contains('togL')) {
            var el2 = document.getElementById(id);
            if (el2) {
                el2.style.display = el2.style.display === 'none' ? '' : 'none';
                refreshLayerList();
            }
        }
    });

    document.getElementById('layerTarget').addEventListener('change', function () {
        document.querySelectorAll('#design .selected').forEach(function (n) {
            n.classList.remove('selected');
        });
        var el = document.getElementById(this.value);
        if (el) el.classList.add('selected');
    updateLayerInfo();
    updateSizeSliderForSelection();
        positionResizeHud();
    });

    document.getElementById('sizeSlider').addEventListener('input', function () {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!el) return;
        var val = parseInt(this.value, 10);
        if (el.classList.contains('prodimg')) {
            oimSetProdimgContainBox(el, val, val * (240 / 300));
            positionResizeHud();
            return;
        }
        if (el.classList.contains('iconitem')) {
            el.style.fontSize = val + 'px';
        }
        positionResizeHud();
    });

    document.getElementById('btnToFront').addEventListener('click', function () {
        var el = document.getElementById(document.getElementById('layerTarget').value);
        if (el) {
            el.style.zIndex = '999';
            updateLayerInfo();
        }
    });
    document.getElementById('btnToBack').addEventListener('click', function () {
        var el = document.getElementById(document.getElementById('layerTarget').value);
        if (el) {
            el.style.zIndex = '0';
            updateLayerInfo();
        }
    });
    document.getElementById('btnUp').addEventListener('click', function () {
        var el = document.getElementById(document.getElementById('layerTarget').value);
        if (el) {
            el.style.zIndex = String(z(el) + 1);
            updateLayerInfo();
        }
    });
    document.getElementById('btnDown').addEventListener('click', function () {
        var el = document.getElementById(document.getElementById('layerTarget').value);
        if (el) {
            el.style.zIndex = String(z(el) - 1);
            updateLayerInfo();
        }
    });

    ['rozet', 'title1', 'title2', 'subtitle', 'bullets', 'prodimg', 'icon1', 'cta1'].forEach(function (id) {
        var e = document.getElementById(id);
        if (e) enableDrag(e);
    });

    document.getElementById('btnHideShow').addEventListener('click', function () {
        var id = document.getElementById('layerTarget').value;
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = el.style.display === 'none' ? '' : 'none';
refreshLayerList();
    });

    document.getElementById('btnDeleteSel').addEventListener('click', function () {
        var layerSel = document.getElementById('layerTarget');
        var id = layerSel.value;
        var el = document.getElementById(id);
        if (!el) return;
        if (id === 'cta1') return;
        el.remove();
        if (id.indexOf('prodimg') === 0 && id !== 'prodimg') {
            document.querySelectorAll('#imgsList button.del-').forEach(function (btn) {
                if (btn.getAttribute('data-id') === id) btn.closest('div.mb-1').remove();
            });
        }
        var rowRm = document.querySelector('#titlesWrap .title-row[data-target="' + id + '"]');
        if (rowRm) rowRm.remove();
        refreshLayerList();
        captureHistoryImmediate();
    });

    document.getElementById('btnDownload').addEventListener('click', function () {
        var out = document.getElementById('saveResult');
        out.textContent = 'Görüntü oluşturuluyor…';
        exportCurrentDesign()
            .then(function (pack) {
                downloadBlob(pack.blob, pack.fileBase + '.' + pack.ext);
                out.textContent = 'İndirildi.';
                setTimeout(function () {
                    out.textContent = '';
                }, 4000);
            })
            .catch(function (err) {
                out.textContent = 'Hata: oluşturulamadı.';
                console.error(err);
            });
    });

    var btnCopyImage = document.getElementById('btnCopyImage');
    if (btnCopyImage) {
        btnCopyImage.addEventListener('click', function () {
            var out = document.getElementById('saveResult');
            if (!navigator.clipboard || typeof window.ClipboardItem === 'undefined') {
                out.textContent = 'Tarayıcı panoya kopyalamayı desteklemiyor — indirin.';
                return;
            }
            out.textContent = 'Kopyalanıyor…';
            var prevFmt = document.getElementById('oimExportFmt').value;
            document.getElementById('oimExportFmt').value = 'png';
            exportCurrentDesign()
                .then(function (pack) {
                    document.getElementById('oimExportFmt').value = prevFmt;
                    return navigator.clipboard.write([
                        new window.ClipboardItem({ 'image/png': pack.blob }),
                    ]);
                })
                .then(function () {
                    out.textContent = 'Panoya kopyalandı — yapıştırabilirsiniz.';
                    setTimeout(function () { out.textContent = ''; }, 4000);
                })
                .catch(function (err) {
                    document.getElementById('oimExportFmt').value = prevFmt;
                    out.textContent = 'Kopyalanamadı: ' + (err && err.message ? err.message : 'hata');
                    console.error(err);
                });
        });
    }

    var btnPreviewNewTab = document.getElementById('btnPreviewNewTab');
    if (btnPreviewNewTab) {
        btnPreviewNewTab.addEventListener('click', function () {
            var out = document.getElementById('saveResult');
            out.textContent = 'Görüntü açılıyor…';
            exportCurrentDesign()
                .then(function (pack) {
                    var url = URL.createObjectURL(pack.blob);
                    window.open(url, '_blank', 'noopener');
                    out.textContent = '';
                    setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
                })
                .catch(function (err) {
                    out.textContent = 'Açılamadı.';
                    console.error(err);
                });
        });
    }

    document.getElementById('btnAlignCenterH').addEventListener('click', function () {
        alignSelected('h');
    });
    document.getElementById('btnAlignCenterV').addEventListener('click', function () {
        alignSelected('v');
    });
    document.getElementById('btnSnapLeft').addEventListener('click', function () {
        alignSelected('l');
    });
    document.getElementById('btnSnapRight').addEventListener('click', function () {
        alignSelected('r');
    });
    document.getElementById('btnSnapTop').addEventListener('click', function () {
        alignSelected('t');
    });
    document.getElementById('btnSnapBottom').addEventListener('click', function () {
        alignSelected('b');
    });
    document.getElementById('btnDuplicateLayer').addEventListener('click', function () {
        duplicateSelectedLayer();
    });
    document.getElementById('btnToggleLock').addEventListener('click', function () {
        toggleSelectedLock();
    });
    document.getElementById('btnUndoOim').addEventListener('click', function () {
        undoOimAct();
    });
    document.getElementById('btnRedoOim').addEventListener('click', function () {
        redoOimAct();
    });

    ['oimCtaText', 'oimCtaColor', 'oimCtaBg', 'oimCtaFont', 'oimCtaRadius', 'oimCtaShow'].forEach(function (id) {
        var node = document.getElementById(id);
        if (!node) return;
        node.addEventListener('input', updatePreview);
        node.addEventListener('change', updatePreview);
    });
    document.getElementById('oimShowSafe').addEventListener('change', syncSafeOverlayVisibility);

    document.querySelectorAll('.oim-typo').forEach(function (bn) {
        bn.addEventListener('click', function () {
            applyTypoPack(bn.getAttribute('data-typo') || 'balanced');
        });
    });

    document.querySelectorAll('.oim-fx').forEach(function (fb) {
        fb.addEventListener('click', function () {
            applyOimTextFx(fb.getAttribute('data-fx') || 'none');
        });
    });

    document.querySelectorAll('.oim-canvas-preset').forEach(function (b) {
        b.addEventListener('click', function () {
            var w = parseInt(b.getAttribute('data-w'), 10);
            var h = parseInt(b.getAttribute('data-h'), 10);
            var iw = document.getElementById('oimCanvasW');
            var ih = document.getElementById('oimCanvasH');
            if (iw && !isNaN(w)) iw.value = String(w);
            if (ih && !isNaN(h)) ih.value = String(h);
            syncDesignDimsFromInputs();
            captureHistoryImmediate();
        });
    });
    var btnApplyDim = document.getElementById('btnApplyCanvasDims');
    if (btnApplyDim) {
        btnApplyDim.addEventListener('click', function () {
            syncDesignDimsFromInputs();
            captureHistoryImmediate();
        });
    }

    document.getElementById('oimWmFile').addEventListener('change', function (ev) {
        var f = ev.target.files && ev.target.files[0];
        if (!f) return;
        var r = new FileReader();
        r.onload = function () {
            setWatermarkFromDataUrl(String(r.result || ''));
        };
        r.readAsDataURL(f);
    });

    document.getElementById('btnSaveDraft').addEventListener('click', function () {
        try {
            localStorage.setItem('oim_autosave_v2', serializeForStorage());
            document.getElementById('saveResult').textContent = 'Taslak tarayıcıda kaydedildi.';
            setTimeout(function () {
                document.getElementById('saveResult').textContent = '';
            }, 3000);
        } catch (e) {
            document.getElementById('saveResult').textContent = 'Taslak kaydedilemedi.';
        }
    });
    document.getElementById('btnLoadDraft').addEventListener('click', function () {
        try {
            var raw = localStorage.getItem('oim_autosave_v2');
            if (!raw || !window.confirm('Mevcut tuval düzeni üzerine taslaktan yüklensin mi?')) return;
            applyFullStateSilent(JSON.parse(raw));
            undoHistory = [raw];
            undoPtr = 0;
        } catch (e2) {
            console.error(e2);
        }
    });
    document.getElementById('btnExportDraft').addEventListener('click', function () {
        var blob = new Blob([serializeForStorage()], { type: 'application/json' });
        downloadBlob(blob, 'siparis_taslak_' + Date.now() + '.json');
    });
    document.getElementById('oimDraftFile').addEventListener('change', function (ev) {
        var f = ev.target.files && ev.target.files[0];
        if (!f) return;
        var rr = new FileReader();
        rr.onload = function () {
            try {
                var parsed = JSON.parse(String(rr.result));
                if (!window.confirm('JSON taslağı uygulanacak — devam?')) return;
                applyFullStateSilent(parsed);
                undoHistory = [JSON.stringify(parsed)];
                undoPtr = 0;
            } catch (e3) {
                alert('JSON okunamadı.');
                console.error(e3);
            }
        };
        rr.readAsText(f);
        ev.target.value = '';
    });
    document.getElementById('btnHydrateOrder').addEventListener('click', function () {
        if (window.OIM_ORDER_INIT) {
            hydrateOrderPayload(window.OIM_ORDER_INIT);
        }
    });

    (function wireOrderByIdLoader() {
        var inp = document.getElementById('oimOrderIdInput');
        var btn = document.getElementById('btnLoadOrderById');
        var msg = document.getElementById('oimOrderLoadMsg');
        if (!inp || !btn) return;
        if (window.OIM_ORDER_INIT && window.OIM_ORDER_INIT.order_id) {
            inp.value = window.OIM_ORDER_INIT.order_id;
        }
        function setMsg(text, kind) {
            if (!msg) return;
            msg.textContent = text || '';
            msg.className = 'small mt-2 mb-0 ' +
                (kind === 'err' ? 'text-danger' : kind === 'ok' ? 'text-success' : 'text-muted');
        }
        function apiUrl() {
            return (window.OIM_FACTORY_BOOT && window.OIM_FACTORY_BOOT.apiUrl) || 'order_image_factory_api.php';
        }
        function loadOrder() {
            var oid = parseInt(String(inp.value || '').trim(), 10);
            if (!oid || oid < 1) {
                setMsg('Geçerli bir sipariş numarası yaz.', 'err');
                return;
            }
            setMsg('Sipariş #' + oid + ' getiriliyor…', 'muted');
            btn.disabled = true;
            var url = apiUrl() + (apiUrl().indexOf('?') >= 0 ? '&' : '?') +
                'action=payload&order_ids=' + encodeURIComponent(oid);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    btn.disabled = false;
                    if (!j || !j.ok || !j.payloads || !j.payloads.length) {
                        setMsg('Sipariş bulunamadı ya da ürün kalemi yok (#' + oid + ').', 'err');
                        return;
                    }
                    var ob = j.payloads[0];
                    window.OIM_ORDER_INIT = ob;
                    hydrateOrderPayload(ob);
                    if (ob.primaryImage) {
                        setPrimaryProductImage(ob.primaryImage).then(function (ok) {
                            setMsg(ok
                                ? 'Sipariş #' + oid + ' yüklendi — ürün görseli tuvale eklendi.'
                                : 'Sipariş #' + oid + ' yüklendi (metinler geldi, görsel açılamadı).',
                                ok ? 'ok' : 'muted');
                        });
                    } else {
                        setMsg('Sipariş #' + oid + ' yüklendi (bu üründe görsel yok).', 'ok');
                    }
                    var ban = document.getElementById('oimOrderBanner');
                    if (ban) {
                        ban.textContent = 'Sipariş #' + oid + ' yüklendi.';
                        ban.classList.remove('d-none');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    setMsg('Bağlantı hatası — sipariş getirilemedi.', 'err');
                });
        }
        btn.addEventListener('click', loadOrder);
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadOrder();
            }
        });
    })();

    document.addEventListener('keydown', function (ev) {
        var ae = document.activeElement;
        var typing =
            ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.tagName === 'SELECT');
        if (typing) return;
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'z' && ev.shiftKey) {
            ev.preventDefault();
            redoOimAct();
            return;
        }
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'z') {
            ev.preventDefault();
            undoOimAct();
            return;
        }
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'y') {
            ev.preventDefault();
            redoOimAct();
            return;
        }
        var step = ev.shiftKey ? 10 : 1;
        if (ev.key === 'ArrowLeft') {
            moveSelectedLayerPx(-step, 0);
            scheduleCaptureAfterDrag();
            ev.preventDefault();
        } else if (ev.key === 'ArrowRight') {
            moveSelectedLayerPx(step, 0);
            scheduleCaptureAfterDrag();
            ev.preventDefault();
        } else if (ev.key === 'ArrowUp') {
            moveSelectedLayerPx(0, -step);
            scheduleCaptureAfterDrag();
            ev.preventDefault();
        } else if (ev.key === 'ArrowDown') {
            moveSelectedLayerPx(0, step);
            scheduleCaptureAfterDrag();
            ev.preventDefault();
        } else if (ev.key === 'Delete') {
            var ks = document.getElementById('layerTarget').value;
            var de = document.getElementById(ks);
            if (de && ks !== 'bullets' && ks !== 'cta1') {
                de.remove();
                if (ks.indexOf('prodimg') === 0 && ks !== 'prodimg') {
                    document.querySelectorAll('#imgsList .del-').forEach(function (btn) {
                        if (btn.getAttribute('data-id') === ks) {
                            var rowP = btn.closest('div.mb-1');
                            if (rowP) rowP.remove();
                        }
                    });
                }
                var rowTit = document.querySelector('#titlesWrap .title-row[data-target="' + ks + '"]');
                if (rowTit) rowTit.remove();
                refreshLayerList();
                captureHistoryImmediate();
            }
            ev.preventDefault();
        }
    });

    wireDesignDelegationOnce();
    reinstateDesignBindings();

    undoHistory = [];
    undoPtr = -1;
    captureHistoryImmediate();

    // Sıfırlama butonları için pristine (varsayılan) durumu yakala.
    try {
        OIM_DEFAULT_STATE = serializeFullState();
    } catch (eDef) {
        OIM_DEFAULT_STATE = null;
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.oim-reset-btn') : null;
        if (!btn) return;
        ev.preventDefault();
        var section = btn.getAttribute('data-reset') || '';
        if (!section) return;
        var label = btn.getAttribute('data-reset-label') || 'Bu bölüm';
        // Not: bloklayan confirm() bilinçli olarak kullanılmıyor — tarayıcı "ek iletişim
        // kutularını engelle" seçeneği işaretlendiğinde confirm() hep false döner ve
        // sıfırlama sessizce çalışmaz. Bunun yerine anında sıfırlayıp geri alınabilir
        // (Ctrl+Z) bir toast gösteriyoruz.
        oimResetSection(section);
        oimToast(label + ' sıfırlandı. Geri almak için Ctrl+Z.', 'ok');
    });

    try {
        if (window.OIM_ORDER_INIT && window.OIM_ORDER_INIT.order_id) {
            var ban = document.getElementById('oimOrderBanner');
            var bx = window.OIM_ORDER_INIT;
            ban.textContent =
                'Sipariş #' +
                bx.order_id +
                ' yüklendi — metinler ve ürün görseli otomatik uygulanıyor.';
            ban.classList.remove('d-none');
            document.getElementById('btnHydrateOrder').classList.remove('d-none');
            hydrateOrderPayload(bx);
            if (bx.primaryImage) {
                setPrimaryProductImage(bx.primaryImage);
            }
        }
    } catch (errOi) {}

    /* —— Fabrika modu —— */
    var OIM_FACTORY_TEMPLATE_KEY = 'oim_factory_template_v1';
    var oimFactoryRunning = false;
    var oimFactoryStopFlag = false;
    var oimFactoryBoot = window.OIM_FACTORY_BOOT || {};
    var oimFactoryApi = String(oimFactoryBoot.apiUrl || 'order_image_factory_api.php');

    function oimSetMode(mode) {
        var studio = document.getElementById('oimStudioWrap');
        var factory = document.getElementById('oimFactoryWrap');
        var isFactory = mode === 'factory';
        if (studio) studio.classList.toggle('d-none', isFactory);
        if (factory) factory.classList.toggle('d-none', !isFactory);
        document.querySelectorAll('.oim-mode-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab.getAttribute('data-oim-mode') === mode);
        });
        if (isFactory) {
            oimFactoryRefreshTemplateStatus();
            oimFactoryUpdateRunButton();
        }
    }

    function oimFactoryRefreshTemplateStatus() {
        var el = document.getElementById('oimFactoryTemplateStatus');
        if (!el) return;
        try {
            var raw = localStorage.getItem(OIM_FACTORY_TEMPLATE_KEY);
            if (raw && raw.length > 40) {
                el.textContent = 'Kayıtlı şablon: hazır (' + Math.round(raw.length / 1024) + ' KB)';
            } else {
                el.textContent = 'Kayıtlı şablon yok — “Mevcut tuvali şablon yap” veya hazır şablon seçin.';
            }
        } catch (e) {
            el.textContent = 'Şablon durumu okunamadı.';
        }
    }

    function oimFactorySaveTemplateFromCanvas() {
        try {
            localStorage.setItem(OIM_FACTORY_TEMPLATE_KEY, serializeForStorage());
            oimFactoryRefreshTemplateStatus();
            oimFactoryRenderTemplatePreview();
            oimFactoryLog('Şablon kaydedildi.');
        } catch (e2) {
            oimFactoryLog('Şablon kaydedilemedi.');
        }
    }

    function oimFactoryRenderTemplatePreview() {
        var wrap = document.getElementById('oimFactoryTemplatePreview');
        var img = document.getElementById('oimFactoryTemplateImg');
        if (!wrap || !img || typeof html2canvas === 'undefined') return;
        exportCurrentDesign()
            .then(function (pack) {
                if (img.src && img.src.indexOf('blob:') === 0) {
                    URL.revokeObjectURL(img.src);
                }
                img.src = URL.createObjectURL(pack.blob);
                wrap.classList.remove('d-none');
            })
            .catch(function () {});
    }

    function oimFactoryLoadTemplate() {
        var raw = localStorage.getItem(OIM_FACTORY_TEMPLATE_KEY);
        if (!raw) return false;
        try {
            applyFullStateSilent(JSON.parse(raw));
            return true;
        } catch (e) {
            return false;
        }
    }

    function oimFactoryLog(msg) {
        var log = document.getElementById('oimFactoryLog');
        if (!log) return;
        var line = document.createElement('div');
        line.textContent = new Date().toLocaleTimeString('tr-TR') + ' — ' + msg;
        log.appendChild(line);
        while (log.childNodes.length > 80) {
            log.removeChild(log.firstChild);
        }
        log.scrollTop = log.scrollHeight;
    }

    function oimFactorySelectedIds() {
        var ids = [];
        document.querySelectorAll('.oim-factory-check:checked').forEach(function (ck) {
            var id = parseInt(ck.value, 10);
            if (id > 0) ids.push(id);
        });
        return ids;
    }

    function oimFactoryUpdateRunButton() {
        var btn = document.getElementById('btnFactoryRun');
        if (!btn) return;
        var n = oimFactorySelectedIds().length;
        btn.disabled = oimFactoryRunning;
        btn.classList.toggle('btn-primary', n > 0);
        btn.classList.toggle('btn-outline-secondary', n === 0);
        btn.innerHTML =
            '<i class="fas fa-play me-1"></i>Seçilenleri üret' + (n > 0 ? ' (' + n + ')' : '');
        var selStat = document.getElementById('oimFactoryStatSel');
        if (selStat) selStat.textContent = String(n);
    }

    function oimFactorySetStat(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(val);
    }

    function oimFactoryPushThumb(orderId, blob, savedUrl) {
        var host = document.getElementById('oimFactoryGallery');
        if (!host) return;
        var url = URL.createObjectURL(blob);
        var fig = document.createElement('figure');
        fig.className = 'oim-factory-thumb';
        var link = savedUrl || url;
        fig.innerHTML =
            '<a href="' + link + '" target="_blank" rel="noopener"><img src="' + url + '" alt="#' + orderId + '"></a>' +
            '<figcaption><span>#' + orderId + '</span><a href="' + link + '" download="siparis_' + orderId + '">indir</a></figcaption>';
        host.insertBefore(fig, host.firstChild);
        while (host.childNodes.length > 60) {
            host.removeChild(host.lastChild);
        }
    }

    function oimFactoryRenderOrders(orders, total) {
        var body = document.getElementById('oimFactoryOrderBody');
        var meta = document.getElementById('oimFactoryOrderMeta');
        if (!body) return;
        body.innerHTML = '';
        if (!orders || !orders.length) {
            body.innerHTML =
                '<tr><td colspan="6" class="text-muted text-center py-3">Sipariş bulunamadı.</td></tr>';
            if (meta) meta.textContent = '0 sipariş';
            oimFactoryUpdateRunButton();
            return;
        }
        var pre = (oimFactoryBoot.preselectIds || []).map(String);
        var selectAllByDefault = pre.length === 0;
        orders.forEach(function (o) {
            var tr = document.createElement('tr');
            var isSel = selectAllByDefault || pre.indexOf(String(o.order_id)) >= 0;
            var checked = isSel ? ' checked' : '';
            tr.style.cursor = 'pointer';
            var totalFmt =
                typeof o.order_total === 'number'
                    ? o.order_total.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL'
                    : '—';
            tr.innerHTML =
                '<td><input type="checkbox" class="oim-factory-check" value="' +
                o.order_id +
                '"' +
                checked +
                '></td><td><strong>#' +
                o.order_id +
                '</strong></td><td>' +
                escapeHtml(o.customer_name || '—') +
                '</td><td>' +
                escapeHtml(o.status_name || '') +
                '</td><td>' +
                escapeHtml(totalFmt) +
                '</td><td class="text-muted">' +
                escapeHtml(String(o.order_date || '').slice(0, 16)) +
                '</td>';
            body.appendChild(tr);
        });
        if (meta) {
            meta.textContent = orders.length + ' sipariş listelendi' + (total ? ' (toplam ' + total + ')' : '');
        }
        body.querySelectorAll('.oim-factory-check').forEach(function (ck) {
            ck.addEventListener('change', oimFactoryUpdateRunButton);
            ck.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
        body.querySelectorAll('tr').forEach(function (row) {
            row.addEventListener('click', function () {
                var ck = row.querySelector('.oim-factory-check');
                if (!ck) return;
                ck.checked = !ck.checked;
                oimFactoryUpdateRunButton();
            });
        });
        oimFactoryUpdateRunButton();
    }

    function oimFactoryFetchOrders() {
        var status = document.getElementById('oimFactoryStatus').value || '';
        var q = document.getElementById('oimFactorySearch').value || '';
        var limit = document.getElementById('oimFactoryLimit').value || '50';
        var url =
            oimFactoryApi +
            '?action=orders&limit=' +
            encodeURIComponent(limit) +
            '&page=1';
        if (status) url += '&status=' + encodeURIComponent(status);
        if (q) url += '&q=' + encodeURIComponent(q);
        oimFactoryLog('Sipariş listesi yükleniyor…');
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Liste alınamadı');
                oimFactoryRenderOrders(data.orders || [], data.total || 0);
                oimFactoryLog((data.orders || []).length + ' sipariş yüklendi.');
            });
    }

    function oimFactoryFetchPayloads(ids) {
        return fetch(
            oimFactoryApi + '?action=payload&order_ids=' + encodeURIComponent(ids.join(',')),
            { credentials: 'same-origin' }
        )
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Veri alınamadı');
                return data.payloads || [];
            });
    }

    function oimFactoryUpload(orderId, blob, ext) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () {
                fetch(oimFactoryApi + '?action=save', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: orderId,
                        image: String(reader.result || ''),
                    }),
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data.ok) reject(new Error(data.error || 'Kayıt hatası'));
                        else resolve(data);
                    })
                    .catch(reject);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    function oimFactorySetProgress(done, total) {
        var bar = document.getElementById('oimFactoryProgressBar');
        var st = document.getElementById('oimFactoryRunStatus');
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        if (bar) bar.style.width = pct + '%';
        if (st) st.textContent = done + ' / ' + total + ' (' + pct + '%)';
    }

    function oimFactoryRunQueue(ids) {
        if (!ids.length) return Promise.resolve();
        oimFactoryRunning = true;
        oimFactoryStopFlag = false;
        document.getElementById('btnFactoryRun').disabled = true;
        document.getElementById('btnFactoryStop').classList.remove('d-none');
        oimFactorySetProgress(0, ids.length);

        var templateRaw = localStorage.getItem(OIM_FACTORY_TEMPLATE_KEY);
        if (!templateRaw) {
            applyOimPreset('ecom');
            oimFactorySaveTemplateFromCanvas();
            templateRaw = localStorage.getItem(OIM_FACTORY_TEMPLATE_KEY);
        }
        var templateObj = JSON.parse(templateRaw);
        var delivery = document.getElementById('oimFactoryDelivery').value || 'download';
        var useImage = document.getElementById('oimFactoryUseImage').value === '1';
        var autoHydrate = document.getElementById('oimFactoryAutoHydrate').checked;
        var fmtSel = document.getElementById('oimFactoryFmt');
        var prevFmt = document.getElementById('oimExportFmt').value;
        if (fmtSel) document.getElementById('oimExportFmt').value = fmtSel.value;

        var chain = Promise.resolve();
        var done = 0;
        var okCount = 0;
        var errCount = 0;
        oimFactorySetStat('oimFactoryStatOk', 0);
        oimFactorySetStat('oimFactoryStatErr', 0);
        ids.forEach(function (orderId) {
            chain = chain.then(function () {
                if (oimFactoryStopFlag) return;
                oimFactoryLog('Üretiliyor: #' + orderId);
                var producedPack = null;
                return oimFactoryFetchPayloads([orderId])
                    .then(function (payloads) {
                        var ob = payloads[0];
                        if (!ob) throw new Error('Sipariş verisi yok');
                        applyFullStateSilent(templateObj);
                        if (autoHydrate) hydrateOrderPayload(ob);
                        var imgP = useImage && ob.primaryImage
                            ? setPrimaryProductImage(ob.primaryImage)
                            : Promise.resolve(false);
                        return imgP.then(function () {
                            return new Promise(function (res) {
                                setTimeout(res, 180);
                            });
                        });
                    })
                    .then(function () {
                        return exportCurrentDesign();
                    })
                    .then(function (pack) {
                        producedPack = pack;
                        var fname = 'siparis_' + orderId + '.' + pack.ext;
                        if (delivery === 'download' || delivery === 'both') {
                            downloadBlob(pack.blob, fname);
                        }
                        if (delivery === 'server' || delivery === 'both') {
                            return oimFactoryUpload(orderId, pack.blob, pack.ext).then(function (saved) {
                                oimFactoryLog('#' + orderId + ' sunucuya kaydedildi: ' + (saved.path || ''));
                                return saved && saved.url ? saved.url : '';
                            });
                        }
                        return '';
                    })
                    .then(function (savedUrl) {
                        done += 1;
                        okCount += 1;
                        oimFactorySetStat('oimFactoryStatOk', okCount);
                        oimFactorySetProgress(done, ids.length);
                        if (producedPack) {
                            oimFactoryPushThumb(orderId, producedPack.blob, savedUrl || '');
                        }
                        oimFactoryLog('#' + orderId + ' tamam.');
                    })
                    .catch(function (err) {
                        done += 1;
                        errCount += 1;
                        oimFactorySetStat('oimFactoryStatErr', errCount);
                        oimFactorySetProgress(done, ids.length);
                        oimFactoryLog('#' + orderId + ' HATA: ' + (err && err.message ? err.message : 'bilinmiyor'));
                    });
            });
        });

        return chain.finally(function () {
            document.getElementById('oimExportFmt').value = prevFmt;
            oimFactoryRunning = false;
            oimFactoryStopFlag = false;
            document.getElementById('btnFactoryStop').classList.add('d-none');
            oimFactoryUpdateRunButton();
            oimFactoryLog('Fabrika kuyruğu bitti — ' + okCount + ' üretildi, ' + errCount + ' hata.');
        });
    }

    document.querySelectorAll('.oim-mode-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            oimSetMode(tab.getAttribute('data-oim-mode') || 'studio');
        });
    });
    document.getElementById('btnFactorySaveTemplate').addEventListener('click', oimFactorySaveTemplateFromCanvas);
    (function () {
        var pick = document.getElementById('oimFactoryPresetPick');
        if (!pick || typeof OIM_PRESET_META === 'undefined') return;
        Object.keys(OIM_PRESET_META).forEach(function (key) {
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = OIM_PRESET_META[key].label + (OIM_PRESET_META[key].badge === 'pro' ? ' ✦' : '');
            pick.appendChild(opt);
        });
        pick.addEventListener('change', function () {
            var key = pick.value;
            if (!key) return;
            applyOimPreset(key);
            oimFactorySaveTemplateFromCanvas();
            oimFactoryLog('Şablon uygulandı: ' + (OIM_PRESET_META[key] ? OIM_PRESET_META[key].label : key));
            pick.value = '';
        });
    })();
    document.getElementById('btnFactoryLoadOrders').addEventListener('click', function () {
        oimFactoryFetchOrders().catch(function (e) {
            oimFactoryLog('Liste hatası: ' + e.message);
        });
    });
    document.getElementById('btnFactorySelectAll').addEventListener('click', function () {
        document.querySelectorAll('.oim-factory-check').forEach(function (ck) {
            ck.checked = true;
        });
        oimFactoryUpdateRunButton();
    });
    document.getElementById('oimFactoryCheckAll').addEventListener('change', function (ev) {
        var on = ev.target.checked;
        document.querySelectorAll('.oim-factory-check').forEach(function (ck) {
            ck.checked = on;
        });
        oimFactoryUpdateRunButton();
    });
    document.getElementById('btnFactoryRun').addEventListener('click', function () {
        var ids = oimFactorySelectedIds();
        if (!ids.length) {
            oimFactoryLog('Önce listeden en az bir sipariş seç (satıra ya da kutucuğa tıkla).');
            var status = document.getElementById('oimFactoryRunStatus');
            if (status) {
                status.textContent = 'Seçili sipariş yok — bir satır seç.';
                status.classList.add('text-danger');
                setTimeout(function () {
                    status.classList.remove('text-danger');
                    status.textContent = 'Hazır';
                }, 3500);
            }
            var tbl = document.getElementById('oimFactoryOrderBody');
            if (tbl) tbl.closest('table') && tbl.closest('table').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        oimFactoryRunQueue(ids);
    });
    document.getElementById('btnFactoryStop').addEventListener('click', function () {
        oimFactoryStopFlag = true;
        oimFactoryLog('Durdurma istendi…');
    });

    var btnEditStudio = document.getElementById('btnFactoryEditStudio');
    if (btnEditStudio) {
        btnEditStudio.addEventListener('click', function () {
            var ids = oimFactorySelectedIds();
            var oid = ids.length ? ids[0] : 0;
            if (!oid) {
                oimFactoryLog('Stüdyoda düzenlemek için önce bir sipariş seç.');
                return;
            }
            // Stüdyoya geç
            oimSetMode('studio');
            // Metinler adımını aç (siparişten getir kutusu orada)
            var step2 = document.querySelector('.oim-step[data-step="2"]');
            if (step2) step2.click();
            // Sipariş numarasını yaz ve yükle (mevcut yükleyiciyi tetikle)
            var input = document.getElementById('oimOrderIdInput');
            var loadBtn = document.getElementById('btnLoadOrderById');
            if (input) input.value = oid;
            if (loadBtn) loadBtn.click();
            var studio = document.getElementById('oimStudioWrap');
            if (studio) studio.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    oimFactoryRefreshTemplateStatus();
    if (oimFactoryBoot.factoryMode) {
        oimSetMode('factory');
        oimFactoryFetchOrders().catch(function () {});
    } else if ((oimFactoryBoot.preselectIds || []).length) {
        oimFactoryFetchOrders().catch(function () {});
    }

    window.OIM = {
        exportCurrentDesign: exportCurrentDesign,
        hydrateOrderPayload: hydrateOrderPayload,
        setPrimaryProductImage: setPrimaryProductImage,
        saveFactoryTemplate: oimFactorySaveTemplateFromCanvas,
    };

    document.getElementById('titlesWrap').addEventListener('input', scheduleDraftInputCapture);
    document.getElementById('bulletsWrap').addEventListener('input', scheduleDraftInputCapture);

    oimAutosaveIv = window.setInterval(function () {
        var ck = document.getElementById('oimAutosaveDraft');
        if (!ck || !ck.checked) return;
        try {
            localStorage.setItem('oim_autosave_v2', serializeForStorage());
        } catch (eSave) {}
    }, 25000);

    syncTitlesFromUI();
    updatePreview();
    refreshLayerList();
    goStep(1);
    syncDesignDimsFromInputs();
    window.addEventListener('resize', fitOimDesignPreview);
    (function observeVp() {
        var vp = document.getElementById('oimDesignViewport');
        if (!vp || typeof ResizeObserver === 'undefined') return;
        var ro = new ResizeObserver(fitOimDesignPreview);
        ro.observe(vp);
    })();
})();
</script>

<?php include 'admin_footer_common.php'; ?>
