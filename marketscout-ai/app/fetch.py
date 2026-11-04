"""Polite HTTP: robots.txt, SSRF guard, size/type limits."""
import ipaddress
import socket
from urllib.parse import urlparse
from urllib.robotparser import RobotFileParser

import requests

from . import config

_session = requests.Session()
_session.headers["User-Agent"] = config.USER_AGENT
_robots: dict = {}


def safe_url(url: str) -> bool:
    p = urlparse(url)
    if p.scheme not in ("http", "https") or not p.hostname:
        return False
    try:
        for info in socket.getaddrinfo(p.hostname, None):
            ip = ipaddress.ip_address(info[4][0])
            if ip.is_private or ip.is_loopback or ip.is_link_local or ip.is_reserved:
                return False
    except (OSError, ValueError):
        return False
    return True


def allowed(url: str) -> bool:
    p = urlparse(url)
    base = f"{p.scheme}://{p.netloc}"
    if base not in _robots:
        rp = RobotFileParser()
        try:
            r = _session.get(base + "/robots.txt", timeout=config.TIMEOUT)
            if r.status_code in (401, 403):
                rp.parse([])
                rp.disallow_all = True
            else:
                rp.parse(r.text.splitlines() if r.status_code == 200 else [])
        except requests.RequestException:
            rp.parse([])
        rp.modified()
        _robots[base] = rp
    return _robots[base].can_fetch(config.USER_AGENT, url)


def get(url: str):
    """Return (html, final_url). Raises on blocked/failed/non-HTML."""
    if not safe_url(url):
        raise ValueError("blocked or invalid URL")
    if not allowed(url):
        raise PermissionError("disallowed by robots.txt")
    r = _session.get(url, timeout=config.TIMEOUT, stream=True)
    try:
        r.raise_for_status()
        if "html" not in r.headers.get("content-type", ""):
            raise ValueError("not an HTML page")
        data = r.raw.read(config.MAX_BYTES, decode_content=True)
        return data.decode(r.encoding or "utf-8", errors="ignore"), r.url
    finally:
        r.close()
