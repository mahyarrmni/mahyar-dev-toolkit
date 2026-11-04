"""Core calculations. All functions are pure and take `today` explicitly where time matters."""
import numpy as np
import pandas as pd

SEVERITY = {"out": 0, "critical": 1, "low": 2}


def enrich(df: pd.DataFrame, today: pd.Timestamp) -> pd.DataFrame:
    d = df.copy()
    d["stock_value"] = d.quantity * d.unit_cost
    d["daily_demand"] = d.monthly_demand / 30
    d["days_cover"] = d.quantity / d.daily_demand.where(d.daily_demand > 0)
    d["days_idle"] = (today - d.last_movement).dt.days
    return d


def abc_classification(d: pd.DataFrame, a: float = 0.80, b: float = 0.95) -> pd.DataFrame:
    """ABC by annual consumption value (monthly_demand*12*unit_cost) per SKU across warehouses.
    An item is A if the cumulative share BEFORE it is below `a`, B if below `b`, else C."""
    g = (d.assign(annual_value=d.monthly_demand * 12 * d.unit_cost)
          .groupby("sku", as_index=False)
          .agg(name=("name", "first"), category=("category", "first"),
               annual_value=("annual_value", "sum"), stock_value=("stock_value", "sum")))
    g = g.sort_values(["annual_value", "sku"], ascending=[False, True], kind="stable").reset_index(drop=True)
    total = g.annual_value.sum()
    g["share"] = g.annual_value / total if total > 0 else 0.0
    g["cum_share"] = g.share.cumsum()
    prev = g.cum_share - g.share
    g["abc"] = np.select([prev < a - 1e-9, prev < b - 1e-9], ["A", "B"], "C")
    g.loc[g.annual_value <= 0, "abc"] = "C"
    return g


def low_stock(d: pd.DataFrame, safety_days: int = 7, cover_days: int = 30) -> pd.DataFrame:
    """reorder_point = daily*(lead+safety). critical: qty < daily*lead. out: qty == 0.
    suggested_order = daily*(lead+safety+cover_days) - qty (rounded up, >= 0)."""
    x = d[d.daily_demand > 0].copy()
    lt = x.lead_time_days
    x["reorder_point"] = np.ceil(x.daily_demand * (lt + safety_days))
    x["status"] = np.select([x.quantity <= 0, x.quantity < x.daily_demand * lt, x.quantity <= x.reorder_point],
                            ["out", "critical", "low"], "ok")
    x["suggested_order"] = np.ceil(np.maximum(0, x.daily_demand * (lt + safety_days + cover_days) - x.quantity))
    x = x[x.status != "ok"]
    return x.sort_values(["status", "days_cover"], key=lambda s: s.map(SEVERITY) if s.name == "status" else s)


def _dead_mask(d, threshold):
    return (d.quantity > 0) & ((d.days_idle >= threshold) | (d.days_idle.isna() & (d.monthly_demand == 0)))


def dead_stock(d: pd.DataFrame, threshold_days: int = 180) -> pd.DataFrame:
    """In stock, and no movement for >= threshold days (or no demand and unknown last movement)."""
    return d[_dead_mask(d, threshold_days)].sort_values("stock_value", ascending=False)


def slow_moving(d: pd.DataFrame, threshold_days: int = 180, max_cover_days: int = 365) -> pd.DataFrame:
    m = (d.daily_demand > 0) & (d.days_cover > max_cover_days) & ~_dead_mask(d, threshold_days)
    return d[m].sort_values("stock_value", ascending=False)


def capacity_usage(d: pd.DataFrame, cap) -> pd.DataFrame:
    u = (d.assign(v=d.quantity * d.unit_volume_m3).groupby("warehouse", as_index=False)
          .agg(used_m3=("v", "sum"), skus=("sku", "nunique"), units=("quantity", "sum")))
    cap = cap if cap is not None and len(cap) else pd.DataFrame({"warehouse": [], "capacity_m3": []})
    u = u.merge(cap[["warehouse", "capacity_m3"]], on="warehouse", how="left")
    u["capacity_m3"] = pd.to_numeric(u.capacity_m3)
    u["utilization"] = u.used_m3 / u.capacity_m3.where(u.capacity_m3 > 0)
    u["free_m3"] = u.capacity_m3 - u.used_m3
    u["status"] = np.select([u.utilization.isna(), u.utilization > 1, u.utilization >= 0.85, u.utilization < 0.4],
                            ["unknown", "over", "high", "low"], "ok")
    return u
