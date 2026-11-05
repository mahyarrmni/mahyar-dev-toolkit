"""Import (CSV/Excel, Persian or English headers), validation and optimization-problem construction."""
from __future__ import annotations

import io
import re
from dataclasses import dataclass, field
from pathlib import Path

import numpy as np
import pandas as pd

from .model import Warehouse


class DataError(ValueError):
    """Invalid or inconsistent input data."""


_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def norm_text(s) -> str:
    """Persian/Arabic digits -> ASCII, Arabic ي/ك -> ی/ک, ZWNJ -> space, collapse whitespace."""
    s = str(s).translate(_DIGITS).replace("ي", "ی").replace("ك", "ک").replace("\u200c", " ")
    return re.sub(r"\s+", " ", s).strip()


def _key(s) -> str:
    return norm_text(s).lower().replace("_", " ").replace("-", " ")


SKU_COLS = {
    "sku": ["sku", "item", "item id", "product", "product id", "کد کالا", "کد", "شناسه کالا", "کد محصول"],
    "name": ["name", "description", "product name", "نام", "نام کالا", "شرح کالا", "شرح"],
    "unit_volume_m3": ["unit volume m3", "unit volume", "volume m3", "volume", "حجم", "حجم واحد", "حجم هر واحد"],
    "unit_weight_kg": ["unit weight kg", "unit weight", "weight kg", "weight", "وزن", "وزن واحد", "وزن هر واحد"],
    "stock_qty": ["stock qty", "stock", "on hand", "qty on hand", "موجودی", "تعداد موجودی"],
    "pickface_qty": ["pickface qty", "pick face qty", "slot qty", "تعداد در محل", "موجودی محل"],
    "storage_class": ["storage class", "class", "zone", "storage", "نوع نگهداری", "کلاس نگهداری", "منطقه"],
    "current_location": ["current location", "location", "location id", "محل فعلی", "محل", "شناسه محل"],
}
ORDER_COLS = {
    "order_id": ["order id", "order", "order no", "سفارش", "شماره سفارش", "کد سفارش"],
    "sku": SKU_COLS["sku"],
    "qty": ["qty", "quantity", "units", "تعداد", "مقدار", "تعداد سفارش"],
}
CLASS_ALIASES = {"general": ["general", "عمومی", "معمولی", "خشک"], "cold": ["cold", "سرد", "سردخانه", "یخچالی"],
                 "hazmat": ["hazmat", "خطرناک", "مواد خطرناک"]}
_CLASS_MAP = {_key(a): k for k, v in CLASS_ALIASES.items() for a in v}


def load_table(src, filename: str | None = None) -> pd.DataFrame:
    """Read .csv/.xlsx from a path or file-like object. CSV encodings: utf-8(-sig), then cp1256."""
    name = (filename or getattr(src, "name", None) or str(src)).lower()
    if name.endswith((".xlsx", ".xlsm", ".xls")):
        return pd.read_excel(src, dtype=object)
    raw = src.read() if hasattr(src, "read") else Path(src).read_bytes()
    for enc in ("utf-8-sig", "utf-8", "cp1256"):
        try:
            text = raw.decode(enc)
            break
        except UnicodeDecodeError:
            continue
    return pd.read_csv(io.StringIO(text), dtype=object, sep=None, engine="python")


def _rename(df: pd.DataFrame, spec: dict) -> pd.DataFrame:
    lookup = {_key(a): c for c, al in spec.items() for a in al + [c]}
    cols = {}
    for col in df.columns:
        canon = lookup.get(_key(col))
        if canon and canon not in cols.values():
            cols[col] = canon
    return df[list(cols)].rename(columns=cols).copy()


def _num(s: pd.Series) -> pd.Series:
    s = s.astype(str).map(norm_text).str.replace("٬", "", regex=False).str.replace(",", "", regex=False)
    return pd.to_numeric(s.str.replace("٫", ".", regex=False), errors="coerce")


def parse_skus(df: pd.DataFrame) -> tuple[pd.DataFrame, list[str]]:
    d, warn = _rename(df, SKU_COLS), []
    err = [f"missing column: {c}" for c in ("sku", "unit_volume_m3", "unit_weight_kg") if c not in d]
    if "stock_qty" not in d and "pickface_qty" not in d:
        err.append("missing column: stock_qty or pickface_qty")
    if err:
        raise DataError("; ".join(err))
    d["sku"] = d["sku"].map(norm_text)
    for c in ("unit_volume_m3", "unit_weight_kg", "stock_qty", "pickface_qty"):
        if c in d:
            d[c] = _num(d[c])
    if "pickface_qty" not in d:
        d["pickface_qty"] = d["stock_qty"]
    if "stock_qty" in d:
        d["pickface_qty"] = d["pickface_qty"].fillna(d["stock_qty"])
    bad = (d.sku == "") | ~(d.unit_volume_m3 > 0) | ~(d.unit_weight_kg >= 0) | ~(d.pickface_qty > 0)
    if bad.any():
        raise DataError(f"invalid sku/volume/weight/quantity in rows {[int(i) + 2 for i in d.index[bad]][:10]}")
    if d.sku.duplicated().any():
        raise DataError(f"duplicate SKU: {d.sku[d.sku.duplicated()].tolist()[:5]}")
    cls = d["storage_class"].map(lambda v: _CLASS_MAP.get(_key(v), None) if pd.notna(v) and str(v).strip() else "general") \
        if "storage_class" in d else pd.Series("general", index=d.index)
    if cls.isna().any():
        raise DataError(f"unknown storage_class in rows {[int(i) + 2 for i in d.index[cls.isna()]][:10]}")
    d["storage_class"] = cls
    d["name"] = d["name"].fillna(d.sku) if "name" in d else d.sku
    if "current_location" in d:
        d["current_location"] = d["current_location"].map(lambda v: norm_text(v) if pd.notna(v) and str(v).strip() else None)
    else:
        d["current_location"] = None
    return d.set_index("sku")[["name", "unit_volume_m3", "unit_weight_kg", "pickface_qty", "storage_class",
                               "current_location"]], warn


