import pytest

from asige import recommend, roadmap


def R(i, typ="x", targets=(), hours=2.0, impact=50.0, conf=0.8, est=None, deps=()):
    r = recommend.make(typ, f"rec {i}", "technical", "d", ["a"], list(targets), "t", conf, ["r"], hours, [recommend.prov("m", "a", "s")], est_clicks=est, base_impact=impact)
    r["dependencies"] = list(deps)
    return r


def test_priority_formula_and_impact_saturation():
    a, b, c = R(1, impact=None, est=100.0), R(2, impact=None, est=300.0), R(3, impact=None, est=0.0)
    out = {r["title"]: r for r in recommend.prioritize([a, b, c])}
    med = 200.0  # median of positive estimates
    assert out["rec 1"]["impact"]["score"] == pytest.approx(100 * 100 / (100 + med), abs=0.06)
    assert out["rec 3"]["impact"]["score"] == 0.0
    r = out["rec 2"]
    assert r["priority"] == pytest.approx(r["impact"]["score"] * r["confidence"]["score"] / (r["effort"]["hours"] + 1), abs=1e-3)


def test_dependencies_by_layer_and_target_overlap():
    tech = R(1, "redirect_chain", ["u1", "u2"])
    link = R(2, "internal_links", ["u2"])
    other = R(3, "internal_links", ["u9"])
    same_layer = R(4, "http_4xx", ["u2"])
    out = {r["title"]: r for r in recommend.link_dependencies([tech, link, other, same_layer])}
    assert out["rec 2"]["dependencies"] == sorted([tech["id"], same_layer["id"]]) and out["rec 3"]["dependencies"] == [] and out["rec 1"]["dependencies"] == []


def test_ids_are_deterministic():
    assert R(1, targets=["a"])["id"] == R(1, targets=["a"])["id"] != R(2, targets=["a"])["id"]


def test_roadmap_respects_dependencies_and_capacity():
    a = R(1, hours=10, impact=90)
    b = R(2, hours=10, impact=80, deps=[a["id"]])
    c = R(3, hours=25, impact=70)
    recs = recommend.prioritize([a, b, c])
    rm = roadmap.build_roadmap(recs, {}, hours_per_week=10, weeks=6)
    t = {x["title"]: x for x in rm["tasks"].values()}
    assert t["rec 2"]["finish_week"] > t["rec 1"]["finish_week"]
    assert all(s["planned_hours"] <= 10.0 + 1e-6 for s in rm["schedule"])
    assert t["rec 3"]["finish_week"] is not None and t["rec 3"]["finish_week"] >= 3  # 25h needs 3 weeks of 10h
    assert sum(s["planned_hours"] for s in rm["schedule"]) == pytest.approx(45.0)
    tiny = roadmap.build_roadmap(recs, {}, hours_per_week=5, weeks=2)
    assert tiny["totals"]["backlog_tasks"] >= 1 and tiny["backlog"]


def test_non_blocking_technical_rules_do_not_gate_other_work():
    thin = R(1, "thin_content", ["u1"])          # on-page quality: not a prerequisite
    dup = R(2, "duplicate_content_exact", ["u1"])  # consolidation: IS a prerequisite for linking to u1
    link = R(3, "internal_links", ["u1"])
    out = {r["title"]: r for r in recommend.link_dependencies([thin, dup, link])}
    assert out["rec 3"]["dependencies"] == [dup["id"]]
