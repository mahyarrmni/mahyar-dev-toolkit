#!/usr/bin/env python3
"""
SEO Audit Bot - by Mahyar Momeni (مهیار مومنی)
Crawls a website and builds a beautiful HTML report: SEO score, page issues,
broken links, missing alt/meta/H1, slow pages, robots/sitemap checks.
No API key needed.

  python seo_audit.py https://example.com --max 100 -o report.html
"""
import argparse, csv, re, sys, time, html
from collections import deque
from concurrent.futures import ThreadPoolExecutor
from urllib.parse import urljoin, urldefrag, urlparse
import requests
from bs4 import BeautifulSoup

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

UA = {"User-Agent": "Mozilla/5.0 (compatible; SEOAuditBot/1.0)"}
SKIP_EXT = re.compile(r"\.(jpe?g|png|gif|svg|webp|avif|ico|pdf|zip|rar|mp4|mp3|css|js|xml|woff2?|ttf)(\?|$)", re.I)

# issue code -> (severity, penalty, Persian text)
ISSUES = {
    "no_title": ("high", 20, "تگ عنوان (Title) ندارد"),
    "title_len": ("med", 8, "طول عنوان مناسب نیست (بهینه: ۳۰ تا ۶۰ کاراکتر)"),
    "no_desc": ("high", 12, "متا دیسکریپشن ندارد"),
    "desc_len": ("low", 5, "طول متا دیسکریپشن مناسب نیست (بهینه: ۷۰ تا ۱۶۰)"),
    "no_h1": ("high", 12, "تگ H1 ندارد"),
    "multi_h1": ("low", 5, "بیش از یک H1 دارد"),
    "alt": ("med", 8, "تصویر بدون متن جایگزین (alt)"),
    "thin": ("med", 8, "محتوای کم (زیر ۳۰۰ کلمه)"),
    "slow": ("med", 8, "پاسخ کند (بیش از ۱.۵ ثانیه)"),
    "no_canon": ("low", 4, "تگ Canonical ندارد"),
    "noindex": ("high", 15, "صفحه noindex است"),
    "no_viewport": ("med", 8, "تگ viewport (موبایل) ندارد"),
    "no_og": ("low", 3, "تگ‌های Open Graph ندارد"),
    "broken": ("high", 10, "لینک شکسته دارد"),
    "http": ("high", 10, "صفحه روی HTTP است نه HTTPS"),
}

def fetch(url):
    t = time.time()
    try:
        r = requests.get(url, headers=UA, timeout=15, allow_redirects=True)
        return r, time.time() - t
    except Exception:
        return None, time.time() - t

def check_link(url, cache):
    if url in cache:
        return cache[url]
    try:
        r = requests.head(url, headers=UA, timeout=10, allow_redirects=True)
        if r.status_code in (403, 405, 501) or r.status_code >= 400:
            r = requests.get(url, headers=UA, timeout=10, stream=True)
        cache[url] = r.status_code
    except Exception:
        cache[url] = 0
    return cache[url]

def analyze(url):
    r, dt = fetch(url)
    page = {"url": url, "status": r.status_code if r is not None else 0, "time": round(dt, 2),
            "issues": [], "links": set(), "title": "", "words": 0, "extra": {}}
    if r is None or "text/html" not in r.headers.get("content-type", ""):
        return page
    soup = BeautifulSoup(r.text, "html.parser")
    iss = page["issues"]
    t = (soup.title.string or "").strip() if soup.title else ""
    page["title"] = t
    if not t: iss.append("no_title")
    elif not 30 <= len(t) <= 60: iss.append("title_len")
    d = soup.find("meta", attrs={"name": re.compile("^description$", re.I)})
    dc = (d.get("content") or "").strip() if d else ""
    if not dc: iss.append("no_desc")
    elif not 70 <= len(dc) <= 160: iss.append("desc_len")
    h1 = soup.find_all("h1")
    if not h1: iss.append("no_h1")
    elif len(h1) > 1: iss.append("multi_h1")
    noalt = [i for i in soup.find_all("img") if not (i.get("alt") or "").strip()]
    if noalt: iss.append("alt"); page["extra"]["alt"] = len(noalt)
    for s in soup(["script", "style", "noscript"]): s.decompose()
    page["words"] = len(soup.get_text(" ").split())
    if page["words"] < 300: iss.append("thin")
    if dt > 1.5: iss.append("slow")
    if not soup.find("link", rel="canonical"): iss.append("no_canon")
    rb = soup.find("meta", attrs={"name": re.compile("^robots$", re.I)})
    if rb and "noindex" in (rb.get("content") or "").lower(): iss.append("noindex")
    if not soup.find("meta", attrs={"name": "viewport"}): iss.append("no_viewport")
    if not soup.find("meta", property="og:title"): iss.append("no_og")
    if urlparse(r.url).scheme == "http": iss.append("http")
    for a in soup.find_all("a", href=True):
        h = urldefrag(urljoin(r.url, a["href"]))[0]
        if urlparse(h).scheme in ("http", "https"): page["links"].add(h)
    return page

