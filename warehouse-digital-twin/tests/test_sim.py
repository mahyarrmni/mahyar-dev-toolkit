import itertools

import numpy as np
import pytest

from wdt.analysis import abc_classes, aisle_load, bottlenecks, capacity_tables, required_pickers
from wdt.objective import Weights
from wdt.optimize import GAConfig, optimize
from wdt.routing import OrderPlan, PickingConfig, plan_orders, summarize, tour_length, tsp
from wdt.sim import SimConfig, auto_rate, simulate, stage_capacity

PC = PickingConfig()


@pytest.fixture(scope="module")
def solved(sample):
    r = optimize(sample, Weights(), GAConfig(pop=40, gens=40, seed=0))
    return r, plan_orders(sample, r.baseline, PC), plan_orders(sample, r.locs, PC)


def test_tsp_matches_bruteforce(sample):
    wh, rng = sample.wh, np.random.default_rng(0)
    for n in (3, 4, 5, 6):
        for _ in range(8):
            stops = [int(v) for v in rng.choice(wh.n, n, replace=False)]
            best = min(tour_length(wh, s) for s in itertools.permutations(stops))
            assert tour_length(wh, tsp(wh, stops)) <= best * 1.02 + 1e-9


def test_plans_cover_orders_and_distances_consistent(sample, solved):
    r, _, plans = solved
    wh = sample.wh
    by_order = sample.orders.groupby("order_id").sku.apply(list)
    for pl in plans[:60]:
        locs = r.locs[sample.skus.index.get_indexer(by_order[pl.order_id])]
        need = {(wh.aisle[l], round(wh.y[l], 2)) for l in locs}
        got = {(wh.aisle[l], round(wh.y[l], 2)) for l in pl.stops}
        assert need == got and len(pl.stops) == len(got)
        assert np.isclose(pl.distance, tour_length(wh, pl.stops))
        assert all(t > 0 for t in pl.stop_pick_s)


def test_optimized_layout_shortens_real_pick_tours(solved):
    _, before, after = solved
    sb, sa = summarize(before, PC), summarize(after, PC)
    assert sa["total_distance_m"] < 0.85 * sb["total_distance_m"]
    assert sa["avg_service_s"] < sb["avg_service_s"]


def test_sim_single_order_matches_hand_calculation(sample):
    wh = sample.wh
    loc = 100
    plan = OrderPlan("o", [loc], [25.0], 2 * float(wh.depot_dist(loc)), 3, 5)
    sc = SimConfig(pickers=1, packers=1, orders=1, seed=3)
    res = simulate(wh, [plan], PC, sc, rate=60)
    d = float(wh.depot_dist(loc))
    expect = PC.setup_s + 2 * d / PC.speed_mps + 25.0 + sc.pack_base_s + sc.pack_line_s * 3
    assert np.isclose(res.orders.cycle[0], expect)


def test_sim_invariants_and_determinism(sample, solved):
    _, before, _ = solved
    sc = SimConfig(orders=200)
    rate = auto_rate(before, PC, sc)
    a, b = simulate(sample.wh, before, PC, sc, rate), simulate(sample.wh, before, PC, sc, rate)
    o = a.orders
    assert o.finish.notna().all() and (o.start >= o.arrival - 1e-9).all()
    assert (o.pick_done >= o.start).all() and (o.finish >= o.pick_done).all()
    assert ((a.resources.utilization >= 0) & (a.resources.utilization <= 1 + 1e-9)).all()
    assert a.kpis == b.kpis


def test_more_pickers_reduce_waiting_and_blocking_capacity_matters(sample, solved):
    _, before, _ = solved
    rate = auto_rate(before, PC, SimConfig(pickers=6))
    few = simulate(sample.wh, before, PC, SimConfig(pickers=6, packers=6, orders=300), rate)
    many = simulate(sample.wh, before, PC, SimConfig(pickers=12, packers=6, orders=300), rate)
    assert many.kpis["mean_queue_wait_min"] <= few.kpis["mean_queue_wait_min"]
    tight = simulate(sample.wh, before, PC, SimConfig(pickers=12, packers=6, aisle_capacity=1, orders=300), rate)
    assert tight.kpis["mean_aisle_wait_s"] >= many.kpis["mean_aisle_wait_s"]


def test_before_after_same_demand(sample, solved):
    _, before, after = solved
    sc = SimConfig(orders=400, packers=4)
    rate = auto_rate(before, PC, sc)
    sb, sa = (simulate(sample.wh, pl, PC, sc, rate) for pl in (before, after))
    assert sa.kpis["picker_utilization"] < sb.kpis["picker_utilization"]
    assert sa.kpis["mean_cycle_min"] <= sb.kpis["mean_cycle_min"]
    assert sa.kpis["avg_distance_m"] < sb.kpis["avg_distance_m"]


def test_bottleneck_detection(sample, solved):
    _, before, _ = solved
    sc = SimConfig(pickers=12, packers=1, orders=300)
    res = simulate(sample.wh, before, PC, sc, rate=auto_rate(before, PC, SimConfig(pickers=6)))
    f = bottlenecks(res)
    assert f and f[0]["resource"] == "Packing" and f[0]["severity"] == "high"
    cap = stage_capacity(before, PC, sc).set_index("stage").capacity_orders_h
    assert cap["packing"] < cap["picking"]


def test_capacity_abc_and_staffing(sample, solved):
    r, _, _ = solved
    t = capacity_tables(sample, r.locs)
    s = t["summary"]
    assert s["occupied"] == sample.S and s["occupied"] + s["free"] == sample.wh.n
    assert t["by_zone"].occupied.sum() == sample.S and t["by_level"].locations.sum() == sample.wh.n
    assert 0 < s["cube_utilization"] < s["occupied_volume_utilization"] <= 1
    cls = abc_classes(sample.freq)
    share_a = sample.freq[cls == "A"].sum() / sample.freq.sum()
    assert 0.5 < share_a <= 0.8 and (cls == "A").sum() < (cls == "C").sum()
    assert np.isclose(aisle_load(sample, r.locs).share.sum(), 1)
    assert required_pickers(120, 180, 0.85) == 8
