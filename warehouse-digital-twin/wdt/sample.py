"""Deterministic realistic sample data (Persian FMCG distributor): `python -m wdt.sample data/sample`."""
from __future__ import annotations

import json
import sys
from pathlib import Path

import numpy as np
import pandas as pd

from .data import build_problem, first_fit, parse_orders, parse_skus
from .model import generate_warehouse

WAREHOUSE = dict(aisles=6, bays=20, levels=3, zones={4: "hazmat", 5: "cold"}, shelf_aisles=[0, 1])
SIZES = [("کارتن ۱۲ عددی", 0.008, 3.0), ("کارتن ۲۴ عددی", 0.02, 8.0), ("کارتن ۴۸ عددی", 0.05, 18.0)]
BRANDS = ["گلستان", "سحر", "نوین"]
# (family, storage class, base names, sizes used, brands used, popularity scale)
FAMILIES = [
    ("غلات و حبوبات", "general", ["برنج ایرانی", "برنج پاکستانی", "عدس", "لوبیا چیتی", "نخود", "ماش", "لپه", "گندم برشته", "جو پوست‌کنده", "لوبیا قرمز"], 3, 2, 1.0),
    ("روغن و کنسرو", "general", ["روغن آفتابگردان", "روغن زیتون", "روغن سرخ‌کردنی", "کنسرو ماهی", "کنسرو لوبیا", "رب گوجه‌فرنگی", "سس مایونز", "سرکه"], 3, 2, 1.0),
    ("چای و نوشیدنی", "general", ["چای سیاه", "چای سبز", "قهوه فوری", "نسکافه", "شربت آلبالو", "آب معدنی", "نوشابه", "آبمیوه", "عرق نعناع"], 3, 2, 1.0),
    ("شوینده و بهداشتی", "general", ["مایع ظرفشویی", "پودر لباسشویی", "شامپو", "صابون", "خمیردندان", "دستمال کاغذی", "مایع دستشویی", "نرم‌کننده", "شیشه‌شوی"], 3, 2, 1.0),
    ("تنقلات و شیرینی", "general", ["بیسکویت", "شکلات", "چیپس", "پفک", "آدامس", "کیک", "ویفر", "نان خشک"], 3, 2, 1.0),
    ("لوازم برقی", "general", ["باتری قلمی", "باتری نیم‌قلمی", "لامپ ال‌ای‌دی", "سشوار", "اتو", "کتری برقی", "چراغ‌قوه", "سیم برق"], 2, 2, 0.5),
    ("لوازم اداری", "general", ["کاغذ A4", "خودکار", "دفتر", "چسب", "منگنه", "پوشه", "ماژیک", "کلاسور"], 3, 2, 0.8),
    ("لبنیات و سردخانه", "cold", ["شیر پاستوریزه", "ماست", "پنیر پیتزا", "پنیر فتا", "کره", "خامه", "دوغ", "بستنی", "سوسیس", "کالباس", "مرغ منجمد", "ماهی منجمد"], 3, 2, 0.6),
    ("مواد شیمیایی", "hazmat", ["تینر", "حلال رنگ", "اسپری رنگ", "وایتکس", "جوهر نمک", "گاز فندک", "چسب صنعتی", "ضدیخ"], 2, 3, 0.3),
]


def make_sample(out: str | Path, seed: int = 42, n_orders: int = 1500) -> None:
    out = Path(out)
    out.mkdir(parents=True, exist_ok=True)
    rng = np.random.default_rng(seed)
    wh = generate_warehouse(**WAREHOUSE)
    rows = []
    for fam, cls, bases, ns, nb, _ in FAMILIES:
        for b in bases:
            for lab, v, w in SIZES[-ns:] if ns < 3 else SIZES:
                for br in BRANDS[:nb]:
                    vol, wt = v * rng.uniform(0.85, 1.2), w * rng.uniform(0.85, 1.2)
                    fp = float(np.clip(rng.lognormal(np.log(0.18), 0.7), 0.02, 1.1))
                    pf = max(1, int(round(fp * 0.75 / vol)))
                    rows.append(dict(fam=fam, cls=cls, name=f"{b} {br} ({lab})", vol=round(vol, 4), wt=round(wt, 2),
                                     pf=pf, stock=pf * int(rng.integers(1, 5))))
    rng.shuffle(rows)  # receiving order is unrelated to product family
    skus = pd.DataFrame(
        {"sku": [f"SKU-{i + 1:04d}" for i in range(len(rows))], "name": [r["name"] for r in rows],
         "unit_volume_m3": [r["vol"] for r in rows], "unit_weight_kg": [r["wt"] for r in rows],
         "stock_qty": [r["stock"] for r in rows], "pickface_qty": [r["pf"] for r in rows],
         "storage_class": [r["cls"] for r in rows], "family": [r["fam"] for r in rows]})
    # popularity ~ Zipf over a random ranking, scaled by family
    pop = (rng.permutation(len(rows)) + 1.0) ** -1.0 * np.array([next(f[5] for f in FAMILIES if f[0] == r["fam"]) for r in rows])
    fams = skus.family.to_numpy()
    fam_w = {f: pop[fams == f].sum() for f in set(fams)}
    names = list(fam_w)
    fw = np.array([fam_w[f] for f in names]) / sum(fam_w.values())
    members = {f: np.flatnonzero(fams == f) for f in names}
    lines = []
    for o in range(n_orders):
        k = min(1 + rng.poisson(3.5), 14)
        prim = names[rng.choice(len(names), p=fw)]
        chosen: set[int] = set()
        while len(chosen) < k:
            pool = members[prim] if rng.random() < 0.65 else np.arange(len(rows))
            chosen.add(int(rng.choice(pool, p=pop[pool] / pop[pool].sum())))
        for s in chosen:
            lines.append((f"ORD-{o + 1:05d}", skus.sku[s], 1 + int(rng.poisson(2.5))))
    orders = pd.DataFrame(lines, columns=["order_id", "sku", "qty"])
    # legacy ("before") layout: sequential first-fit put-away in receiving order
    sk, _ = parse_skus(skus.drop(columns="family"))
    od, _ = parse_orders(orders, sk)
    p = build_problem(wh, sk, od)
    skus["current_location"] = wh.locations.location_id.to_numpy()[first_fit(p)]
    skus.drop(columns="family").to_csv(out / "skus.csv", index=False, encoding="utf-8-sig")
    orders.to_csv(out / "orders.csv", index=False, encoding="utf-8-sig")
    wh.locations.to_csv(out / "locations.csv", index=False)
    (out / "warehouse.json").write_text(json.dumps(WAREHOUSE, ensure_ascii=False, indent=1))


if __name__ == "__main__":
    make_sample(sys.argv[1] if len(sys.argv) > 1 else "data/sample")
