#!/usr/bin/env python3
"""
Keyword Miner - by Mahyar Momeni (مهیار مومنی)
Turns one seed keyword into hundreds of real search ideas using the free
autocomplete of Google, YouTube and Bing. Classifies search intent, groups
keywords into topic clusters and builds an interactive HTML report + CSV.
No API key needed.

  python keyword_miner.py "خرید لپ تاپ" --lang fa -o laptop.html
"""
import argparse, csv, html, json, re, string, sys, time
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor
import requests

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Gecko/20100101 Firefox/120.0"}
SOURCES = {
    "google": "https://suggestqueries.google.com/complete/search?client=firefox&hl={lang}&q={q}",
    "youtube": "https://suggestqueries.google.com/complete/search?client=firefox&ds=yt&hl={lang}&q={q}",
    "bing": "https://api.bing.com/osjson.aspx?query={q}",
}
FA_LETTERS = list("آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی")
PREFIX = {
    "fa": ["چطور", "چگونه", "چرا", "چیست", "بهترین", "ارزان", "قیمت", "خرید", "مقایسه", "آموزش", "رایگان", "کجا"],
    "en": ["how to", "why", "what is", "best", "cheap", "price", "buy", "vs", "review", "free", "near me", "top"],
}
SUFFIX = {
    "fa": ["قیمت", "خرید", "چیست", "آموزش", "بهترین", "مقایسه", "نمایندگی", "دانلود", "رایگان", "در تهران", "نظرات", "کاربردها"],
    "en": ["price", "buy", "review", "vs", "tutorial", "free", "near me", "alternatives", "for beginners", "tips", "download", "online"],
}
INTENT = {
    "transactional": ["خرید", "قیمت", "ارزان", "فروش", "سفارش", "تخفیف", "نمایندگی", "buy", "price", "cheap", "order", "discount", "coupon", "shop", "near me"],
    "commercial": ["بهترین", "مقایسه", "نظرات", "بررسی", "برتر", "best", "top", "vs", "review", "compare", "alternative"],
    "informational": ["چیست", "چطور", "چگونه", "چرا", "آموزش", "راهنما", "کاربرد", "how", "what", "why", "tutorial", "guide", "learn", "tips", "when", "who"],
}
STOP = set("و در به از با برای که این را how to the a of for in and is on".split())

def suggest(job):
    src, q, lang = job
    try:
        r = requests.get(SOURCES[src].format(q=requests.utils.quote(q), lang=lang), headers=UA, timeout=10)
        r.encoding = "utf-8"
        return src, [s for s in json.loads(r.text)[1] if isinstance(s, str)]
    except Exception:
        return src, []

def expand(seed, lang, deep):
    qs = {seed}
    letters = FA_LETTERS if lang == "fa" else list(string.ascii_lowercase)
    for p in PREFIX.get(lang, PREFIX["en"]): qs.add(f"{p} {seed}")
    for s in SUFFIX.get(lang, SUFFIX["en"]): qs.add(f"{seed} {s}")
    if deep:
        for l in letters: qs.add(f"{seed} {l}")
    return sorted(qs)

def intent(k):
    kl = k.lower()
    for name in ("transactional", "commercial", "informational"):
        if any(re.search(r"(?<!\w)" + re.escape(w) + r"(?!\w)", kl) for w in INTENT[name]):
            return name
    return "other"

def cluster(keys, seed):
    seedw = set(seed.lower().split())
    cnt = Counter(w for k in keys for w in set(k.lower().split()) if w not in seedw and w not in STOP and len(w) > 2)
    common = {w for w, c in cnt.items() if c >= 3}
    out = {}
    for k in keys:
        ws = [w for w in k.lower().split() if w in common]
        out[k] = max(ws, key=lambda w: cnt[w]) if ws else "سایر"
    return out

