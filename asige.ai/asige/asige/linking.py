"""Internal link graph analytics and semantic link suggestions with measurable simulated impact."""
import math
from collections import deque

import networkx as nx
import numpy as np

from .semantic import topk_cosine
from .text import normalize


def build_graph(pages, flags) -> nx.DiGraph:
    by = {p["url"]: p for p in pages}
    nodes = {p["url"] for p in pages if flags[p["url"]]["indexable"]}
    G = nx.DiGraph()
    G.add_nodes_from(nodes)
    for u in nodes:
        for l in by[u]["links"]:
            if l["nofollow"]:
                continue
            t = by.get(l["url"])
            dst = t["final_url"] if t and t["redirect_chain"] and t["final_url"] else l["url"]
            if dst in nodes and dst != u:
                G.add_edge(u, dst)
    return G


def click_depth(G, start):
    d, q = {start: 0}, deque([start])
    while q:
        u = q.popleft()
        for v in G.successors(u) if u in G else []:
            if v not in d:
                d[v] = d[u] + 1
                q.append(v)
    return d


def metrics(G, start) -> dict:
    pr = nx.pagerank(G, alpha=0.85) if G.number_of_nodes() else {}
    depth = click_depth(G, start)
    return {"pagerank": pr, "depth": depth, "inlinks": dict(G.in_degree()), "outlinks": dict(G.out_degree()),
            "orphans": [n for n in G if G.in_degree(n) == 0 and n != start], "unreachable": [n for n in G if n not in depth]}


def find_context(text: str, phrase: str, width=70, normalized=False):
    """Locate the anchor phrase inside source text; returns snippet or None."""
    t, ph = (text if normalized else normalize(text)), normalize(phrase)
    i = t.find(ph) if ph else -1
    return t[max(0, i - width): i + len(ph) + width] if i >= 0 else None


def suggest_links(G, pages, E, M, gsc_pages=None, start=None, max_out=100, per_target=3, min_sim=0.35, top_n=60):
    """pages/E: indexable pages and their L2-normalized embeddings (aligned). Score = sim * (0.5+0.5*PR_src/maxPR) * target_need,
    target_need = 1 + opportunity(gsc) + 0.5*[few inlinks] + 0.5*[deep]."""
    if len(pages) < 2:
        return []
    ids, sims = topk_cosine(E, E, k=min(per_target * 4 + 1, len(pages)))
    pr, mx = M["pagerank"], max(M["pagerank"].values() or [1])
    gains = {u: s.get("impressions", 0) for u, s in (gsc_pages or {}).items()}
    gmax = max(gains.values() or [1]) or 1
    out, ncache = [], {}
    for ti, t in enumerate(pages):
        tu = t["url"]
        if tu == start:
            continue
        need = 1 + gains.get(tu, 0) / gmax + 0.5 * (M["inlinks"].get(tu, 0) <= 1) + 0.5 * (M["depth"].get(tu, 9) > 3)
        top_q = ((gsc_pages or {}).get(tu, {}).get("top_queries") or [{}])[0].get("query")
        anchor, anchor_src = (top_q, "gsc_top_query") if top_q else ((t["h1s"] or [t["title"]])[0], "h1/title")
        got = 0
        for si, s in zip(ids[ti], sims[ti]):
            src = pages[si]
            su = src["url"]
            if su == tu or G.has_edge(su, tu) or G.out_degree(su) >= max_out or s < min_sim:
                continue
            if su not in ncache:
                ncache[su] = normalize(src["text"])
            ctx = find_context(ncache[su], anchor, normalized=True)
            out.append({"source": su, "target": tu, "score": round(float(s) * (0.5 + 0.5 * pr.get(su, 0) / mx) * need, 4),
                        "evidence": {"semantic_similarity": round(float(s), 3), "source_pagerank": round(pr.get(su, 0), 6),
                                     "target_inlinks": M["inlinks"].get(tu, 0), "target_depth": M["depth"].get(tu),
                                     "target_impressions": gains.get(tu), "anchor": anchor, "anchor_source": anchor_src,
                                     "placement": ctx or "no existing mention: add a sentence", "has_existing_mention": ctx is not None}})
            got += 1
            if got >= per_target:
                break
    return sorted(out, key=lambda x: -x["score"])[:top_n]


def simulate_links(G, suggestions, start, k=50) -> dict:
    """Add the top-k suggested edges, recompute PageRank/depth: measured (not guessed) structural effect."""
    H = G.copy()
    top = suggestions[:k]
    H.add_edges_from((s["source"], s["target"]) for s in top)
    b, a = nx.pagerank(G), nx.pagerank(H)
    targets = {s["target"] for s in top}
    db, da = click_depth(G, start), click_depth(H, start)
    return {"edges_added": len(top), "targets": len(targets),
            "mean_target_pagerank_change_pct": round(100 * float(np.mean([(a[t] - b[t]) / b[t] for t in targets if b.get(t)])) if targets else 0.0, 2),
            "orphans_before": sum(1 for n in G if G.in_degree(n) == 0 and n != start),
            "orphans_after": sum(1 for n in H if H.in_degree(n) == 0 and n != start),
            "mean_depth_before": round(float(np.mean(list(db.values()))), 3), "mean_depth_after": round(float(np.mean(list(da.values()))), 3),
            "unreachable_before": G.number_of_nodes() - len(db), "unreachable_after": H.number_of_nodes() - len(da)}


def layout(G, pr, top=150, seed=0):
    keep = sorted(G.nodes, key=lambda n: -pr.get(n, 0))[:top]
    H = G.subgraph(keep)
    pos = nx.spring_layout(H.to_undirected(), seed=seed, k=1 / math.sqrt(max(1, len(H))))
    return {"nodes": [{"url": n, "x": round(float(pos[n][0]), 4), "y": round(float(pos[n][1]), 4), "pagerank": round(pr.get(n, 0), 6)} for n in H],
            "edges": [[a, b] for a, b in H.edges]}
