"""Import and normalization: Excel/CSV, Persian headers, Persian digits, Jalali dates."""
import io
import re
from datetime import datetime

import pandas as pd

DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩٫٬", "01234567890123456789.,")

ALIASES = {
    "sku": ["sku", "code", "itemcode", "کد", "کد کالا"],
    "name": ["name", "product", "productname", "itemname", "نام", "نام کالا"],
    "category": ["category", "group", "گروه", "دسته", "دسته بندی", "گروه کالا"],
    "warehouse": ["warehouse", "location", "انبار", "محل"],
    "quantity": ["quantity", "qty", "stock", "onhand", "موجودی", "تعداد"],
    "unit_cost": ["unit_cost", "unitcost", "cost", "بهای واحد", "قیمت خرید", "بهای تمام شده",
                  "قیمت تمام شده", "هزینه واحد", "قیمت واحد"],
    "monthly_demand": ["monthly_demand", "demand", "monthlysales", "فروش ماهانه", "مصرف ماهانه",
                       "میانگین فروش ماهانه", "میانگین مصرف ماهانه"],
    "last_movement": ["last_movement", "lastmovement", "last_movement_date", "last_sale", "آخرین گردش",
                      "تاریخ آخرین گردش", "تاریخ آخرین حرکت", "آخرین حرکت"],
    "lead_time_days": ["lead_time", "lead_time_days", "leadtime", "زمان تامین", "زمان تأمین", "مدت تامین"],
    "unit_volume_m3": ["unit_volume_m3", "volume", "unitvolume", "حجم", "حجم واحد"],
}
CAP_ALIASES = {
    "warehouse": ALIASES["warehouse"],
    "capacity_m3": ["capacity_m3", "capacity", "ظرفیت", "ظرفیت متر مکعب", "ظرفیت مترمکعب"],
}
FA_HEADERS = {"sku": "کد کالا", "name": "نام کالا", "category": "گروه", "warehouse": "انبار",
              "quantity": "موجودی", "unit_cost": "بهای واحد", "monthly_demand": "فروش ماهانه",
              "last_movement": "آخرین گردش", "lead_time_days": "زمان تامین",
              "unit_volume_m3": "حجم واحد", "capacity_m3": "ظرفیت"}
COLUMNS = list(ALIASES)
REQUIRED = ["sku", "name", "quantity", "unit_cost"]


def _norm(s) -> str:
    s = str(s).translate(DIGITS).replace("ي", "ی").replace("ك", "ک")
    return re.sub(r"[\s_\-\u200c\u200e\u200f()]+", "", s).lower()


def _lookup(aliases):
    return {_norm(a): k for k, v in aliases.items() for a in v}


LOOKUP, CAP_LOOKUP = _lookup(ALIASES), _lookup(CAP_ALIASES)