def report(seeds, rows, path):
    e = html.escape
    data = json.dumps(rows, ensure_ascii=False).replace("</", "<\\/")
    open(path, "w", encoding="utf-8").write(f"""<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Keyword Miner</title><style>
*{{box-sizing:border-box}}body{{margin:0;background:#f8fafc;font-family:Tahoma,Vazirmatn,system-ui,sans-serif;color:#0f172a}}
.w{{max-width:1000px;margin:auto;padding:24px 14px}}h1{{margin:0}}.m{{color:#64748b;margin:4px 0 16px}}
.bar{{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}}input{{flex:1;min-width:200px;border:1px solid #e2e8f0;border-radius:12px;padding:10px 14px;font:inherit}}
.chip{{border:1px solid #e2e8f0;background:#fff;border-radius:99px;padding:8px 14px;cursor:pointer;font:inherit}}.chip.on{{background:#6366f1;color:#fff;border-color:#6366f1}}
.g{{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}}.c{{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px}}.n{{font-size:26px;font-weight:800}}
table{{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0}}td,th{{padding:9px 12px;border-top:1px solid #e2e8f0;text-align:start;font-size:14px}}th{{background:#f1f5f9;color:#64748b;font-size:12px;cursor:pointer}}
.t{{padding:2px 9px;border-radius:99px;font-size:11px}}.transactional{{background:#dcfce7;color:#166534}}.commercial{{background:#fef3c7;color:#92400e}}.informational{{background:#e0e7ff;color:#3730a3}}.other{{background:#f1f5f9;color:#475569}}
button.cp{{border:0;background:none;cursor:pointer;font-size:15px}}</style><div class="w">
<h1>🔎 Keyword Miner</h1><div class="m">{e(" ، ".join(seeds))} • ساخته شده توسط مهیار مومنی</div>
<div class="g" id="st"></div><div class="bar"><input id="q" placeholder="جستجو در کلمات کلیدی..."></div>
<div class="bar" id="ch"></div><div class="bar" id="cl"></div><div style="margin:8px 0"><button class="chip" onclick="cpAll()">📋 کپی همه نتایج نمایش‌داده‌شده</button></div>
<table><thead><tr><th onclick="srt('k')">کلمه کلیدی<th onclick="srt('intent')">قصد جستجو<th onclick="srt('cluster')">دسته<th onclick="srt('src')">منبع<th></tr></thead><tbody id="tb"></tbody></table></div>
<script>const D={data};let I="",C="",S="",sk="k";const L={{transactional:"خرید",commercial:"مقایسه",informational:"اطلاعاتی",other:"سایر"}};
const $=i=>document.getElementById(i);function cur(){{return D.filter(r=>(!I||r.intent==I)&&(!C||r.cluster==C)&&(!S||r.k.includes(S))).sort((a,b)=>String(a[sk]).localeCompare(String(b[sk]),"fa"))}}
function srt(k){{sk=k;draw()}}function chips(id,arr,cur,set){{$(id).innerHTML="";arr.forEach(([v,t])=>{{const b=document.createElement("button");b.className="chip"+(cur==v?" on":"");b.textContent=t;b.onclick=()=>{{set(cur==v?"":v);draw()}};$(id).appendChild(b)}})}}
function draw(){{const r=cur();$("tb").innerHTML=r.slice(0,1500).map(x=>`<tr><td>${{x.k}}</td><td><span class="t ${{x.intent}}">${{L[x.intent]}}</span></td><td>${{x.cluster}}</td><td>${{x.src}}</td><td><button class="cp" onclick="navigator.clipboard.writeText(this.closest('tr').children[0].textContent)">⧉</button></td></tr>`).join("");
const cn={{}};D.forEach(x=>cn[x.cluster]=(cn[x.cluster]||0)+1);
chips("ch",Object.keys(L).map(k=>[k,L[k]+" ("+D.filter(x=>x.intent==k).length+")"]),I,v=>I=v);
chips("cl",Object.entries(cn).sort((a,b)=>b[1]-a[1]).slice(0,14).map(([k,n])=>[k,k+" ("+n+")"]),C,v=>C=v);
$("st").innerHTML=`<div class=c><div class=n>${{D.length}}</div>کل کلمات</div><div class=c><div class=n>${{D.filter(x=>x.intent=="transactional").length}}</div>قصد خرید</div><div class=c><div class=n>${{D.filter(x=>x.intent=="informational").length}}</div>اطلاعاتی</div><div class=c><div class=n>${{r.length}}</div>نمایش</div>`}}
function cpAll(){{navigator.clipboard.writeText(cur().map(x=>x.k).join("\\n"))}}$("q").oninput=e=>{{S=e.target.value;draw()}};draw()</script></html>""")

def main():
    ap = argparse.ArgumentParser(description="Keyword Miner by Mahyar Momeni")
    ap.add_argument("seeds", nargs="+", help="seed keyword(s)")
    ap.add_argument("--lang", default="fa", help="fa / en / ar / tr ...")
    ap.add_argument("--sources", default="google,youtube,bing")
    ap.add_argument("--deep", action="store_true", help="also expand with every letter (more results, slower)")
    ap.add_argument("--delay", type=float, default=0.15, help="seconds between requests")
    ap.add_argument("-o", "--out", default="keywords.html")
    a = ap.parse_args()
    srcs = [s for s in a.sources.split(",") if s in SOURCES] or sys.exit("ERROR: bad --sources")
    found = {}
    for seed in a.seeds:
        jobs = [(s, q, a.lang) for q in expand(seed, a.lang, a.deep) for s in srcs]
        print(f"[{seed}] {len(jobs)} requests ...")
        def run(j):
            time.sleep(a.delay); return suggest(j)
        with ThreadPoolExecutor(3) as ex:
            for src, res in ex.map(run, jobs):
                for k in res:
                    k = " ".join(k.split())
                    if k: found.setdefault(k, set()).add(src)
        print(f"   -> {len(found)} unique so far")
    if not found:
        sys.exit("ERROR: no results. Check your internet/VPN (Google may be blocked).")
    keys = sorted(found)
    cl = cluster(keys, " ".join(a.seeds))
    rows = [{"k": k, "intent": intent(k), "cluster": cl[k], "src": "+".join(sorted(found[k]))} for k in keys]
    report(a.seeds, rows, a.out)
    with open(a.out.rsplit(".", 1)[0] + ".csv", "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f); w.writerow(["keyword", "intent", "cluster", "sources"])
        w.writerows([[r["k"], r["intent"], r["cluster"], r["src"]] for r in rows])
    print(f"Done: {len(rows)} keywords -> {a.out} (+ CSV)")

if __name__ == "__main__":
    main()
