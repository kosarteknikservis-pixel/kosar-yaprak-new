* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
    background: #f8fafc;
    color: #0f172a;
    line-height: 1.65;
}
a { color: #0d9488; text-decoration: none; }
a:hover { text-decoration: underline; }
.sp-container { max-width: 1080px; margin: 0 auto; padding: 0 1.25rem; }
.sp-header {
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 10;
}
.sp-header__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 64px;
    gap: 1rem;
}
.sp-logo {
    font-weight: 800;
    font-size: 1.15rem;
    color: #0f172a;
    text-decoration: none;
    letter-spacing: -0.02em;
}
.sp-nav { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.sp-nav a { color: #475569; font-size: 0.92rem; font-weight: 500; text-decoration: none; }
.sp-nav a:hover { color: #0d9488; }
.sp-hero {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdfa 45%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 3rem 0 2.5rem;
}
.sp-hero__eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.72rem;
    font-weight: 700;
    color: #0d9488;
    margin: 0 0 0.5rem;
}
.sp-hero__title {
    font-size: clamp(1.75rem, 4vw, 2.35rem);
    line-height: 1.15;
    margin: 0 0 0.75rem;
    letter-spacing: -0.03em;
}
.sp-hero__lead {
    margin: 0;
    max-width: 42rem;
    color: #475569;
    font-size: 1.05rem;
}
.sp-main { padding: 2rem 0 3rem; }
.sp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.35rem;
}
.sp-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sp-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
}
.sp-card__thumb {
    height: 140px;
    background: linear-gradient(135deg, #99f6e4 0%, #5eead4 50%, #2dd4bf 100%);
    opacity: 0.85;
}
.sp-card:nth-child(2) .sp-card__thumb {
    background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%);
}
.sp-card:nth-child(3) .sp-card__thumb {
    background: linear-gradient(135deg, #bfdbfe 0%, #60a5fa 100%);
}
.sp-card:nth-child(4) .sp-card__thumb {
    background: linear-gradient(135deg, #fbcfe8 0%, #f472b6 100%);
}
.sp-card__body { padding: 1.15rem 1.2rem 1.25rem; flex: 1; display: flex; flex-direction: column; }
.sp-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.65rem;
    font-size: 0.78rem;
    color: #64748b;
}
.sp-pill {
    background: #ecfdf5;
    color: #0f766e;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-weight: 600;
}
.sp-card__title {
    font-size: 1.08rem;
    line-height: 1.35;
    margin: 0 0 0.5rem;
}
.sp-card__title a { color: #0f172a; text-decoration: none; }
.sp-card__title a:hover { color: #0d9488; }
.sp-card__excerpt {
    margin: 0 0 0.85rem;
    color: #475569;
    font-size: 0.92rem;
    flex: 1;
}
.sp-card__link { font-weight: 600; font-size: 0.88rem; }
.sp-footer {
    border-top: 1px solid #e2e8f0;
    background: #fff;
    padding: 1.25rem 0;
    font-size: 0.85rem;
}
.sp-footer__inner {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem;
    color: #334155;
}
.sp-footer__muted { color: #94a3b8; }
@media (max-width: 640px) {
    .sp-nav { display: none; }
    .sp-hero { padding: 2rem 0 1.75rem; }
}
