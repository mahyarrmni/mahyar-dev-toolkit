import re
from urllib.parse import parse_qsl, quote, unquote, urlencode, urljoin, urlsplit, urlunsplit

TRACKING = re.compile(r"^(utm_.*|gclid|fbclid|msclkid|mc_cid|mc_eid|yclid|_ga|igshid)$", re.I)
_SAFE = "/:@!$&'()*+,;=-._~"


def normalize_url(url, base=None):
    """Canonical form used as the page identity: lowercase host, IDNA, no fragment/default port/tracking params,
    consistent percent-encoding (so Persian paths compare equal), sorted query."""
    if not url:
        return None
    try:
        u = urljoin(base, url.strip()) if base else url.strip()
        p = urlsplit(u)
        scheme = p.scheme.lower()
        if scheme not in ("http", "https") or not p.hostname:
            return None
        host = p.hostname.lower().rstrip(".").encode("idna").decode("ascii")
        if ":" in host:
            host = f"[{host}]"
        netloc = host if p.port in (None, 80 if scheme == "http" else 443) else f"{host}:{p.port}"
        path = quote(unquote(p.path or "/"), safe=_SAFE)
        q = sorted((k, v) for k, v in parse_qsl(p.query, keep_blank_values=True) if not TRACKING.match(k))
        return urlunsplit((scheme, netloc, path, urlencode(q, quote_via=quote), ""))
    except (ValueError, UnicodeError):
        return None


def host_key(url: str) -> str:
    h = (urlsplit(url).hostname or "").lower()
    return h[4:] if h.startswith("www.") else h


def same_site(a: str, b: str, include_subdomains: bool = False) -> bool:
    ha, hb = host_key(a), host_key(b)
    return ha == hb or (include_subdomains and (ha.endswith("." + hb) or hb.endswith("." + ha)))


def display_url(u: str) -> str:
    return unquote(u)
