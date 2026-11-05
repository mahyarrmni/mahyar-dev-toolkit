import io

import pandas as pd
import pytest
from fastapi.testclient import TestClient

from asige import config, db, gsc, pipeline


def persist(kit, site, stats):
    with db.session() as s:
        p = db.Project(name="t", site_url=site + "/")
        s.add(p)
        s.flush()
        c = db.Crawl(project_id=p.id, status="done", stats=stats, config={})
        s.add(c)
        s.flush()
        s.add_all(db.Page(crawl_id=c.id, **{k: r.get(k) for k in db.PAGE_COLS if k != "crawl_id"}) for r in kit["pages"])
        s.add_all(db.GscRow(project_id=p.id, date=None, query=r.query, page=r.page, clicks=r.clicks, impressions=r.impressions, position=r.position)
                  for r in kit["gsc"].itertuples())
        return c.id


@pytest.fixture(scope="module")
def run(kit, site, tmp_path_factory):
    db.configure(f"sqlite:///{tmp_path_factory.mktemp('db')}/p.db")
    cid = persist(kit, site, {**kit["stats"], "budget_exhausted": False})
    res = pipeline.run_analysis(cid, {"brand_terms": ["acme"], "n_boot": 20})
    with db.session() as s:
        data = {r.kind: r.data for r in s.query(db.Analysis).filter_by(crawl_id=cid)}
    return cid, res, data


def test_all_analyses_stored(run):
    _, res, d = run
    assert {"technical", "semantic", "topical", "cannibalization", "linking", "predictive", "recommendations", "roadmap", "meta",
            "report_technical", "report_strategic"} <= set(d)
    assert res["health_score"] == d["technical"]["health_score"]
    assert d["predictive"]["curve_source"] == "generic_prior"  # tiny synthetic GSC: must NOT pretend to be site-specific
    assert any("cross-validation skipped" in w for w in d["meta"]["warnings"])


def test_every_recommendation_has_provenance_and_valid_dependencies(run):
    recs = run[2]["recommendations"]
    ids = [r["id"] for r in recs]
    assert len(ids) == len(set(ids)) > 10
    for r in recs:
        assert r["provenance"] and all({"module", "algorithm", "source", "metrics", "params"} <= set(p) for p in r["provenance"])
        assert 0 < r["confidence"]["score"] <= 0.95 and r["confidence"]["reasons"] and r["impact"]["basis"]
        assert set(r["dependencies"]) <= set(ids) and r["id"] not in r["dependencies"]
        assert r["priority"] == pytest.approx(r["impact"]["score"] * r["confidence"]["score"] / (r["effort"]["hours"] + 1), abs=2e-3)
    assert [r["priority"] for r in recs] == sorted((r["priority"] for r in recs), reverse=True)
    kinds = {r["type"] for r in recs}
    assert {"redirect_chain", "cannibalization", "internal_links"} <= kinds
    assert any(r["dependencies"] for r in recs)


def test_cannibalization_and_gaps_end_to_end(run, site):
    d = run[2]
    assert d["cannibalization"][0]["action"] == "consolidate" and d["cannibalization"][0]["winner"].endswith("canonical-tags")
    assert {"bitcoin price today"} <= {q[0] for g in d["topical"]["gaps"] for q in g["queries"]}
    gap = [r for r in d["recommendations"] if r["type"] == "content_gap"]
    assert gap and gap[0]["impact"]["basis"] == "ctr_curve_model" and gap[0]["impact"]["est_clicks_per_period"] > 0


def test_roadmap_consistency(run):
    rm, recs = run[2]["roadmap"], {r["id"]: r for r in run[2]["recommendations"]}
    for t in rm["tasks"].values():
        for dep in t["depends_on"]:
            assert rm["tasks"][dep]["finish_week"] is not None and (t["finish_week"] is None or rm["tasks"][dep]["finish_week"] < t["finish_week"])
    assert all(s["planned_hours"] <= rm["hours_per_week"] + 1e-6 for s in rm["schedule"])
    assert rm["narrative"] and "est_clicks_note" in rm["totals"]


