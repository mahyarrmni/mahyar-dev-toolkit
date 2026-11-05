"""Recommendation engine: impact x confidence / effort, explicit dependencies, provenance on every item."""
import hashlib
import math
from statistics import median
from urllib.parse import urlsplit

import networkx as nx

from . import config

SEV_BASE = {"critical": 90, "high": 70, "medium": 40, "low": 15}
DEFAULT_CONF = (0.8, "deterministic check on crawled data")
CONF = {
    "http_5xx": (0.95, "observed directly in the crawl"), "http_4xx": (0.95, "observed directly in the crawl"),
    "broken_internal_link": (0.95, "observed directly in the crawl"), "redirect_chain": (0.95, "observed directly in the crawl"),
    "redirect_loop": (0.95, "observed directly in the crawl"), "noindex_in_sitemap": (0.9, "robots meta vs sitemap membership"),
    "orphan_page": (0.7, "depends on crawl completeness and sitemap coverage"), "deep_page": (0.7, "depends on crawl completeness"),
    "near_duplicate_content": (0.7, "SimHash is an approximation; verify pairs manually"),
    "thin_content": (0.6, "word count is only a proxy for usefulness"), "js_dependent_content": (0.6, "heuristic: few words + many scripts"),
    "no_outlinks": (0.6, "structural proxy"), "structured_data_missing_required": (0.75, "subset validator, not Google's full rules"),
    "title_long": (0.6, "character count approximates pixel width"), "meta_long": (0.6, "character count approximates pixel width"),
}
COMPLETENESS_DEPENDENT = {"orphan_page", "deep_page", "broken_internal_link", "http_4xx", "link_to_redirect", "no_outlinks"}
EFFORT = {  # rule: (base hours, hours per affected URL, capped at 200 URLs)
    "http_5xx": (4, 0.5), "http_4xx": (1, 0.1), "broken_internal_link": (1, 0.05), "redirect_chain": (1, 0.05), "redirect_loop": (1, 0.5),
    "link_to_redirect": (1, 0.02), "title_missing": (0.5, 0.15), "title_duplicate": (1, 0.15), "meta_missing": (0.5, 0.15),
    "duplicate_content_exact": (2, 0.5), "near_duplicate_content": (2, 0.75), "thin_content": (2, 1.0), "orphan_page": (1, 0.1),
    "structured_data_missing_required": (1, 0.2), "jsonld_parse_error": (1, 0.25), "hreflang_missing_return": (1, 0.1),
}
RANK = {"http_5xx": 0, "redirect_loop": 0, "fetch_error": 0, "robots_blocked_in_sitemap": 0, "noindex_in_sitemap": 0, "canonical_to_non_200": 0,
        "canonical_to_noindex": 0, "canonical_chain": 0, "http_4xx": 0, "broken_internal_link": 0, "redirect_chain": 0,
        "cannibalization": 1, "duplicate_content_exact": 1, "near_duplicate_content": 1, "content_gap": 2, "internal_links": 3, "ctr_snippet": 3, "striking_distance": 3, "competitor_gap": 2,
        "intent_mismatch": 2, "topic_hub": 3}


def prov(module, algorithm, source, metrics=None, urls=None, params=None):
    return {"module": module, "algorithm": algorithm, "source": source, "metrics": metrics or {}, "evidence_urls": (urls or [])[:20],
            "params": params or {}, "engine_version": config.VERSION}


def make(type_, title, category, description, actions, targets, impact_basis, confidence, conf_reasons, effort_h, provenance,
         est_clicks=None, base_impact=None):
    rid = "R-" + hashlib.sha1(f"{type_}|{title}|{sorted(targets)[:20]}".encode()).hexdigest()[:8]
    return {"id": rid, "type": type_, "title": title, "category": category, "description": description, "actions": actions,
            "targets": sorted(targets)[:200], "impact": {"score": base_impact, "est_clicks_per_period": est_clicks, "basis": impact_basis},
            "confidence": {"score": round(min(0.95, max(0.05, confidence)), 2), "reasons": conf_reasons},
            "effort": {"hours": round(effort_h, 1)}, "dependencies": [], "provenance": provenance}


