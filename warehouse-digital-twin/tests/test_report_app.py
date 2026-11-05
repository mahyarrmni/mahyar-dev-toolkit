import io
from pathlib import Path

import pandas as pd
import pytest
from streamlit.testing.v1 import AppTest

from wdt import viz
from wdt.i18n import EN, FA
from wdt.objective import Weights
from wdt.optimize import GAConfig, optimize
from wdt.pipeline import evaluate, kpi_table
from wdt.report import assignment_csv, build_html, build_xlsx
from wdt.routing import PickingConfig
from wdt.sim import SimConfig


@pytest.fixture(scope="module")
def study(sample):
    r = optimize(sample, Weights(), GAConfig(pop=30, gens=25, seed=0))
    return r, evaluate(sample, r, PickingConfig(), SimConfig(orders=200))


def test_i18n_keys_match():
    assert set(EN) == set(FA)
    assert all(FA[k].count("{") == EN[k].count("{") for k in EN)


def test_figures_and_route_geometry(sample, study):
    r, ev = study
    for metric in ("frequency", "utilization", "zone"):
        for lvl in (None, 0):
            f = viz.layout_figure(sample, r.locs, metric, lvl, "t", ev.plans_a[0].stops)
            assert len(f.data) == 3 and f.data[0].z.shape[0] == len(set(sample.wh.y.round(3)))
    pts = f.data[2]
    assert len(pts.x) == len(ev.plans_a[0].stops) * 3 + 2 or len(pts.x) >= 4
    assert (kpi_table(ev).improvement_pct.iloc[[0, 2]] > 0).all()


@pytest.mark.parametrize("lang", ["en", "fa"])
def test_report_html_and_xlsx(sample, study, lang):
    r, ev = study
    h = build_html(sample, r, ev, Weights(), lang)
    assert "<table" in h and "plotly" in h and ('dir="rtl"' in h) == (lang == "fa")
    assert (FA if lang == "fa" else EN)["r_title"] in h
    x = pd.read_excel(io.BytesIO(build_xlsx(sample, r, ev, lang)), sheet_name=None)
    assert {"assignment", "kpis", "objective", "capacity_zone"} <= set(x)
    a = x["assignment"]
    assert len(a) == sample.S and a.location_after.is_unique and a.moved.any()
    assert len(pd.read_csv(io.BytesIO(assignment_csv(sample, r)))) == sample.S


def test_streamlit_app_end_to_end():
    at = AppTest.from_file(str(Path(__file__).parent.parent / "app.py"), default_timeout=180).run()
    assert not at.exception
    at.sidebar.radio(key="lang").set_value("en").run()
    at.button(key="btn_build").click().run()
    assert not at.exception and not at.error
    at.number_input(key="pop").set_value(20)
    at.number_input(key="gens").set_value(15)
    at.button(key="btn_opt").click().run()
    at.number_input(key="n_ord").set_value(60)
    at.button(key="btn_sim").click().run()
    assert not at.exception and not at.error
    assert "ev" in at.session_state and "res" in at.session_state
    at.sidebar.radio(key="lang").set_value("fa").run()
    assert not at.exception
