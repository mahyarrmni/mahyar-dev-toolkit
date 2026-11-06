"""Explainable predictive SEO analytics: monotone CTR curve, honest cross-validation, scenario simulation."""
import numpy as np
import pandas as pd
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.inspection import permutation_importance
from sklearn.isotonic import IsotonicRegression

from . import config
from .intent import INTENTS, classify_query

POS_MAX = 30
# Generic prior (rounded approximation of published organic CTR-by-position studies). NOT measured on this site;
# used only when there is too little Search Console data to fit a site-specific curve, and labelled as such.
PRIOR_CTR = np.array([.285, .157, .110, .080, .062, .050, .040, .033, .028, .024, .016, .014, .012, .011, .010, .009, .008,
                      .0075, .007, .0065, .006, .0055, .005, .0047, .0044, .0041, .0038, .0035, .0033, .0030])


class CTRCurve:
    def __init__(self, min_impressions=5000, min_bins=6, min_bin_impr=100, min_rows=200):
        self.min_impressions, self.min_bins, self.min_bin_impr, self.min_rows = min_impressions, min_bins, min_bin_impr, min_rows

    def fit(self, df: pd.DataFrame, exclude_mask=None):
        d = df if exclude_mask is None else df[~exclude_mask]
        d = d[(d.position >= 1) & (d.position <= POS_MAX + 0.5) & (d.impressions > 0)]
        self.fit_rows = len(d)
        b = d.assign(bin=d.position.round().clip(1, POS_MAX).astype(int), pw=d.position * d.impressions).groupby("bin").agg(
            c=("clicks", "sum"), i=("impressions", "sum"), pw=("pw", "sum"))
        b = b[b.i >= self.min_bin_impr]
        b["x"] = b.pw / b.i  # impression-weighted mean position of the bin (removes binning bias)
        self.iso, self.bins = None, b
        if len(d) >= self.min_rows and b.i.sum() >= self.min_impressions and len(b) >= self.min_bins:
            self.iso = IsotonicRegression(increasing=False, y_min=0, y_max=1, out_of_bounds="clip").fit(
                b.x.values, (b.c / b.i).values, sample_weight=b.i.values)
        self.source = "site_gsc" if self.iso is not None else "generic_prior"
        return self

    def predict(self, pos):
        pos = np.clip(np.asarray(pos, dtype=float), 1, POS_MAX)
        return self.iso.predict(pos) if self.iso is not None else np.interp(pos, np.arange(1, POS_MAX + 1), PRIOR_CTR)

    def table(self):
        return {int(p): round(float(c), 5) for p, c in zip(range(1, POS_MAX + 1), self.predict(np.arange(1, POS_MAX + 1)))}


def _wape(y, yhat):
    return float(np.abs(y - yhat).sum() / max(1e-9, y.sum()))


def featurize(df, brand_terms=()):
    q = df["query"].fillna("")
    cls = {x: classify_query(x, brand_terms) for x in q.unique()}
    return pd.DataFrame({
        "position": df.position.values, "log_impressions": np.log1p(df.impressions.values),
        "query_words": q.str.split().str.len().fillna(0).values,
        "is_brand": [int(any(b.lower() in x for b in brand_terms)) if brand_terms else 0 for x in q],
        "intent": [INTENTS.index(cls[x]["intent"]) for x in q]})


def cross_validate(df, brand_terms=(), k=5, seed=config.SEED, with_gbm=True) -> dict:
    """Impression-weighted absolute error of predicted clicks (WAPE = sum|err| / sum clicks), k-fold, vs baselines."""
    idx = np.random.default_rng(seed).permutation(len(df))
    folds = np.array_split(idx, k)
    num, den = {"site_curve": 0.0, "generic_prior": 0.0, "constant_ctr": 0.0, "gbm": 0.0}, 0.0
    X = featurize(df, brand_terms) if with_gbm else None
    imp, last = None, None
    for f in folds:
        tr, te = df.iloc[np.setdiff1d(idx, f)], df.iloc[f]
        y, im = te.clicks.values, te.impressions.values
        c = CTRCurve(min_impressions=1000, min_rows=100).fit(tr)
        num["site_curve"] += np.abs(y - im * c.predict(te.position.values)).sum()
        num["generic_prior"] += np.abs(y - im * np.interp(np.clip(te.position.values, 1, POS_MAX), np.arange(1, POS_MAX + 1), PRIOR_CTR)).sum()
        num["constant_ctr"] += np.abs(y - im * tr.clicks.sum() / tr.impressions.sum()).sum()
        if with_gbm:
            m = HistGradientBoostingRegressor(max_depth=3, max_iter=80, learning_rate=0.1, random_state=seed)
            m.fit(X.iloc[np.setdiff1d(idx, f)], tr.ctr.values, sample_weight=tr.impressions.values)
            num["gbm"] += np.abs(y - im * m.predict(X.iloc[f])).sum()
            last = (m, X.iloc[f], te)
        den += y.sum()
    res = {"wape": {k_: round(v / max(den, 1e-9), 4) for k_, v in num.items() if v or k_ != "gbm"}, "folds": k, "rows": len(df)}
    if last:
        m, Xt, te = last
        pi = permutation_importance(m, Xt, te.ctr.values, scoring="neg_mean_absolute_error", n_repeats=5,
                                    random_state=seed, sample_weight=te.impressions.values)
        tot = max(pi.importances_mean.clip(0).sum(), 1e-12)
        res["gbm_permutation_importance"] = {c: round(float(max(v, 0) / tot), 3) for c, v in zip(Xt.columns, pi.importances_mean)}
    return res


