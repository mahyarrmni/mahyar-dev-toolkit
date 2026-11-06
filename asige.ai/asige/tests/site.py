"""Synthetic site served on localhost for crawler/pipeline tests (covers common SEO defects)."""
import random
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

TOPICS = {
    "coffee": "espresso roast grinder brew arabica crema barista beans latte extraction pressure portafilter tamping milk foam".split(),
    "seo": "backlinks crawl indexing canonical sitemap keyword ranking snippet schema redirect anchor authority crawler robots".split(),
    "fa_coffee": "قهوه اسپرسو دانه برشته آسیاب عربیکا کافئین لاته فرنچ‌پرس طعم دم‌آوری".split(),
    "fa_seo": "سئو لینک‌سازی خزش ایندکس رتبه‌بندی گوگل محتوا نقشه‌سایت ریدایرکت کلمه‌کلیدی".split(),
}
FILL = "the of and to in for with on a is are you can how best".split()
FILL_FA = "و در به از که این برای با را است".split()


def para(topic, rng, n=170):
    fill = FILL_FA if topic.startswith("fa") else FILL
    words = TOPICS[topic]
    return " ".join(rng.choice(words) if rng.random() < 0.6 else rng.choice(fill) for _ in range(n)) + "."


def page(title, body, links=(), h1=None, meta=None, head="", lang="en", extra=""):
    nav = " ".join(f'<a href="{h}">{t}</a>' for h, t in links)
    m = f'<meta name="description" content="{meta}">' if meta else ""
    return (f'<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><title>{title}</title>{m}{head}</head>'
            f'<body><nav><a href="/">Home</a></nav><h1>{h1 or title}</h1><p>{body}</p><div>{nav}</div>{extra}</body></html>')


