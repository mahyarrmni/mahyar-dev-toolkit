import math
from pathlib import Path

import pandas as pd
import pytest

from stockpilot import analysis as an, data, db, report, sample

TODAY = pd.Timestamp("2025-06-01")


def mk(**kw):
    base = dict(sku="S1", name="n", category="c", warehouse="W1", quantity=10, unit_cost=100,
                monthly_demand=0, last_movement=TODAY, lead_time_days=10, unit_volume_m3=0.0)
    base.update(kw)
    return base


def frame(*rows):
    return an.enrich(pd.DataFrame([mk(**r) for r in rows]), TODAY)


def test_jalali():
    assert data.jalali_to_gregorian(1403, 1, 1) == (2024, 3, 20)
    assert data.jalali_to_gregorian(1400, 1, 1) == (2021, 3, 21)


def test_persian_import():
    raw = pd.DataFrame({"کد کالا": ["۱۰۰۱", "1002"], "نام کالا": ["كابل", "پریز"], "موجودی": ["۱٬۲۰۰", "50"],
                        "بهای واحد": ["۲۵۰٫۵", "100"], "آخرین گردش": ["1403/01/01", "2024-05-01"], "انبار": ["الف", "ب"]})
    df, warns = data.normalize_inventory(raw)
    assert df.sku.tolist() == ["1001", "1002"] and df.quantity.tolist() == [1200, 50]
    assert df.unit_cost[0] == 250.5 and df.name[0] == "کابل"
    assert df.last_movement[0] == pd.Timestamp("2024-03-20") and df.last_movement[1] == pd.Timestamp("2024-05-01")
    assert any("حجم" in w for w in warns)


def test_import_validation():
    with pytest.raises(ValueError):
        data.normalize_inventory(pd.DataFrame({"sku": ["a"], "name": ["x"]}))
    raw = pd.DataFrame({"sku": ["a", "b", "a"], "name": ["x", "y", "x"], "quantity": [5, -1, 7], "unit_cost": [1, 1, 1]})
    df, warns = data.normalize_inventory(raw)
    assert len(df) == 1 and df.quantity[0] == 5 and len(warns) >= 2


def test_csv_and_xlsx_roundtrip(tmp_path):
    inv, cap = sample.make_sample(TODAY)
    p = tmp_path / "x.xlsx"
    with pd.ExcelWriter(p) as w:
        inv.rename(columns=data.FA_HEADERS).to_excel(w, sheet_name="موجودی", index=False)
        cap.rename(columns=data.FA_HEADERS).to_excel(w, sheet_name="انبارها", index=False)
    inv2, cap2, _ = data.load_bytes(p.read_bytes(), "x.xlsx")
    assert len(inv2) == len(inv) and cap2.capacity_m3.sum() == cap.capacity_m3.sum()
    inv.to_csv(tmp_path / "x.csv", index=False)
    inv3, cap3, _ = data.load_bytes((tmp_path / "x.csv").read_bytes(), "x.csv")
    assert len(inv3) == len(inv) and cap3 is None


def test_abc_exact():
    # annual values 9600, 1200, 600, 360, 240 -> total 12000; cumulative before: 0, .8, .9, .95, .98
    d = frame(*[dict(sku=f"S{i}", monthly_demand=m, unit_cost=100) for i, m in enumerate([8, 1, 0.5, 0.3, 0.2])])
    abc = an.abc_classification(d)
    assert abc.abc.tolist() == ["A", "B", "B", "C", "C"]
    assert math.isclose(abc.cum_share.iloc[-1], 1.0)


def test_abc_aggregates_warehouses():
    d = frame(dict(sku="X", warehouse="W1", monthly_demand=5), dict(sku="X", warehouse="W2", monthly_demand=5))
    assert len(an.abc_classification(d)) == 1


def test_low_stock():
    d = frame(dict(sku="a", quantity=5, monthly_demand=30), dict(sku="b", quantity=15, monthly_demand=30),
              dict(sku="c", quantity=100, monthly_demand=30), dict(sku="d", quantity=0, monthly_demand=30),
              dict(sku="e", quantity=0, monthly_demand=0))
    low = an.low_stock(d, safety_days=7, cover_days=30).set_index("sku")
    assert low.status.to_dict() == {"d": "out", "a": "critical", "b": "low"}
    assert low.reorder_point["a"] == 17 and low.suggested_order["a"] == 42  # 1/day*(10+7+30)-5
    assert list(low.index) == ["d", "a", "b"]


def test_dead_stock():
    d = frame(dict(sku="old", last_movement=TODAY - pd.Timedelta(days=200)),
              dict(sku="empty", quantity=0, last_movement=TODAY - pd.Timedelta(days=500)),
              dict(sku="new", last_movement=TODAY - pd.Timedelta(days=10), monthly_demand=3),
              dict(sku="nodate", last_movement=pd.NaT, monthly_demand=0),
              dict(sku="nodate_active", last_movement=pd.NaT, monthly_demand=4))
    assert set(an.dead_stock(d, 180).sku) == {"old", "nodate"}


def test_capacity():
    d = frame(dict(sku="a", quantity=100, unit_volume_m3=0.5), dict(sku="b", warehouse="W2", quantity=10, unit_volume_m3=1))
    cap = pd.DataFrame({"warehouse": ["W1"], "capacity_m3": [55.0]})
    u = an.capacity_usage(d, cap).set_index("warehouse")
    assert u.used_m3["W1"] == 50 and math.isclose(u.utilization["W1"], 50 / 55) and u.status["W1"] == "high"
    assert u.status["W2"] == "unknown" and math.isclose(u.free_m3["W1"], 5)


def test_sample_pipeline_and_report():
    inv, cap = sample.make_sample(TODAY)
    d = an.enrich(inv, TODAY)
    abc = an.abc_classification(d)
    d = d.merge(abc[["sku", "abc"]], on="sku")
    low, dead, cu = an.low_stock(d), an.dead_stock(d), an.capacity_usage(d, cap)
    assert set(abc.abc) == {"A", "B", "C"} and len(low) > 5 and len(dead) > 5
    assert cu.utilization.between(0.2, 1.0).all()
    f = report.build_facts(d, abc, low, dead, cu, TODAY)
    text = report.template_fa(f)
    assert f"{f['stock_value']:,}" in text and str(f["dead_stock"]["rows"]) in text


def test_db_roundtrip():
    assert db.load() == (None, None)
    inv, cap = sample.make_sample(TODAY, n=20)
    db.save(inv, cap)
    inv2, cap2 = db.load()
    assert len(inv2) == 20 and inv2.last_movement.dtype.kind == "M" and len(cap2) == 3


def test_app_smoke():
    pytest.importorskip("streamlit")
    from streamlit.testing.v1 import AppTest
    at = AppTest.from_file(str(Path(__file__).parent.parent / "app.py"), default_timeout=90).run()
    assert not at.exception
    at.button(key="load_sample").click().run()
    assert not at.exception and len(at.tabs) == 6