def jalali_to_gregorian(jy: int, jm: int, jd: int):
    jy += 1595
    days = -355668 + 365 * jy + (jy // 33) * 8 + ((jy % 33) + 3) // 4 + jd
    days += (jm - 1) * 31 if jm < 7 else (jm - 7) * 30 + 186
    gy = 400 * (days // 146097)
    days %= 146097
    if days > 36524:
        days -= 1
        gy += 100 * (days // 36524)
        days %= 36524
        if days >= 365:
            days += 1
    gy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        gy += (days - 1) // 365
        days = (days - 1) % 365
    gd = days + 1
    leap = (gy % 4 == 0 and gy % 100 != 0) or gy % 400 == 0
    months = [0, 31, 29 if leap else 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    gm = 0
    while gm < 13 and gd > months[gm]:
        gd -= months[gm]
        gm += 1
    return gy, gm, gd


JAL = re.compile(r"^(1[34]\d\d)[/\-.](\d{1,2})[/\-.](\d{1,2})")


def _date(v):
    if pd.isna(v):
        return pd.NaT
    if isinstance(v, (pd.Timestamp, datetime)):
        return pd.Timestamp(v)
    s = str(v).translate(DIGITS).strip()
    m = JAL.match(s)
    if m:
        try:
            return pd.Timestamp(*jalali_to_gregorian(*map(int, m.groups())))
        except ValueError:
            return pd.NaT
    return pd.to_datetime(s, errors="coerce")


def _num(s: pd.Series) -> pd.Series:
    t = s.fillna("").astype(str).str.translate(DIGITS).str.replace(r"[,\s]", "", regex=True)
    return pd.to_numeric(t, errors="coerce")


def _text(s: pd.Series, default="") -> pd.Series:
    t = s.fillna("").astype(str).str.strip().str.replace("ي", "ی").str.replace("ك", "ک")
    t = t.str.replace(r"\.0$", "", regex=True)
    return t.where(t != "", default)


def normalize_inventory(raw: pd.DataFrame):
    warns = []
    df = raw.rename(columns=lambda c: LOOKUP.get(_norm(c), str(c)))
    df = df.loc[:, ~df.columns.duplicated()].dropna(how="all")
    miss = [c for c in REQUIRED if c not in df.columns]
    if miss:
        raise ValueError(f"ستون‌های ضروری یافت نشد: {', '.join(miss)} | Missing required columns: {', '.join(miss)}")
    out = pd.DataFrame(index=df.index)
    out["sku"] = _text(df["sku"].fillna("").astype(str).str.translate(DIGITS))
    out["name"] = _text(df["name"])
    out["category"] = _text(df["category"], "بدون گروه") if "category" in df else "بدون گروه"
    out["warehouse"] = _text(df["warehouse"], "انبار اصلی") if "warehouse" in df else "انبار اصلی"
    out["quantity"] = _num(df["quantity"])
    out["unit_cost"] = _num(df["unit_cost"])
    for c, default in (("monthly_demand", 0.0), ("lead_time_days", 14.0), ("unit_volume_m3", 0.0)):
        out[c] = _num(df[c]).fillna(default).clip(lower=0) if c in df else default
    if "unit_volume_m3" not in df:
        warns.append("ستون حجم واحد وجود ندارد؛ تحلیل ظرفیت دقیق نخواهد بود.")
    if "last_movement" in df:
        out["last_movement"] = pd.to_datetime(df["last_movement"].map(_date))
        if out["last_movement"].isna().mean() > 0.5:
            warns.append("بیشتر تاریخ‌های آخرین گردش قابل خواندن نبودند (قالب YYYY-MM-DD یا شمسی 1403/05/12).")
    else:
        out["last_movement"] = pd.NaT
        warns.append("ستون آخرین گردش وجود ندارد؛ شناسایی کالای راکد فقط بر پایه فروش ماهانه انجام می‌شود.")
    bad = (out.quantity.isna() | out.unit_cost.isna() | (out.quantity < 0) | (out.unit_cost < 0)
           | (out.sku == "") | (out.name == ""))
    if bad.any():
        warns.append(f"{int(bad.sum())} ردیف نامعتبر حذف شد.")
    out = out[~bad]
    dup = out.duplicated(["sku", "warehouse"])
    if dup.any():
        warns.append(f"{int(dup.sum())} ردیف تکراری (کد کالا + انبار) حذف شد.")
    out = out[~dup]
    if out.empty:
        raise ValueError("هیچ ردیف معتبری یافت نشد | No valid rows found")
    return out[COLUMNS].reset_index(drop=True), warns


def normalize_capacity(raw: pd.DataFrame) -> pd.DataFrame:
    df = raw.rename(columns=lambda c: CAP_LOOKUP.get(_norm(c), str(c)))
    if not {"warehouse", "capacity_m3"} <= set(df.columns):
        raise ValueError("برگه ظرفیت باید ستون‌های «انبار» و «ظرفیت» داشته باشد.")
    out = pd.DataFrame({"warehouse": _text(df["warehouse"]), "capacity_m3": _num(df["capacity_m3"])})
    return out[(out.warehouse != "") & (out.capacity_m3 > 0)].drop_duplicates("warehouse").reset_index(drop=True)


def complete_capacity(inv: pd.DataFrame, cap) -> pd.DataFrame:
    """One row per warehouse in the inventory; unknown capacity stays NaN."""
    base = pd.DataFrame({"warehouse": sorted(inv["warehouse"].unique())})
    if cap is None or len(cap) == 0:
        base["capacity_m3"] = float("nan")
        return base
    return base.merge(cap[["warehouse", "capacity_m3"]], on="warehouse", how="left")


def load_bytes(data: bytes, filename: str):
    """Returns (inventory_df, capacity_df | None, warnings)."""
    cap_raw = None
    if filename.lower().endswith((".xlsx", ".xls")):
        sheets = pd.read_excel(io.BytesIO(data), sheet_name=None, dtype=object)
        names = {_norm(n): n for n in sheets}
        inv_name = next((names[k] for k in ("inventory", "stock", "موجودی") if k in names), next(iter(sheets)))
        cap_name = next((names[k] for k in ("warehouses", "capacity", "انبارها", "ظرفیت") if k in names), None)
        inv_raw, cap_raw = sheets[inv_name], sheets[cap_name] if cap_name else None
    else:
        try:
            text = data.decode("utf-8-sig")
        except UnicodeDecodeError:
            text = data.decode("cp1256", errors="replace")
        inv_raw = pd.read_csv(io.StringIO(text), dtype=object)
    inv, warns = normalize_inventory(inv_raw)
    cap = normalize_capacity(cap_raw) if cap_raw is not None else None
    return inv, cap, warns
