"""Pure functions that turn one HTML page into structured contact intelligence.
No network, no I/O -> trivially unit-testable."""
from __future__ import annotations
import html as htmllib, json, re
from urllib.parse import unquote, urljoin, urlparse

from bs4 import BeautifulSoup

from .models import PageData
from .techdetect import detect

# Persian + Arabic-Indic digits -> ASCII
DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")

EMAIL_RE = re.compile(r"[A-Za-z0-9._%+\-]{1,64}@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,24}")
_OBF = [(re.compile(r"\s*[\[\(\{]\s*at\s*[\]\)\}]\s*", re.I), "@"),
        (re.compile(r"\s*[\[\(\{]\s*dot\s*[\]\)\}]\s*", re.I), "."),
        (re.compile(r"\s+\(?at\)?\s+(?=[\w-]+\s*(?:\.|\(dot\)))", re.I), "@")]
JUNK_TLDS = {"png", "jpg", "jpeg", "gif", "svg", "webp", "css", "js", "woff", "woff2", "ico", "avif"}
JUNK_DOMAINS = {"example.com", "domain.com", "email.com", "yourdomain.com", "sentry.io",
                "wixpress.com", "sentry-next.wixpress.com", "godaddy.com", "test.com"}
GENERIC_LOCAL = {"info", "contact", "sales", "support", "hello", "admin", "office", "mail", "service",
                 "help", "hr", "careers", "jobs", "marketing", "press", "team", "billing", "enquiries",
                 "inquiry", "webmaster", "noreply", "no-reply"}

IR_MOBILE = re.compile(r"(?<!\d)(?:\+98|0098|0)?9\d{9}(?!\d)")
IR_LAND = re.compile(r"(?<!\d)\(?0[1-8]\d\)?[\s\-]*\d{8}(?!\d)|(?<!\d)\(?0[1-8]\d\)?[\s\-]*\d{4}[\s\-]\d{4}(?!\d)")
INTL = re.compile(r"(?<![\w+])\+\d{1,3}[\s\-.()]*\d(?:[\s\-.()]*\d){7,12}(?!\d)")

SOCIAL = {
    "linkedin": r"linkedin\.com/(?:company|school|in)/[^/?#\s\"']+",
    "instagram": r"instagram\.com/(?!p/|reel/|explore|accounts)[A-Za-z0-9_.]+",
    "twitter": r"(?:twitter|x)\.com/(?!intent|share|home|i/)[A-Za-z0-9_]{1,15}",
    "facebook": r"facebook\.com/(?!sharer|share|tr\?|plugins|dialog)[A-Za-z0-9_.\-]+",
    "youtube": r"youtube\.com/(?:@|c/|channel/|user/)[A-Za-z0-9_.\-]+",
    "telegram": r"t\.me/(?!share)[A-Za-z0-9_]{4,}",
    "whatsapp": r"(?:wa\.me/|api\.whatsapp\.com/send\?phone=)\d+",
    "github": r"github\.com/[A-Za-z0-9\-]+(?![\w/])",
    "aparat": r"aparat\.com/[A-Za-z0-9_.]+",
}
_SOCIAL_RE = {k: re.compile(v, re.I) for k, v in SOCIAL.items()}

CONTACT_WORDS = [  # (word, weight)
    ("contact", 10), ("تماس", 10), ("ارتباط", 8), ("about", 6), ("درباره", 6),
    ("team", 5), ("company", 4), ("support", 3), ("پشتیبانی", 3), ("career", 1), ("location", 3),
]
FILE_EXT = re.compile(r"\.(pdf|jpe?g|png|gif|svg|zip|rar|docx?|xlsx?|mp4|mp3)(\?|$)", re.I)


# --------------------------------------------------------------------- emails
def decode_cf_email(hexstr: str) -> str | None:
    """Decode Cloudflare's data-cfemail obfuscation (XOR with first byte)."""
    try:
        raw = bytes.fromhex(hexstr)
        key = raw[0]
        return bytes(b ^ key for b in raw[1:]).decode("utf-8")
    except (ValueError, IndexError, UnicodeDecodeError):
        return None


