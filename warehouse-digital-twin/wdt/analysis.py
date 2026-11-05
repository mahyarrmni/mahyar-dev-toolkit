"""Capacity, ABC and bottleneck analysis."""
from __future__ import annotations

import math

import numpy as np
import pandas as pd

from .data import Problem
from .sim import SimResult


def abc_classes(freq: np.ndarray, a: float = 0.80, b: float = 0.95) -> np.ndarray:
    """Pareto classes by cumulative share of order lines: A <= 80 %, B <= 95 %, else C (zero-demand SKUs are C)."""
    order = np.argsort(-freq, kind="stable")
    share = np.cumsum(freq[order]) / max(freq.sum(), 1e-12)
    cls = np.where(share <= a, "A", np.where(share <= b, "B", "C"))
    cls[freq[order] == 0] = "C"
    out = np.empty(len(freq), dtype="<U1")
    out[order] = cls
    return out


def capacity_tables(p: Problem, locs: np.ndarray) -> dict:
    wh = p.wh
    phys = (p.skus.pickface_qty * p.skus.unit_volume_m3).to_numpy(float)
    occ = np.zeros(wh.n, bool)
    occ[locs] = True
    used_v, used_w = np.zeros(wh.n), np.zeros(wh.n)
    used_v[locs], used_w[locs] = phys, p.stored_w
    df = pd.DataFrame({"zone": wh.zone, "level": wh.level + 1, "occupied": occ, "vol_cap": wh.vol_cap,
                       "wgt_cap": wh.wgt_cap, "used_v": used_v, "used_w": used_w})

    def agg(by):
        g = df.groupby(by).agg(locations=("occupied", "size"), occupied=("occupied", "sum"), vol_cap=("vol_cap", "sum"),
                               used_v=("used_v", "sum"), wgt_cap=("wgt_cap", "sum"), used_w=("used_w", "sum")).reset_index()
        g["slot_utilization"] = g.occupied / g.locations
        g["volume_utilization"] = g.used_v / g.vol_cap
        g["weight_utilization"] = g.used_w / g.wgt_cap
        return g.drop(columns=["used_w", "wgt_cap"])

    occ_cap = df[df.occupied]
    summary = {"locations": wh.n, "occupied": int(occ.sum()), "free": int((~occ).sum()),
               "slot_utilization": float(occ.mean()), "cube_utilization": float(used_v.sum() / wh.vol_cap.sum()),
               "occupied_volume_utilization": float(used_v.sum() / occ_cap.vol_cap.sum()),
               "locations_over_90pct_volume": int(((used_v / wh.vol_cap) > 0.9).sum()),
               "free_by_zone": df[~df.occupied].groupby("zone").size().to_dict()}
    return {"by_zone": agg("zone"), "by_level": agg("level"), "summary": summary}


def aisle_load(p: Problem, locs: np.ndarray) -> pd.DataFrame:
    """Share of order lines per aisle; load_index = share x number of aisles (1.0 = perfectly balanced)."""
    df = pd.DataFrame({"aisle": p.wh.aisle[locs] + 1, "lines": p.freq})
    g = df.groupby("aisle", as_index=False).lines.sum()
    g["share"] = g.lines / max(g.lines.sum(), 1e-12)
    g["load_index"] = g.share * len(g)
    return g


def required_pickers(rate_per_h: float, mean_service_s: float, target_util: float = 0.85) -> int:
    """Little's law: pickers = arrival rate x service time / target utilisation."""
    return math.ceil(rate_per_h * mean_service_s / 3600.0 / target_util)


def bottlenecks(sim: SimResult, load: pd.DataFrame | None = None, hi: float = 0.85, mid: float = 0.70) -> list[dict]:
    """Ranked findings: high utilisation, aisle blocking, hot aisles."""
    out = []
    for r in sim.resources.itertuples():
        sev = "high" if r.utilization >= hi else "medium" if r.utilization >= mid else None
        if sev:
            out.append({"severity": sev, "kind": "utilization", "resource": r.resource, "value": float(r.utilization)})
        if r.resource.startswith("Aisle") and r.avg_wait_s >= 10:
            out.append({"severity": "high" if r.avg_wait_s >= 30 else "medium", "kind": "blocking",
                        "resource": r.resource, "value": float(r.avg_wait_s)})
    if load is not None:
        for r in load[load.load_index >= 1.5].itertuples():
            out.append({"severity": "medium", "kind": "hot_aisle", "resource": f"Aisle {int(r.aisle):02d}",
                        "value": float(r.load_index)})
    return sorted(out, key=lambda f: (f["severity"] != "high", -f["value"]))
