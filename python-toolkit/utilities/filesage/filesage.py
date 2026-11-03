#!/usr/bin/env python3
"""
FileSage - Smart File Organizer, Duplicate & Similar-Image Finder
by Mahyar Momeni (مهیار مومنی)

Safe by design: dry-run by default, never deletes anything, every move is
journaled so it can be fully undone.

  python filesage.py scan     ~/Downloads            # beautiful HTML report
  python filesage.py organize ~/Downloads --by type  # preview   (add --apply to do it)
  python filesage.py dupes    ~/Pictures --apply     # move duplicates to _duplicates/
  python filesage.py undo     ~/Downloads            # revert the last operation
"""
import argparse, base64, hashlib, io, json, os, shutil, sys, time
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass
try:
    from PIL import Image
except ImportError:
    Image = None

CATS = {
    "Images": "jpg jpeg png gif bmp webp tiff tif heic heif svg ico raw cr2 nef arw psd ai",
    "Videos": "mp4 mkv avi mov wmv flv webm m4v mpg mpeg 3gp",
    "Audio": "mp3 wav flac aac ogg m4a wma opus",
    "Documents": "pdf doc docx xls xlsx ppt pptx txt rtf odt ods odp csv md epub mobi",
    "Archives": "zip rar 7z tar gz bz2 xz iso dmg",
    "Code": "py js ts jsx tsx html css php java c cpp h cs go rs rb sh json xml yml yaml sql ipynb",
    "Apps": "exe msi apk deb rpm pkg appimage bat",
    "Fonts": "ttf otf woff woff2",
}
EXT2CAT = {e: c for c, exts in CATS.items() for e in exts.split()}
SKIP_DIRS = {"_duplicates", "node_modules", "__pycache__", "$RECYCLE.BIN", "System Volume Information"}
JOURNAL = ".filesage-journal-"
IMG_EXT = {e for e in CATS["Images"].split() if e not in {"svg", "ico", "psd", "ai", "raw", "cr2", "nef", "arw", "heic", "heif"}}


def human(n):
    for u in ("B", "KB", "MB", "GB", "TB"):
        if n < 1024 or u == "TB":
            return f"{n:.0f} {u}" if u == "B" else f"{n:.1f} {u}"
        n /= 1024


def category(p: Path) -> str:
    return EXT2CAT.get(p.suffix.lower().lstrip("."), "Other")


# ------------------------------------------------------------------ scanning
def scan(root: Path, recursive=True):
    files = []
    for dp, dns, fns in os.walk(root):
        dns[:] = [d for d in dns if not d.startswith(".") and d not in SKIP_DIRS] if recursive else []
        for fn in fns:
            if fn.startswith(".filesage-"):
                continue
            p = Path(dp, fn)
            try:
                if p.is_symlink():
                    continue
                st = p.stat()
            except OSError:
                continue
            files.append({"path": p, "size": st.st_size, "mtime": st.st_mtime, "cat": category(p)})
    return files


def sha(path: Path, limit=None):
    h, left = hashlib.sha256(), limit
    try:
        with open(path, "rb") as f:
            while True:
                n = 1 << 20 if left is None else min(1 << 20, left)
                b = f.read(n)
                if not b:
                    break
                h.update(b)
                if left is not None:
                    left -= len(b)
                    if left <= 0:
                        break
    except OSError:
        return None
    return h.hexdigest()


def find_dupes(files):
    """size -> first-64KB hash -> full SHA-256. Only hashes what could possibly match."""
    by_size = defaultdict(list)
    for f in files:
        if f["size"] > 0:
            by_size[f["size"]].append(f)
    cand = [f for g in by_size.values() if len(g) > 1 for f in g]
    with ThreadPoolExecutor(4) as ex:
        for f, h in zip(cand, ex.map(lambda x: sha(x["path"], 65536), cand)):
            f["h1"] = h
    by_h1 = defaultdict(list)
    for f in cand:
        if f.get("h1"):
            by_h1[(f["size"], f["h1"])].append(f)
    cand2 = [f for g in by_h1.values() if len(g) > 1 for f in g]
    with ThreadPoolExecutor(4) as ex:
        for f, h in zip(cand2, ex.map(lambda x: sha(x["path"]), cand2)):
            f["h2"] = h
    by_h2 = defaultdict(list)
    for f in cand2:
        if f.get("h2"):
            by_h2[f["h2"]].append(f)
    groups = []
    for h, g in by_h2.items():
        if len(g) > 1:   # keeper = oldest file, then shortest path
            g.sort(key=lambda x: (x["mtime"], len(str(x["path"]))))
            groups.append(g)
    return sorted(groups, key=lambda g: -g[0]["size"] * (len(g) - 1))