def _tech_recs(tech, gsc_pages, exhausted, crawl_id):
    n_idx = max(1, tech["summary"]["indexable"])
    out = []
    for i in tech["issues"]:
        if i["severity"] == "info":
            continue
        rule, cnt = i["rule"], i["count"]
        conf, why = CONF.get(rule, DEFAULT_CONF)
        reasons = [why]
        if exhausted and rule in COMPLETENESS_DEPENDENT:
            conf *= 0.85
            reasons.append("crawl budget was exhausted: counts may be incomplete")
        base, per = EFFORT.get(rule, (1.0, 0.1))
        share = min(1.0, cnt / n_idx)
        exposed = sum((gsc_pages.get(u) or {}).get("clicks", 0) for u in i["urls"])
        out.append(make(rule, f"{i['message']} ({cnt})", "technical", f"{i['message']}. {i['fix']}",
                        [i["fix"], f"Re-crawl and confirm rule '{rule}' reports 0 affected URLs."], i["urls"], "severity_heuristic", conf, reasons,
                        base + per * min(cnt, 200),
                        [prov("technical", f"rule:{rule}", "crawl", {"affected": cnt, "severity": i["severity"], "share_of_indexable": round(share, 3),
                                                                 "gsc_clicks_on_listed_urls": exposed}, i["urls"], {"crawl_id": crawl_id})],
                        base_impact=round(SEV_BASE[i["severity"]] * (0.6 + 0.4 * share ** 0.5), 1)))
    return out


def _cannib_recs(cann, curve, top=10):
    out = []
    for c in [x for x in cann if x["action"] != "monitor"][:top]:
        best_pos = min(p["position"] for q in c["queries"] for p in q["pages"])
        gain = max(0.0, float(c["impressions"] * curve.predict([best_pos])[0] - c["clicks"])) if curve is not None else None
        conf = 0.5 + 0.3 * (c["semantic_similarity"] >= 0.85) + 0.1 * (c["n_queries"] >= 3) + 0.1 * (c["dispersion"] >= 0.5)
        steps = {"consolidate": [f"301-redirect the weaker page(s) to {c['winner']} and merge unique content into it.", "Update internal links to the winner."],
                 "differentiate_intent": ["Re-target each page to a distinct intent/keyword set.", "Use distinct anchors when interlinking."],
                 "merge_or_reoptimize": ["Merge overlapping sections or re-optimize titles/H1s so each page targets different queries."]}[c["action"]]
        out.append(make("cannibalization", f"Cannibalization: {', '.join(q['query'] for q in c['queries'][:2])}", "content",
                        f"{len(c['pages'])} pages split impressions for {c['n_queries']} queries (semantic similarity {c['semantic_similarity']}).", steps, c["pages"],
                        "ctr_curve_model" if gain is not None else "none", conf,
                        [f"dispersion {c['dispersion']}", f"similarity {c['semantic_similarity']}", f"{c['n_queries']} queries affected"],
                        3 + 1.5 * len(c["pages"]),
                        [prov("cannibalization", "gsc_share_dispersion+embedding_similarity", "gsc+crawl", {k: c[k] for k in ("impressions", "clicks", "dispersion", "semantic_similarity", "url_similarity", "score")},
                              c["pages"], {"min_share": 0.1, "min_impressions": 50})], est_clicks=round(gain, 1) if gain is not None else None, base_impact=None))
    return out


def _opp_recs(opps, rate, top=10):
    out = []
    by = {}
    for r in opps.get("ctr_underperformers", []):
        by.setdefault(r["page"], []).append(r)
    for page, rows in sorted(by.items(), key=lambda kv: -sum(r["gain"] for r in kv[1]))[:top]:
        gain = sum(r["gain"] for r in rows) * rate
        z = min(abs(r["z"]) for r in rows)
        prior = opps["curve_source"] != "site_gsc"
        out.append(make("ctr_snippet", f"Improve title/meta for {urlsplit(page).path or page}", "on-page",
                        f"{len(rows)} queries get significantly fewer clicks than the CTR curve predicts for their position.",
                        ["Rewrite title and meta description around the user's query intent; add differentiators.", "A/B compare CTR in GSC after 3-4 weeks."], [page],
                        "ctr_curve_model", min(0.85, 0.35 + 0.1 * z) * (0.6 if prior else 1.0),
                        [f"min |z| = {round(z, 2)} (one-sided binomial test)", f"curve source: {opps['curve_source']}"] + (["discounted x0.6: generic prior curve, not fitted on this site"] if prior else []),
                        1 + 0.5 * len(rows), [prov("predict", "ctr_curve_residual_binomial_z", "gsc", {"queries": [(r["query"], r["position"], r["ctr"], r["expected_ctr"], r["z"]) for r in rows[:5]],
                                                                                 "assumed_recovery_rate": rate}, [page], {"z_cut": -2.0})], est_clicks=round(gain, 1)))
    by = {}
    for r in opps.get("striking_distance", []):
        by.setdefault(r["page"], []).append(r)
    for page, rows in sorted(by.items(), key=lambda kv: -sum(r["gain"] for r in kv[1]))[:top]:
        gain = sum(r["gain"] for r in rows) * rate
        out.append(make("striking_distance", f"Push striking-distance queries on {urlsplit(page).path or page}", "content",
                        f"{len(rows)} queries rank at positions 8-20; moving up 3 positions is modelled with the CTR curve.",
                        ["Strengthen on-page coverage of these queries (headings, FAQ, examples).", "Add internal links with query-matching anchors."], [page],
                        "ctr_curve_model", 0.5, ["moving 3 positions is an assumption; success rate applied explicitly", f"curve source: {opps['curve_source']}"], 2 + 0.5 * len(rows),
                        [prov("predict", "striking_distance_curve_delta", "gsc", {"queries": [(r["query"], r["position"], r["impressions"]) for r in rows[:5]], "assumed_success_rate": rate},
                              [page], {"lift_positions": 3})], est_clicks=round(gain, 1)))
    return out


