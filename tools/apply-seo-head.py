from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
BASE = "https://kupsiapku.cz"
APP_SLUGS = ["zdravi", "finance", "investice", "cile", "vztahy", "rozvoj", "firma", "podnikani", "prace"]
LEGAL = ["ochrana-osobnich-udaju.html", "obchodni-podminky.html", "cookies.html"]

pairs = []
for slug in APP_SLUGS:
    pairs.append((f"aplikace-{slug}.html", f"en/aplikace-{slug}.html", True))
for name in LEGAL:
    pairs.append((name, f"en/{name}", False))


def strip_existing(html: str) -> str:
    html = re.sub(r'<meta\s+name=["\']robots["\'][^>]*>\s*', '', html, flags=re.I)
    html = re.sub(r'<link\s+rel=["\']canonical["\'][^>]*>\s*', '', html, flags=re.I)
    html = re.sub(r'<link\s+rel=["\']alternate["\'][^>]*hreflang=["\'][^"\']+["\'][^>]*>\s*', '', html, flags=re.I)
    return html


def add_head(path: str, canonical: str, cs_url: str, en_url: str, image_preview: bool) -> None:
    file = ROOT / path
    html = strip_existing(file.read_text(encoding="utf-8"))
    robots = "index,follow,max-image-preview:large" if image_preview else "index,follow"
    block = (
        f'<meta name="robots" content="{robots}">'
        f'<link rel="canonical" href="{canonical}">'
        f'<link rel="alternate" hreflang="cs-CZ" href="{cs_url}">'
        f'<link rel="alternate" hreflang="en" href="{en_url}">'
        f'<link rel="alternate" hreflang="x-default" href="{cs_url}">'
    )
    marker = '<link rel="stylesheet"'
    idx = html.find(marker)
    if idx < 0:
        idx = html.find('</head>')
    if idx < 0:
        raise RuntimeError(f"No <head> insertion point in {path}")
    html = html[:idx] + block + html[idx:]
    file.write_text(html, encoding="utf-8")


for cs_path, en_path, image_preview in pairs:
    cs_url = f"{BASE}/{cs_path}"
    en_url = f"{BASE}/{en_path}"
    add_head(cs_path, cs_url, cs_url, en_url, image_preview)
    add_head(en_path, en_url, cs_url, en_url, image_preview)

print(f"SEO head updated for {len(pairs) * 2} HTML files")
