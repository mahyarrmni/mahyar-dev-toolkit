"""Async, polite crawler: robots.txt, sitemaps, redirect chains, budgets, SSRF guard, optional rendering."""
import asyncio
import gzip
import ipaddress
import re
import time
from dataclasses import dataclass
from urllib.parse import urlsplit
from urllib.robotparser import RobotFileParser

import httpx

from . import config, parse
from .urls import normalize_url, same_site

BINARY = re.compile(r"\.(jpe?g|png|gif|webp|svg|ico|pdf|zip|gz|rar|mp[34]|avi|mov|css|js|woff2?|ttf|eot|xml|json|txt|docx?|xlsx?|pptx?)$", re.I)
REDIRECTS = (301, 302, 303, 307, 308)
PAGE_DEFAULTS = {
    "final_url": None, "status": None, "depth": 0, "content_type": "", "blocked_by_robots": False, "in_sitemap": False,
    "error": None, "fetch_ms": None, "size_bytes": 0, "redirect_chain": [], "redirect_loop": False, "title": "",
    "meta_description": "", "meta_robots": "", "x_robots": "", "canonical": None, "h1s": [], "h2s": [], "lang_attr": "",
    "lang": "", "hreflang": [], "rel_next": None, "rel_prev": None, "text": "", "word_count": 0, "content_hash": "",
    "simhash": "", "links": [], "jsonld": [], "jsonld_errors": [], "images_total": 0, "images_missing_alt": 0,
    "script_count": 0, "rendered": False,
}


@dataclass
class CrawlConfig:
    start_url: str
    max_pages: int = 500          # budget on discovered URLs
    max_depth: int = 8
    concurrency: int = 8
    delay: float = 0.25           # min seconds between requests to the same host
    timeout: float = 15.0
    max_bytes: int = 2_000_000
    max_time: float = None        # seconds
    respect_robots: bool = True
    include_subdomains: bool = False
    follow_nofollow: bool = False
    use_sitemaps: bool = True
    check_external: bool = True
    max_external_checks: int = 100
    render: bool = False
    allow_private: bool = config.ALLOW_PRIVATE
    user_agent: str = config.USER_AGENT