def deobfuscate(text: str) -> str:
    for rx, rep in _OBF:
        text = rx.sub(rep, text)
    return text


def classify_email(address: str, company_domain: str) -> dict:
    local, _, dom = address.lower().partition("@")
    root = company_domain.lower().removeprefix("www.")
    same = dom == root or dom.endswith("." + root)
    kind = "generic" if local in GENERIC_LOCAL else "personal"
    return {"address": address.lower(), "kind": kind, "same_domain": same}


def _valid_email(addr: str) -> bool:
    local, _, dom = addr.lower().partition("@")
    tld = dom.rsplit(".", 1)[-1]
    return bool(local) and tld not in JUNK_TLDS and dom not in JUNK_DOMAINS and ".." not in addr


def extract_emails(soup: BeautifulSoup, text: str, company_domain: str) -> list:
    found: dict[str, dict] = {}

    def add(addr: str):
        addr = addr.strip().strip(".,;:()<>[]").lower()
        if EMAIL_RE.fullmatch(addr) and _valid_email(addr):
            found.setdefault(addr, classify_email(addr, company_domain))

    for a in soup.select('a[href^="mailto:" i]'):
        for part in unquote(a["href"][7:]).split("?")[0].split(","):
            add(part)
    for el in soup.select("[data-cfemail]"):
        dec = decode_cf_email(el.get("data-cfemail", ""))
        if dec:
            add(dec)
    for a in soup.select('a[href*="/cdn-cgi/l/email-protection#"]'):
        dec = decode_cf_email(a["href"].split("#", 1)[1])
        if dec:
            add(dec)
    for m in EMAIL_RE.finditer(deobfuscate(text)):
        add(m.group(0))
    return sorted(found.values(), key=lambda e: (not e["same_domain"], e["kind"] == "generic", e["address"]))


# --------------------------------------------------------------------- phones
def normalize_phone(raw: str, default_cc: str = "98") -> str | None:
    raw = raw.translate(DIGITS).strip()
    digits = re.sub(r"\D", "", raw)
    if not 8 <= len(digits) <= 15:
        return None
    if raw.startswith("+"):
        num = digits
    elif digits.startswith("00"):
        num = digits[2:]
    elif digits.startswith("0"):
        num = default_cc + digits[1:]
    elif len(digits) == 10 and digits.startswith("9"):
        num = "98" + digits
    else:
        return None
    return "+" + num if 8 <= len(num) <= 15 else None


def extract_phones(soup: BeautifulSoup, text: str) -> list:
    out: list[str] = []

    def add(raw: str):
        n = normalize_phone(raw)
        if n and n not in out:
            out.append(n)

    for a in soup.select('a[href^="tel:" i]'):           # highest confidence
        add(unquote(a["href"][4:]))
    for a in soup.select('a[href*="wa.me/"]'):
        m = re.search(r"wa\.me/(\d+)", a["href"])
        if m:
            add("+" + m.group(1))
    t = text.translate(DIGITS)
    for rx in (INTL, IR_MOBILE, IR_LAND):
        for m in rx.finditer(t):
            add(m.group(0))
    return out[:12]


# --------------------------------------------------------------- socials/meta
def extract_socials(soup: BeautifulSoup) -> dict:
    res: dict[str, str] = {}
    for a in soup.find_all("a", href=True):
        href = a["href"]
        for name, rx in _SOCIAL_RE.items():
            if name not in res and (m := rx.search(href)):
                res[name] = "https://" + m.group(0).rstrip("/")
    return res


def _walk(node):
    if isinstance(node, list):
        for i in node:
            yield from _walk(i)
    elif isinstance(node, dict):
        yield node
        for v in node.values():
            if isinstance(v, (list, dict)):
                yield from _walk(v)


