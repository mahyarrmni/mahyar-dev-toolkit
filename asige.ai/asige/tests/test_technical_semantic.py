import numpy as np
import pytest
from sklearn.metrics import adjusted_rand_score

from asige import intent, semantic, technical
from asige.crawler import PAGE_DEFAULTS


@pytest.fixture(scope="module")
def tech(crawled, site):
    pages, stats = crawled
    return technical.analyze(pages, start_url=site + "/", external=stats["external"])


def rules(tech):
    return {i["rule"]: i for i in tech["issues"]}


def short(tech, rule, site):
    return {u.replace(site, "") for u in rules(tech)[rule]["urls"]}


def test_technical_rules_on_site(tech, site):
    r = rules(tech)
    assert "/old" in short(tech, "redirect_chain", site) and "/loop-a" in short(tech, "redirect_loop", site)
    assert {"/missing", "/blog/page/4"} <= short(tech, "http_4xx", site)
    assert "/about" in short(tech, "broken_internal_link", site)
    assert "/private" in short(tech, "noindex_in_sitemap", site)
    assert "/orphan" in short(tech, "orphan_page", site) and "/d6" in short(tech, "deep_page", site)
    assert "/thin" in short(tech, "thin_content", site)
    assert "/seo/robots" in short(tech, "jsonld_parse_error", site)
    assert "/seo/canonical-tags" in short(tech, "structured_data_missing_required", site)
    assert "/seo/" in short(tech, "hreflang_missing_return", site)
    assert "/blog/page/3" in short(tech, "pagination_broken_sequence", site)
    assert {"/seo/canonical-tags", "/seo/canonical-copy"} <= short(tech, "title_duplicate", site)
    assert {"/seo/canonical-tags", "/seo/canonical-copy"} <= short(tech, "duplicate_content_exact", site)
    assert "/about" in short(tech, "broken_external_link", site)
    assert "/print/espresso" not in {u.replace(site, "") for u in r["title_duplicate"]["urls"]}  # non-indexable pages excluded
    flags = tech["page_flags"]
    assert flags[site + "/print/espresso"]["reasons"] == ["canonicalized"] and not flags[site + "/private"]["indexable"]
    assert flags[site + "/d3"]["click_depth"] == 3 and flags[site + "/orphan"]["inlinks"] == 0
    assert 0 < tech["health_score"] < 100


def test_near_duplicate_detected(tech, site):
    ev = rules(tech)["near_duplicate_content"]["evidence"]
    pairs = {frozenset((e["url"].replace(site, ""), e["other"].replace(site, ""))) for e in ev}
    assert any("/seo/canonical-tag-guide" in p for p in pairs)


def test_structured_data_validation():
    assert technical.validate_jsonld([{"@type": "Product", "name": "x"}]) == [{"type": "Product", "missing": ["offers"]}]
    assert technical.validate_jsonld([{"@type": ["Article"], "headline": "h", "datePublished": "d", "author": "a"}]) == []


def test_health_score_formula():
    assert technical.health_score([{"severity": "high", "count": 5}], 10) == 92.5
    assert technical.health_score([], 10) == 100.0


GT = {"/coffee/": 0, "/seo/": 1, "/fa/seo": 2, "/fa/ghahve": 3}


@pytest.fixture(scope="module")
def index_pages(crawled, site):
    pages = [p for p in crawled[0] if p["status"] == 200 and p["word_count"] > 100 and not p["meta_robots"] and not p["canonical"]
             and not p["redirect_chain"]]
    texts = [semantic.page_text(p) for p in pages]
    return pages, semantic.SemanticIndex().fit(texts)


def truth(p, site):
    path = p["url"].replace(site, "")
    return next((v for k, v in GT.items() if path.startswith(k)), None)


def test_embeddings_separate_topics_in_both_languages(index_pages, site):
    pages, idx = index_pages
    lab = [(i, truth(p, site)) for i, p in enumerate(pages) if truth(p, site) is not None and "canonical" not in p["url"]]
    ii, y = [i for i, _ in lab], [t for _, t in lab]
    pred = semantic.cluster_vectors(idx.E[ii], distance_threshold=0.8)
    assert adjusted_rand_score(y, pred) > 0.8
    E = {p["url"].replace(site, ""): idx.E[i] for i, p in enumerate(pages)}
    assert E["/fa/seo-chist"] @ E["/fa/seo-amoozesh"] > E["/fa/seo-chist"] @ E["/fa/ghahve"]
    assert E["/coffee/espresso"] @ E["/coffee/grinders"] > E["/coffee/espresso"] @ E["/seo/sitemaps"]


def test_query_embedding_and_topk(index_pages, site):
    pages, idx = index_pages
    q = idx.transform(["espresso crema barista", "سئو و ایندکس گوگل"])
    ids, sims = semantic.topk_cosine(q, idx.E, k=3)
    assert pages[ids[0][0]]["url"].replace(site, "").startswith("/coffee")
    assert pages[ids[1][0]]["url"].replace(site, "").startswith("/fa/seo")
    assert (np.diff(sims, axis=1) <= 1e-9).all()


def test_keywords_entities_graph(index_pages):
    pages, idx = index_pages
    i = next(j for j, p in enumerate(pages) if p["url"].endswith("/coffee/espresso"))
    assert {"espresso", "coffee"} & {t for t, _ in idx.top_terms(i, 8)} or any("espresso" in t or "crema" in t for t, _ in idx.top_terms(i, 8))
    per, corpus = semantic.extract_entities(pages)
    assert any(e["source"] == "jsonld" and e["text"] == "Sara" for ents in per.values() for e in ents)
    assert any(c[1] == "PHRASE" for c in corpus)
    G = semantic.build_knowledge_graph(per)
    s = semantic.graph_summary(G)
    assert s["nodes"] > 10 and s["central_entities"]


def test_collocation_npmi_math():
    # N=20 tokens; alpha=10, beta=5; "alpha beta"=5 -> pxy=.25 px=.5 py=.25 -> npmi = ln(2)/-ln(.25) = 0.5
    docs = ["alpha beta"] * 5 + ["alpha gamma"] * 5
    c = semantic.collocations(docs, min_count=3, min_df=2, npmi_min=0.3)
    assert abs(c["alpha beta"]["npmi"] - 0.5) < 1e-3 and c["alpha gamma"]["df"] == 5
    assert all(v["npmi"] <= 1.0 for v in semantic.collocations(["a1 b1 c1 d1"] * 10, 3, 2, -1).values())
