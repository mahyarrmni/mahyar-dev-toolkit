"""HTML -> structured SEO fields."""
import hashlib
import json
import re

from bs4 import BeautifulSoup

from . import dedup
from .text import detect_lang, normalize, tokenize
from .urls import normalize_url

JUNK = ["script", "style", "noscript", "nav", "footer", "header", "aside", "form", "svg", "template"]
WS = re.compile(r"\s+")
SKIP = ("#", "javascript:", "mailto:", "tel:", "data:", "sms:")


def _clean(s) -> str:
    return WS.sub(" ", s or "").strip()


def _jsonld(soup):
    blocks, errors = [], []
    for s in soup.find_all("script", type=lambda t: t and "ld+json" in t.lower()):
        raw = (s.string or s.get_text() or "").strip()
        if not raw:
            continue
        try:
            data = json.loads(raw)
        except ValueError as e:
            errors.append(str(e)[:120])
            continue
        if isinstance(data, dict):
            data = data["@graph"] if isinstance(data.get("@graph"), list) else [data]
        blocks.extend(i for i in data if isinstance(i, dict)) if isinstance(data, list) else None
    return blocks, errors


def _link(tag, rel, base):
    h = tag.find("link", rel=lambda r: r and rel in [x.lower() for x in (r if isinstance(r, list) else [r])], href=True)
    return normalize_url(h["href"], base) if h else None


def parse_html(html: str, url: str, x_robots: str = "") -> dict:
    soup = BeautifulSoup(html, "lxml")
    title = _clean(soup.title.get_text()) if soup.title else ""
    md = soup.find("meta", attrs={"name": re.compile("^description$", re.I)})
    robots = ",".join((m.get("content") or "") for m in soup.find_all("meta", attrs={"name": re.compile("^(robots|googlebot)$", re.I)}))
    hreflang = [{"lang": l.get("hreflang", "").strip(), "url": normalize_url(l["href"], url)}
                for l in soup.find_all("link", href=True, hreflang=True)
                if "alternate" in [r.lower() for r in (l.get("rel") or [])]]
    head = soup.head or soup
    h1s = [_clean(h.get_text(" ")) for h in soup.find_all("h1")]
    h2s = [_clean(h.get_text(" ")) for h in soup.find_all("h2")][:30]
    links, seen = [], set()
    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if not href or href.lower().startswith(SKIP):
            continue
        dst = normalize_url(href, url)
        if not dst:
            continue
        rel = {r.lower() for r in (a.get("rel") or [])}
        anchor = _clean(a.get_text(" ")) or _clean((a.find("img") or {}).get("alt", ""))
        key = (dst, anchor)
        if key in seen:
            continue
        seen.add(key)
        links.append({"url": dst, "anchor": anchor[:200], "nofollow": bool(rel & {"nofollow", "ugc", "sponsored"})})
    imgs = soup.find_all("img")
    scripts = len(soup.find_all("script"))
    jsonld, jerrs = _jsonld(soup)
    for t in soup(JUNK):
        t.decompose()
    text = _clean((soup.body or soup).get_text(" "))[:100_000]
    tokens = tokenize(text, stop=False)
    html_tag = soup.find("html")
    return {
        "title": title, "meta_description": _clean(md.get("content")) if md else "",
        "meta_robots": robots.lower(), "x_robots": (x_robots or "").lower(),
        "canonical": _link(head, "canonical", url), "h1s": h1s, "h2s": h2s,
        "lang_attr": (html_tag.get("lang") if html_tag else "") or "", "lang": detect_lang(text[:3000]),
        "hreflang": hreflang, "rel_next": _link(head, "next", url), "rel_prev": _link(head, "prev", url),
        "text": text, "word_count": len(tokens),
        "content_hash": hashlib.sha1(normalize(text).encode()).hexdigest() if tokens else "",
        "simhash": f"{dedup.simhash(tokens[:3000]):016x}" if tokens else "",
        "links": links, "jsonld": jsonld, "jsonld_errors": jerrs,
        "images_total": len(imgs), "images_missing_alt": sum(1 for i in imgs if not (i.get("alt") or "").strip()),
        "script_count": scripts,
    }
