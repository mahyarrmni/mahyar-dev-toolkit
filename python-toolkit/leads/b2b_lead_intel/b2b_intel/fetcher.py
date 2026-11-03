"""Async HTTP layer: global concurrency cap, per-host politeness delay,
robots.txt cache, bounded downloads and retry with exponential back-off."""
from __future__ import annotations
import asyncio, re, time
from urllib.parse import urlparse
from urllib.robotparser import RobotFileParser

import aiohttp

from .config import Config
from .models import FetchResult


class Fetcher:
    def __init__(self, cfg: Config):
        self.cfg = cfg
        self._session: aiohttp.ClientSession | None = None
        self._sem = asyncio.Semaphore(cfg.concurrency * 2)
        self._host_lock: dict[str, asyncio.Lock] = {}
        self._host_last: dict[str, float] = {}
        self._robots: dict[str, RobotFileParser | None] = {}
        self._robots_lock: dict[str, asyncio.Lock] = {}

    async def __aenter__(self) -> "Fetcher":
        self._session = aiohttp.ClientSession(
            timeout=aiohttp.ClientTimeout(total=self.cfg.timeout),
            headers={"User-Agent": self.cfg.user_agent,
                     "Accept": "text/html,application/xhtml+xml",
                     "Accept-Language": "fa,en;q=0.8"},
            connector=aiohttp.TCPConnector(limit=self.cfg.concurrency * 2, ttl_dns_cache=300),
        )
        return self

    async def __aexit__(self, *exc) -> None:
        if self._session:
            await self._session.close()

    # ---------------------------------------------------------------- helpers
    async def _throttle(self, host: str) -> None:
        lock = self._host_lock.setdefault(host, asyncio.Lock())
        async with lock:
            wait = self._host_last.get(host, 0.0) + self.cfg.per_host_delay - time.monotonic()
            if wait > 0:
                await asyncio.sleep(wait)
            self._host_last[host] = time.monotonic()

    async def _allowed(self, url: str) -> bool:
        if not self.cfg.respect_robots:
            return True
        p = urlparse(url)
        origin = f"{p.scheme}://{p.netloc}"
        lock = self._robots_lock.setdefault(origin, asyncio.Lock())
        async with lock:
            if origin not in self._robots:
                rp: RobotFileParser | None = RobotFileParser()
                try:
                    await self._throttle(p.netloc)
                    async with self._session.get(origin + "/robots.txt") as r:
                        if r.status == 200:
                            body = (await r.content.read(200_000)).decode("utf-8", "replace")
                            rp.parse(body.splitlines())
                        else:
                            rp = None          # no robots.txt -> everything allowed
                except Exception:
                    rp = None
                self._robots[origin] = rp
        rp = self._robots[origin]
        return True if rp is None else rp.can_fetch(self.cfg.user_agent, url)

    @staticmethod
    def _decode(raw: bytes, charset: str | None) -> str:
        if not charset:
            m = re.search(rb"charset=[\"']?([A-Za-z0-9_\-]+)", raw[:4096])
            charset = m.group(1).decode() if m else "utf-8"
        try:
            return raw.decode(charset, "replace")
        except LookupError:
            return raw.decode("utf-8", "replace")

    # ------------------------------------------------------------------ fetch
    async def fetch(self, url: str) -> FetchResult:
        assert self._session is not None, "use 'async with Fetcher(cfg)'"
        host = urlparse(url).netloc
        if not await self._allowed(url):
            return FetchResult(url=url, error="blocked_by_robots")
        last_err = ""
        for attempt in range(self.cfg.retries + 1):
            try:
                async with self._sem:
                    await self._throttle(host)
                    async with self._session.get(url) as r:
                        ctype = r.headers.get("Content-Type", "")
                        if r.status == 429 or r.status >= 500:
                            last_err = f"http_{r.status}"
                            retry_after = r.headers.get("Retry-After", "")
                            await asyncio.sleep(min(int(retry_after) if retry_after.isdigit() else 2 ** attempt, 10))
                            continue
                        if r.status != 200:
                            return FetchResult(url=url, final_url=str(r.url), status=r.status,
                                               error=f"http_{r.status}")
                        if "html" not in ctype.lower() and "xml" not in ctype.lower():
                            return FetchResult(url=url, final_url=str(r.url), status=r.status,
                                               error=f"not_html:{ctype[:30]}")
                        raw = await r.content.read(self.cfg.max_bytes)
                        return FetchResult(url=url, final_url=str(r.url), status=200,
                                           text=self._decode(raw, r.charset),
                                           headers={k: v for k, v in r.headers.items()})
            except (asyncio.TimeoutError, aiohttp.ClientError) as e:
                last_err = type(e).__name__
                await asyncio.sleep(0.5 * 2 ** attempt)
        return FetchResult(url=url, error=last_err or "unknown")
