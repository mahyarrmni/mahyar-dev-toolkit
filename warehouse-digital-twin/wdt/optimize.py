"""Optimisation: exact min-cost-flow assignment (OR-Tools) for the linear part + genetic algorithm for the full objective."""
from __future__ import annotations

import time
from dataclasses import dataclass, field

import numpy as np
import pandas as pd
from ortools.graph.python import min_cost_flow

from .data import DataError, Problem, legacy_assignment
from .objective import KEYS, Objective, Weights


def solve_linear(p: Problem, C: np.ndarray) -> tuple[np.ndarray, float]:
    """Exact minimum of sum_i C[i, l(i)] s.t. injective, feasible assignment (min-cost flow, OR-Tools).
    Returns (locations, objective value)."""
    S, L = C.shape
    si, li = np.nonzero(p.feasible)
    c = C[si, li]
    scale = 1e9 / max(float(np.abs(c).max()), 1e-12)
    ci = np.round(c * scale).astype(np.int64)
    src, snk = 0, S + L + 1
    start = np.concatenate([np.zeros(S, int), 1 + si, 1 + S + np.arange(L)])
    end = np.concatenate([1 + np.arange(S), 1 + S + li, np.full(L, snk)])
    cap = np.ones(len(start), np.int64)
    cost = np.concatenate([np.zeros(S, np.int64), ci, np.zeros(L, np.int64)])
    f = min_cost_flow.SimpleMinCostFlow()
    f.add_arcs_with_capacity_and_unit_cost(start, end, cap, cost)
    f.set_nodes_supplies(np.array([src, snk]), np.array([S, -S]))
    if f.solve() != f.OPTIMAL:
        raise DataError("infeasible: SKUs cannot all be assigned to distinct compatible locations")
    flows = f.flows(np.arange(S, S + len(si)))
    locs = np.empty(S, int)
    chosen = np.flatnonzero(np.asarray(flows) > 0)
    locs[si[chosen]] = li[chosen]
    return locs, float(C[np.arange(S), locs].sum())


@dataclass
class GAConfig:
    pop: int = 80
    gens: int = 150
    crossover: float = 0.9
    mutation: float = 0.4
    elite: int = 3
    tournament: int = 3
    patience: int = 60
    ls_every: int = 25
    ls_iters: int = 8
    seed: int = 0


