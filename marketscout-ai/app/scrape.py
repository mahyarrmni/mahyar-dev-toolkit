"""Extract business details from public website pages."""
import re
import time
from urllib.parse import urljoin, urlparse

from bs4 import BeautifulSoup

from . import config, fetch

SERVICE_HINTS = ("service", "leistung", "angebot", "treatment", "behandlung", "product",
                 "solution", "what-we-do", "offer", "menu", "speise")
PAGE_HINTS = SERVICE_HINTS + ("about", "ueber", "contact", "kontakt")
STOP_WORDS = {"privacy", "datenschutz", "impressum", "cookie", "cookies", "contact", "kontakt",
              "login", "imprint", "terms", "agb", "home", "startseite", "search", "newsletter",
              "subscribe", "follow", "faq", "careers", "blog", "news", "about", "copyright",
              "menu", "sitemap", "jobs", "©"}
STOP_PHRASES = ("read more", "mehr erfahren", "über uns", "learn more")
SOCIAL = ("facebook.com", "instagram.com", "linkedin.com", "twitter.com", "x.com",
          "youtube.com", "tiktok.com")
BOOKING = ("book", "appointment", "termin", "reserv", "schedule", "buchen", "get a quote", "request a quote")
EMAIL_RE = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+")
JUNK_TAGS = ["script", "style", "nav", "footer", "header", "noscript", "form", "aside"]


def _clean(text: str):
    t = re.sub(r"\s+", " ", text).strip(" -–•:|")
    low = t.lower()
    if not 3 <= len(t) <= 60 or len(t.split()) > 6 or "@" in t or re.search(r"\d{3,}", t):
        return None
    if set(re.findall(r"\w+|©", low)) & STOP_WORDS or any(p in low for p in STOP_PHRASES):
        return None
    return low


def parse(html: str, base: str, service_page: bool) -> dict:
    soup = BeautifulSoup(html, "html.parser")
    links = [(a.get_text(" ", strip=True).lower(), urljoin(base, a["href"])) for a in soup.find_all("a", href=True)]
    title = soup.title.get_text(strip=True) if soup.title else ""
    md = soup.find("meta", attrs={"name": "description"})
    desc = (md.get("content") or "").strip() if md else ""
    emails = {l[7:].split("?")[0] for _, l in links if l.startswith("mailto:")}
    emails |= {e for e in EMAIL_RE.findall(soup.get_text(" ")) if not e.lower().endswith((".png", ".jpg", ".webp", ".gif"))}
    phones = {l[4:].strip() for _, l in links if l.startswith("tel:")}
    socials = set()
    for _, l in links:
        host = (urlparse(l).hostname or "").removeprefix("www.")
        if any(host == s or host.endswith("." + s) for s in SOCIAL):
            socials.add(l)
    booking = any(any(k in txt for k in BOOKING) or any(k in l.lower() for k in ("/book", "termin", "appointment")) for txt, l in links)
    for t in soup(JUNK_TAGS):
        t.decompose()
    tags = ["h2", "h3", "h4", "li"] if service_page else ["h2", "h3"]
    services = []
    for e in soup.find_all(tags):
        c = _clean(e.get_text(" ", strip=True))
        if c and c not in services:
            services.append(c)
    return {"title": title, "description": desc, "emails": sorted(emails), "phones": sorted(phones),
            "socials": sorted(socials), "booking": booking, "services": services[:25], "links": links}


def _merge(out: dict, p: dict):
    out["title"] = out["title"] or p["title"]
    out["description"] = out["description"] or p["description"]
    out["booking"] = out["booking"] or p["booking"]
    for k in ("emails", "phones", "socials", "services"):
        out[k] += [x for x in p[k] if x not in out[k]]


def scrape_site(url: str) -> dict:
    out = {"url": url, "pages": [], "title": "", "description": "", "services": [], "emails": [],
           "phones": [], "socials": [], "booking": False, "errors": []}
    try:
        html, final = fetch.get(url)
    except Exception as e:
        out["errors"].append(f"{url}: {e}")
        return out
    first = parse(html, final, False)
    _merge(out, first)
    out["pages"].append(final)
    host = urlparse(final).netloc.removeprefix("www.")
    extra = []
    for text, link in first["links"]:
        u = link.split("#")[0]
        pu = urlparse(u)
        if pu.netloc.removeprefix("www.") != host or u == final or u in extra:
            continue
        if any(h in (pu.path.lower() + " " + text) for h in PAGE_HINTS):
            extra.append(u)
    extra.sort(key=lambda u: not any(h in u.lower() for h in SERVICE_HINTS))
    for u in extra[: config.MAX_PAGES - 1]:
        time.sleep(config.DELAY)
        try:
            h, f = fetch.get(u)
            _merge(out, parse(h, f, any(k in u.lower() for k in SERVICE_HINTS)))
            out["pages"].append(f)
        except Exception as e:
            out["errors"].append(f"{u}: {e}")
    out["services"] = out["services"][:30]
    return out
