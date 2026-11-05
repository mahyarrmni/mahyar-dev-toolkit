"""Multi-objective cost model for storage-location assignment (see docs/ALGORITHMS.md).

Raw objectives (all minimised), for assignment l(i) of SKU i:
  distance    Z1 = sum_i 2 f_i d0(l(i))                    pick-frequency-weighted round-trip depot distance (m)
  affinity    Z2 = sum_(i,j) a_ij d(l(i), l(j))            co-picked SKUs should be close (m)
  ergonomics  Z3 = sum_i f_i h(level) (1 + w_i / 10 kg)    vertical handling time (s)
  slotfit     Z4 = sum_i (cap_l(i) - footprint_i)          unused volume of occupied slots (m3)
Scalarised cost: J = sum_k w_k Z_k / Zref_k  + M * violations, Zref = value for the baseline layout (J(base) = 1).
"""
from __future__ import annotations

from dataclasses import dataclass

import numpy as np

from .data import Problem
from .model import WEIGHT_REF_KG

KEYS = ("distance", "affinity", "ergonomics", "slotfit")
LINEAR = ("distance", "ergonomics", "slotfit")


@dataclass
class Weights:
    distance: float = 0.5
    affinity: float = 0.2
    ergonomics: float = 0.2
    slotfit: float = 0.1

    def normalized(self) -> dict:
        v = {k: max(float(getattr(self, k)), 0.0) for k in KEYS}
        t = sum(v.values())
        if t <= 0:
            raise ValueError("at least one weight must be positive")
        return {k: x / t for k, x in v.items()}


class Objective:
    def __init__(self, p: Problem, weights: Weights, baseline: np.ndarray, penalty: float = 10.0):
        self.p, self.w, self.penalty = p, weights.normalized(), penalty
        wh = p.wh
        self.si = np.arange(p.S)
        d0 = wh.depot_dist(np.arange(wh.n))
        self.M = {
            "distance": 2 * p.freq[:, None] * d0[None, :],
            "ergonomics": p.freq[:, None] * wh.level_extra[None, :] * (1 + p.unit_w[:, None] / WEIGHT_REF_KG),
            "slotfit": wh.vol_cap[None, :] - p.footprint[:, None],
        }
        z0 = self.components(baseline)
        self.ref = {k: max(abs(float(z0[k])), 1e-9) for k in KEYS}
        self.C_lin = sum(self.w[k] / self.ref[k] * self.M[k] for k in LINEAR)

    def components(self, locs) -> dict:
        """Raw objective values; locs shape (S,) or (P, S)."""
        locs = np.asarray(locs)
        z = {k: m[self.si, locs].sum(-1) for k, m in self.M.items()}
        i, j, a = self.p.pairs
        z["affinity"] = (a * self.p.wh.dist(locs[..., i], locs[..., j])).sum(-1) if len(a) \
            else np.zeros(locs.shape[:-1])
        return z

    def violations(self, locs) -> np.ndarray:
        locs = np.asarray(locs)
        return (~self.p.feasible[self.si, locs]).sum(-1) + (self.p.S - _n_unique(locs))

    def cost(self, locs):
        z = self.components(locs)
        j = sum(self.w[k] * z[k] / self.ref[k] for k in KEYS)
        return j + self.penalty * self.violations(locs)


def _n_unique(locs: np.ndarray) -> np.ndarray:
    s = np.sort(locs, axis=-1)
    return 1 + (np.diff(s, axis=-1) != 0).sum(-1)
