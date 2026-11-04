"""Rule-based competitor analysis and opportunity detection."""
from collections import Counter, defaultdict


def analyze(businesses: list) -> dict:
    n = len(businesses)
    sc = [b for b in businesses if b.get("scraped")]
    m = len(sc)
    cnt, who = Counter(), defaultdict(list)
    for b in sc:
        for s in set(b["scraped"]["services"]):
            cnt[s] += 1
            who[s].append(b["name"])
    table = [{"service": s, "count": c, "share": round(c / m, 2)}
             for s, c in cnt.most_common() if c >= 2 and c / m >= 0.3][:15]
    niche = [{"service": s, "business": who[s][0]} for s, c in cnt.items() if c == 1][:15]
    no_site = [b["name"] for b in businesses if not b.get("website")]

    def missing(f):
        return [b["name"] for b in sc if not f(b["scraped"])]

    gaps = {"online_booking": missing(lambda s: s["booking"]),
            "social_presence": missing(lambda s: s["socials"]),
            "contact_email": missing(lambda s: s["emails"])}
    opps = []
    if no_site:
        opps.append({"type": "digital_presence", "insight": f"{len(no_site)} of {n} listed businesses have no website.", "evidence": no_site[:5]})
    msgs = {"online_booking": "show no visible online booking/inquiry path",
            "social_presence": "show no social media links",
            "contact_email": "publish no email address"}
    for k, lst in gaps.items():
        if m and len(lst) / m >= 0.5:
            opps.append({"type": k, "insight": f"{len(lst)} of {m} analyzed competitors {msgs[k]}.", "evidence": lst[:5]})
    if niche:
        opps.append({"type": "differentiation", "insight": "Services offered by only one competitor: possible differentiators or underserved niches.",
                     "evidence": [f"{x['service']} ({x['business']})" for x in niche[:5]]})
    if table and m >= 3:
        opps.append({"type": "table_stakes", "insight": "Services most competitors offer: baseline expectation for new entrants.",
                     "evidence": [t["service"] for t in table[:5]]})
    return {"businesses_found": n, "businesses_scraped": m,
            "confidence": "low" if m < 3 else "medium" if m < 8 else "high",
            "table_stakes": table, "niche_services": niche,
            "digital_gaps": {**{k: len(v) for k, v in gaps.items()}, "no_website": len(no_site)},
            "opportunities": opps}
