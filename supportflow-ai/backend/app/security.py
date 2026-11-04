import collections
import hmac
import time

from fastapi import Header, HTTPException, Request

from .config import settings

_hits: dict[str, collections.deque] = collections.defaultdict(collections.deque)


def rate_limit(key: str) -> None:
    """Sliding-window limiter (per process; use a reverse proxy for multi-replica setups)."""
    now = time.monotonic()
    q = _hits[key]
    while q and now - q[0] > 60:
        q.popleft()
    if len(q) >= settings.rate_limit_per_min:
        raise HTTPException(429, "rate limit exceeded")
    q.append(now)


async def require_key(request: Request, x_api_key: str = Header(default="")) -> None:
    rate_limit(request.client.host if request.client else "unknown")
    if not hmac.compare_digest(x_api_key.encode(), settings.api_key.encode()):
        raise HTTPException(401, "invalid API key")