def test_reports(run):
    tech, strat = run[2]["report_technical"], run[2]["report_strategic"]
    assert "Health score" in tech and "redirect" in tech.lower()
    assert "Top recommendations" in strat and "Internal linking" in strat and "reproducibility hash" in strat
    assert run[2]["meta"]["repro_hash"] in strat


def test_reproducible(run):
    cid, res, _ = run
    again = pipeline.run_analysis(cid, {"brand_terms": ["acme"], "n_boot": 20})
    assert again["repro_hash"] == res["repro_hash"]


def test_api_end_to_end(site, kit, tmp_path, monkeypatch):
    db.configure(f"sqlite:///{tmp_path}/api.db")
    monkeypatch.setenv("ASIGE_SYNC_JOBS", "1")
    monkeypatch.setattr(config, "ALLOW_PRIVATE", True)
    c = TestClient(__import__("asige.api", fromlist=["app"]).app)
    assert c.get("/health").json()["ok"]
    assert c.post("/projects", json={"name": "x", "site_url": "ftp://bad"}).status_code == 422
    pid = c.post("/projects", json={"name": "demo", "site_url": site}).json()["id"]
    cid = c.post(f"/projects/{pid}/crawls", json={"max_pages": 80, "delay": 0.05, "check_external": False}).json()["id"]
    st = c.get(f"/crawls/{cid}").json()
    assert st["status"] == "done" and st["stats"]["pages"] > 30, st
    csv = kit["gsc"][["query", "page", "clicks", "impressions", "position"]].to_csv(index=False)
    up = c.post(f"/projects/{pid}/gsc/upload", files={"file": ("gsc.csv", csv.encode(), "text/csv")})
    assert up.status_code == 200 and up.json()["rows"] > 50
    assert c.post(f"/projects/{pid}/gsc/upload", files={"file": ("bad.csv", b"a,b\n1,2", "text/csv")}).status_code == 422
    assert c.post(f"/crawls/{cid}/analyze", json={"brand_terms": [], "n_boot": 10}).status_code == 202
    assert c.get(f"/crawls/{cid}/analysis/status").json()["state"] == "done"
    recs = c.get(f"/crawls/{cid}/analysis/recommendations").json()
    assert recs and recs[0]["provenance"]
    md = c.get(f"/crawls/{cid}/report/strategic")
    assert md.status_code == 200 and md.text.startswith("# SEO Strategy Report")
    assert c.get(f"/crawls/{cid}/report/other").status_code == 404 and c.get(f"/crawls/{cid}/analysis/nope").status_code == 404
    # token passes through but is never echoed/stored
    monkeypatch.setattr(gsc.GSCClient, "query", lambda self, *a, **k: pd.DataFrame(
        {"query": ["q"], "page": [site + "/"], "clicks": [1.0], "impressions": [10.0], "position": [3.0], "ctr": [0.1]}))
    r = c.post(f"/projects/{pid}/gsc/sync", json={"site_url": "sc-domain:x", "start": "2025-01-01", "end": "2025-01-31", "access_token": "SECRET-TOKEN"})
    assert r.status_code == 200 and "SECRET-TOKEN" not in r.text
    # auth
    monkeypatch.setattr(config, "API_KEY", "k3y")
    assert c.get("/projects").status_code == 401 and c.get("/projects", headers={"X-API-Key": "k3y"}).status_code == 200


def test_pipeline_without_gsc(kit, site, tmp_path):
    db.configure(f"sqlite:///{tmp_path}/nogsc.db")
    k = {**kit, "gsc": kit["gsc"].head(0)}
    cid = persist(k, site, {**kit["stats"], "budget_exhausted": True})
    res = pipeline.run_analysis(cid, {})
    with db.session() as s:
        d = {r.kind: r.data for r in s.query(db.Analysis).filter_by(crawl_id=cid)}
    assert d["predictive"] is None and any("no Search Console data" in w for w in res["warnings"])
    assert d["recommendations"] and all(r["provenance"] for r in d["recommendations"])
    assert "Potential cannibalization" in d["report_strategic"] or "Keyword cannibalization" not in d["report_strategic"]
    assert any("crawl budget was exhausted" in " ".join(r["confidence"]["reasons"]) for r in d["recommendations"] if r["type"] in recommend_types())


def recommend_types():
    from asige import recommend
    return recommend.COMPLETENESS_DEPENDENT
