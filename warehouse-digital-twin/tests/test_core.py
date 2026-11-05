import io
import itertools

import numpy as np
import pandas as pd
import pytest

from wdt.data import DataError, build_problem, first_fit, load_table, parse_orders, parse_skus
from wdt.model import generate_warehouse
from wdt.objective import Objective, Weights
from wdt.optimize import GAConfig, nondominated, optimize, solve_linear
from conftest import tiny_problem


def test_distance_metric_properties():
    wh = generate_warehouse(aisles=4, bays=6, levels=2)
    rng = np.random.default_rng(1)
    a, b, c = (rng.integers(wh.n, size=300) for _ in range(3))
    assert np.allclose(wh.dist(a, b), wh.dist(b, a))
    assert (wh.dist(a, a) == 0).all()
    assert (wh.dist(a, c) <= wh.dist(a, b) + wh.dist(b, c) + 1e-9).all()
    assert (wh.depot_dist(a) >= 0).all()


def test_path_length_equals_distance():
    wh = generate_warehouse(aisles=3, bays=8, levels=1)
    rng = np.random.default_rng(2)
    for _ in range(50):
        i, j = (int(v) for v in rng.integers(wh.n, size=2))
        pts = np.array(wh.path(i, j))
        assert np.isclose(np.abs(np.diff(pts, axis=0)).sum(), wh.dist(i, j))
    stops = [int(v) for v in rng.integers(wh.n, size=4)]
    pts = np.array(wh.route_points(stops))
    exp = wh.depot_dist(stops[0]) + sum(wh.dist(a, b) for a, b in zip(stops, stops[1:])) + wh.depot_dist(stops[-1])
    assert np.isclose(np.abs(np.diff(pts, axis=0)).sum(), exp)


def test_parse_persian_headers_digits_and_classes():
    sk = pd.DataFrame({"کد کالا": ["A1", "A2"], "نام کالا": ["برنج", "شیر"], "حجم": ["۰٫۰۲", "0.01"],
                       "وزن": ["۵", "2"], "موجودی": ["۱۰۰", "50"], "نوع نگهداری": ["عمومی", "سردخانه"]})
    s, _ = parse_skus(sk)
    assert s.loc["A1", "unit_volume_m3"] == 0.02 and s.loc["A1", "pickface_qty"] == 100
    assert s.loc["A2", "storage_class"] == "cold"
    o, w = parse_orders(pd.DataFrame({"شماره سفارش": ["۱", "۱", "۲", "۲"], "کد کالا": ["A1", "A1", "A2", "ZZ"],
                                      "تعداد": ["۲", "۳", "۱", "۱"]}), s)
    assert len(o) == 2 and o.qty.iloc[0] == 5 and any("unknown SKU" in x for x in w)


def test_load_csv_cp1256_arabic_letters_and_semicolon():
    # legacy Windows-1256 exports use Arabic yeh (ي), which must map to Persian ی
    raw = "کد کالا;حجم;وزن;موجودی\nA1;0.5;2;3\n".replace("ی", "ي").encode("cp1256")
    df = load_table(io.BytesIO(raw), "x.csv")
    sk, _ = parse_skus(df)
    assert sk.index[0] == "A1" and sk.pickface_qty.iloc[0] == 3


def test_missing_columns_and_infeasible_sku():
    with pytest.raises(DataError):
        parse_skus(pd.DataFrame({"sku": ["a"]}))
    wh = generate_warehouse(aisles=1, bays=2, levels=1)
    sk, _ = parse_skus(pd.DataFrame({"sku": ["a"], "unit_volume_m3": [5], "unit_weight_kg": [1], "stock_qty": [1]}))
    od, _ = parse_orders(pd.DataFrame({"order_id": [1], "sku": ["a"]}), sk)
    with pytest.raises(DataError):
        build_problem(wh, sk, od)


def _brute(p, obj):
    perms = np.array(list(itertools.permutations(range(p.wh.n), p.S)))
    c = obj.cost(perms)
    return perms[int(np.argmin(c))], float(c.min())


def test_exact_assignment_matches_bruteforce():
    p = tiny_problem()
    base = first_fit(p)
    obj = Objective(p, Weights(distance=0.6, affinity=0, ergonomics=0.3, slotfit=0.1), base)
    locs, val = solve_linear(p, obj.C_lin)
    _, best = _brute(p, obj)
    assert np.isclose(val, best) and obj.violations(locs) == 0


def test_ga_finds_bruteforce_optimum_of_full_objective():
    p = tiny_problem(3)
    r = optimize(p, Weights(0.4, 0.4, 0.1, 0.1), GAConfig(pop=40, gens=60, seed=1))
    _, best = _brute(p, r.objective)
    assert r.cost <= best * 1.005 and r.objective.violations(r.locs) == 0
    assert r.cost <= r.seed_cost + 1e-12 and r.lower_bound <= r.cost + 1e-9


def test_sample_optimization_valid_and_better(sample):
    r = optimize(sample, Weights(), GAConfig(pop=40, gens=40, seed=0))
    wh = sample.wh
    assert len(set(r.locs.tolist())) == sample.S                       # one SKU per location
    assert sample.feasible[np.arange(sample.S), r.locs].all()           # size, weight, zone constraints
    assert r.cost < 0.7 * r.base_cost and r.cost <= r.seed_cost + 1e-12
    assert r.components["distance"] < r.base_components["distance"]
    # high-frequency SKUs end up closer to the depot than low-frequency ones
    d0 = wh.depot_dist(r.locs)
    hot = sample.freq >= np.quantile(sample.freq, 0.9)
    cold = sample.freq <= np.quantile(sample.freq, 0.5)
    assert d0[hot].mean() < d0[cold].mean()


def test_determinism(sample):
    a = optimize(sample, Weights(), GAConfig(pop=20, gens=10, seed=5)).locs
    b = optimize(sample, Weights(), GAConfig(pop=20, gens=10, seed=5)).locs
    assert (a == b).all()


def test_nondominated():
    df = pd.DataFrame({"distance": [1, 2, 3], "affinity": [3, 2, 3], "ergonomics": [1, 1, 2], "slotfit": [1, 1, 1]})
    assert nondominated(df).tolist() == [True, True, False]