def dhash(path: Path):
    try:
        with Image.open(path) as im:
            im.draft("L", (64, 64))
            g = im.convert("L").resize((9, 8), Image.Resampling.LANCZOS)
            px = list(g.tobytes())
        bits = 0
        for r in range(8):
            for c in range(8):
                bits = (bits << 1) | (px[r * 9 + c] > px[r * 9 + c + 1])
        return bits
    except Exception:
        return None


def thumb(path: Path):
    try:
        with Image.open(path) as im:
            im.thumbnail((110, 110))
            buf = io.BytesIO()
            im.convert("RGB").save(buf, "JPEG", quality=70)
        return "data:image/jpeg;base64," + base64.b64encode(buf.getvalue()).decode()
    except Exception:
        return ""


def find_similar(files, max_dist=5, cap=4000):
    """Perceptual (dHash) clustering with union-find. Catches resized/recompressed copies."""
    if Image is None:
        return []
    imgs = [f for f in files if f["path"].suffix.lower().lstrip(".") in IMG_EXT and f["size"] > 0][:cap]
    with ThreadPoolExecutor(4) as ex:
        hs = list(ex.map(lambda f: dhash(f["path"]), imgs))
    items = [(f, h) for f, h in zip(imgs, hs) if h is not None]
    parent = list(range(len(items)))

    def find(i):
        while parent[i] != i:
            parent[i] = parent[parent[i]]
            i = parent[i]
        return i
    for i in range(len(items)):
        for j in range(i + 1, len(items)):
            if (items[i][1] ^ items[j][1]).bit_count() <= max_dist:
                parent[find(i)] = find(j)
    groups = defaultdict(list)
    for i, (f, _) in enumerate(items):
        groups[find(i)].append(f)
    out = [sorted(g, key=lambda x: -x["size"]) for g in groups.values() if len(g) > 1]
    return sorted(out, key=lambda g: -len(g))


# -------------------------------------------------------------- moving + undo
def unique_dest(dst: Path) -> Path:
    if not dst.exists():
        return dst
    i = 1
    while True:
        c = dst.with_name(f"{dst.stem} ({i}){dst.suffix}")
        if not c.exists():
            return c
        i += 1


def do_moves(root: Path, moves, apply: bool, label: str):
    if not moves:
        print("Nothing to do.")
        return
    for s, d in moves[:40]:
        print(f"  {s.relative_to(root)}  ->  {d.relative_to(root)}")
    if len(moves) > 40:
        print(f"  ... and {len(moves) - 40} more")
    if not apply:
        print(f"\nDRY RUN: {len(moves)} moves planned. Add --apply to execute.")
        return
    done = []
    try:
        for s, d in moves:
            d = unique_dest(d)
            d.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(s), str(d))
            done.append({"src": str(s), "dst": str(d)})
    finally:  # even if interrupted, what moved is journaled and undoable
        if done:
            j = root / f"{JOURNAL}{datetime.now():%Y%m%d-%H%M%S-%f}.json"
            j.write_text(json.dumps({"op": label, "moves": done}, ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"\nDone: {len(done)} files moved. Undo with:  python filesage.py undo \"{root}\"")


def cmd_undo(root: Path):
    js = sorted(root.glob(JOURNAL + "*.json"))
    if not js:
        sys.exit("No journal found - nothing to undo.")
    j = js[-1]
    data = json.loads(j.read_text(encoding="utf-8"))
    n = 0
    for m in reversed(data["moves"]):
        s, d = Path(m["src"]), Path(m["dst"])
        if d.exists() and not s.exists():
            s.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(d), str(s))
            n += 1
    for dp, dns, fns in os.walk(root, topdown=False):   # remove folders left empty
        p = Path(dp)
        if p != root and not any(p.iterdir()):
            p.rmdir()
    j.rename(j.with_suffix(".undone"))
    print(f"Restored {n}/{len(data['moves'])} files from {j.name} ({data['op']}).")


