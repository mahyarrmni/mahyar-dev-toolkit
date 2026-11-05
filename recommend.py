"""Keyword cannibalization: GSC query->multiple pages, scored with share dispersion and semantic/URL similarity."""
from collections import defaultdict
from itertools import combinations
from urllib.parse import unquote, urlsplit

import numpy as np

from .text import tokenize


def url_similarity(a, b):
    ta = set(tokenize(unquote(urlsplit(a).path).replace("/", " ").replace("-", " "), stop=False))
    tb = set(tokenize(unquote(urlsplit(b).path).replace("/", " ").replace("-", " "), stop=False))
    return len(ta & tb) / len(ta | tb) if ta | tb else 0.0


def detect(gsc_df, emb, info, min_impressions=50, min_share=0.1, min_page_impr=10):
    """emb: {url: unit vector}; info: {url: {'intent':..., 'title':...}}. score = ln(1+impr) * dispersion * (0.5 + 0.5*sim)
    dispersion = 1 - normalized HHI of the query's impression shares (0 = one page owns the query)."""
    d = gsc_df.dropna(subset=["page"])
    d = d[d["query"] != ""]
    sets = defaultdict(lambda: {"queries": [], "impressions": 0.0, "clicks": 0.0})
    for q, g in d.groupby("query"):
        tot = g.impressions.sum()
        if tot < min_impressions:
            continue
        g = g.groupby("page").agg(impressions=("impressions", "sum"), clicks=("clicks", "sum"),
                                  pw=("position", lambda s: float(np.average(s, weights=g.loc[s.index, "impressions"])))).reset_index()
        share = g.impressions / tot
        comp = g[(share >= min_share) & (g.impressions >= min_page_impr)]
        if len(comp) < 2:
            continue
        n = len(g)
        hhi = float((share ** 2).sum())
        disp = 1 - (hhi - 1 / n) / (1 - 1 / n) if n > 1 else 0.0
        urls = tuple(sorted(comp.page))
        s = sets[urls]
        s["queries"].append({"query": q, "impressions": float(tot), "dispersion": round(disp, 3),
                             "pages": [{"url": r.page, "impressions": float(r.impressions), "clicks": float(r.clicks),
                                        "position": round(float(r.pw), 1)} for r in comp.itertuples()]})
        s["impressions"] += float(tot)
        s["clicks"] += float(comp.clicks.sum())
    out = []
    for urls, s in sets.items():
        sims = [float(emb[a] @ emb[b]) if a in emb and b in emb else url_similarity(a, b) for a, b in combinations(urls, 2)]
        sim = float(np.mean(sims))
        disp = float(np.average([q["dispersion"] for q in s["queries"]], weights=[q["impressions"] for q in s["queries"]]))
        clicks = {u: sum(p["clicks"] for q in s["queries"] for p in q["pages"] if p["url"] == u) for u in urls}
        winner = max(urls, key=lambda u: clicks[u])
        intents = {info.get(u, {}).get("intent") for u in urls}
        if sim >= 0.85:
            action = "consolidate"
        elif len(intents - {None}) > 1:
            action = "differentiate_intent"
        elif sim >= 0.5:
            action = "merge_or_reoptimize"
        else:
            action = "monitor"
        out.append({"pages": list(urls), "winner": winner, "queries": sorted(s["queries"], key=lambda q: -q["impressions"])[:10],
                    "n_queries": len(s["queries"]), "impressions": s["impressions"], "clicks": s["clicks"],
                    "semantic_similarity": round(sim, 3), "url_similarity": round(float(np.mean([url_similarity(a, b) for a, b in combinations(urls, 2)])), 3),
                    "dispersion": round(disp, 3), "score": round(float(np.log1p(s["impressions"]) * disp * (0.5 + 0.5 * sim)), 3),
                    "action": action, "intents": sorted(i for i in intents if i)})
    return sorted(out, key=lambda x: -x["score"])


def rank_swaps(gsc_df, query):
    """Number of times the top-impression page for `query` changes between consecutive dates (needs a date column)."""
    if "date" not in gsc_df:
        return None
    g = gsc_df[gsc_df["query"] == query].dropna(subset=["date", "page"])
    tops = g.sort_values("impressions").groupby("date").tail(1).sort_values("date").page.tolist()
    return sum(1 for a, b in zip(tops, tops[1:]) if a != b)


def crawl_only_candidates(pages, E, min_sim=0.85, min_title_jaccard=0.6):
    """Without GSC: page pairs with near-identical meaning AND overlapping title terms (lower confidence)."""
    from .semantic import topk_cosine
    ids, sims = topk_cosine(E, E, k=min(4, len(pages)))
    seen, out = set(), []
    for i in range(len(pages)):
        for j, s in zip(ids[i], sims[i]):
            if j == i or s < min_sim or (min(i, j), max(i, j)) in seen:
                continue
            seen.add((min(i, j), max(i, j)))
            ta, tb = set(tokenize(pages[i]["title"])), set(tokenize(pages[j]["title"]))
            jac = len(ta & tb) / len(ta | tb) if ta | tb else 0
            if jac >= min_title_jaccard:
                out.append({"pages": [pages[i]["url"], pages[j]["url"]], "semantic_similarity": round(float(s), 3), "title_jaccard": round(jac, 3)})
    return out