class Crawler:
    def __init__(self, cfg: CrawlConfig, on_page=None, transport=None):
        self.cfg, self.on_page, self.transport = cfg, on_page or (lambda p: None), transport
        self.start = normalize_url(cfg.start_url)
        if not self.start:
            raise ValueError("invalid start_url")
        self.seen, self.sitemap, self.external = set(), set(), {}
        self.robots, self.crawl_delay, self.sitemaps_from_robots = {}, {}, {}
        self._next, self._locks, self._ssrf = {}, {}, {}
        self._rob_lock = None
        self.dropped = 0
        self.counts = {"pages": 0, "errors": 0, "blocked_by_robots": 0, "redirects": 0}
        self.status_counts = {}

    # ---------- public ----------
    async def run(self) -> dict:
        cfg, t0 = self.cfg, time.monotonic()
        self._t0, self._seq, self._rob_lock = t0, 0, asyncio.Lock()
        limits = httpx.Limits(max_connections=cfg.concurrency * 2, max_keepalive_connections=cfg.concurrency)
        async with httpx.AsyncClient(headers={"User-Agent": cfg.user_agent}, timeout=cfg.timeout, limits=limits,
                                     transport=self.transport) as client:
            self.client, self.queue = client, asyncio.PriorityQueue()
            self._enqueue(self.start, 0)
            if cfg.use_sitemaps:
                await self._seed_sitemaps()
            workers = [asyncio.create_task(self._worker()) for _ in range(cfg.concurrency)]
            await self.queue.join()
            for w in workers:
                w.cancel()
            await asyncio.gather(*workers, return_exceptions=True)
            if cfg.check_external:
                await self._check_external()
        el = time.monotonic() - t0
        return {**self.counts, "status_counts": self.status_counts, "elapsed_s": round(el, 3),
                "pages_per_s": round(self.counts["pages"] / el, 2) if el else 0,
                "budget_exhausted": self.dropped > 0, "dropped_urls": self.dropped,
                "sitemap_urls": len(self.sitemap), "external": {
                    u: {"status": v.get("status"), "sources": sorted(v["sources"])[:5]} for u, v in self.external.items()}}

    # ---------- queue ----------
    def _enqueue(self, url, depth) -> bool:
        if url in self.seen or depth > self.cfg.max_depth or BINARY.search(urlsplit(url).path):
            return False
        if len(self.seen) >= self.cfg.max_pages:
            self.dropped += 1
            return False
        self.seen.add(url)
        self._seq += 1
        self.queue.put_nowait((depth, self._seq, url))
        return True

    async def _worker(self):
        while True:
            depth, _, url = await self.queue.get()
            try:
                if self.cfg.max_time and time.monotonic() - self._t0 > self.cfg.max_time:
                    self.dropped += 1
                    continue
                await self._process(url, depth)
            except Exception as e:  # never kill a worker
                self._emit(url, depth, status=None, error=f"{type(e).__name__}: {e}"[:300])
            finally:
                self.queue.task_done()

    def _emit(self, url, depth, **fields):
        rec = {**PAGE_DEFAULTS, "url": url, "depth": depth, **fields}
        rec["in_sitemap"] = url in self.sitemap
        self.counts["pages"] += 1
        self.counts["errors"] += bool(rec["error"])
        self.counts["blocked_by_robots"] += rec["blocked_by_robots"]
        self.counts["redirects"] += bool(rec["redirect_chain"])
        key = str(rec["status"]) if rec["status"] else ("blocked" if rec["blocked_by_robots"] else "error")
        self.status_counts[key] = self.status_counts.get(key, 0) + 1
        self.on_page(rec)

    # ---------- safety / politeness ----------
    async def _guard(self, url):
        host = urlsplit(url).hostname
        if host not in self._ssrf:
            ok = True
            if not self.cfg.allow_private:
                infos = await asyncio.get_running_loop().getaddrinfo(host, None)
                for i in infos:
                    ip = ipaddress.ip_address(i[4][0])
                    if ip.is_private or ip.is_loopback or ip.is_link_local or ip.is_reserved:
                        ok = False
            self._ssrf[host] = ok
        if not self._ssrf[host]:
            raise PermissionError("blocked: private/loopback address")

    async def _throttle(self, url):
        p = urlsplit(url)
        origin = f"{p.scheme}://{p.netloc}"
        delay = max(self.cfg.delay, self.crawl_delay.get(origin, 0))
        lock = self._locks.setdefault(p.netloc, asyncio.Lock())
        async with lock:
            loop = asyncio.get_running_loop()
            wait = self._next.get(p.netloc, 0) - loop.time()
            if wait > 0:
                await asyncio.sleep(wait)
            self._next[p.netloc] = loop.time() + delay

    async def _robots_for(self, url):
        p = urlsplit(url)
        origin = f"{p.scheme}://{p.netloc}"
        async with self._rob_lock:
            if origin not in self.robots:
                rp = RobotFileParser()
                try:
                    await self._guard(origin)
                    r = await self.client.get(origin + "/robots.txt", follow_redirects=True)
                    if r.status_code == 200:
                        rp.parse(r.text.splitlines())
                    elif r.status_code < 500:
                        rp.parse([])
                    else:  # server error: assume disallow (conservative, like Google)
                        rp.parse(["User-agent: *", "Disallow: /"])
                except Exception:
                    rp.parse([])
                rp.modified()
                self.robots[origin] = rp
                cd = rp.crawl_delay(self.cfg.user_agent)
                if cd:
                    self.crawl_delay[origin] = float(cd)
                self.sitemaps_from_robots[origin] = rp.site_maps() or []
            return self.robots[origin]

    # ---------- fetching ----------
    async def _fetch(self, url):
        chain, cur, visited = [], url, {url}
        for _ in range(10):
            await self._guard(cur)
            await self._throttle(cur)
            t = time.monotonic()
            async with self.client.stream("GET", cur, follow_redirects=False) as r:
                status, headers = r.status_code, r.headers
                if status in REDIRECTS and "location" in headers:
                    chain.append({"url": cur, "status": status})
                    nxt = normalize_url(headers["location"], base=cur)
                    if not nxt:
                        return {"chain": chain, "final": cur, "status": status, "error": "bad redirect target"}
                    if nxt in visited:
                        return {"chain": chain, "final": cur, "status": status, "loop": True}
                    visited.add(nxt)
                    cur = nxt
                    continue
                ctype, body = headers.get("content-type", "").lower(), b""
                if status < 400 and "html" in ctype:
                    chunks, size = [], 0
                    async for c in r.aiter_bytes():
                        chunks.append(c)
                        size += len(c)
                        if size >= self.cfg.max_bytes:
                            break
                    body = b"".join(chunks)
                return {"chain": chain, "final": cur, "status": status, "headers": dict(headers), "ctype": ctype,
                        "body": body, "ms": (time.monotonic() - t) * 1000, "enc": r.encoding or "utf-8"}
        return {"chain": chain, "final": cur, "status": None, "error": "too many redirects"}

    async def _process(self, url, depth):
        cfg = self.cfg
        if cfg.respect_robots:
            rp = await self._robots_for(url)
            if not rp.can_fetch(cfg.user_agent, url):
                return self._emit(url, depth, blocked_by_robots=True)
        res = await self._fetch(url)
        chain, final = res["chain"], res["final"]
        if chain:
            self._emit(url, depth, status=chain[0]["status"], final_url=final, redirect_chain=chain,
                       redirect_loop=bool(res.get("loop")), error=res.get("error"))
            internal_final = same_site(final, self.start, cfg.include_subdomains)
            if res.get("loop") or res.get("error") or not internal_final or final in self.seen:
                return
            self.seen.add(final)  # handle the final response inline, no second request
            url = final
        await self._handle(url, depth, res)

    async def _handle(self, url, depth, res):
        status, body = res["status"], res.get("body", b"")
        fields = {"status": status, "final_url": url, "content_type": res.get("ctype", ""), "fetch_ms": res.get("ms"),
                  "size_bytes": len(body), "error": res.get("error")}
        if body:
            try:
                html = body.decode(res["enc"], errors="replace")
            except LookupError:
                html = body.decode("utf-8", errors="replace")
            xr = (res.get("headers") or {}).get("x-robots-tag", "")
            p = parse.parse_html(html, url, xr)
            if self.cfg.render and p["word_count"] < 50 and p["script_count"] >= 5:
                try:
                    from . import renderer
                    p = {**parse.parse_html(await renderer.render(url, self.cfg.user_agent), url, xr)}
                    fields["rendered"] = True
                except Exception as e:
                    fields["error"] = f"render failed: {type(e).__name__}"[:100]
            fields.update(p)
            for extra in [p["rel_next"], p["rel_prev"]] + [h["url"] for h in p["hreflang"]]:
                if extra and same_site(extra, self.start, self.cfg.include_subdomains):
                    self._enqueue(extra, depth + 1)
            for l in p["links"]:
                dst = l["url"]
                if same_site(dst, self.start, self.cfg.include_subdomains):
                    if self.cfg.follow_nofollow or not l["nofollow"]:
                        self._enqueue(dst, depth + 1)
                else:
                    self.external.setdefault(dst, {"sources": set()})["sources"].add(url)
        elif status and status < 400:
            fields["x_robots"] = ((res.get("headers") or {}).get("x-robots-tag", "")).lower()
        self._emit(url, depth, **fields)

    # ---------- sitemaps ----------
    async def _seed_sitemaps(self):
        origin = "{0.scheme}://{0.netloc}".format(urlsplit(self.start))
        await self._robots_for(self.start)
        todo = list(self.sitemaps_from_robots.get(origin) or [origin + "/sitemap.xml"])
        done = 0
        while todo and done < 20:
            sm = todo.pop(0)
            done += 1
            try:
                await self._guard(sm)
                r = await self.client.get(sm, follow_redirects=True)
                if r.status_code != 200:
                    continue
                raw = gzip.decompress(r.content) if sm.endswith(".gz") else r.content
                txt = raw.decode("utf-8", errors="replace")
            except Exception:
                continue
            locs = re.findall(r"<loc>\s*(.*?)\s*</loc>", txt, re.S)
            if "<sitemapindex" in txt:
                todo.extend(locs[:20])
                continue
            for loc in locs[:50_000]:
                u = normalize_url(loc.replace("&amp;", "&"))
                if u and same_site(u, self.start, self.cfg.include_subdomains):
                    self.sitemap.add(u)
                    self._enqueue(u, 1)

    async def _check_external(self):
        sem = asyncio.Semaphore(10)
        items = sorted(self.external.items(), key=lambda kv: -len(kv[1]["sources"]))[: self.cfg.max_external_checks]

        async def check(u, rec):
            async with sem:
                try:
                    await self._guard(u)
                    await self._throttle(u)
                    r = await self.client.head(u, follow_redirects=True)
                    if r.status_code in (403, 405, 501):
                        r = await self.client.get(u, follow_redirects=True)
                    rec["status"] = r.status_code
                except Exception:
                    rec["status"] = None

        await asyncio.gather(*(check(u, r) for u, r in items))