def extract_jsonld_org(soup: BeautifulSoup) -> dict:
    org: dict = {}
    for s in soup.find_all("script", type="application/ld+json"):
        try:
            data = json.loads(s.string or s.get_text() or "")
        except (ValueError, TypeError):
            continue
        for n in _walk(data):
            types = n.get("@type")
            types = types if isinstance(types, list) else [types]
            if not any(isinstance(t, str) and re.search(r"Organization|Corporation|LocalBusiness|Store|Company", t) for t in types):
                continue
            org.setdefault("name", n.get("name"))
            org.setdefault("telephone", n.get("telephone"))
            org.setdefault("email", n.get("email"))
            addr = n.get("address")
            if isinstance(addr, dict):
                addr = ", ".join(str(addr.get(k)) for k in
                                 ("streetAddress", "addressLocality", "addressRegion", "addressCountry") if addr.get(k))
            org.setdefault("address", addr if isinstance(addr, str) else None)
            emp = n.get("numberOfEmployees")
            if isinstance(emp, dict):
                emp = emp.get("value") or emp.get("maxValue")
            if emp is not None and str(emp).isdigit():
                org.setdefault("employees", int(emp))
            same = n.get("sameAs")
            if same:
                org.setdefault("sameAs", same if isinstance(same, list) else [same])
    return {k: v for k, v in org.items() if v}


def meta(soup: BeautifulSoup, *names) -> str:
    for n in names:
        el = soup.find("meta", attrs={"name": n}) or soup.find("meta", attrs={"property": n})
        if el and (el.get("content") or "").strip():
            return html_clean(el["content"])
    return ""


def html_clean(s: str) -> str:
    return " ".join(htmllib.unescape(s).split())


def detect_lang(text: str) -> str:
    sample = text[:5000]
    letters = [c for c in sample if c.isalpha()]
    if not letters:
        return ""
    fa = sum("؀" <= c <= "ۿ" for c in letters)
    return "fa" if fa / len(letters) > 0.3 else "en"


# ------------------------------------------------------------- contact links
def find_contact_links(soup: BeautifulSoup, base_url: str, limit: int = 6) -> list:
    host = urlparse(base_url).netloc.removeprefix("www.")
    scored: dict[str, int] = {}
    for a in soup.find_all("a", href=True):
        url = urljoin(base_url, a["href"]).split("#")[0]
        p = urlparse(url)
        if p.scheme not in ("http", "https") or p.netloc.removeprefix("www.") != host or FILE_EXT.search(url):
            continue
        hay = (unquote(p.path) + " " + a.get_text(" ")).lower()
        s = sum(w for word, w in CONTACT_WORDS if word in hay)
        if s and url.rstrip("/") != base_url.rstrip("/"):
            scored[url] = max(scored.get(url, 0), s)
    return [u for u, _ in sorted(scored.items(), key=lambda kv: -kv[1])][:limit]


# ----------------------------------------------------------------- main entry
def extract_page(html: str, url: str, headers: dict | None, company_domain: str) -> PageData:
    soup = BeautifulSoup(html, "lxml")
    pd = PageData(url=url)
    pd.tech = detect(html, headers)
    title = soup.title.get_text(" ") if soup.title else ""
    pd.title = html_clean(title)
    pd.description = meta(soup, "description", "og:description")
    pd.site_name = meta(soup, "og:site_name", "application-name")
    pd.socials = extract_socials(soup)
    pd.org = extract_jsonld_org(soup)
    pd.contact_links = find_contact_links(soup, url)
    for t in soup(["script", "style", "noscript", "template"]):
        t.decompose()
    text = soup.get_text(" ")
    pd.lang = detect_lang(text)
    pd.emails = extract_emails(soup, text, company_domain)
    pd.phones = extract_phones(soup, text)
    if pd.org.get("telephone"):
        n = normalize_phone(str(pd.org["telephone"]))
        if n and n not in pd.phones:
            pd.phones.insert(0, n)
    if pd.org.get("email") and EMAIL_RE.fullmatch(str(pd.org["email"]).strip()):
        c = classify_email(str(pd.org["email"]).strip(), company_domain)
        if all(e["address"] != c["address"] for e in pd.emails):
            pd.emails.insert(0, c)
    return pd