class _GA:
    """Permutation-encoded GA: chromosome = permutation of all L locations; SKU i sits at position i (i < S)."""

    def __init__(self, obj: Objective, cfg: GAConfig):
        self.o, self.cfg = obj, cfg
        self.S, self.L = obj.p.S, obj.p.wh.n
        self.F = obj.p.feasible
        self.feas = [np.flatnonzero(self.F[s]) for s in range(self.S)]
        self.rng = np.random.default_rng(cfg.seed)

    def to_perm(self, locs):
        rest = np.setdiff1d(np.arange(self.L), locs)
        self.rng.shuffle(rest)
        return np.concatenate([locs, rest])

    def random_feasible(self):
        used = np.zeros(self.L, bool)
        locs = np.empty(self.S, int)
        for s in np.argsort([len(f) for f in self.feas] + self.rng.random(self.S), kind="stable"):
            c = self.feas[s][~used[self.feas[s]]]
            if c.size == 0:
                return None
            locs[s] = self.rng.choice(c)
            used[locs[s]] = True
        return locs

    def swap_to(self, perm, pos, s, tries=6):
        """Move SKU s to a random feasible location, swapping with the occupant, if the occupant stays feasible."""
        for _ in range(tries):
            loc = self.feas[s][self.rng.integers(len(self.feas[s]))]
            j = pos[loc]
            if j == s:
                continue
            if j < self.S and not self.F[j, perm[s]]:
                continue
            old = perm[s]
            perm[s], perm[j] = loc, old
            pos[loc], pos[old] = s, j
            return True
        return False

    def mutate(self, perm):
        pos = np.empty(self.L, int)
        pos[perm] = np.arange(self.L)
        for _ in range(1 + self.rng.poisson(2)):
            self.swap_to(perm, pos, self.rng.integers(self.S))

    def crossover(self, a, b):
        child, pos = a.copy(), np.empty(self.L, int)
        pos[child] = np.arange(self.L)
        locked = np.zeros(self.L, bool)
        for s in np.flatnonzero((self.rng.random(self.S) < 0.5) & (a[: self.S] != b[: self.S])):
            val, j = b[s], pos[b[s]]
            if locked[j]:
                continue
            old = child[s]
            child[s], child[j] = val, old
            pos[val], pos[old] = s, j
            locked[s] = True
        return child

    def repair(self, perm):
        bad = np.flatnonzero(~self.F[np.arange(self.S), perm[: self.S]])
        if bad.size:
            pos = np.empty(self.L, int)
            pos[perm] = np.arange(self.L)
            for s in bad:
                self.swap_to(perm, pos, s, tries=15)

    def improve(self, perm, iters, batch=48):
        best, bc, stall = perm.copy(), float(self.o.cost(perm[: self.S])), 0
        for _ in range(iters):
            pos = np.empty(self.L, int)
            pos[best] = np.arange(self.L)
            cands = np.tile(best, (batch, 1))
            for r in range(batch):
                self.swap_to(cands[r], pos.copy(), self.rng.integers(self.S))
            cs = self.o.cost(cands[:, : self.S])
            k = int(np.argmin(cs))
            if cs[k] < bc - 1e-12:
                best, bc, stall = cands[k].copy(), float(cs[k]), 0
            else:
                stall += 1
                if stall >= 3:
                    break
        return best

    def run(self, seeds, progress=None):
        cfg, P = self.cfg, self.cfg.pop
        pop = [self.to_perm(s) for s in seeds]
        while len(pop) < P:
            base = seeds[self.rng.integers(len(seeds))]
            rf = self.random_feasible() if self.rng.random() < 0.25 else None
            c = self.to_perm(base if rf is None else rf)
            for _ in range(self.rng.integers(1, max(2, self.S // 3))):
                self.mutate(c)
            pop.append(c)
        pop = np.array(pop[:P])
        hist, best_c, since = [], np.inf, 0
        for g in range(cfg.gens):
            cost = self.o.cost(pop[:, : self.S])
            order = np.argsort(cost, kind="stable")
            pop, cost = pop[order], cost[order]
            if g % cfg.ls_every == cfg.ls_every - 1:
                pop[0] = self.improve(pop[0], cfg.ls_iters)
                cost[0] = self.o.cost(pop[0][: self.S])
            hist.append((g, float(cost.min()), float(cost.mean())))
            if progress:
                progress(g + 1, cfg.gens, float(cost.min()))
            if cost[0] < best_c - 1e-10:
                best_c, since = cost[0], 0
            else:
                since += 1
                if since >= cfg.patience:
                    break
            sel = self.rng.integers(P, size=(P - cfg.elite, 2, cfg.tournament)).min(axis=2)
            kids = []
            for a, b in sel:
                child = self.crossover(pop[a], pop[b]) if self.rng.random() < cfg.crossover else pop[a].copy()
                if self.rng.random() < cfg.mutation:
                    self.mutate(child)
                self.repair(child)
                kids.append(child)
            pop = np.vstack([pop[: cfg.elite], kids])
        cost = self.o.cost(pop[:, : self.S])
        best = self.improve(pop[int(np.argmin(cost))], 40)
        return best[: self.S].copy(), pd.DataFrame(hist, columns=["generation", "best", "mean"])


@dataclass
class Result:
    locs: np.ndarray
    baseline: np.ndarray
    seed: np.ndarray
    objective: Objective
    history: pd.DataFrame
    lower_bound: float          # exact optimum of the linear part (a lower bound on J since affinity >= 0)
    runtime_s: float
    cost: float = 0.0
    base_cost: float = 0.0
    seed_cost: float = 0.0
    components: dict = field(default_factory=dict)
    base_components: dict = field(default_factory=dict)

    @property
    def gap(self) -> float:
        return (self.cost - self.lower_bound) / max(self.cost, 1e-12)


def optimize(p: Problem, weights: Weights | None = None, cfg: GAConfig | None = None,
             baseline: np.ndarray | None = None, progress=None) -> Result:
    t0 = time.time()
    weights, cfg = weights or Weights(), cfg or GAConfig()
    base = legacy_assignment(p) if baseline is None else np.asarray(baseline)
    obj = Objective(p, weights, base)
    seed, lb = solve_linear(p, obj.C_lin)
    ga = _GA(obj, cfg)
    seeds = [seed, base] if obj.violations(base) == 0 else [seed]
    locs, hist = ga.run(seeds, progress)
    if obj.violations(locs) > 0 or obj.cost(locs) > obj.cost(seed):  # safeguard: never worse than the exact seed
        locs = seed
    z, z0 = obj.components(locs), obj.components(base)
    return Result(locs, base, seed, obj, hist, lb, time.time() - t0, float(obj.cost(locs)),
                  float(obj.cost(base)), float(obj.cost(seed)),
                  {k: float(z[k]) for k in KEYS}, {k: float(z0[k]) for k in KEYS})


def nondominated(df: pd.DataFrame, cols=KEYS) -> np.ndarray:
    """Boolean mask of Pareto-optimal rows (all objectives minimised)."""
    v = df[list(cols)].to_numpy(float)
    return np.array([not ((v <= r).all(1) & (v < r).any(1)).any() for r in v])


def pareto_sweep(p: Problem, weight_sets: list[Weights], cfg: GAConfig | None = None,
                 baseline: np.ndarray | None = None) -> pd.DataFrame:
    """Solve one scalarised problem per weight vector; flag the non-dominated solutions."""
    rows = []
    for w in weight_sets:
        r = optimize(p, w, cfg, baseline)
        rows.append({**{f"w_{k}": getattr(w, k) for k in KEYS}, **r.components, "J": r.cost})
    df = pd.DataFrame(rows)
    df["pareto"] = nondominated(df)
    return df