def _link_recs(sug, sim, gsc_pages, top=8):
    by = {}
    for s in sug:
        by.setdefault(s["target"], []).append(s)
    mx = max([(gsc_pages.get(t) or {}).get("impressions", 0) for t in by] or [1]) or 1
    out = []
    for t, rows in sorted(by.items(), key=lambda kv: -sum(r["score"] for r in kv[1]))[:top]:
        imp = (gsc_pages.get(t) or {}).get("impressions", 0)
        out.append(make("internal_links", f"Add {len(rows)} internal links to {urlsplit(t).path or t}", "architecture",
                        "Semantically related, higher-authority pages that do not yet link to this page.",
                        [f"On {urlsplit(r['source']).path}: link with anchor '{r['evidence']['anchor']}' ({'existing mention' if r['evidence']['has_existing_mention'] else 'add a sentence'})" for r in rows],
                        [t], "structural", 0.65, ["semantic similarity + PageRank of source", f"simulated across all suggestions: {sim}"], 0.5 * len(rows) + 0.5,
                        [prov("linking", "embedding_similarity*pagerank*need", "crawl(+gsc)", {"edges": [(r["source"], r["evidence"]["semantic_similarity"], r["evidence"]["source_pagerank"]) for r in rows],
                                                                                              "target_impressions": imp, "simulation": sim}, [t] + [r["source"] for r in rows])],
                        base_impact=round(20 + 60 * min(1, imp / mx), 1)))
    return out


def _topic_recs(topics, curve, top=8):
    out = []
    for g in topics["gaps"][:top]:
        est = None
        if curve is not None:
            est = sum(q[1] * (float(curve.predict([10])[0]) - float(curve.predict([q[3] or 40])[0])) for q in g["queries"])
        out.append(make("content_gap", f"Create content: {g['label']}", "content",
                        f"{len(g['queries'])} queries (impressions {int(g['impressions'])}) have no semantically matching page (best similarity < {topics['params']['gap_sim']}).",
                        [f"Publish a {g['suggested_format']} targeting '{g['label']}' and related queries.", "Link it from the topic pillar and related spokes."], [],
                        "ctr_curve_model" if est is not None else "none", 0.55, [f"intent: {g['intent']}", "gap = no page above similarity threshold"],
                        6, [prov("topical", "query_page_max_cosine<threshold+clustering", "gsc+crawl", {"queries": g["queries"][:5], "impressions": g["impressions"], "nearest_topic": g["nearest_topic"]},
                                 [], topics["params"])], est_clicks=round(est, 1) if est is not None else None))
    for h in topics["hub_spoke"]:
        miss = len(h["missing_spoke_to_pillar"])
        if miss >= 2:
            out.append(make("topic_hub", f"Link {miss} spokes to pillar of topic '{h['label']}'", "architecture",
                            "Pages in the topic cluster do not link to the cluster pillar.", [f"Add a contextual link to {h['pillar']} from each spoke."],
                            [h["pillar"]] + h["missing_spoke_to_pillar"], "structural", 0.7, ["graph edges missing inside a semantic cluster"], 0.3 * miss + 0.5,
                            [prov("topical", "pillar=argmax(pagerank+centroid_sim); missing hub edges", "crawl", {"pillar": h["pillar"], "missing": miss}, h["missing_spoke_to_pillar"])],
                            base_impact=35.0))
    return out


