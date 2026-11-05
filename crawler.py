"""Google Search Console: CSV/Excel export import + API client (token supplied by caller, never stored)."""
import io
import time
from urllib.parse import quote

import httpx
import numpy as np
import pandas as pd

from .text import DIGITS, normalize
from .urls import normalize_url

ALIASES = {
    "query": ["query", "queries", "top queries", "search query", "کوئری", "عبارت جستجو"],
    "page": ["page", "pages", "top pages", "landing page", "url", "صفحه", "صفحات برتر"],
    "clicks": ["clicks", "click", "کلیک", "کلیک ها"],
    "impressions": ["impressions", "impr", "نمایش", "تعداد نمایش"],
    "position": ["position", "avg position", "average position", "موقعیت", "میانگین موقعیت"],
    "ctr": ["ctr", "نرخ کلیک"],
    "date": ["date", "تاریخ"],
}
_LOOK = {normalize(a).replace(" ", ""): k for k, v in ALIASES.items() for a in v}


def _num(s: pd.Series) -> pd.Series:
    t = s.fillna("").astype(str).str.translate(DIGITS).str.replace(r"[,%\s]", "", regex=True)
    return pd.to_numeric(t, errors="coerce")


def load_gsc(data, filename: str = "gsc.csv") -> pd.DataFrame:
    """Search Console export -> DataFrame[query, page, clicks, impressions, position, ctr, (date)]."""
    if isinstance(data, pd.DataFrame):
        raw = data
    elif filename.lower().endswith((".xlsx", ".xls")):
        raw = pd.read_excel(io.BytesIO(data), dtype=object)
    else:
        txt = data.decode("utf-8-sig", errors="replace") if isinstance(data, bytes) else data
        raw = pd.read_csv(io.StringIO(txt), dtype=object)
    df = raw.rename(columns=lambda c: _LOOK.get(normalize(str(c)).replace(" ", ""), str(c)))
    df = df.loc[:, ~df.columns.duplicated()]
    miss = [c for c in ("clicks", "impressions", "position") if c not in df]
    if miss or ("query" not in df and "page" not in df):
        raise ValueError(f"GSC file needs clicks, impressions, position and query and/or page columns (missing: {miss})")
    out = pd.DataFrame({c: _num(df[c]) for c in ("clicks", "impressions", "position")})
    out["query"] = df["query"].fillna("").astype(str).map(normalize) if "query" in df else ""
    out["page"] = df["page"].map(lambda u: normalize_url(str(u)) if isinstance(u, str) else None) if "page" in df else None
    if "date" in df:
        out["date"] = pd.to_datetime(df["date"], errors="coerce").dt.strftime("%Y-%m-%d")
    out = out.dropna(subset=["clicks", "impressions", "position"])
    out = out[(out.impressions > 0) & (out.position >= 1) & (out.clicks >= 0) & (out.clicks <= out.impressions)]
    out["ctr"] = out.clicks / out.impressions
    return out.reset_index(drop=True)


def aggregate(df: pd.DataFrame, by) -> pd.DataFrame:
    """Sum clicks/impressions; position = impression-weighted mean (how GSC itself averages)."""
    g = df.assign(_pw=df.position * df.impressions).groupby(by, dropna=False, as_index=False).agg(
        clicks=("clicks", "sum"), impressions=("impressions", "sum"), _pw=("_pw", "sum"))
    g["position"] = g._pw / g.impressions
    g["ctr"] = g.clicks / g.impressions
    return g.drop(columns="_pw")


def page_stats(df: pd.DataFrame, top_n=5) -> dict:
    """{url: {clicks, impressions, position, top_queries}} — used for linking/recommendation evidence."""
    d = df.dropna(subset=["page"])
    stats = {r.page: {"clicks": float(r.clicks), "impressions": float(r.impressions), "position": float(r.position)}
             for r in aggregate(d, "page").itertuples()}
    for page, g in d.groupby("page"):
        top = g.sort_values("impressions", ascending=False).head(top_n)
        stats[page]["top_queries"] = [{"query": q, "impressions": float(i), "position": round(float(p), 1)}
                                      for q, i, p in zip(top["query"], top.impressions, top.position)]
    return stats


class GSCError(RuntimeError):
    pass


class GSCClient:
    BASE = "https://www.googleapis.com/webmasters/v3/sites"

    def __init__(self, access_token: str, transport=None, timeout: float = 30.0):
        self._token = access_token  # kept in memory only
        self._http = httpx.Client(timeout=timeout, transport=transport)

    def __repr__(self):
        return "GSCClient(token=***)"

    def query(self, site_url, start, end, dimensions=("query", "page"), row_limit=25000, max_rows=200_000,
              search_type="web") -> pd.DataFrame:
        url = f"{self.BASE}/{quote(site_url, safe='')}/searchAnalytics/query"
        rows, start_row = [], 0
        while len(rows) < max_rows:
            body = {"startDate": start, "endDate": end, "dimensions": list(dimensions), "rowLimit": row_limit,
                    "startRow": start_row, "type": search_type}
            for attempt in range(4):
                r = self._http.post(url, json=body, headers={"Authorization": f"Bearer {self._token}"})
                if r.status_code in (429, 500, 502, 503) and attempt < 3:
                    time.sleep(0.5 * 2 ** attempt)
                    continue
                break
            if r.status_code != 200:
                msg = r.json().get("error", {}).get("message", "") if r.headers.get("content-type", "").startswith("application/json") else ""
                raise GSCError(f"Search Console API error {r.status_code}: {msg}"[:300])
            batch = r.json().get("rows", [])
            rows.extend(batch)
            if len(batch) < row_limit:
                break
            start_row += row_limit
        recs = [{**dict(zip(dimensions, x["keys"])), "clicks": x["clicks"], "impressions": x["impressions"],
                 "position": x["position"]} for x in rows]
        return load_gsc(pd.DataFrame(recs)) if recs else pd.DataFrame(columns=["query", "page", "clicks", "impressions", "position", "ctr"])