def parse_orders(df: pd.DataFrame, skus: pd.DataFrame) -> tuple[pd.DataFrame, list[str]]:
    d, warn = _rename(df, ORDER_COLS), []
    if "order_id" not in d or "sku" not in d:
        raise DataError("orders need order_id and sku columns")
    d["order_id"] = d["order_id"].map(norm_text)
    d["sku"] = d["sku"].map(norm_text)
    d["qty"] = _num(d["qty"]) if "qty" in d else 1.0
    ok = d.qty > 0
    if (~ok).any():
        warn.append(f"{int((~ok).sum())} order lines with invalid qty dropped")
    d = d[ok]
    known = d.sku.isin(skus.index)
    if (~known).any():
        warn.append(f"{int((~known).sum())} order lines with unknown SKU dropped")
    d = d[known].groupby(["order_id", "sku"], as_index=False, sort=False).qty.sum()
    if d.empty:
        raise DataError("no valid order lines")
    return d, warn


@dataclass
class Problem:
    wh: Warehouse
    skus: pd.DataFrame
    orders: pd.DataFrame
    freq: np.ndarray       # order lines per SKU
    units: np.ndarray      # units picked per SKU
    footprint: np.ndarray  # required slot volume (m3)
    unit_w: np.ndarray     # kg per unit
    stored_w: np.ndarray   # kg stored in the pick face
    pairs: tuple           # (i, j, co-occurrence count)
    feasible: np.ndarray   # (S, L) bool
    current: np.ndarray | None
    warnings: list = field(default_factory=list)

    @property
    def S(self) -> int:
        return len(self.skus)


def build_problem(wh: Warehouse, skus: pd.DataFrame, orders: pd.DataFrame, fill_factor: float = 0.75,
                  max_pairs: int = 3000) -> Problem:
    """fill_factor: usable share of a location's volume (footprint = qty * unit_volume / fill_factor)."""
    S = len(skus)
    if S > wh.n:
        raise DataError(f"{S} SKUs but only {wh.n} locations")
    si = pd.Series(np.arange(S), index=skus.index)
    o = orders.assign(si=orders.sku.map(si).astype(int))
    freq = np.bincount(o.si, minlength=S).astype(float)
    units = np.bincount(o.si, weights=o.qty, minlength=S)
    fp = (skus.pickface_qty * skus.unit_volume_m3 / fill_factor).to_numpy(float)
    unit_w = skus.unit_weight_kg.to_numpy(float)
    stored_w = (skus.pickface_qty * skus.unit_weight_kg).to_numpy(float)
    m = o[["order_id", "si"]].merge(o[["order_id", "si"]], on="order_id")
    m = m[m.si_x < m.si_y].groupby(["si_x", "si_y"]).size().nlargest(max_pairs)
    pairs = (m.index.get_level_values(0).to_numpy(int), m.index.get_level_values(1).to_numpy(int),
             m.to_numpy(float))
    cls = skus.storage_class.to_numpy()
    feas = (fp[:, None] <= wh.vol_cap[None, :] + 1e-9) & (stored_w[:, None] <= wh.wgt_cap[None, :] + 1e-9) \
        & (cls[:, None] == wh.zone[None, :])
    none = np.flatnonzero(~feas.any(1))
    if none.size:
        raise DataError(f"no feasible location (size/weight/zone) for SKUs: {skus.index[none][:8].tolist()}")
    warn, current = [], None
    if skus.current_location.notna().all():
        idx = skus.current_location.map(wh.index)
        if idx.isna().any():
            warn.append("current_location contains unknown ids; using first-fit baseline")
        elif idx.duplicated().any():
            warn.append("current_location has duplicates; using first-fit baseline")
        else:
            current = idx.to_numpy(int)
    return Problem(wh, skus, orders, freq, units, fp, unit_w, stored_w, pairs, feas, current, warn)


def first_fit(p: Problem, order: np.ndarray | None = None) -> np.ndarray:
    """Sequential put-away: each SKU (in `order`, default SKU-code order) takes the lowest-index free feasible slot."""
    used = np.zeros(p.wh.n, bool)
    locs = np.empty(p.S, int)
    for s in (np.arange(p.S) if order is None else order):
        c = np.flatnonzero(p.feasible[s] & ~used)
        if c.size == 0:
            if order is None:  # retry most-constrained-first
                return first_fit(p, np.argsort(p.feasible.sum(1), kind="stable"))
            raise DataError("first-fit failed: capacity insufficient")
        locs[s] = c[0]
        used[c[0]] = True
    return locs


def legacy_assignment(p: Problem) -> np.ndarray:
    """Baseline ('before') layout: the supplied current locations, otherwise first-fit put-away."""
    return p.current.copy() if p.current is not None else first_fit(p)
