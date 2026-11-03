"""Orchestration: domain normalisation -> async crawl -> merge -> score -> store."""
from __future__ import annotations
import asyncio, re, time
from datetime import datetime, timezone
from urllib.parse import urlparse

from .config import Config
from .extractors import classify_email, extract_page
from .fetcher import Fetcher
from .models import Lead, PageData
from .scoring import load_weights, score_lead
from .storage import Store

_DOMAIN_RE = re.compile(r"^(?=.{4,253}$)([a-z0-9؀-ۿ](?:[a-z0-9\-؀-ۿ]{0,61}[a-z0-9؀-ۿ])?\.)+[a-z؀-ۿ]{2,24}$")
_TOKEN_SPLIT = re.compile(r"[,\s;|]+")


def normalize_domain(raw: str) -> str | None:
    s = raw.strip().strip("\"'").lower()
    if not s or s.startswith("#"):
        return None
    s = re.sub(r"^[a-z]+://", "", s)
    s = s.split("/")[0].split("?")[0].split("#")[0].split(":")[0].removeprefix("www.")
    try:
        s = s.encode("idna").decode("ascii") if not s.isascii() else s
    except UnicodeError:
        return None
    return s if _DOMAIN_RE.match(s) or (s.isascii() and re.match(r"^([a-z0-9-]+\.)+[a-z]{2,24}$", s)) else None


def parse_inputs(lines: list[str]) -> list[str]:
    """Accepts .txt / .csv content or CLI args; keeps order, drops duplicates & junk."""
    seen, out = set(), []
    for line in lines:
        for tok in _TOKEN_SPLIT.split(line):
            d = normalize_domain(tok)
            if d and d not in seen:
                seen.add(d)
                out.append(d)
    return out


def _clean_name(title: str, domain: str) -> str:
    if not title:
        return domain
    parts = re.split(r"\s[|\-–—•·:]\s", title)
    # the brand is usually the shortest meaningful segment or the one matching the domain
    stem = domain.split(".")[0].replace("-", "")
    for p in parts:
        if stem and stem in re.sub(r"[\s\-]", "", p.lower()):
            return p.strip()
    return parts[-1].strip() if len(parts) > 1 and len(parts[-1]) > 2 else parts[0].strip()


def merge(lead: Lead, pages: list[PageData]) -> Lead:
    emails: dict[str, dict] = {}
    for p in pages:
        for e in p.emails:
            emails.setdefault(e["address"], e)
        for ph in p.phones:
            if ph not in lead.phones:
                lead.phones.append(ph)
        for k, v in p.socials.items():
            lead.socials.setdefault(k, v)
        for t in p.tech:
            if t not in lead.tech:
                lead.tech.append(t)
        lead.description = lead.description or p.description
        lead.lang = lead.lang or p.lang
        org = p.org
        lead.name = lead.name or org.get("name") or ""
        lead.address = lead.address or org.get("address") or ""
        lead.employees = lead.employees if lead.employees is not None else org.get("employees")
        for s in org.get("sameAs", []):
            for plat in ("linkedin", "instagram", "twitter", "facebook", "youtube", "telegram", "github"):
                if plat in s.lower() and plat not in lead.socials:
                    lead.socials[plat] = s
    first = pages[0]
    lead.name = lead.name or first.site_name or _clean_name(first.title, lead.domain)
    lead.emails = sorted(emails.values(), key=lambda e: (not e["same_domain"], e["kind"] == "generic", e["address"]))
    lead.phones = lead.phones[:12]
    lead.tech.sort(key=lambda t: (t["category"], t["name"]))
    return lead


async def process_domain(domain: str, fetcher: Fetcher, cfg: Config) -> Lead:
    lead = Lead(domain=domain, crawled_at=datetime.now(timezone.utc).isoformat(timespec="seconds"))
    home = None
    for scheme in ("https", "http"):
        res = await fetcher.fetch(f"{scheme}://{domain}/")
        if res.ok:
            home = res
            break
        lead.error = res.error
    if home is None:
        lead.status = "blocked" if lead.error == "blocked_by_robots" else "unreachable"
        return lead
    lead.url = home.final_url or home.url
    pages = [extract_page(home.text, lead.url, home.headers, domain)]
    for link in pages[0].contact_links[: max(0, cfg.max_pages - 1)]:
        res = await fetcher.fetch(link)
        if res.ok:
            pages.append(extract_page(res.text, res.final_url or link, res.headers, domain))
    lead.pages_crawled = len(pages)
    lead.error = ""
    lead.status = "ok"
    return merge(lead, pages)


async def run(domains: list[str], cfg: Config, store: Store, log=print) -> dict:
    """Crawl `domains` concurrently; every finished lead is persisted immediately,
    so a Ctrl+C / crash loses at most the in-flight domains (re-run to resume)."""
    todo = domains if cfg.force else [d for d in domains if d not in store.done_domains()]
    skipped = len(domains) - len(todo)
    weights = load_weights(cfg.weights_path)
    queue: asyncio.Queue = asyncio.Queue()
    for d in todo:
        queue.put_nowait(d)
    counts = {"ok": 0, "unreachable": 0, "blocked": 0, "error": 0}
    started, total = time.monotonic(), len(todo)

    async with Fetcher(cfg) as fetcher:
        async def worker():
            while True:
                try:
                    d = queue.get_nowait()
                except asyncio.QueueEmpty:
                    return
                try:
                    lead = await process_domain(d, fetcher, cfg)
                except Exception as e:   # never let one bad site kill the run
                    lead = Lead(domain=d, status="error", error=f"{type(e).__name__}: {e}"[:200])
                score_lead(lead, weights)
                store.upsert(lead)
                counts[lead.status] = counts.get(lead.status, 0) + 1
                done = sum(counts.values())
                log(f"[{done}/{total}] {lead.status:<11} {d:<32} "
                    f"score={lead.score:>3} {lead.grade} emails={len(lead.emails)} phones={len(lead.phones)}")

        await asyncio.gather(*(worker() for _ in range(max(1, cfg.concurrency))))
    return {"total": total, "skipped": skipped, "seconds": round(time.monotonic() - started, 1), **counts}
