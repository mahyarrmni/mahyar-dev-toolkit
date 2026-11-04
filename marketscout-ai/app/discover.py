"""Business discovery via OpenStreetMap Nominatim (free, no key)."""
import requests

from . import config


def search(industry: str, location: str, limit: int = 10) -> list:
    r = requests.get(
        config.NOMINATIM_URL,
        params={"q": f"{industry} in {location}", "format": "jsonv2",
                "extratags": 1, "limit": min(limit * 3, 40)},
        headers={"User-Agent": config.USER_AGENT}, timeout=15)
    r.raise_for_status()
    out, seen = [], set()
    for p in r.json():
        name = (p.get("name") or "").strip()
        if not name or name.lower() in seen:
            continue
        seen.add(name.lower())
        t = p.get("extratags") or {}
        site = t.get("website") or t.get("contact:website")
        if site and not site.startswith("http"):
            site = "http://" + site
        out.append({
            "name": name, "address": p.get("display_name"), "website": site,
            "phone": t.get("phone") or t.get("contact:phone"),
            "email": t.get("email") or t.get("contact:email"),
            "category": p.get("type"), "lat": p.get("lat"), "lon": p.get("lon"),
            "sources": [f"https://www.openstreetmap.org/{p.get('osm_type')}/{p.get('osm_id')}"],
        })
    out.sort(key=lambda b: b["website"] is None)  # sites first
    return out[:limit]
