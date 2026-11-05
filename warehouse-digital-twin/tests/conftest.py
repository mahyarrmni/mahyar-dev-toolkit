import json
from pathlib import Path

import numpy as np
import pandas as pd
import pytest

from wdt.data import build_problem, load_table, parse_orders, parse_skus
from wdt.model import generate_warehouse

SAMPLE = Path(__file__).parent.parent / "data" / "sample"


def tiny_problem(seed=0):
    wh = generate_warehouse(aisles=2, bays=2, levels=2, zones={1: "hazmat"})  # 16 locations, aisle 1 = hazmat
    rng = np.random.default_rng(seed)
    skus = pd.DataFrame({"sku": [f"S{i}" for i in range(4)], "unit_volume_m3": [0.01, 0.02, 0.01, 0.03],
                         "unit_weight_kg": [1, 5, 2, 8], "pickface_qty": [5, 5, 5, 5],
                         "storage_class": ["general", "general", "hazmat", "general"]})
    sk, _ = parse_skus(skus)
    lines = [(f"O{o}", f"S{s}", 1) for o in range(30) for s in rng.choice(4, size=rng.integers(1, 4), replace=False)]
    od, _ = parse_orders(pd.DataFrame(lines, columns=["order_id", "sku", "qty"]), sk)
    return build_problem(wh, sk, od)


@pytest.fixture(scope="session")
def sample():
    cfg = json.loads((SAMPLE / "warehouse.json").read_text())
    cfg["zones"] = {int(k): v for k, v in cfg["zones"].items()}
    wh = generate_warehouse(**cfg)
    sk, _ = parse_skus(load_table(SAMPLE / "skus.csv"))
    od, _ = parse_orders(load_table(SAMPLE / "orders.csv"), sk)
    return build_problem(wh, sk, od)
