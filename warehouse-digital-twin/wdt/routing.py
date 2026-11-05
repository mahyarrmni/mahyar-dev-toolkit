"""Order picking tours (OR-Tools routing, depot -> stops -> depot) and the shared picking-time model."""
from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd
from ortools.constraint_solver import pywrapcp, routing_enums_pb2

from .data import Problem
from .model import WEIGHT_REF_KG, Warehouse


@dataclass
class PickingConfig:
    speed_mps: float = 1.1   # walking speed
    setup_s: float = 30.0    # per-order setup (label/scan/cart)
    pick_s: float = 10.0     # per order line (search + grab + confirm)
    unit_s: float = 1.5      # per unit picked


@dataclass
class OrderPlan:
    order_id: str
    stops: list              # location indices in visiting order (unique access points)
    stop_pick_s: list        # handling time at each stop (s)
    distance: float          # tour length incl. return to depot (m)
    lines: int
    units: float


def tour_length(wh: Warehouse, seq) -> float:
    seq = list(seq)
    if not seq:
        return 0.0
    mid = sum(float(wh.dist(a, b)) for a, b in zip(seq, seq[1:]))
    return float(wh.depot_dist(seq[0])) + mid + float(wh.depot_dist(seq[-1]))


def tsp(wh: Warehouse, stops) -> list:
    """Shortest depot->stops->depot visiting order (exact for <=2 stops, OR-Tools local search above)."""
    stops = [int(s) for s in stops]
    if len(stops) <= 1:
        return stops
    if len(stops) == 2:
        return min((stops, stops[::-1]), key=lambda s: tour_length(wh, s))
    n = len(stops)
    idx = np.array(stops)
    D = np.zeros((n + 1, n + 1))
    D[0, 1:] = D[1:, 0] = wh.depot_dist(idx)
    D[1:, 1:] = wh.dist(idx[:, None], idx[None, :])
    Di = np.round(D * 100).astype(np.int64)
    mgr = pywrapcp.RoutingIndexManager(n + 1, 1, 0)
    routing = pywrapcp.RoutingModel(mgr)
    cb = routing.RegisterTransitCallback(lambda a, b: int(Di[mgr.IndexToNode(a), mgr.IndexToNode(b)]))
    routing.SetArcCostEvaluatorOfAllVehicles(cb)
    prm = pywrapcp.DefaultRoutingSearchParameters()
    prm.first_solution_strategy = routing_enums_pb2.FirstSolutionStrategy.PATH_CHEAPEST_ARC
    sol = routing.SolveWithParameters(prm)
    if sol is None:  # nearest-neighbour fallback
        left, seq, cur = stops[:], [], None
        while left:
            nxt = min(left, key=lambda s: wh.depot_dist(s) if cur is None else wh.dist(cur, s))
            seq.append(nxt)
            left.remove(nxt)
            cur = nxt
        return seq
    out, i = [], routing.Start(0)
    while not routing.IsEnd(i):
        node = mgr.IndexToNode(i)
        if node:
            out.append(stops[node - 1])
        i = sol.Value(routing.NextVar(i))
    return out


def plan_orders(p: Problem, locs: np.ndarray, cfg: PickingConfig | None = None) -> list[OrderPlan]:
    """Pick tour and handling times of every order under a given SKU->location assignment."""
    cfg, wh = cfg or PickingConfig(), p.wh
    si = p.skus.index.get_indexer(p.orders.sku)
    loc = np.asarray(locs)[si]
    qty = p.orders.qty.to_numpy(float)
    t = cfg.pick_s + cfg.unit_s * qty + wh.level_extra[loc] * (1 + p.unit_w[si] / WEIGHT_REF_KG)
    key = wh.aisle[loc].astype(np.int64) * 10**6 + np.round(wh.y[loc] * 100).astype(np.int64)  # access point
    df = pd.DataFrame({"o": p.orders.order_id.to_numpy(), "loc": loc, "key": key, "t": t, "q": qty})
    plans, cache = [], {}
    for oid, g in df.groupby("o", sort=False):
        st = g.groupby("key", sort=False).agg(loc=("loc", "first"), t=("t", "sum"))
        reps = st["loc"].tolist()
        ck = frozenset(reps)
        if ck not in cache:
            seq = tsp(wh, reps)
            cache[ck] = (seq, tour_length(wh, seq))
        seq, dist = cache[ck]
        tm = dict(zip(reps, st.t))
        plans.append(OrderPlan(str(oid), seq, [float(tm[s]) for s in seq], dist, len(g), float(g.q.sum())))
    return plans


def summarize(plans: list[OrderPlan], cfg: PickingConfig | None = None) -> dict:
    cfg = cfg or PickingConfig()
    d = np.array([pl.distance for pl in plans])
    pick = np.array([sum(pl.stop_pick_s) for pl in plans])
    svc = cfg.setup_s + d / cfg.speed_mps + pick
    return {"orders": len(plans), "total_distance_m": float(d.sum()), "avg_distance_m": float(d.mean()),
            "avg_stops": float(np.mean([len(pl.stops) for pl in plans])), "avg_travel_s": float((d / cfg.speed_mps).mean()),
            "avg_service_s": float(svc.mean()), "p95_service_s": float(np.percentile(svc, 95)),
            "orders_per_picker_hour": float(3600 / svc.mean())}
