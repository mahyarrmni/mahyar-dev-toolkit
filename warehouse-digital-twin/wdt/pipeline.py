"""End-to-end evaluation of a baseline vs optimised layout (tours, simulation, capacity, bottlenecks)."""
from __future__ import annotations

from dataclasses import dataclass

from .analysis import aisle_load, bottlenecks, capacity_tables
from .data import Problem
from .optimize import Result
from .routing import PickingConfig, plan_orders, summarize
from .sim import SimConfig, SimResult, auto_rate, simulate, stage_capacity


@dataclass
class Evaluation:
    pc: PickingConfig
    sc: SimConfig
    rate: float
    plans_b: list
    plans_a: list
    sum_b: dict
    sum_a: dict
    sim_b: SimResult
    sim_a: SimResult
    cap_b: dict
    cap_a: dict
    load_b: object
    load_a: object
    find_b: list
    find_a: list
    stage_b: object
    stage_a: object


def evaluate(p: Problem, res: Result, pc: PickingConfig, sc: SimConfig) -> Evaluation:
    pb, pa = plan_orders(p, res.baseline, pc), plan_orders(p, res.locs, pc)
    rate = auto_rate(pb, pc, sc)  # identical demand for both layouts
    sb, sa = simulate(p.wh, pb, pc, sc, rate), simulate(p.wh, pa, pc, sc, rate)
    lb, la = aisle_load(p, res.baseline), aisle_load(p, res.locs)
    return Evaluation(pc, sc, rate, pb, pa, summarize(pb, pc), summarize(pa, pc), sb, sa,
                      capacity_tables(p, res.baseline), capacity_tables(p, res.locs), lb, la,
                      bottlenecks(sb, lb), bottlenecks(sa, la), stage_capacity(pb, pc, sc), stage_capacity(pa, pc, sc))


KPI_KEYS = [("sum", "avg_distance_m"), ("sum", "total_distance_m"), ("sum", "avg_service_s"), ("sum", "orders_per_picker_hour"),
            ("sim", "mean_cycle_min"), ("sim", "p95_cycle_min"), ("sim", "mean_queue_wait_min"), ("sim", "mean_aisle_wait_s"),
            ("sim", "mean_pack_wait_min"), ("sim", "picker_utilization"), ("sim", "throughput_per_h")]
HIGHER_BETTER = {"orders_per_picker_hour", "throughput_per_h"}


def kpi_table(ev: Evaluation):
    import pandas as pd
    rows = []
    for src, k in KPI_KEYS:
        b = (ev.sum_b if src == "sum" else ev.sim_b.kpis)[k]
        a = (ev.sum_a if src == "sum" else ev.sim_a.kpis)[k]
        ch = 100 * (a - b) / max(abs(b), 1e-12)
        rows.append((k, b, a, ch, ch if k in HIGHER_BETTER else -ch))
    return pd.DataFrame(rows, columns=["key", "before", "after", "change_pct", "improvement_pct"])