def plan_organize(root: Path, mode: str, recursive: bool):
    moves = []
    for f in scan(root, recursive):
        p, cats = f["path"], set(CATS) | {"Other"}
        if p.parent != root and p.relative_to(root).parts[0] in cats:
            continue   # already organised
        parts = []
        if mode in ("type", "both"):
            parts.append(f["cat"])
        if mode in ("date", "both"):
            t = datetime.fromtimestamp(f["mtime"])
            parts += [f"{t:%Y}", f"{t:%m}"]
        dst = root.joinpath(*parts, p.name)
        if dst != p:
            moves.append((p, dst))
    return moves


# --------------------------------------------------------------------- report
TEMPLATE = r"""<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FileSage Report</title><style>
:root{--a:#6366f1;--b:#06b6d4;--m:#64748b;--l:#e2e8f0}*{box-sizing:border-box}
body{margin:0;background:#f8fafc;font-family:Tahoma,Vazirmatn,system-ui,sans-serif;color:#0f172a;font-size:14px}.w{max-width:1050px;margin:auto;padding:22px 14px}
h1{margin:0;font-size:26px}h1 i{display:inline-block;width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--a),var(--b));vertical-align:middle;margin-inline-end:8px}
.m{color:var(--m);margin:6px 0 18px}.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:14px}
.c{background:#fff;border:1px solid var(--l);border-radius:16px;padding:16px;margin-bottom:14px}.g .c{margin:0}.n{font-size:28px;font-weight:800}.k{color:var(--m);font-size:12px}
h2{font-size:16px;margin:0 0 10px}.stack{display:flex;height:22px;border-radius:99px;overflow:hidden;margin:6px 0 12px;background:#e2e8f0}
.lg{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px}.lg span:before{content:"";display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--c);margin-inline-end:5px}
table{width:100%;border-collapse:collapse}td,th{padding:8px;border-top:1px solid var(--l);text-align:start;font-size:13px}th{color:var(--m);font-size:11px;border:0}
.p{direction:ltr;text-align:right;font-family:ui-monospace,Consolas,monospace;font-size:12px;word-break:break-all}
.grp{border:1px solid var(--l);border-radius:12px;padding:10px;margin:8px 0}.grp b{color:#dc2626}.keep{color:#16a34a;font-weight:700}
.th{display:flex;flex-wrap:wrap;gap:10px}.th div{width:130px;font-size:10px;color:var(--m);direction:ltr;word-break:break-all}.th img{width:110px;height:110px;object-fit:cover;border-radius:10px;border:1px solid var(--l);display:block}
</style><div class="w"><h1><i></i>FileSage</h1><div class="m" id="meta"></div><div class="g" id="kpi"></div>
<div class="c"><h2>فضای مصرفی بر اساس نوع فایل</h2><div class="stack" id="stack"></div><div class="lg" id="lg"></div></div>
<div class="c"><h2>🗂 فایل‌های تکراری دقیق (همان محتوا)</h2><div id="dup"></div></div>
<div class="c"><h2>🖼 تصاویر مشابه (تغییر اندازه / فشرده‌سازی مجدد)</h2><div id="sim"></div></div>
<div class="c"><h2>📦 ۲۰ فایل سنگین‌تر</h2><table id="big"></table></div></div>
<script>const D=__DATA__;const $=i=>document.getElementById(i);
const H=n=>{const u=["B","KB","MB","GB","TB"];let i=0;while(n>=1024&&i<4){n/=1024;i++}return (i?n.toFixed(1):n)+" "+u[i]};
const esc=s=>String(s).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
const COL=["#6366f1","#06b6d4","#f59e0b","#10b981","#ef4444","#8b5cf6","#ec4899","#64748b","#94a3b8"];
$("meta").textContent=D.root+" • "+D.when+" • ساخته شده توسط مهیار مومنی";
$("kpi").innerHTML=[[D.files.toLocaleString(),"تعداد فایل"],[H(D.size),"حجم کل"],[D.dups.length,"گروه تکراری"],[H(D.wasted),"قابل آزادسازی"],[D.sims.length,"گروه تصویر مشابه"]].map(([n,t])=>`<div class=c><div class=n>${n}</div><div class=k>${t}</div></div>`).join("");
$("stack").innerHTML=D.cats.map((c,i)=>`<div title="${c.name}" style="width:${c.size/Math.max(1,D.size)*100}%;background:${COL[i%9]}"></div>`).join("");
$("lg").innerHTML=D.cats.map((c,i)=>`<span style="--c:${COL[i%9]}">${c.name}: ${H(c.size)} (${c.count})</span>`).join("");
$("dup").innerHTML=D.dups.map(g=>`<div class=grp><div><b>−${H(g.wasted)}</b> • ${g.files.length} نسخه • ${H(g.size)} هر کدام</div>${g.files.map((f,i)=>`<div class=p>${i?"":'<span class=keep>✔ نگه‌دار </span>'}${esc(f)}</div>`).join("")}</div>`).join("")||"🎉 فایل تکراری پیدا نشد";
$("sim").innerHTML=D.sims.map(g=>`<div class=grp><div class=th>${g.map(f=>`<div><img src="${f.thumb}"><br>${esc(f.path)}<br>${H(f.size)}</div>`).join("")}</div></div>`).join("")||(D.pil?"تصویر مشابهی پیدا نشد":"برای این بخش کتابخانه Pillow لازم است");
$("big").innerHTML="<tr><th>فایل<th>حجم<th>تاریخ</tr>"+D.big.map(f=>`<tr><td class=p>${esc(f.path)}<td>${H(f.size)}<td>${f.date}</tr>`).join("");
</script></html>"""


