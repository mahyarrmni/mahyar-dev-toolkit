"""Discrete-event picking & packing simulation (SimPy).

Orders arrive as a Poisson process into a FIFO queue; each of `pickers` workers takes an order, walks its pre-computed
tour, and at every stop occupies one place of that aisle's resource (capacity = aisle_capacity, models blocking),
then returns to the depot; the order then queues for one of `packers` packing stations.
"""
from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd
import simpy

from .model import Warehouse
from .routing import OrderPlan, PickingConfig


@dataclass
class SimConfig:
    pickers: int = 8
    packers: int = 3
    aisle_capacity: int = 2
    orders: int = 400
    target_util: float = 0.85   # used by auto_rate (picker utilisation implied by the baseline layout)
    pack_base_s: float = 40.0
    pack_line_s: float = 6.0
    seed: int = 1


@dataclass
class SimResult:
    orders: pd.DataFrame
    kpis: dict
    resources: pd.DataFrame
    series: pd.DataFrame


def auto_rate(baseline_plans: list[OrderPlan], pcfg: PickingConfig, scfg: SimConfig) -> float:
    """Arrival rate (orders/h) that loads the pickers to target_util under the baseline layout (analytical)."""
    d = np.array([pl.distance for pl in baseline_plans])
    svc = pcfg.setup_s + d / pcfg.speed_mps + np.array([sum(pl.stop_pick_s) for pl in baseline_plans])
    return scfg.target_util * scfg.pickers * 3600.0 / float(svc.mean())


def stage_capacity(plans: list[OrderPlan], pcfg: PickingConfig, scfg: SimConfig) -> pd.DataFrame:
    """Analytical maximum sustainable throughput (orders/h) of each stage."""
    d = np.array([pl.distance for pl in plans])
    svc = pcfg.setup_s + d / pcfg.speed_mps + np.array([sum(pl.stop_pick_s) for pl in plans])
    pack = scfg.pack_base_s + scfg.pack_line_s * np.array([pl.lines for pl in plans])
    return pd.DataFrame({"stage": ["picking", "packing"],
                         "capacity_orders_h": [scfg.pickers * 3600 / svc.mean(), scfg.packers * 3600 / pack.mean()]})


def simulate(wh: Warehouse, plans: list[OrderPlan], pcfg: PickingConfig, scfg: SimConfig, rate: float) -> SimResult:
    rng = np.random.default_rng(scfg.seed)            # same seed => same order stream for every layout
    N = scfg.orders
    pick_idx = rng.integers(len(plans), size=N)
    arrivals = np.cumsum(rng.exponential(3600.0 / rate, size=N))
    env = simpy.Environment()
    queue = simpy.Store(env)
    packers = simpy.Resource(env, scfg.packers)
    aisles = {int(a): simpy.Resource(env, scfg.aisle_capacity) for a in np.unique(wh.aisle)}
    rec = {k: np.full(N, np.nan) for k in ("start", "pick_done", "finish", "aisle_wait", "pack_wait")}
    busy = {"pickers": 0.0, "packers": 0.0}
    ab = {a: [0.0, 0.0, 0] for a in aisles}  # busy s, wait s, visits
    done, series = [0], []

    def source():
        for k in range(N):
            yield env.timeout(max(0.0, float(arrivals[k] - env.now)))
            yield queue.put(k)

    def pack(k, plan):
        t0 = env.now
        with packers.request() as r:
            yield r
            rec["pack_wait"][k] = env.now - t0
            s = scfg.pack_base_s + scfg.pack_line_s * plan.lines
            yield env.timeout(s)
            busy["packers"] += s
        rec["finish"][k] = env.now
        done[0] += 1

    def picker():
        while True:
            k = yield queue.get()
            plan, t0, pos, wtot = plans[pick_idx[k]], env.now, None, 0.0
            rec["start"][k] = t0
            yield env.timeout(pcfg.setup_s)
            for loc, tp in zip(plan.stops, plan.stop_pick_s):
                d = wh.depot_dist(loc) if pos is None else wh.dist(pos, loc)
                yield env.timeout(float(d) / pcfg.speed_mps)
                a = int(wh.aisle[loc])
                with aisles[a].request() as req:
                    tw = env.now
                    yield req
                    w = env.now - tw
                    wtot += w
                    yield env.timeout(tp)
                ab[a][0] += tp
                ab[a][1] += w
                ab[a][2] += 1
                pos = loc
            yield env.timeout(float(wh.depot_dist(pos)) / pcfg.speed_mps)
            busy["pickers"] += env.now - t0
            rec["pick_done"][k], rec["aisle_wait"][k] = env.now, wtot
            env.process(pack(k, plan))

    def monitor():
        while done[0] < N:
            series.append((env.now, len(queue.items), len(packers.queue), sum(len(r.queue) for r in aisles.values())))
            yield env.timeout(60)

    for _ in range(scfg.pickers):
        env.process(picker())
    env.process(source())
    env.process(monitor())
    env.run()

    o = pd.DataFrame({"arrival": arrivals, **rec})
    o["pick_queue_wait"] = o.start - o.arrival
    o["cycle"] = o.finish - o.arrival
    T = float(o.finish.max())
    util_p = busy["pickers"] / (scfg.pickers * T)
    util_k = busy["packers"] / (scfg.packers * T)
    rows = [("Pickers", scfg.pickers, util_p, float(o.pick_queue_wait.mean()), N),
            ("Packing", scfg.packers, util_k, float(o.pack_wait.mean()), N)]
    for a, (b, w, v) in ab.items():
        rows.append((f"Aisle {a + 1:02d}", scfg.aisle_capacity, b / (scfg.aisle_capacity * T), w / v if v else 0.0, v))
    res = pd.DataFrame(rows, columns=["resource", "capacity", "utilization", "avg_wait_s", "visits"])
    kpis = {"orders": N, "makespan_h": T / 3600, "throughput_per_h": N / (T / 3600),
            "mean_cycle_min": float(o.cycle.mean() / 60), "p95_cycle_min": float(o.cycle.quantile(0.95) / 60),
            "mean_queue_wait_min": float(o.pick_queue_wait.mean() / 60), "mean_aisle_wait_s": float(o.aisle_wait.mean()),
            "mean_pack_wait_min": float(o.pack_wait.mean() / 60),
            "avg_distance_m": float(np.mean([plans[i].distance for i in pick_idx])),
            "picker_utilization": util_p, "packer_utilization": util_k, "arrivals_per_h": rate,
            "max_pick_queue": int(max((s[1] for s in series), default=0))}
    return SimResult(o, kpis, res, pd.DataFrame(series, columns=["t", "pick_queue", "pack_queue", "aisle_queue"]))
