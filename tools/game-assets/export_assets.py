"""Render the fictional game images in stem-game-assets.html and write them as PNGs.

Usage (needs patchright + Google Chrome):
  { printf '<!doctype html><meta charset="utf-8">\\n'; cat stem-game-assets.html; } > preview.html
  python export_assets.py "$PWD/preview.html" ../../assets/games

Output: <out>/{windows_only,mac_only,both}/<genre>/gXX_{cover,ss1,ss2,ss3}.png and <out>/products.csv
"""
import base64, csv, os, sys
from patchright.sync_api import sync_playwright

PREVIEW = sys.argv[1]
OUT = sys.argv[2]
OS_DIR = {"win": "windows_only", "mac": "mac_only", "both": "both"}
GENRE_DIR = {"FPS": "fps", "MMO": "mmo", "クラフト": "craft", "PVP": "pvp", "RPG": "rpg", "パズル": "puzzle", "レース": "race", "ホラー": "horror"}

JS = """async () => {
  for (let i = 0; i < 60; i++) {
    const imgs = [...document.querySelectorAll('img[data-file]')];
    if (imgs.length === 48 && imgs.every(im => im.complete && im.naturalWidth > 0)) break;
    await new Promise(r => setTimeout(r, 500));
  }
  const out = {};
  for (const [name, a] of assets) {
    const buf = await a.blob.arrayBuffer();
    let s = ''; const b = new Uint8Array(buf);
    for (let i = 0; i < b.length; i += 0x8000) s += String.fromCharCode.apply(null, b.subarray(i, i + 0x8000));
    out[name] = btoa(s);
  }
  return {games: GAMES.map(g => ({id: g.id, title: g.title, en: g.en, genre: g.genre, os: g.os, price: g.price, size: g.size, dev: g.dev, release: g.release, desc: g.desc})), files: out};
}"""

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, channel="chrome")
    page = browser.new_page()
    page.goto("file://" + PREVIEW)
    data = page.evaluate(JS, isolated_context=False)
    browser.close()

games, files = data["games"], data["files"]
if len(files) != 48:
    sys.exit(f"expected 48 images, got {len(files)}")

rows = []
for g in games:
    key = "both" if len(g["os"]) == 2 else ("win" if g["os"][0] == "Windows" else "mac")
    rel = f"{OS_DIR[key]}/{GENRE_DIR[g['genre']]}"
    os.makedirs(os.path.join(OUT, rel), exist_ok=True)
    paths = []
    for suffix in ("cover", "ss1", "ss2", "ss3"):
        name = f"{g['id']}_{suffix}.png"
        with open(os.path.join(OUT, rel, name), "wb") as f:
            f.write(base64.b64decode(files[name]))
        paths.append(f"{rel}/{name}")
    rows.append([g["id"], g["title"], g["en"], g["genre"], " / ".join(g["os"]),
                 int("Windows" in g["os"]), int("Mac" in g["os"]), g["price"], g["size"],
                 g["dev"], g["release"], g["desc"], *paths])

with open(os.path.join(OUT, "products.csv"), "w", encoding="utf-8-sig", newline="") as f:
    w = csv.writer(f)
    w.writerow(["product_id", "title", "title_en", "genre", "platform", "windows", "mac", "price_yen", "size_gb",
                "developer", "release_date", "description", "cover_image", "screenshot1", "screenshot2", "screenshot3"])
    w.writerows(rows)
print(f"wrote {len(files)} images + products.csv")
