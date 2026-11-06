import json

import httpx
import numpy as np
import pandas as pd
import pytest

from asige import gsc, predict
from tests.synth import TRUE_CTR, make_curve_data


def test_load_gsc_csv_variants():
    csv = "Top queries,Clicks,Impressions,CTR,Position\nSEO guide,\"1,234\",\"10,000\",12.34%,3.2\nbad,5,2,1%,1\n"
    df = gsc.load_gsc(csv.encode())
    assert len(df) == 1 and df.clicks[0] == 1234 and abs(df.ctr[0] - 0.1234) < 1e-9  # bad row: clicks > impressions dropped
    fa = gsc.load_gsc("کوئری,صفحه,کلیک,نمایش,موقعیت\nآموزش سئو,https://e.com/آموزش,۱۲,۱٬۲۰۰,۴٫۵\n")
    assert fa["query"][0] == "آموزش سئو" and fa.impressions[0] == 1200 and fa.position[0] == 4.5 and fa.page[0].startswith("https://e.com/%")
    with pytest.raises(ValueError):
        gsc.load_gsc("a,b\n1,2")


def test_aggregate_weighted_position():
    df = pd.DataFrame({"query": ["a", "a"], "clicks": [1, 1], "impressions": [100, 300], "position": [2.0, 6.0]})
    assert gsc.aggregate(df, "query").position[0] == pytest.approx(5.0)


def test_gsc_api_client_pagination_and_errors():
    calls = []

    def handler(req: httpx.Request):
        body = json.loads(req.content)
        calls.append((str(req.url), body["startRow"], req.headers["authorization"]))
        rows = [{"keys": [f"q{body['startRow'] + i}", "https://e.com/p"], "clicks": 1, "impressions": 10, "ctr": 0.1, "position": 3}
                for i in range(2 if body["startRow"] == 0 else 1)]
        return httpx.Response(200, json={"rows": rows})

    c = gsc.GSCClient("TOKEN123", transport=httpx.MockTransport(handler))
    df = c.query("sc-domain:e.com", "2025-01-01", "2025-01-31", row_limit=2)
    assert len(df) == 3 and [x[1] for x in calls] == [0, 2] and "sc-domain%3Ae.com" in calls[0][0]
    assert calls[0][2] == "Bearer TOKEN123" and "TOKEN" not in repr(c)
    bad = gsc.GSCClient("SECRETTOKEN", transport=httpx.MockTransport(lambda r: httpx.Response(403, json={"error": {"message": "forbidden"}})))
    with pytest.raises(gsc.GSCError) as e:
        bad.query("https://e.com/", "2025-01-01", "2025-01-02")
    assert "SECRETTOKEN" not in str(e.value) and "403" in str(e.value)


def test_ctr_curve_recovers_truth_and_is_monotone():
    df = make_curve_data()
    c = predict.CTRCurve().fit(df)
    assert c.source == "site_gsc"
    t = c.table()
    assert all(t[i] >= t[i + 1] for i in range(1, 30))
    for p in (2, 3, 5, 8, 12, 20):
        assert abs(t[p] - float(TRUE_CTR(p))) / float(TRUE_CTR(p)) < 0.15, p
    assert predict.CTRCurve().fit(df.head(20)).source == "generic_prior"


def test_cross_validation_beats_baseline_and_explains():
    cv = predict.cross_validate(make_curve_data())
    assert cv["wape"]["site_curve"] < cv["wape"]["constant_ctr"]
    imp = cv["gbm_permutation_importance"]
    assert max(imp, key=imp.get) == "position"


def test_scenario_math_exact_and_reproducible():
    df = pd.DataFrame({"query": ["a", "b", "c"], "page": ["p1", "p2", "p3"], "clicks": [20.0, 5.0, 1.0],
                       "impressions": [1000.0, 800.0, 50.0], "position": [10.0, 4.0, 15.0]}).assign(ctr=lambda d: d.clicks / d.impressions)
    prior = predict.CTRCurve().fit(df)
    sc = [{"name": "t", "select": {"min_position": 8, "max_position": 20}, "improve_by": 3}]
    r = predict.simulate(df, prior, sc)[0]
    exp = 1000 * (predict.PRIOR_CTR[6] - predict.PRIOR_CTR[9]) + 50 * (predict.PRIOR_CTR[11] - predict.PRIOR_CTR[14])
    assert r["rows"] == 2 and r["projected_extra_clicks"] == pytest.approx(exp, abs=0.06) and r["interval_90"] is None
    big = make_curve_data()
    site = predict.CTRCurve().fit(big)
    s1 = predict.simulate(big, site, [{"name": "x", "select": {"min_position": 5}, "target_position": 3}], n_boot=30)
    s2 = predict.simulate(big, site, [{"name": "x", "select": {"min_position": 5}, "target_position": 3}], n_boot=30)
    assert s1 == s2 and s1[0]["interval_90"][0] <= s1[0]["projected_extra_clicks"] <= s1[0]["interval_90"][1]


def test_opportunities_flag_underperformers():
    df = make_curve_data().head(400).copy()
    df.loc[0, ["position", "impressions", "clicks"]] = [2.0, 5000.0, 10.0]
    df["ctr"] = df.clicks / df.impressions
    o = predict.opportunities(df, predict.CTRCurve().fit(make_curve_data()))
    assert o["ctr_underperformers"][0]["impressions"] == 5000 and o["ctr_underperformers"][0]["z"] < -2
    assert all(8 <= r["position"] <= 20 for r in o["striking_distance"])
