import networkx as nx
import numpy as np
import pandas as pd
import pytest

from asige import cannibalization, competitor, linking, predict, semantic, topical
from asige.text import normalize


def test_cannibalization_dispersion_and_actions():
    rows = []
    for q, a, b in (("split query", 500, 500), ("dominated query", 900, 100)):
        rows += [dict(query=q, page="https://e.com/a", clicks=a / 20, impressions=a, position=5.0),
                 dict(query=q, page="https://e.com/b", clicks=b / 40, impressions=b, position=7.0)]
    df = pd.DataFrame(rows).assign(ctr=lambda d: d.clicks / d.impressions)
    u = np.array([1.0, 0.0])
    emb = {"https://e.com/a": u, "https://e.com/b": u}
    out = cannibalization.detect(df, emb, {}, min_share=0.05)
    assert len(out) == 1 and out[0]["n_queries"] == 2 and out[0]["action"] == "consolidate"
    disp = {q["query"]: q["dispersion"] for q in out[0]["queries"]}
    assert disp["split query"] == 1.0 and disp["dominated query"] == pytest.approx(0.36, abs=1e-3)
    emb2 = {"https://e.com/a": u, "https://e.com/b": np.array([0.0, 1.0])}
    assert cannibalization.detect(df, emb2, {}, min_share=0.05)[0]["action"] == "monitor"
    day = lambda dt, a, b: pd.DataFrame([dict(query="split query", page="https://e.com/a", date=dt, impressions=a, clicks=1.0, position=5.0),
                                         dict(query="split query", page="https://e.com/b", date=dt, impressions=b, clicks=1.0, position=6.0)])
    assert cannibalization.rank_swaps(pd.concat([day("2025-01-01", 900, 100), day("2025-01-02", 100, 900), day("2025-01-03", 100, 900)]), "split query") == 1


def test_cannibalization_on_site(kit, site):
    ip, idx = kit["ipages"], kit["index"]
    emb = {p["url"]: idx.E[i] for i, p in enumerate(ip)}
    info = {u: {"intent": v["intent"]} for u, v in kit["intents"].items()}
    out = cannibalization.detect(kit["gsc"], emb, info)
    top = out[0]
    assert {site + "/seo/canonical-tags", site + "/seo/canonical-tag-guide"} == set(top["pages"])
    assert top["semantic_similarity"] > 0.85 and top["action"] == "consolidate" and top["winner"].endswith("canonical-tags")


def test_linking_graph_and_suggestions(kit, site):
    G, M, ip, idx = kit["G"], kit["M"], kit["ipages"], kit["index"]
    assert site + "/orphan" in M["orphans"] and M["depth"][site + "/d3"] == 3
    assert sum(M["pagerank"].values()) == pytest.approx(1.0)
    pages = {p["url"]: i for i, p in enumerate(ip)}
    sug = linking.suggest_links(G, ip, idx.E, M, start=kit["start"], min_sim=0.3, top_n=100)
    assert sug and all(not G.has_edge(s["source"], s["target"]) and s["source"] != s["target"] for s in sug)
    orphan = [s for s in sug if s["target"] == site + "/orphan"]
    assert orphan and "/seo/" in orphan[0]["source"] + "/seo/"  # a topically related page links to the orphan
    sim = linking.simulate_links(G, sug, kit["start"], k=40)
    assert sim["orphans_after"] <= sim["orphans_before"] and sim["mean_target_pagerank_change_pct"] > 0
    assert linking.find_context("Learn about كابل برق here", "کابل برق") is not None


def test_topics_authority_gaps(kit, site):
    ip, idx = kit["ipages"], kit["index"]
    pr = kit["M"]["pagerank"]
    t = topical.analyze_topics(idx, ip, kit["G"], pr, kit["gsc"])
    assert len(t["topics"]) >= 3 and all(x["details"]["pages"] >= 2 for x in t["topics"]) and t["unclustered"]
    for x in t["topics"]:
        assert 0 <= x["authority"] <= 1 and set(x["components"]) <= set(topical.AUTH_WEIGHTS) and x["pillar"] in x["pages"]
        w = x["weights_used"]
        assert x["authority"] == pytest.approx(sum(x["components"][k] * w[k] for k in w) / sum(w.values()), abs=2e-3)
    gap_q = {q[0] for g in t["gaps"] for q in g["queries"]}
    assert {"bitcoin price today", "crypto exchange fees"} <= gap_q
    assert "canonical tags" not in gap_q
    assert any(h["missing_spoke_to_pillar"] or h["missing_pillar_to_spoke"] for h in t["hub_spoke"])


def test_competitor_gaps_and_sov(kit):
    own = [p for p in kit["ipages"] if "/coffee/" in p["url"] and p["word_count"] > 100]
    comp = own[:3] + [p for p in kit["ipages"] if "/seo/" in p["url"] and p["word_count"] > 100]
    r = competitor.topic_gaps(own, comp, threshold=0.3)
    assert r["gaps"] and r["gaps"][0]["n_pages"] >= 3 and all("/seo/" in u for u in r["gaps"][0]["competitor_pages"])
    serp = pd.DataFrame({"keyword": ["a", "a", "b", "b", "c"], "position": [1, 2, 1, 15, 3],
                         "url": ["https://me.com/x", "https://rival.com/y", "https://rival.com/z", "https://me.com/q", "https://rival.com/w"],
                         "volume": [100, 100, 50, 50, 10]})
    s = competitor.load_serp(serp)
    sov = competitor.share_of_voice(s, lambda p: predict.CTRCurve().fit(serp.assign(clicks=0.0, impressions=1.0, position=1.0)).predict(p), "https://www.me.com")
    assert sum(sov["share_of_voice"].values()) == pytest.approx(1.0, abs=1e-3)
    assert {g["keyword"] for g in sov["keyword_gap"]} == {"c"}  # 'b': own at 15 (<=20) so not a gap
