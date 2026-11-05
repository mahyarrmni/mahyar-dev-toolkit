import numpy as np
import pandas as pd

from asige.text import normalize


def TRUE_CTR(p):
    return 0.32 * np.power(np.maximum(p, 1.0), -1.15)


def make_gsc(pages, site, seed=5):
    rng, rows = np.random.default_rng(seed), []

    def row(q, url, impr, pos):
        rows.append({"query": normalize(q), "page": url, "impressions": float(impr), "position": float(pos),
                     "clicks": float(rng.binomial(int(impr), float(TRUE_CTR(pos))))})

    for p in pages:
        if p["word_count"] < 100:
            continue
        base = p["title"].lower()
        for q in (base, f"{base} guide", f"how to {base}"):
            row(q, p["url"], int(rng.lognormal(6, 1)) + 20, float(np.clip(rng.lognormal(2.0, 0.6), 1, 40)))
    tags, guide = site + "/seo/canonical-tags", site + "/seo/canonical-tag-guide"
    for q in ("canonical tags", "what is a canonical tag"):
        row(q, tags, 1100, 6.0)
        row(q, guide, 900, 7.5)
    for q, imp in (("bitcoin price today", 900), ("crypto exchange fees", 600), ("قیمت بیت کوین", 500)):
        row(q, site + "/seo/sitemaps", imp, 35.0)
    return pd.DataFrame(rows).assign(ctr=lambda d: d.clicks / d.impressions)


def make_curve_data(n=3000, seed=2):
    rng = np.random.default_rng(seed)
    pos = rng.uniform(1, 30, n)
    impr = (rng.lognormal(5.5, 1.0, n) + 30).astype(int)
    clicks = rng.binomial(impr, TRUE_CTR(pos))
    q = rng.choice(["how to bake bread", "best buy laptop", "pizza near me", "brandx login", "seo guide"], n)
    return pd.DataFrame({"query": q, "page": [f"https://e.com/p{i % 50}" for i in range(n)], "clicks": clicks.astype(float),
                         "impressions": impr.astype(float), "position": pos}).assign(ctr=lambda d: d.clicks / d.impressions)
