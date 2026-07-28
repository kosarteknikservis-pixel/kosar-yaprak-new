#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build_tour.py — ADMIN_TOUR.md dosyasini okur, makine-okunabilir bir tur (tour.json)
uretir ve "to-do list turu"nu CANLI RENKLERLE calistirir.

- Her kontrol maddesini HTTP ile dogrular (harici bagimlilik yok, sadece stdlib).
- Bir madde basarisiz olsa bile DURMAZ; tum turu tamamlar ("durmasin").
- Sonunda renkli ozet basar.

Kullanim:
    python build_tour.py [--base-url http://localhost/3dhesap] [--md ADMIN_TOUR.md] [--no-color]
"""
from __future__ import annotations

import argparse
import http.cookiejar
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.request
import urllib.error

# ------------------------------------------------------------------ renkler --
class C:
    RESET = "\033[0m"
    BOLD = "\033[1m"
    DIM = "\033[2m"
    RED = "\033[91m"
    GREEN = "\033[92m"
    YELLOW = "\033[93m"
    BLUE = "\033[94m"
    MAGENTA = "\033[95m"
    CYAN = "\033[96m"
    WHITE = "\033[97m"
    BG_GREEN = "\033[42m"
    BG_RED = "\033[41m"
    BG_BLUE = "\033[44m"
    BG_MAGENTA = "\033[45m"


def enable_windows_ansi() -> None:
    """Windows terminallerinde ANSI (VT) islemeyi ac."""
    if os.name != "nt":
        return
    try:
        import ctypes

        kernel32 = ctypes.windll.kernel32
        # STD_OUTPUT_HANDLE = -11, ENABLE_VIRTUAL_TERMINAL_PROCESSING = 0x0004
        handle = kernel32.GetStdHandle(-11)
        mode = ctypes.c_uint32()
        if kernel32.GetConsoleMode(handle, ctypes.byref(mode)):
            kernel32.SetConsoleMode(handle, mode.value | 0x0004)
    except Exception:
        pass


NO_COLOR = False


def paint(text: str, *codes: str) -> str:
    if NO_COLOR or not codes:
        return text
    return "".join(codes) + text + C.RESET


# ---------------------------------------------------------------- ayristirma --
CHECK_RE = re.compile(
    r"^- \[[ xX]\]\s+(?P<text>.*?)\s*<!--\s*check:\s*(?P<method>[A-Z]+)\s+(?P<path>\S+)\s+(?P<status>\d+)"
    r"(?P<rest>[^>]*?)\s*-->\s*$"
)
SECTION_RE = re.compile(r"^##\s+\d+\.\s+(?P<title>.+?)\s*$")

# PHP hata izleri (sayfa govdesinde bulunmamali)
PHP_ERROR_MARKERS = [
    "Fatal error", "Parse error", "Uncaught",
    "<b>Warning</b>", "<b>Notice</b>", "<b>Deprecated</b>",
    "Stack trace:", "PDOException",
]


def parse_markdown(md_path: str) -> list[dict]:
    sections: list[dict] = []
    current: dict | None = None
    with open(md_path, "r", encoding="utf-8") as fh:
        for line in fh:
            line = line.rstrip("\n")
            m_sec = SECTION_RE.match(line)
            if m_sec:
                current = {"title": m_sec.group("title"), "items": []}
                sections.append(current)
                continue
            m_it = CHECK_RE.match(line)
            if m_it and current is not None:
                rest = m_it.group("rest") or ""
                m_contains = re.search(r"contains:(.+)$", rest.strip())
                contains = m_contains.group(1).strip() if m_contains else None
                current["items"].append({
                    "text": m_it.group("text"),
                    "method": m_it.group("method"),
                    "path": m_it.group("path"),
                    "expect_status": int(m_it.group("status")),
                    "contains": contains,
                    "auth": bool(re.search(r"\bauth\b", rest)),
                    "noerror": bool(re.search(r"\bnoerror\b", rest)),
                })
    return sections


# -------------------------------------------------------------------- giris --
class Session:
    """Kimlik dogrulamali gezinti icin cerez tasiyan opener."""

    def __init__(self) -> None:
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.opener.addheaders = [("User-Agent", "phx-tour/1.0")]
        self.logged_in = False

    def get(self, url: str, timeout: float = 15.0) -> tuple[int, str, str]:
        try:
            with self.opener.open(url, timeout=timeout) as resp:
                return resp.getcode(), resp.read().decode("utf-8", errors="replace"), resp.geturl()
        except urllib.error.HTTPError as e:
            body = ""
            try:
                body = e.read().decode("utf-8", errors="replace")
            except Exception:
                pass
            return e.code, body, url

    def login(self, base_url: str, username: str, password: str) -> tuple[bool, str]:
        login_url = base_url.rstrip("/") + "/phx/login.php"
        status, body, _ = self.get(login_url)
        if status != 200:
            return False, f"giris sayfasi HTTP {status}"
        m = re.search(r'name="csrf_token"\s+value="([^"]+)"', body)
        if not m:
            return False, "csrf_token bulunamadi"
        data = urllib.parse.urlencode({
            "csrf_token": m.group(1),
            "username": username,
            "password": password,
        }).encode()
        req = urllib.request.Request(login_url, data=data, method="POST")
        try:
            with self.opener.open(req, timeout=15.0) as resp:
                final = resp.geturl()
                rbody = resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e:
            return False, f"giris POST HTTP {e.code}"
        # Basari: index.php'ye yonlendi ve login formu yok
        if "login.php" in final or 'name="csrf_token"' in rbody:
            return False, "kimlik dogrulama basarisiz (parola/kullanici?)"
        self.logged_in = True
        return True, "giris basarili"


# ------------------------------------------------------------------- kontrol --
def run_check(base_url: str, item: dict, session: "Session | None", timeout: float = 15.0) -> dict:
    url = base_url.rstrip("/") + item["path"]
    result = {"ok": False, "status": None, "detail": "", "url": url}

    if item.get("auth"):
        if session is None or not session.logged_in:
            result["detail"] = "giris yok — atlandi"
            result["skipped"] = True
            return result
        status, body, final = session.get(url, timeout)
    else:
        req = urllib.request.Request(url, method=item["method"], headers={"User-Agent": "phx-tour/1.0"})
        try:
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                status = resp.getcode()
                body = resp.read().decode("utf-8", errors="replace")
                final = resp.geturl()
        except urllib.error.HTTPError as e:
            status = e.code
            try:
                body = e.read().decode("utf-8", errors="replace")
            except Exception:
                body = ""
            final = url
        except Exception as e:
            result["detail"] = f"baglanti hatasi: {e}"
            return result

    result["status"] = status
    status_ok = (status == item["expect_status"])

    contains_ok = True
    if item["contains"]:
        contains_ok = item["contains"] in body

    # Kimlik dogrulamali sayfa yanlislikla login'e dustu mu?
    redirected_login = item.get("auth") and ("login.php" in final or 'name="csrf_token"' in body)

    err_hit = None
    if item.get("noerror"):
        for marker in PHP_ERROR_MARKERS:
            if marker in body:
                err_hit = marker
                break

    result["ok"] = status_ok and contains_ok and not err_hit and not redirected_login

    parts = [f"HTTP {status} (beklenen {item['expect_status']})"]
    if item["contains"]:
        parts.append("icerik VAR" if contains_ok else f"icerik YOK ('{item['contains']}')")
    if redirected_login:
        parts.append("LOGIN'e dustu")
    if err_hit:
        parts.append(f"PHP HATASI: {err_hit}")
    result["detail"] = ", ".join(parts)
    return result


def progress_bar(done: int, total: int, width: int = 28) -> str:
    filled = int(width * done / total) if total else width
    bar = "█" * filled + "░" * (width - filled)
    pct = int(100 * done / total) if total else 100
    return f"{bar} {pct:3d}%"


# ----------------------------------------------------------------------- ana --
def run_tour(base_url: str, sections: list[dict], session: "Session | None") -> tuple[int, int, int]:
    total = sum(len(s["items"]) for s in sections)
    passed = 0
    skipped = 0
    done = 0

    title = " phxcore0 ADMIN — CANLI TUR "
    print()
    print(paint(f"╔{'═' * (len(title))}╗", C.CYAN, C.BOLD))
    print(paint(f"║{title}║", C.BG_MAGENTA, C.WHITE, C.BOLD))
    print(paint(f"╚{'═' * (len(title))}╝", C.CYAN, C.BOLD))
    print(paint(f"  Hedef: {base_url}", C.DIM))
    print(paint(f"  Toplam {total} kontrol — tur durmadan tamamlanacak.", C.DIM))
    print()

    for s_idx, sec in enumerate(sections, start=1):
        header = f"  ◆ {s_idx}. {sec['title']}"
        print(paint(header, C.BLUE, C.BOLD))
        for item in sec["items"]:
            res = run_check(base_url, item, session)
            done += 1
            if res.get("skipped"):
                skipped += 1
                mark = paint(" ~ ", C.BG_BLUE, C.WHITE, C.BOLD)
                text = paint(item["text"], C.YELLOW)
            elif res["ok"]:
                passed += 1
                mark = paint(" ✔ ", C.BG_GREEN, C.WHITE, C.BOLD)
                text = paint(item["text"], C.GREEN)
            else:
                mark = paint(" ✘ ", C.BG_RED, C.WHITE, C.BOLD)
                text = paint(item["text"], C.RED, C.BOLD)
            detail = paint(f"({res['detail']})", C.DIM)
            print(f"    {mark} {text} {detail}")
            time.sleep(0.02)  # akici "tur" hissi
        print(f"    {paint(progress_bar(done, total), C.MAGENTA)}")
        print()

    # ozet
    failed = total - passed - skipped
    line = "─" * 46
    print(paint(line, C.CYAN))
    if failed == 0 and skipped == 0:
        print(paint(f"  ✅ TUR TAMAM — {passed}/{total} kontrol GECTI. Her sey hatasiz.", C.GREEN, C.BOLD))
    elif failed == 0:
        print(paint(f"  ✅ TUR TAMAM — {passed}/{total} gecti, {skipped} atlandi (giris yok).", C.GREEN, C.BOLD))
    else:
        print(paint(f"  ⚠ TUR TAMAM — {passed} gecti, {failed} BASARISIZ, {skipped} atlandi (toplam {total}).", C.YELLOW, C.BOLD))
    print(paint(line, C.CYAN))
    print()
    return passed, failed, skipped


def main() -> int:
    global NO_COLOR
    # Windows konsollarinda (cp1254) Unicode cikti icin UTF-8'e gec.
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8")
        except Exception:
            pass
    here = os.path.dirname(os.path.abspath(__file__))
    parser = argparse.ArgumentParser(description="Admin canli tur calistir")
    parser.add_argument("--base-url", default=os.environ.get("PHX_BASE_URL", "http://localhost/3dhesap"))
    parser.add_argument("--md", default=os.path.join(here, "ADMIN_TOUR.md"))
    parser.add_argument("--json-out", default=os.path.join(here, "tour.json"))
    parser.add_argument("--user", default=os.environ.get("PHX_ADMIN_USER", ""))
    parser.add_argument("--pass", dest="password", default=os.environ.get("PHX_ADMIN_PASS", ""))
    parser.add_argument("--no-color", action="store_true")
    args = parser.parse_args()

    NO_COLOR = args.no_color
    if not NO_COLOR:
        enable_windows_ansi()

    if not os.path.exists(args.md):
        print(paint(f"[build_tour] {args.md} bulunamadi. Once generate_docs.py calistirin.", C.RED, C.BOLD))
        return 2

    sections = parse_markdown(args.md)

    # tur.json yaz (makine-okunabilir)
    with open(args.json_out, "w", encoding="utf-8") as fh:
        json.dump({"base_url": args.base_url, "sections": sections}, fh, ensure_ascii=False, indent=2)

    # Kimlik dogrulamali kontrol var mi? Varsa giris yap.
    needs_auth = any(it.get("auth") for s in sections for it in s["items"])
    session: Session | None = None
    if needs_auth:
        session = Session()
        if args.user and args.password:
            ok, msg = session.login(args.base_url, args.user, args.password)
            color = C.GREEN if ok else C.RED
            print(paint(f"  [giris] {args.user}: {msg}", color, C.BOLD))
        else:
            print(paint("  [giris] kimlik verilmedi — menu sayfalari ATLANACAK "
                        "(--user/--pass veya PHX_ADMIN_USER/PASS).", C.YELLOW))

    passed, failed, skipped = run_tour(args.base_url, sections, session)
    # "durmasin": basarisiz olsa bile turu tamamla. CI icin exit kodu: hata varsa 1.
    return 1 if failed > 0 else 0


if __name__ == "__main__":
    sys.exit(main())