def _select(df, sel):
    m = pd.Series(True, index=df.index)
    if "min_position" in sel: m &= df.position >= sel["min_position"]
    if "max_position" in sel: m &= df.position <= sel["max_position"]
    if "min_impressions" in sel: m &= df.impressions >= sel["min_impressions"]
    if "pages" in sel: m &= df.page.isin(sel["pages"])
    if "queries" in sel: m &= df["query"].isin(sel["queries"])
    return m


def _new_pos(pos, sc):
    if "target_position" in sc:
        return np.minimum(pos, sc["target_position"])
    return np.maximum(1.0, pos - sc.get("improve_by", 1.0))


def simulate(df, curve: CTRCurve, scenarios, n_boot=200, seed=config.SEED, ctr_curve_fit_df=None) -> list:
    """Projected click change = sum(impressions * (CTR(new_pos) - CTR(old_pos))) over selected rows. Interval from bootstrap
    refits of the curve (only when the curve is site-fitted)."""
    rng, out = np.random.default_rng(seed), []
    fit_df = ctr_curve_fit_df if ctr_curve_fit_df is not None else df
    boots = []
    if curve.source == "site_gsc":
        for _ in range(n_boot):
            boots.append(CTRCurve(curve.min_impressions, curve.min_bins, curve.min_bin_impr, curve.min_rows).fit(fit_df.iloc[rng.integers(0, len(fit_df), len(fit_df))]))
    for sc in scenarios:
        d = df[_select(df, sc.get("select", {}))]
        old, new = d.position.values, _new_pos(d.position.values, sc)
        gain_row = d.impressions.values * (curve.predict(new) - curve.predict(old))
        gains = np.array([(d.impressions.values * (b.predict(new) - b.predict(old))).sum() for b in boots])
        top = np.argsort(-gain_row)[:15]
        out.append({
            "name": sc["name"], "rows": int(len(d)), "baseline_clicks": float(d.clicks.sum()),
            "projected_extra_clicks": round(float(gain_row.sum()), 1),
            "interval_90": [round(float(np.percentile(gains, 5)), 1), round(float(np.percentile(gains, 95)), 1)] if len(gains) else None,
            "curve_source": curve.source, "assumption": {k: v for k, v in sc.items() if k != "name"},
            "top_contributors": [{"query": d["query"].iloc[i], "page": d.page.iloc[i], "from": round(float(old[i]), 1),
                                  "to": round(float(new[i]), 1), "impressions": float(d.impressions.iloc[i]),
                                  "extra_clicks": round(float(gain_row[i]), 1)} for i in top if gain_row[i] > 0]})
    return out


def opportunities(df, curve: CTRCurve, min_impressions=100, z_cut=-2.0, striking=(8, 20), lift=3.0) -> dict:
    """(a) CTR underperformance: observed clicks significantly below the curve (one-sided binomial z-score).
    (b) striking distance: queries at positions 8-20 and the click gain from moving up `lift` positions."""
    d = df[df.impressions >= min_impressions].copy()
    exp = curve.predict(d.position.values)
    n = d.impressions.values
    z = (d.clicks.values - n * exp) / np.sqrt(np.maximum(n * exp * (1 - exp), 1e-9))
    d["expected_ctr"], d["z"] = exp, z
    d["gain"] = n * np.maximum(0, exp - d.ctr.values)
    ctr_rows = d[d.z <= z_cut].sort_values("gain", ascending=False)
    s = d[(d.position >= striking[0]) & (d.position <= striking[1])].copy()
    s["gain"] = s.impressions * (curve.predict(np.maximum(1, s.position.values - lift)) - curve.predict(s.position.values))
    rec = lambda r, **k: {"query": r["query"], "page": r.page, "position": round(float(r.position), 1), "impressions": float(r.impressions),
                          "clicks": float(r.clicks), "ctr": round(float(r.ctr), 4), **k}
    return {
        "ctr_underperformers": [rec(r, expected_ctr=round(float(r.expected_ctr), 4), z=round(float(r.z), 2), gain=round(float(r.gain), 1))
                                for _, r in ctr_rows.head(50).iterrows()],
        "striking_distance": [rec(r, gain=round(float(r.gain), 1), target_position=round(max(1.0, float(r.position) - lift), 1))
                              for _, r in s.sort_values("gain", ascending=False).head(50).iterrows()],
        "curve_source": curve.source}
