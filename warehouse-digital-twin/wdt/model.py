"""Warehouse geometry: parallel-aisle layout with front/back cross-aisles.

Each storage location has an *access point* (x, y) on its aisle centre-line. Picker travel is rectilinear:
  same aisle:       |y1 - y2|
  different aisles: min(y1 + y2, 2L - y1 - y2) + |x1 - x2|   (via front or back cross-aisle)
  depot -> loc:     |x - depot_x| + y                         (depot at y = 0)
"""
from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd

LEVEL_STEP_S = 4.0    # extra handling seconds per rack level above level index 1
WEIGHT_REF_KG = 10.0  # unit-weight scale of vertical handling time
REQUIRED = ["location_id", "aisle", "bay", "side", "level", "x", "y", "zone", "volume_cap", "weight_cap"]
# (volume m3, weight kg) per level index
CAPS = {"pallet": [(1.6, 1000), (1.2, 800), (1.2, 600), (1.0, 400)],
        "shelf": [(0.3, 120), (0.3, 100), (0.3, 80), (0.3, 60)]}


@dataclass
class Warehouse:
    locations: pd.DataFrame
    length: float
    depot_x: float
    bay_width: float = 1.2
    aisle_width: float = 3.0
    rack_depth: float = 1.1

    def __post_init__(self):
        df = self.locations.reset_index(drop=True)
        miss = [c for c in REQUIRED if c not in df]
        if miss:
            raise ValueError(f"missing location columns: {miss}")
        if df.location_id.duplicated().any():
            raise ValueError("duplicate location_id")
        if ((df.y < 0) | (df.y > self.length)).any():
            raise ValueError("location y outside [0, length]")
        self.locations = df
        self.n = len(df)
        self.x = df.x.to_numpy(float)
        self.y = df.y.to_numpy(float)
        self.aisle = df.aisle.to_numpy(int)
        self.side = df.side.to_numpy(int)
        self.level = df.level.to_numpy(int)
        self.zone = df.zone.astype(str).to_numpy()
        self.vol_cap = df.volume_cap.to_numpy(float)
        self.wgt_cap = df.weight_cap.to_numpy(float)
        self.level_extra = np.maximum(self.level - 1, 0) * LEVEL_STEP_S
        self.index = {k: i for i, k in enumerate(df.location_id)}

    def dist(self, i, j):
        """Travel distance (m) between location indices; broadcasts over arrays."""
        i, j = np.asarray(i), np.asarray(j)
        yi, yj = self.y[i], self.y[j]
        via = np.minimum(yi + yj, 2 * self.length - yi - yj) + np.abs(self.x[i] - self.x[j])
        return np.where(self.aisle[i] == self.aisle[j], np.abs(yi - yj), via)

    def depot_dist(self, i):
        i = np.asarray(i)
        return np.abs(self.x[i] - self.depot_x) + self.y[i]

    def path(self, i: int, j: int) -> list[tuple[float, float]]:
        """Polyline of the shortest route from location i to j (length equals dist(i, j))."""
        xi, yi, xj, yj = self.x[i], self.y[i], self.x[j], self.y[j]
        if self.aisle[i] == self.aisle[j]:
            return [(xi, yi), (xj, yj)]
        ye = 0.0 if yi + yj <= 2 * self.length - yi - yj else self.length
        return [(xi, yi), (xi, ye), (xj, ye), (xj, yj)]

    def route_points(self, stops: list[int]) -> list[tuple[float, float]]:
        """Depot -> stops (in order) -> depot, as a polyline."""
        if not stops:
            return [(self.depot_x, 0.0)]
        pts = [(self.depot_x, 0.0), (self.x[stops[0]], 0.0), (self.x[stops[0]], self.y[stops[0]])]
        for a, b in zip(stops, stops[1:]):
            pts += self.path(a, b)[1:]
        last = stops[-1]
        pts += [(self.x[last], 0.0), (self.depot_x, 0.0)]
        return pts

    @property
    def width(self) -> float:
        return float(self.x.max() + self.aisle_width / 2 + self.rack_depth)


def generate_warehouse(aisles=6, bays=20, levels=3, bay_width=1.2, aisle_width=3.0, rack_depth=1.1,
                       front_gap=4.0, back_gap=3.0, zones: dict | None = None, shelf_aisles=(),
                       depot_x: float | None = None) -> Warehouse:
    """Build a parallel-aisle warehouse. zones: {aisle_index: 'cold'|'hazmat'|'general'}."""
    zones = zones or {}
    pitch = aisle_width + 2 * rack_depth
    rows = []
    for a in range(aisles):
        prof = CAPS["shelf" if a in shelf_aisles else "pallet"]
        for b in range(bays):
            for s in (0, 1):
                for lv in range(levels):
                    vc, wc = prof[min(lv, len(prof) - 1)]
                    rows.append((f"A{a + 1:02d}-{b + 1:02d}-{'LR'[s]}{lv + 1}", a, b, s, lv,
                                 a * pitch + pitch / 2, front_gap + (b + 0.5) * bay_width,
                                 zones.get(a, "general"), vc, wc))
    length = front_gap + bays * bay_width + back_gap
    return Warehouse(pd.DataFrame(rows, columns=REQUIRED), length,
                     aisles * pitch / 2 if depot_x is None else depot_x, bay_width, aisle_width, rack_depth)
