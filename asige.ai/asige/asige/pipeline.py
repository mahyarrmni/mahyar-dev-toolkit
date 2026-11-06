"""End-to-end orchestration: stored crawl (+GSC) -> analyses -> recommendations -> roadmap -> reports. Deterministic."""
import time
from collections import defaultdict

import numpy as np
import pandas as pd

from . import (cannibalization, competitor, config, db, gsc, intent, linking, predict, recommend, reports, roadmap, semantic,
               technical, topical)
from .text import normalize
from .urls import normalize_url
from .util import digest, jsonable

DEFAULTS = {"brand_terms": [], "competitor_crawl_id": None, "serp_csv": None, "hours_per_week": 20.0, "weeks": 12, "n_boot": 100,
            "embeddings": None, "capture_rate": 0.5, "use_llm": False}


def _gsc_frame(rows):
    df = pd.DataFrame([{"date": r.date, "query": r.query, "page": r.page, "clicks": r.clicks, "impressions": r.impressions, "position": r.position} for r in rows])
    if df.empty:
        return df
    df["ctr"] = df.clicks / df.impressions
    return df.sort_values(["query", "page", "date"], na_position="first").reset_index(drop=True)


def intent_mismatches(g, page_intents, brands, mapping_best, top=150):
    a = g.groupby(["query", "page"], as_index=False).agg(impressions=("impressions", "sum"), clicks=("clicks", "sum"))
    out = []
    for r in a.sort_values(["impressions", "query"], ascending=[False, True]).head(top).itertuples():
        qc, pi = intent.classify_query(r.query, brands), page_intents.get(r.page)
        if not pi or qc["default"] or qc["confidence"] < 0.5 or pi["confidence"] < 0.5 or intent.compat(qc["intent"], pi["intent"]) > 0:
            continue
        out.append({"query": r.query, "page": r.page, "impressions": float(r.impressions), "query_intent": qc["intent"], "query_confidence": qc["confidence"],
                    "page_intent": pi["intent"], "page_confidence": pi["confidence"], "matched": qc["matched"], "better_page": mapping_best.get(r.query)})
    return out


