"""Topic clusters, topical authority, content gaps, hub-and-spoke plans."""
from collections import defaultdict

import numpy as np
import pandas as pd

from .intent import classify_query
from .semantic import cluster_vectors, medoid, topk_cosine

AUTH_WEIGHTS = {"coverage": 0.35, "depth": 0.25, "cohesion": 0.20, "performance": 0.20}


def default_threshold(backend: str) -> float:
    return 0.55 if backend == "sbert" else 0.8  # cosine-distance cut for average-linkage clustering


def analyze_topics(index, pages, G, pr, gsc_df=None, threshold=None, assign_sim=0.3, cover_sim=0.4, gap_sim=0.3, min_gap_impr=20,
                   extra_keywords=None):
    """pages/index.E aligned (indexable pages only). Authority = weighted mean of available components, all in [0, 1]."""
    n = len(pages)
    th = threshold or default_threshold(index.backend)
    labels = cluster_vectors(index.E, th)
    members = defaultdict(list)
    for i, l in enumerate(labels):
        members[int(l)].append(i)
    cents = {t: index.E[m].mean(axis=0) for t, m in members.items()}
    cents = {t: c / (np.linalg.norm(c) or 1) for t, c in cents.items()}
    tids = sorted(members)
    C = np.vstack([cents[t] for t in tids]) if tids else np.zeros((0, index.E.shape[1]))

    # demand: GSC queries (or user keyword list) as [query, impressions, position]
    dem = None
    if gsc_df is not None and len(gsc_df):
        g = gsc_df.groupby("query", as_index=False).agg(impressions=("impressions", "sum"), clicks=("clicks", "sum"))
        dem = g[(g["query"] != "") & (g.impressions >= min_gap_impr)]
    if extra_keywords is not None and len(extra_keywords):
        ek = extra_keywords.rename(columns={"volume": "impressions"})[["query", "impressions"]].assign(clicks=0.0)
        dem = ek if dem is None else (pd.concat([dem, ek]).drop_duplicates("query"))
    q_best_sim = q_best_idx = q_topic = q_topic_sim = None
    if dem is not None and len(dem):
        Q = index.transform(dem["query"].tolist())
        ids, sims = topk_cosine(Q, index.E, k=1)
        q_best_idx, q_best_sim = ids[:, 0], sims[:, 0]
        ts = Q @ C.T
        q_topic, q_topic_sim = np.array(tids)[ts.argmax(axis=1)], ts.max(axis=1)

    total_clicks = float(gsc_df.clicks.sum()) if gsc_df is not None and len(gsc_df) else 0.0
    page_clicks = gsc_df.groupby("page").clicks.sum().to_dict() if total_clicks else {}
    topics, unclustered = [], []
    for t in tids:
        m = members[t]
        if len(m) < 2:
            unclustered.append(pages[m[0]]["url"])
            continue
        urls = [pages[i]["url"] for i in m]
        sub = G.subgraph(urls)
        k = len(urls)
        density = sub.number_of_edges() / (k * (k - 1)) if k > 1 else 0.0
        sims_c = index.E[m] @ cents[t]
        pil = max(range(k), key=lambda j: 0.5 * pr.get(urls[j], 0) / (max(pr.values() or [1]) or 1) + 0.5 * sims_c[j])
        wc = float(np.mean([pages[i]["word_count"] for i in m]))
        comp = {"depth": round(0.5 * min(1, np.log1p(k) / np.log1p(10)) + 0.5 * min(1, wc / 800), 3), "cohesion": round(min(1.0, density / 0.3), 3)}
        det = {"pages": k, "avg_words": round(wc), "link_density": round(density, 3)}
        if total_clicks:
            share = sum(page_clicks.get(u, 0) for u in urls) / total_clicks
            comp["performance"] = round(min(1.0, share * len(tids)), 3)
            det["click_share"] = round(share, 3)
        if q_topic is not None:
            sel = q_topic == t
            sel &= q_topic_sim >= assign_sim
            if sel.any():
                imp = dem["impressions"].values[sel]
                Qm = index.transform(dem["query"].values[sel].tolist())
                covered = (Qm @ index.E[m].T).max(axis=1) >= cover_sim
                comp["coverage"] = round(float(imp[covered].sum() / imp.sum()), 3)
                det["demand_queries"], det["demand_impressions"] = int(sel.sum()), float(imp.sum())
        w = {k_: AUTH_WEIGHTS[k_] for k_ in comp}
        auth = sum(comp[k_] * w[k_] for k_ in comp) / sum(w.values())
        topics.append({"id": t, "label": " / ".join(index.centroid_terms(m, 4)), "pillar": urls[pil], "pages": urls,
                       "authority": round(float(auth), 3), "components": comp, "weights_used": w, "details": det,
                       "centroid_sim_mean": round(float(sims_c.mean()), 3)})
    topics.sort(key=lambda x: -x["authority"])

    gaps, weak = [], []
    if dem is not None and len(dem):
        pos = gsc_df.groupby("query").apply(lambda g: (g.position * g.impressions).sum() / g.impressions.sum(), include_groups=False).to_dict() \
            if gsc_df is not None and len(gsc_df) else {}
        gmask = q_best_sim < gap_sim
        for j in np.nonzero((q_best_sim >= gap_sim) & (q_best_sim < cover_sim + 0.05))[0]:
            q = dem["query"].values[j]
            if pos.get(q, 0) > 20:
                weak.append({"query": q, "impressions": float(dem["impressions"].values[j]), "best_page": pages[q_best_idx[j]]["url"],
                             "similarity": round(float(q_best_sim[j]), 3), "position": round(float(pos[q]), 1)})
        gi = np.nonzero(gmask)[0]
        if len(gi):
            Qg = index.transform(dem["query"].values[gi].tolist())
            lab = cluster_vectors(Qg, 0.7, texts=dem["query"].values[gi].tolist())
            for c in sorted(set(lab)):
                mem = np.nonzero(lab == c)[0]
                mi = medoid(Qg, mem.tolist())
                qs = sorted(((dem["query"].values[gi[j]], float(dem["impressions"].values[gi[j]]), round(float(q_best_sim[gi[j]]), 3),
                              round(float(pos[dem["query"].values[gi[j]]]), 1) if dem["query"].values[gi[j]] in pos else None) for j in mem),
                            key=lambda x: -x[1])
                nt = int(q_topic[gi[mi]]) if q_topic_sim[gi[mi]] >= assign_sim / 2 else None
                it = classify_query(dem["query"].values[gi[mi]])
                gaps.append({"label": dem["query"].values[gi[mi]], "impressions": sum(x[1] for x in qs), "queries": qs[:10],
                             "nearest_topic": nt, "nearest_topic_sim": round(float(q_topic_sim[gi[mi]]), 3), "intent": it["intent"],
                             "suggested_format": {"informational": "guide / article", "commercial": "comparison / review page",
                                                  "transactional": "product or category page", "navigational": "brand/landing page"}[it["intent"]]})
            gaps.sort(key=lambda g: -g["impressions"])
    tmap = {t["id"]: t for t in topics}
    hub = []
    for t in topics:
        spokes = [u for u in t["pages"] if u != t["pillar"]]
        hub.append({"topic": t["id"], "label": t["label"], "pillar": t["pillar"],
                    "missing_spoke_to_pillar": [u for u in spokes if not G.has_edge(u, t["pillar"])],
                    "missing_pillar_to_spoke": [u for u in spokes if not G.has_edge(t["pillar"], u)],
                    "planned_spokes": [g["label"] for g in gaps if g["nearest_topic"] == t["id"]]})
    return {"topics": topics, "gaps": gaps, "weak_coverage": weak[:50], "hub_spoke": hub, "unclustered": unclustered, "threshold": th,
            "params": {"assign_sim": assign_sim, "cover_sim": cover_sim, "gap_sim": gap_sim, "min_gap_impressions": min_gap_impr},
            "page_topic": {pages[i]["url"]: int(labels[i]) for i in range(n)}}
