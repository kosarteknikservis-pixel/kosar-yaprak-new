#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
generate_docs.py — Admin panel "tur" dokumantasyonunu (.md) uretir.

Kullanim:
    python generate_docs.py [--out ADMIN_TOUR.md]

Uretilen .md, build_tour.py tarafindan okunur. Her kontrol maddesi,
makine tarafindan okunabilir bir yonerge icerir:
    - [ ] Aciklama  <!-- check: GET <path> <expect_status> [contains:<metin>] -->
"""
from __future__ import annotations

import argparse
import datetime
import os
import re
import sys

DOC_TITLE = "phxcore0 Admin — Canli Tur & Dogrulama Listesi"

# Bolum -> maddeler. Her madde: (aciklama, method, path, expect_status, contains|None)
SECTIONS: list[tuple[str, str, list[tuple[str, str, str, int, str | None]]]] = [
    (
        "Giris & Yol",
        "Panel yeni /phx yolundan acilir; eski /admin kapali olmali.",
        [
            ("Giris sayfasi /phx/login.php acilyor", "GET", "/phx/login.php", 200, "login"),
            ("Giris CSS'i yukleniyor", "GET", "/phx/css/login.css", 200, None),
            ("Eski /admin/login.php kapali (404)", "GET", "/admin/login.php", 404, None),
        ],
    ),
    (
        "Tema (Aydinlik / Karanlik)",
        "Varsayilan aydinlik; menu aydinlik; karanlik tema toggle ile.",
        [
            ("Tema kontrolcusu JS yukleniyor", "GET", "/phx/js/admin-theme.js", 200, "data-theme"),
            ("Karanlik tema CSS tanimli", "GET", "/phx/css/modern_admin.css", 200, '[data-theme="dark"]'),
            ("Aydinlik menu (sidebar-bg beyaz)", "GET", "/phx/css/modern_admin.css", 200, "--sidebar-bg: #ffffff"),
            ("Tema gecis butonu stili var", "GET", "/phx/css/modern_admin.css", 200, ".topbar-theme-btn"),
        ],
    ),
    (
        "Klavye Kisayollari",
        "Kisayol rozetleri (kbd) artik renkli ve gorunur.",
        [
            ("kbd metin rengi tanimli (gorunur)", "GET", "/phx/css/admin-panel-ui.css", 200, "#3730a3"),
            ("Kisayol modal paneli tema uyumlu", "GET", "/phx/css/admin-panel-ui.css", 200, "var(--surface"),
        ],
    ),
    (
        "Menu Tasarimi",
        "Sol menu canli indigo vurgu, aktif pill, ikonlar renkli.",
        [
            ("Aktif nav vurgu rengi tanimli", "GET", "/phx/css/modern_admin.css", 200, "--sidebar-active-text"),
            ("Nav ikon renk degiskeni tanimli", "GET", "/phx/css/modern_admin.css", 200, "--sidebar-icon"),
        ],
    ),
    (
        "Vitrin Sagligi",
        "Musteriye acik sayfalar sorunsuz calisiyor.",
        [
            ("Ana sayfa aciliyor", "GET", "/", 200, None),
            ("Siparis sayfasi aciliyor", "GET", "/order.php", 200, None),
        ],
    ),
    (
        "Landing Page (Acilis) Sistemi",
        "Blok tabanli acilis sayfalari: vitrin render, bloklar, temiz URL, editor, gorsel yukleme.",
        [
            # --- Vitrin (herkese acik) ---
            ("Landing CSS yukleniyor", "GET", "/css/landing.css", 200, ".lp-hero"),
            ("Yayindaki demo sayfa aciliyor", "GET", "/landing.php?slug=demo-kampanya", 200, "lp-hero", "noerror"),
            ("Hero blogu render ediliyor", "GET", "/landing.php?slug=demo-kampanya", 200, "lp-hero__title"),
            ("Geri sayim blogu render ediliyor", "GET", "/landing.php?slug=demo-kampanya", 200, "lp-cd__timer"),
            ("Urun blogu (ad/fiyat/gorsel) render ediliyor", "GET", "/landing.php?slug=demo-kampanya", 200, "lp-product"),
            ("Yorumlar blogu render ediliyor", "GET", "/landing.php?slug=demo-kampanya", 200, "lp-review"),
            ("SSS blogu render ediliyor", "GET", "/landing.php?slug=demo-kampanya", 200, "lp-faq__item"),
            ("Yok/taslak slug 404 veriyor", "GET", "/landing.php?slug=___olmayan-sayfa___", 404, None),
            ("Temiz URL /l/<slug> calisiyor", "GET", "/l/demo-kampanya", 200, "lp-hero"),
            # --- Admin (kimlik dogrulamali) ---
            ("Admin liste sayfasi aciliyor", "GET", "/phx/landing_pages.php", 200, "Landing sayfalar", "auth noerror"),
            ("Admin editor aciliyor", "GET", "/phx/landing_edit.php?id=1", 200, "Bloklar", "auth noerror"),
            ("Editor blok karti render ediliyor", "GET", "/phx/landing_edit.php?id=1", 200, "lp-block", "auth noerror"),
            ("Editor tema secici var", "GET", "/phx/landing_edit.php?id=1", 200, "Aurora", "auth noerror"),
            ("Gorsel yukleme ucu CSRF'siz reddediyor (403)", "GET", "/phx/landing_upload.php", 403, None, "auth"),
        ],
    ),
    (
        "Cok Dilli & Para Birimi (i18n)",
        "On yuz TR/EN/AR dil degisimi, RTL yon, para birimi donusumu ve admin yonetimi.",
        [
            # --- Vitrin (herkese acik) ---
            ("RTL stil dosyasi yukleniyor", "GET", "/css/rtl.css", 200, 'dir="rtl"'),
            ("Dil/para secici on yuzde render ediliyor", "GET", "/", 200, "site-switcher", "noerror"),
            ("Ingilizce ceviri uygulaniyor (Track Order)", "GET", "/?lang=en", 200, "Track Order", "noerror"),
            ("Arapca ceviri uygulaniyor (menu.order_query)", "GET", "/?lang=ar", 200, "تتبع الطلب", "noerror"),
            ("Arapcada RTL etkin (rtl.css yukleniyor)", "GET", "/?lang=ar", 200, "css/rtl.css", "noerror"),
            ("Para birimi degistirme baglantisi var (USD)", "GET", "/?cur=USD", 200, "cur=USD", "noerror"),
            # --- Admin (kimlik dogrulamali) ---
            ("Admin diller sayfasi aciliyor", "GET", "/phx/languages.php", 200, "Diller", "auth noerror"),
            ("Para birimleri sekmesi + kur guncelle", "GET", "/phx/languages.php?tab=currencies", 200, "Kurları güncelle", "auth noerror"),
            ("Ceviri editoru sekmesi", "GET", "/phx/languages.php?tab=translations", 200, "Arayüz çevirileri", "auth noerror"),
            ("Ice/disa aktar sekmesi", "GET", "/phx/languages.php?tab=io", 200, "Dışa aktar", "auth noerror"),
            ("JSON disa aktarma calisiyor", "GET", "/phx/languages.php?export=json", 200, "menu.home", "auth"),
        ],
    ),
]


def discover_menu_pages(project_root: str) -> list[str]:
    """phx/admin_header.php icindeki admin_href('...') cagrilarindan menu sayfalarini cikar."""
    header = os.path.join(project_root, "phx", "admin_header.php")
    if not os.path.exists(header):
        return []
    with open(header, "r", encoding="utf-8") as fh:
        src = fh.read()
    found = re.findall(r"admin_href\((['\"])(.+?)\1\)", src)
    seen: dict[str, None] = {}
    for _, path in found:
        path = path.strip()
        # oturum kapatan / disari acilan / anlamsiz linkleri atla
        if not path or path in ("logout.php",):
            continue
        seen.setdefault(path, None)
    return list(seen.keys())


def build_markdown(project_root: str) -> str:
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
    lines: list[str] = []
    lines.append(f"# {DOC_TITLE}")
    lines.append("")
    lines.append(f"> Otomatik uretildi: {now}  ")
    lines.append("> Bu dosya `generate_docs.py` ile uretilir, `build_tour.py` ile calistirilir.")
    lines.append("")
    for idx, (title, desc, items) in enumerate(SECTIONS, start=1):
        lines.append(f"## {idx}. {title}")
        lines.append("")
        lines.append(f"_{desc}_")
        lines.append("")
        for item in items:
            text, method, path, status = item[0], item[1], item[2], item[3]
            contains = item[4] if len(item) > 4 else None
            flags = item[5] if len(item) > 5 else ""
            directive = f"check: {method} {path} {status}"
            if flags:
                directive += f" {flags}"
            if contains:
                directive += f" contains:{contains}"
            lines.append(f"- [ ] {text}  <!-- {directive} -->")
        lines.append("")

    # Otomatik: tum menu sayfalari (kimlik dogrulamali gezinti + PHP hata taramasi)
    pages = discover_menu_pages(project_root)
    if pages:
        idx = len(SECTIONS) + 1
        lines.append(f"## {idx}. Tum Menu Sayfalari (kimlik dogrulamali)")
        lines.append("")
        lines.append("_Her menu sayfasi giris yapilarak gezilir; HTTP 200 ve PHP hatasi olmamasi beklenir._")
        lines.append("")
        for page in pages:
            path = "/phx/" + page.lstrip("/")
            lines.append(f"- [ ] Menu: {page}  <!-- check: GET {path} 200 auth noerror -->")
        lines.append("")

    return "\n".join(lines)


def main() -> int:
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except Exception:
        pass
    parser = argparse.ArgumentParser(description="Admin tur .md uretici")
    here = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.dirname(os.path.dirname(here))
    parser.add_argument("--out", default=os.path.join(here, "ADMIN_TOUR.md"))
    parser.add_argument("--root", default=project_root)
    args = parser.parse_args()

    md = build_markdown(args.root)
    with open(args.out, "w", encoding="utf-8") as fh:
        fh.write(md)
    base = sum(len(items) for _, _, items in SECTIONS)
    pages = len(discover_menu_pages(args.root))
    print(f"[generate_docs] {args.out} yazildi - {len(SECTIONS) + (1 if pages else 0)} bolum, "
          f"{base + pages} kontrol ({pages} menu sayfasi).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