def run_analysis(crawl_id: int, params: dict = None) -> dict:
    P, T, W = {**DEFAULTS, **(params or {})}, {}, []
    tick = lambda name, t0: T.__setitem__(name, round(time.perf_counter() - t0, 3))
    with db.session() as s:
        crawl = s.get(db.Crawl, crawl_id)
        project = s.get(db.Project, crawl.project_id)
        pages = sorted((db.page_to_dict(p) for p in s.query(db.Page).filter_by(crawl_id=crawl_id)), key=lambda p: p["url"])
        gdf = _gsc_frame(s.query(db.GscRow).filter_by(project_id=project.id).all())
        cstats, site = crawl.stats or {}, project.site_url
        comp_pages = None
        if P["competitor_crawl_id"]:
            comp_pages = [db.page_to_dict(p) for p in s.query(db.Page).filter_by(crawl_id=P["competitor_crawl_id"]).order_by(db.Page.url)]
    if not pages:
        raise ValueError("crawl has no pages")
    start = normalize_url(site)
    brands = [normalize(b) for b in P["brand_terms"]]

    t0 = time.perf_counter()
    tech = technical.analyze(pages, start_url=start, external=cstats.get("external"))
    flags = tech["page_flags"]
    ip = [p for p in pages if flags[p["url"]]["indexable"]]
    if len(ip) < 3:
        raise ValueError("need at least 3 indexable pages for semantic analysis")
    tick("technical", t0)

    t0 = time.perf_counter()
    index = semantic.SemanticIndex(backend=P["embeddings"]).fit([semantic.page_text(p) for p in ip])
    page_intents = {p["url"]: intent.classify_page(p) for p in ip}
    ents, corpus = semantic.extract_entities(ip)
    tick("semantic", t0)

    t0 = time.perf_counter()
    G = linking.build_graph(pages, flags)
    M = linking.metrics(G, start)
    tick("link_graph", t0)

    has_gsc = len(gdf) > 0
    curve, nonbrand, gp, opps, pred, cann, mapping, mism, bmask = None, None, {}, None, None, None, [], [], None
    if has_gsc:
        t0 = time.perf_counter()
        bmask = gdf["query"].map(lambda q: any(b in q for b in brands)) if brands else pd.Series(False, index=gdf.index)
        nonbrand = gdf[~bmask].reset_index(drop=True)
        crawled = set(flags)
        gp = {u: v for u, v in gsc.page_stats(gdf).items() if u in crawled}
        curve = predict.CTRCurve().fit(gdf, exclude_mask=bmask)
        cv = predict.cross_validate(nonbrand, brands) if len(nonbrand) >= 200 else None
        if cv is None:
            W.append("fewer than 200 non-brand GSC rows: curve cross-validation skipped")
        sc = [{"name": "Striking distance: +3 positions for queries at 8-20", "select": {"min_position": 8, "max_position": 20, "min_impressions": 50}, "improve_by": 3},
              {"name": "Top-10 queries to position 3 (impressions >= 100)", "select": {"min_position": 4, "max_position": 10, "min_impressions": 100}, "target_position": 3}]
        opps = predict.opportunities(nonbrand, curve)
        pred = {"curve_source": curve.source, "curve": curve.table(), "fit_rows": curve.fit_rows, "cross_validation": cv,
                "scenarios": predict.simulate(nonbrand, curve, sc, n_boot=P["n_boot"]), "opportunities": opps,
                "brand_rows_excluded": int(bmask.sum())}
        emb = {p["url"]: index.E[i] for i, p in enumerate(ip)}
        cann = cannibalization.detect(nonbrand, emb, {u: {"intent": v["intent"]} for u, v in page_intents.items()})
        for c in cann[:20]:
            c["rank_swaps"] = {q["query"]: cannibalization.rank_swaps(nonbrand, q["query"]) for q in c["queries"][:3]} if "date" in nonbrand else None
        top_q = nonbrand.groupby("query").impressions.sum().sort_values(ascending=False).head(300).index.tolist()
        cur = nonbrand[nonbrand["query"].isin(top_q)].sort_values(["clicks", "impressions"]).groupby("query").tail(1).set_index("query").page.to_dict()
        mapping = intent.map_keywords(top_q, index, ip, page_intents, cur, brands)
        best = {m["keyword"]: m["best"]["url"] for m in mapping if m["mapped"]}
        mism = intent_mismatches(nonbrand, page_intents, brands, best)
        tick("gsc_models", t0)
    else:
        W.append("no Search Console data: predictive models, cannibalization (GSC) and demand-based gaps are skipped")
        cann = cannibalization.crawl_only_candidates(ip, index.E)

    t0 = time.perf_counter()
    topics = topical.analyze_topics(index, ip, G, M["pagerank"], nonbrand)
    sug = linking.suggest_links(G, ip, index.E, M, gp, start=start)
    lsim = linking.simulate_links(G, sug, start) if sug else None
    tick("topical_linking", t0)

    comp = None
    if comp_pages or P["serp_csv"]:
        comp = {}
        if comp_pages:
            cp = [p for p in comp_pages if p["status"] == 200 and "html" in (p["content_type"] or "") and p["word_count"] >= 100]
            comp["topic_gaps"] = competitor.topic_gaps(ip, cp, backend=P["embeddings"])
        if P["serp_csv"]:
            c2 = curve or predict.CTRCurve().fit(pd.DataFrame({"clicks": [], "impressions": [], "position": []}))
            comp["serp"] = competitor.share_of_voice(competitor.load_serp(P["serp_csv"]), c2.predict, site)

    ctx = {"tech": tech, "crawl_id": crawl_id, "budget_exhausted": cstats.get("budget_exhausted", False), "gsc_pages": gp, "curve": curve,
           "cannibalization": cann if has_gsc else None, "opportunities": opps, "link_suggestions": sug, "link_simulation": lsim, "topics": topics,
           "intent_mismatches": mism, "competitor": comp, "capture_rate": P["capture_rate"]}
    recs = recommend.build(ctx)
    rm = roadmap.build_roadmap(recs, gp, P["hours_per_week"], P["weeks"])
    rm["narrative"] = roadmap.narrate(rm, tech["health_score"])
    if P["use_llm"]:
        rm["narrative_llm"] = roadmap.narrate_llm(rm)

    depth = [d for d in M["depth"].values()]
    payload = {
        "technical": tech,
        "semantic": {"backend": index.backend, "pages": len(ip), "page_terms": {p["url"]: [t for t, _ in index.top_terms(i, 8)] for i, p in enumerate(ip)},
                     "page_intents": page_intents, "entities": {u: e[:8] for u, e in ents.items()}, "corpus_entities": corpus[:60],
                     "knowledge_graph": semantic.graph_summary(semantic.build_knowledge_graph(ents, topics["page_topic"])),
                     "keyword_mapping": mapping, "intent_mismatches": mism, "limitations": "heuristic entities; LSA is monolingual per script"},
        "topical": topics, "cannibalization": cann,
        "linking": {"stats": {"pages": G.number_of_nodes(), "edges": G.number_of_edges(), "orphans": len(M["orphans"]), "unreachable": len(M["unreachable"]),
                              "max_depth": max(depth) if depth else 0},
                    "top_pagerank": sorted(M["pagerank"].items(), key=lambda kv: (-kv[1], kv[0]))[:20], "orphans": M["orphans"][:50],
                    "suggestions": sug, "simulation": lsim, "graph": linking.layout(G, M["pagerank"])},
        "predictive": pred, "competitor": comp, "recommendations": recs, "roadmap": rm}
    payload = jsonable(payload)
    meta = {"engine_version": config.VERSION, "seed": config.SEED, "params": jsonable({k: v for k, v in P.items() if k != "serp_csv"}),
            "inputs": {"pages_digest": digest([(p["url"], p["content_hash"], p["status"]) for p in pages]), "gsc_digest": digest(gdf.to_dict("records")) if has_gsc else None},
            "warnings": W, "crawl": {k: cstats.get(k) for k in ("pages", "errors", "blocked_by_robots", "elapsed_s", "pages_per_s", "budget_exhausted")}}
    meta["repro_hash"] = digest(payload)
    meta["stage_seconds"] = T
    bundle = {**payload, "meta": meta, "site": site}
    payload["report_technical"], payload["report_strategic"] = reports.technical_report(bundle), reports.strategic_report(bundle)
    payload["meta"] = jsonable(meta)
    with db.session() as s:
        s.query(db.Analysis).filter_by(crawl_id=crawl_id).delete()
        s.add_all(db.Analysis(crawl_id=crawl_id, kind=k, data=v, meta={"engine_version": config.VERSION}) for k, v in payload.items())
    return {"repro_hash": meta["repro_hash"], "health_score": tech["health_score"], "recommendations": len(recs), "warnings": W, "stage_seconds": T}