def _intent_recs(mism, top=8):
    return [make("intent_mismatch", f"Intent mismatch for '{m['query']}'", "content",
                 f"Query intent is {m['query_intent']} but the ranking page looks {m['page_intent']}.",
                 [f"Create or re-target a {m['query_intent']} page" + (f"; closest existing page: {m['better_page']}" if m.get("better_page") else "")], [m["page"]],
                 "none", 0.5 * m["query_confidence"] + 0.4 * m["page_confidence"], [f"matched terms: {m['matched']}"], 3,
                 [prov("intent", "lexicon_classifier(query)+page_signals", "gsc+crawl", m, [m["page"]])], base_impact=min(60.0, 15 + 10 * math.log10(max(10, m["impressions"]))))
            for m in mism[:top]]


def _comp_recs(comp, curve, top=5):
    out = []
    for g in (comp or {}).get("topic_gaps", {}).get("gaps", [])[:top]:
        out.append(make("competitor_gap", f"Competitor covers a topic you don't: {g['label']}", "content",
                        f"{g['n_pages']} competitor page(s) have no close match on your site (max similarity {g['max_similarity_to_own']}).",
                        ["Evaluate search demand for this topic, then plan a page or cluster."], [], "none", 0.45,
                        ["based on semantic distance to your pages only; demand not verified"], 5,
                        [prov("competitor", "joint_embedding_max_cosine<threshold", "competitor_crawl", {"terms": g["terms"], "n_pages": g["n_pages"]}, g["competitor_pages"])],
                        base_impact=min(55.0, 20 + 5 * g["n_pages"])))
    for g in ((comp or {}).get("serp") or {}).get("keyword_gap", [])[:top]:
        est = g["volume"] * float(curve.predict([8])[0]) * 0.3 if curve is not None else None
        out.append(make("competitor_gap", f"Keyword gap: {g['keyword']}", "content", "Competitors rank top-10; you are not in the top 20.",
                        ["Map the keyword to a page or create one; compare with ranking competitor pages."], [], "ctr_curve_model" if est else "none", 0.5,
                        ["SERP data supplied by the user"], 4, [prov("competitor", "serp_keyword_gap", "user_serp_export", g, [c["url"] for c in g["competitors"]])],
                        est_clicks=round(est, 1) if est else None))
    return out


def prioritize(recs):
    """Impact (0-100): click-model recs use est/(est+median est); others use their heuristic base. priority = impact*confidence/(hours+1)."""
    pos = [r["impact"]["est_clicks_per_period"] for r in recs if r["impact"]["est_clicks_per_period"]]
    k = median(pos) if pos else 1.0
    for r in recs:
        e = r["impact"]["est_clicks_per_period"]
        if e is not None and r["impact"]["score"] is None:
            r["impact"]["score"] = round(100 * e / (e + k), 1) if e > 0 else 0.0
        elif r["impact"]["score"] is None:
            r["impact"]["score"] = 20.0
        r["priority"] = round(r["impact"]["score"] * r["confidence"]["score"] / (r["effort"]["hours"] + 1), 3)
    return sorted(recs, key=lambda r: (-r["priority"], r["id"]))


def link_dependencies(recs):
    """B depends on A only if A is a true prerequisite (rank 0 crawl/index blockers, rank 1 consolidation) of lower rank and shares a URL. Acyclic by construction."""
    rank = lambda r: RANK.get(r["type"], 2)  # unlisted rules never block other work
    for b in recs:
        tb = set(b["targets"])
        b["dependencies"] = sorted(a["id"] for a in recs if a is not b and rank(a) <= 1 and rank(a) < rank(b) and tb & set(a["targets"]))
    g = nx.DiGraph((d, r["id"]) for r in recs for d in r["dependencies"])
    assert nx.is_directed_acyclic_graph(g)
    return recs


def build(ctx) -> list:
    gp, curve = ctx.get("gsc_pages") or {}, ctx.get("curve")
    recs = _tech_recs(ctx["tech"], gp, ctx.get("budget_exhausted", False), ctx.get("crawl_id"))
    if ctx.get("cannibalization"):
        recs += _cannib_recs(ctx["cannibalization"], curve)
    if ctx.get("opportunities"):
        recs += _opp_recs(ctx["opportunities"], ctx.get("capture_rate", 0.5))
    recs += _link_recs(ctx.get("link_suggestions", []), ctx.get("link_simulation"), gp)
    if ctx.get("topics"):
        recs += _topic_recs(ctx["topics"], curve)
    recs += _intent_recs(ctx.get("intent_mismatches", []))
    recs += _comp_recs(ctx.get("competitor"), curve)
    return link_dependencies(prioritize(recs))