def score(page):
    if page["status"] != 200:
        return 0
    return max(0, 100 - sum(ISSUES[i][1] for i in page["issues"]))

def crawl(start, limit, workers):
    host = urlparse(start).netloc
    seen, q, pages = {start}, deque([start]), []
    with ThreadPoolExecutor(workers) as ex:
        while q and len(pages) < limit:
            batch = [q.popleft() for _ in range(min(workers, len(q), limit - len(pages)))]
            for p in ex.map(analyze, batch):
                pages.append(p)
                print(f"  [{len(pages)}/{limit}] {p['status']} {p['url']}", flush=True)
                for l in p["links"]:
                    if urlparse(l).netloc == host and l not in seen and not SKIP_EXT.search(l):
                        seen.add(l); q.append(l)
    return pages

def site_checks(start):
    base = f"{urlparse(start).scheme}://{urlparse(start).netloc}"
    out = []
    for name, path in (("robots.txt", "/robots.txt"), ("sitemap.xml", "/sitemap.xml")):
        r, _ = fetch(base + path)
        out.append((name, bool(r is not None and r.status_code == 200 and len(r.text) > 20)))
    out.append(("HTTPS", urlparse(start).scheme == "https"))
    return out

def report(start, pages, broken, checks, path):
    n = len(pages)
    scores = [score(p) for p in pages]
    avg = round(sum(scores) / n) if n else 0
    color = "#16a34a" if avg >= 80 else "#f59e0b" if avg >= 60 else "#dc2626"
    cnt = {}
    for p in pages:
        for i in p["issues"]: cnt[i] = cnt.get(i, 0) + 1
    if broken: cnt["broken"] = len(broken)
    top = sorted(cnt.items(), key=lambda x: -x[1])
    e = html.escape
    rows = "".join(
        f'<tr><td dir="ltr"><a href="{e(p["url"])}" target="_blank">{e(p["url"][:80])}</a>'
        f'<div class="t">{e(p["title"][:70])}</div></td><td>{p["status"]}</td><td>{p["time"]}s</td><td>{p["words"]}</td>'
        f'<td><span class="s" style="background:{"#16a34a" if s>=80 else "#f59e0b" if s>=60 else "#dc2626"}">{s}</span></td>'
        f'<td>{"".join(f"<span class=tag {ISSUES[i][0]}>{ISSUES[i][2]}</span>" for i in p["issues"])}</td></tr>'
        for p, s in sorted(zip(pages, scores), key=lambda x: x[1]))
    tops = "".join(f'<div class="bar"><span>{ISSUES[k][2]}</span><b>{v}</b><i style="width:{v/max(1,top[0][1])*100:.0f}%"></i></div>' for k, v in top)
    chk = "".join(f'<span class="tag {"ok" if ok else "high"}">{"✓" if ok else "✕"} {nm}</span>' for nm, ok in checks)
    brk = "".join(f'<tr><td dir="ltr">{e(b[0][:70])}</td><td dir="ltr"><a href="{e(b[1])}">{e(b[1][:70])}</a></td><td>{b[2]}</td></tr>' for b in broken[:200])
    doc = f"""<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SEO Audit - {e(urlparse(start).netloc)}</title><style>
*{{box-sizing:border-box}}body{{margin:0;background:#f8fafc;font-family:Tahoma,Vazirmatn,system-ui,sans-serif;color:#0f172a}}
.w{{max-width:1050px;margin:auto;padding:24px 14px}}h1{{margin:0 0 4px}}.m{{color:#64748b}}
.g{{display:grid;grid-template-columns:200px 1fr;gap:14px;margin:18px 0}}@media(max-width:700px){{.g{{grid-template-columns:1fr}}}}
.c{{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;overflow:auto}}
.ring{{width:140px;height:140px;border-radius:50%;margin:auto;display:grid;place-items:center;font-size:40px;font-weight:800;color:{color};
background:conic-gradient({color} {avg*3.6}deg,#e2e8f0 0)}}.ring span{{background:#fff;width:112px;height:112px;border-radius:50%;display:grid;place-items:center}}
table{{width:100%;border-collapse:collapse;font-size:13px}}th{{text-align:start;color:#64748b;padding:8px;font-size:11px}}td{{padding:9px 8px;border-top:1px solid #e2e8f0;vertical-align:top}}
.t{{color:#64748b;font-size:11px}}a{{color:#6366f1;text-decoration:none}}.s{{color:#fff;border-radius:8px;padding:2px 9px;font-weight:700}}
.tag{{display:inline-block;margin:2px;padding:2px 8px;border-radius:99px;font-size:11px;background:#f1f5f9}}.high{{background:#fee2e2;color:#991b1b}}.med{{background:#fef3c7;color:#92400e}}.low{{background:#e0e7ff;color:#3730a3}}.ok{{background:#dcfce7;color:#166534}}
.bar{{position:relative;display:flex;justify-content:space-between;padding:8px 10px;margin:5px 0;border-radius:8px;background:#f1f5f9;overflow:hidden}}.bar span,.bar b{{position:relative;z-index:1}}.bar i{{position:absolute;inset:0 auto 0 0;background:#c7d2fe;opacity:.7}}
h2{{font-size:16px}}</style><div class="w"><h1>گزارش سئو: <span dir="ltr">{e(urlparse(start).netloc)}</span></h1>
<div class="m">{n} صفحه بررسی شد • {len(broken)} لینک شکسته • {time.strftime("%Y-%m-%d %H:%M")} • ساخته شده توسط مهیار مومنی</div>
<div class="g"><div class="c"><div class="ring"><span>{avg}</span></div><p style="text-align:center" class="m">امتیاز کل از ۱۰۰</p></div>
<div class="c"><h2>پرتکرارترین مشکلات</h2>{tops or "🎉 مشکلی پیدا نشد"}<div style="margin-top:10px">{chk}</div></div></div>
<div class="c"><h2>صفحات (ضعیف‌ترین اول)</h2><table><tr><th>صفحه<th>وضعیت<th>سرعت<th>کلمات<th>امتیاز<th>مشکلات</tr>{rows}</table></div>
{f'<div class="c" style="margin-top:14px"><h2>لینک‌های شکسته</h2><table><tr><th>در صفحه<th>لینک خراب<th>کد</tr>{brk}</table></div>' if broken else ''}
</div></html>"""
    open(path, "w", encoding="utf-8").write(doc)