def build_site(base):
    rng = random.Random(1)
    R = {}
    H = {"Content-Type": "text/html; charset=utf-8"}

    def add(path, html, status=200, headers=None):
        R[path] = (status, {**H, **(headers or {})}, html)

    coffee = {f"/coffee/{s}": t for s, t in [("espresso", "Espresso brewing guide"), ("grinders", "Best coffee grinders"),
              ("latte-art", "Latte art for beginners"), ("arabica", "Arabica beans explained"),
              ("crema", "What is crema in espresso"), ("roasting", "Home coffee roasting")]}
    seo = {f"/seo/{s}": t for s, t in [("sitemaps", "XML sitemaps explained"), ("redirects", "Redirects and SEO"),
           ("backlinks", "Backlinks and authority"), ("robots", "Robots txt guide"), ("keywords", "Keyword research basics"),
           ("schema", "Schema markup guide")]}
    canon_body = para("seo", rng) + " canonical canonical canonical tags duplicate"
    for path, title in coffee.items():
        sib = rng.sample([p for p in coffee if p != path], 2)
        extra = ""
        if path.endswith("espresso"):
            extra = ('<script type="application/ld+json">{"@type":"Article","headline":"Espresso","datePublished":"2024-01-01",'
                     '"author":{"@type":"Person","name":"Sara"}}</script>')
        add(path, page(title, para("coffee", rng), [("/coffee/", "Coffee hub")] + [(s, "related") for s in sib],
                       meta=f"{title} - practical tips for coffee lovers and baristas worldwide", extra=extra))
    for path, title in seo.items():
        sib = rng.sample([p for p in seo if p != path], 2)
        extra = '<script type="application/ld+json">{bad json</script>' if path.endswith("robots") else ""
        add(path, page(title, para("seo", rng), [("/seo/", "SEO hub")] + [(s, "related") for s in sib],
                       meta=f"{title} - a practical SEO guide with examples for site owners", extra=extra))
    add("/seo/canonical-tags", page("Canonical tags guide", canon_body, [("/seo/", "SEO hub")], meta="Canonical tags guide with examples and common mistakes for SEO",
        extra='<script type="application/ld+json">{"@type":"Article","headline":"Canonical"}</script>'))
    add("/seo/canonical-tag-guide", page("Canonical tag guide", canon_body.replace("duplicate", "duplicates"), [("/seo/", "SEO hub")],
        meta="Canonical tag guide with examples and common mistakes for SEO"))
    add("/seo/canonical-copy", page("Canonical tags guide", canon_body, [("/seo/", "SEO hub")], meta="Canonical tags guide with examples and common mistakes for SEO"))
    add("/coffee/", page("Coffee hub", "All coffee articles", [(p, t) for p, t in coffee.items()], meta="Coffee hub with espresso grinders and brewing guides for baristas"))
    add("/seo/", page("SEO hub", "All SEO articles", [(p, t) for p, t in {**seo, "/seo/canonical-tags": "c", "/seo/canonical-tag-guide": "c2"}.items()],
        meta="SEO hub with technical SEO guides and tutorials for website owners",
        head=f'<link rel="alternate" hreflang="fa" href="{base}/fa/seo-chist"><link rel="alternate" hreflang="en" href="{base}/seo/">'))
    fa = [("/fa/seo-chist", "سئو چیست", "fa_seo"), ("/fa/seo-amoozesh", "آموزش سئو سایت", "fa_seo"),
          ("/fa/ghahve", "قهوه و انواع آن", "fa_coffee"), ("/fa/ghahve-espresso", "اسپرسو چیست", "fa_coffee")]
    for path, title, tp in fa:
        add(path, page(title, para(tp, rng), [("/fa/", "خانه")], lang="fa", meta=f"{title} راهنمای کامل و کاربردی برای علاقه‌مندان این حوزه"))
    add("/fa/", page("راهنما", "فهرست مقالات", [(p, t) for p, t, _ in fa], lang="fa", meta="فهرست مقالات فارسی درباره سئو و قهوه برای مطالعه"))
    add("/print/espresso", page("Espresso brewing guide", para("coffee", rng), [], head=f'<link rel="canonical" href="{base}/coffee/espresso">'))
    add("/private", page("Private area", para("seo", rng), [], head='<meta name="robots" content="noindex,follow">'))
    add("/admin/", page("Admin", "secret", []))
    add("/old", "", 301, {"Location": "/old2"})
    add("/old2", "", 301, {"Location": "/coffee/espresso"})
    add("/loop-a", "", 302, {"Location": "/loop-b"})
    add("/loop-b", "", 302, {"Location": "/loop-a"})
    add("/thin", page("Thin page", "tiny content here", []))
    add("/orphan", page("Orphan page about sitemaps", para("seo", rng), [], meta="An orphan page that only exists in the sitemap file of the site"))
    for i in range(1, 4):
        nxt = f'<link rel="next" href="{base}/blog/page/{i + 1}">'
        prv = f'<link rel="prev" href="{base}/blog/page/{i - 1}">' if i > 1 else ""
        add(f"/blog/page/{i}", page(f"Blog page {i}", para("coffee", rng, 80), [(f"/blog/page/{i + 1}", "next")], head=nxt + prv))
    for i in range(1, 7):
        add(f"/d{i}", page(f"Deep page {i}", para("seo", rng, 60), [(f"/d{i + 1}", "next")] if i < 6 else []))
    add("/about", page("About us", para("seo", rng, 90), [("/missing", "broken link"), ("http://external.invalid/x", "external")]))
    add("/", page("Home", "Welcome to the test site", [("/coffee/", "coffee"), ("/seo/", "seo"), ("/fa/", "fa"), ("/blog/page/1", "blog"),
        ("/old", "old"), ("/loop-a", "loop"), ("/d1", "deep"), ("/about", "about"), ("/thin", "thin"), ("/private", "p"),
        ("/admin/", "admin"), ("/print/espresso", "print"), ("/seo/canonical-copy", "copy")], meta="Home page of the synthetic test site for crawler checks"))
    sm = ["/", "/coffee/", "/seo/", "/orphan", "/private", "/print/espresso"] + list(coffee) + list(seo)
    add("/sitemap.xml", "<urlset>" + "".join(f"<url><loc>{base}{p}</loc></url>" for p in sm) + "</urlset>", headers={"Content-Type": "application/xml"})
    add("/robots.txt", f"User-agent: *\nDisallow: /admin/\nSitemap: {base}/sitemap.xml\n", headers={"Content-Type": "text/plain"})
    return R


def serve(routes):
    class H(BaseHTTPRequestHandler):
        def log_message(self, *a):
            pass

        def _send(self, head):
            r = routes.get(self.path) or (404, {"Content-Type": "text/html"}, "<html><title>Not found</title></html>")
            status, headers, body = r
            body = body.encode() if isinstance(body, str) else body
            self.send_response(status)
            for k, v in headers.items():
                self.send_header(k, v)
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            if not head:
                self.wfile.write(body)

        def do_GET(self):
            self._send(False)

        def do_HEAD(self):
            self._send(True)

    srv = ThreadingHTTPServer(("127.0.0.1", 0), H)
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    return f"http://127.0.0.1:{srv.server_address[1]}", srv