def cmd_scan(root: Path, out: str, recursive=True):
    t0 = time.time()
    print("Scanning ...")
    files = scan(root, recursive)
    print(f"  {len(files)} files. Looking for exact duplicates ...")
    dups = find_dupes(files)
    print(f"  {len(dups)} duplicate groups. Looking for similar images ...")
    sims = find_similar(files)
    rel = lambda p: str(p.relative_to(root))
    cats = defaultdict(lambda: [0, 0])
    for f in files:
        cats[f["cat"]][0] += 1
        cats[f["cat"]][1] += f["size"]
    data = {
        "root": str(root), "when": f"{datetime.now():%Y-%m-%d %H:%M}", "files": len(files),
        "size": sum(f["size"] for f in files), "pil": Image is not None,
        "cats": sorted([{"name": k, "count": v[0], "size": v[1]} for k, v in cats.items()], key=lambda c: -c["size"]),
        "dups": [{"size": g[0]["size"], "wasted": g[0]["size"] * (len(g) - 1), "files": [rel(f["path"]) for f in g]} for g in dups[:60]],
        "sims": [[{"path": rel(f["path"]), "size": f["size"], "thumb": thumb(f["path"])} for f in g[:8]] for g in sims[:30]],
        "big": [{"path": rel(f["path"]), "size": f["size"], "date": f"{datetime.fromtimestamp(f['mtime']):%Y-%m-%d}"}
                for f in sorted(files, key=lambda x: -x["size"])[:20]],
    }
    data["wasted"] = sum(g[0]["size"] * (len(g) - 1) for g in dups)
    Path(out).write_text(TEMPLATE.replace("__DATA__", json.dumps(data, ensure_ascii=False).replace("</", "<\\/")), encoding="utf-8")
    print(f"Report -> {out}  ({time.time() - t0:.1f}s) | reclaimable from exact duplicates: {human(data['wasted'])}")


def main():
    ap = argparse.ArgumentParser(description="FileSage by Mahyar Momeni")
    sub = ap.add_subparsers(dest="cmd", required=True)
    for name in ("scan", "organize", "dupes", "undo"):
        s = sub.add_parser(name)
        s.add_argument("folder")
        if name == "scan":
            s.add_argument("-o", "--out", default="filesage-report.html")
        if name == "organize":
            s.add_argument("--by", choices=["type", "date", "both"], default="type")
            s.add_argument("--recursive", action="store_true", help="also organise files inside sub-folders")
        if name in ("organize", "dupes"):
            s.add_argument("--apply", action="store_true", help="actually move files (default is a dry run)")
    a = ap.parse_args()
    root = Path(a.folder).expanduser().resolve()
    if not root.is_dir():
        sys.exit(f"ERROR: folder not found: {root}")
    if a.cmd == "scan":
        cmd_scan(root, a.out)
    elif a.cmd == "organize":
        do_moves(root, plan_organize(root, a.by, a.recursive), a.apply, f"organize:{a.by}")
    elif a.cmd == "dupes":
        moves = [(f["path"], root / "_duplicates" / f"{g[0]['size']}-{i}" / f["path"].name)
                 for i, g in enumerate(find_dupes(scan(root))) for f in g[1:]]
        do_moves(root, moves, a.apply, "dupes")
    else:
        cmd_undo(root)


if __name__ == "__main__":
    main()
