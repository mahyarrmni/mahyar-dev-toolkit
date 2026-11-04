"""Deterministic, realistic sample data (Persian names, Toman prices, Pareto-like demand)."""
import numpy as np
import pandas as pd

from .data import COLUMNS

CATS = {  # name: (product names, cost range Toman, unit volume range m3)
    "لوازم برقی": (["کابل برق", "پریز", "کلید تبدیل", "لامپ LED", "فیوز", "رله"], (40_000, 900_000), (0.0005, 0.01)),
    "ابزار صنعتی": (["دریل شارژی", "فرز", "سنگ سنباده", "مته", "آچار", "پیچ‌گوشتی"], (150_000, 6_000_000), (0.002, 0.03)),
    "قطعات یدکی": (["فیلتر روغن", "لنت ترمز", "بلبرینگ", "تسمه", "شمع"], (100_000, 3_000_000), (0.001, 0.02)),
    "بسته‌بندی": (["کارتن", "نوار چسب", "نایلون حبابدار", "پالت چوبی", "استرچ"], (20_000, 1_500_000), (0.01, 0.2)),
    "مواد شوینده": (["مایع ظرفشویی", "پودر لباسشویی", "سفیدکننده", "شیشه‌پاک‌کن"], (30_000, 400_000), (0.002, 0.02)),
    "لوازم اداری": (["کاغذ A4", "خودکار", "منگنه", "فایل", "کارتریج"], (15_000, 1_200_000), (0.0005, 0.02)),
}
WAREHOUSES = {"انبار مرکزی تهران": (0.5, 0.88), "انبار کرج": (0.3, 0.55), "انبار اصفهان": (0.2, 0.32)}  # weight, target utilization


def make_sample(today=None, n: int = 120, seed: int = 7):
    rng = np.random.default_rng(seed)
    today = today or pd.Timestamp.today().normalize()
    cats, whs = list(CATS), list(WAREHOUSES)
    rows = []
    for i in range(n):
        cat = cats[i % len(cats)]
        names, (c0, c1), (v0, v1) = CATS[cat]
        cost = int(round(float(np.exp(rng.uniform(np.log(c0), np.log(c1)))), -3))
        demand = int(max(1, rng.lognormal(3.0, 1.1) * (1e6 / cost) ** 0.25))
        lt = int(rng.choice([7, 10, 14, 21, 30]))
        kind = rng.random()
        if kind < 0.14:      # dead stock
            demand = 0 if rng.random() < 0.7 else 1
            qty, idle = int(rng.integers(5, 250)), int(rng.integers(190, 700))
        elif kind < 0.31:    # running low
            qty, idle = int(demand / 30 * lt * rng.uniform(0, 1.25)), int(rng.integers(0, 20))
        else:                # healthy
            qty, idle = int(demand * rng.uniform(0.8, 6)), int(rng.integers(0, 25))
        rows.append({
            "sku": f"SKU-{1001 + i}",
            "name": f"{rng.choice(names)} مدل {rng.choice(list('ABCDEFGH'))}{int(rng.integers(100, 999))}",
            "category": cat,
            "warehouse": str(rng.choice(whs, p=[WAREHOUSES[w][0] for w in whs])),
            "quantity": qty, "unit_cost": cost, "monthly_demand": demand,
            "last_movement": today - pd.Timedelta(days=idle), "lead_time_days": lt,
            "unit_volume_m3": round(float(rng.uniform(v0, v1)), 4),
        })
    inv = pd.DataFrame(rows)[COLUMNS]
    used = (inv.quantity * inv.unit_volume_m3).groupby(inv.warehouse).sum()
    cap = pd.DataFrame({"warehouse": whs, "capacity_m3": [
        max(10.0, float(np.ceil(used.get(w, 0) / WAREHOUSES[w][1] / 10) * 10)) for w in whs]})
    return inv, cap


if __name__ == "__main__":  # writes files into sample_data/
    from .data import FA_HEADERS
    inv, cap = make_sample()
    inv.to_csv("sample_data/sample_inventory.csv", index=False, encoding="utf-8-sig")
    with pd.ExcelWriter("sample_data/sample_inventory.xlsx") as w:
        inv.rename(columns=FA_HEADERS).to_excel(w, sheet_name="موجودی", index=False)
        cap.rename(columns=FA_HEADERS).to_excel(w, sheet_name="انبارها", index=False)