def main():
    ap = argparse.ArgumentParser(description="SEO Audit Bot by Mahyar Momeni")
    ap.add_argument("url")
    ap.add_argument("--max", type=int, default=50, help="max pages to crawl")
    ap.add_argument("--workers", type=int, default=6)
    ap.add_argument("--no-external", action="store_true", help="skip checking external links")
    ap.add_argument("-o", "--out", default="seo-report.html")
    a = ap.parse_args()
    start = a.url if a.url.startswith("http") else "https://" + a.url
    if not urlparse(start).path: start += "/"
    print(f"Crawling {start} ...")
    pages = crawl(start, a.max, a.workers)
    if not pages or pages[0]["status"] == 0:
        sys.exit("ERROR: could not reach the site.")
    print("Checking links ...")
    host, cache, jobs = urlparse(start).netloc, {}, set()
    for p in pages:
        for l in p["links"]:
            if a.no_external and urlparse(l).netloc != host: continue
            jobs.add(l)
    with ThreadPoolExecutor(a.workers * 2) as ex:
        list(ex.map(lambda u: check_link(u, cache), jobs))
    broken = []
    for p in pages:
        bad = [(p["url"], l, cache[l]) for l in p["links"] if l in cache and (cache[l] == 0 or cache[l] >= 400)]
        if bad: p["issues"].append("broken"); broken += bad
    report(start, pages, broken, site_checks(start), a.out)
    with open(a.out.rsplit(".", 1)[0] + ".csv", "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f); w.writerow(["url", "status", "seconds", "words", "score", "issues"])
        for p in pages: w.writerow([p["url"], p["status"], p["time"], p["words"], score(p), "|".join(p["issues"])])
    print(f"Done. Report: {a.out}  (also CSV)")

if __name__ == "__main__":
    main()
