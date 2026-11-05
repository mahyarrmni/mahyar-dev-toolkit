"""Exportable technical report (self-contained offline HTML) and Excel workbook."""
from __future__ import annotations

import html
import io
from datetime import datetime

import numpy as np
import pandas as pd
from plotly.offline import get_plotlyjs

from . import viz
from .analysis import abc_classes
from .data import Problem
from .i18n import tr
from .objective import KEYS, Weights
from .optimize import Result
from .pipeline import Evaluation, kpi_table

CSS = ("body{font-family:Vazirmatn,Tahoma,Arial,sans-serif;max-width:1100px;margin:auto;padding:24px;color:#0f172a}"
       "h1{border-bottom:3px solid #6366f1;padding-bottom:8px}h2{margin-top:36px;color:#312e81}"
       "table{border-collapse:collapse;margin:12px 0}th,td{border:1px solid #cbd5e1;padding:4px 10px;text-align:inherit}"
       "th{background:#eef2ff}.fig{margin:16px 0}.muted{color:#64748b}")


def _tbl(df: pd.DataFrame) -> str:
    return df.to_html(index=False, border=0, float_format=lambda v: f"{v:,.3f}", escape=True)


def kpi_localized(ev: Evaluation, T: dict) -> pd.DataFrame:
    k = kpi_table(ev)
    return pd.DataFrame({T["kpi"]: [T[f"k_{x}"] for x in k.key], T["before"]: k.before, T["after"]: k.after,
                         T["change"] + " %": k.change_pct})


def components_table(res: Result, weights: Weights, T: dict) -> pd.DataFrame:
    w = weights.normalized()
    rows = [(T[f"w_{k}"], w[k], res.base_components[k], res.components[k],
             100 * (res.components[k] - res.base_components[k]) / max(abs(res.base_components[k]), 1e-12)) for k in KEYS]
    return pd.DataFrame(rows, columns=[T["components"], T["weight"], T["before"], T["after"], T["change"] + " %"])


def build_html(p: Problem, res: Result, ev: Evaluation, weights: Weights, lang: str = "en") -> str:
    T, rtl = tr(lang), lang == "fa"
    kt = kpi_table(ev)
    figs = [viz.layout_figure(p, res.baseline, title=f"{T['before']} — {T['m_frequency']}", T=T),
            viz.layout_figure(p, res.locs, title=f"{T['after']} — {T['m_frequency']}", T=T),
            viz.convergence_figure(res.history, T),
            viz.delta_figure([(T[f"k_{k}"], v) for k, v in zip(kt.key, kt.improvement_pct)], T),
            viz.util_figure(ev.sim_b.resources, ev.sim_a.resources, T), viz.queue_figure(ev.sim_b.series, ev.sim_a.series, T)]
    fig_html = "".join(f'<div class="fig">{f.to_html(full_html=False, include_plotlyjs=False)}</div>' for f in figs)
    res_tbl = lambda r: r.assign(utilization=r.utilization.round(3)).rename(columns={
        "resource": T["stage"], "capacity": T["pickers"] + "/cap", "utilization": T["utilization"], "avg_wait_s": T["avg_wait_s"], "visits": "n"})
    finds = lambda fs: "".join(f"<li><b>{T['sev_' + f['severity']]}</b> — {html.escape(T['f_' + f['kind']].format(r=f['resource'], v=f['value']))}</li>"
                               for f in fs) or f"<li>{T['no_bottleneck']}</li>"
    cap = lambda ev_c: _tbl(ev_c["by_zone"].rename(columns={"locations": T["locations_n"], "occupied": T["occupied"],
                            "volume_utilization": T["volume_utilization"], "slot_utilization": T["slot_utilization"]}))
    s = ev.cap_a["summary"]
    cls = abc_classes(p.freq)
    summary = pd.DataFrame({T["kpi"]: [T["skus"], T["locations"], T["lines"], T["orders"], "ABC (A/B/C)", T["j_base"], T["j_ga"], T["lower_bound"], T["gap"], T["runtime"]],
                            T["raw"]: [p.S, p.wh.n, len(p.orders), p.orders.order_id.nunique(),
                                       "/".join(str(int((cls == c).sum())) for c in "ABC"), 1.0, res.cost, res.lower_bound,
                                       f"{res.gap:.1%}", f"{res.runtime_s:.1f}"]}).astype(str)
    stage = ev.stage_b.merge(ev.stage_a, on="stage", suffixes=("_b", "_a")).rename(
        columns={"stage": T["stage"], "capacity_orders_h_b": T["before"], "capacity_orders_h_a": T["after"]})
    body = f"""<h1>{T['r_title']}</h1><p class="muted">{datetime.now():%Y-%m-%d %H:%M} · Warehouse Digital Twin & Optimization Engine</p>
<h2>{T['r_summary']}</h2>{_tbl(summary)}{_tbl(kpi_localized(ev, T))}
<h2>{T['r_objective']}</h2><p>{T['r_obj_text']}</p>{_tbl(components_table(res, weights, T))}
<h2>{T['r_results']}</h2>{fig_html}
<h2>{T['r_sim']}</h2><p class="muted">{T['arrival_rate']}: {ev.rate:.1f} · {T['pickers']}: {ev.sc.pickers} · {T['packers']}: {ev.sc.packers} · {T['aisle_cap']}: {ev.sc.aisle_capacity}</p>
<h3>{T['before']}</h3>{_tbl(res_tbl(ev.sim_b.resources.head(8)))}<h3>{T['after']}</h3>{_tbl(res_tbl(ev.sim_a.resources.head(8)))}
<h3>{T['stage_cap']}</h3>{_tbl(stage)}
<h2>{T['bottlenecks']}</h2><h3>{T['before']}</h3><ul>{finds(ev.find_b)}</ul><h3>{T['after']}</h3><ul>{finds(ev.find_a)}</ul>
<h2>{T['r_capacity']}</h2><p>{T['slot_utilization']}: {s['slot_utilization']:.1%} · {T['volume_utilization']}: {s['cube_utilization']:.1%}</p>{cap(ev.cap_a)}
<h2>{T['r_assump']}</h2><ul>{''.join(f'<li>{html.escape(x)}</li>' for x in T['r_assump_list'].split(chr(10)))}</ul>"""
    return (f'<!doctype html><html lang="{lang}" dir="{"rtl" if rtl else "ltr"}"><head><meta charset="utf-8">'
            f"<title>{T['r_title']}</title><style>{CSS}</style><script>{get_plotlyjs()}</script></head><body>{body}</body></html>")


def build_xlsx(p: Problem, res: Result, ev: Evaluation, lang: str = "en") -> bytes:
    T, wh = tr(lang), p.wh
    ids = wh.locations.location_id.to_numpy()
    assign = pd.DataFrame({"sku": p.skus.index, "name": p.skus.name.to_numpy(), "storage_class": p.skus.storage_class.to_numpy(),
                           "order_lines": p.freq, "abc": abc_classes(p.freq), "location_before": ids[res.baseline],
                           "location_after": ids[res.locs], "moved": res.baseline != res.locs})
    buf = io.BytesIO()
    with pd.ExcelWriter(buf, engine="openpyxl") as xw:
        assign.to_excel(xw, sheet_name="assignment", index=False)
        kpi_localized(ev, T).to_excel(xw, sheet_name="kpis", index=False)
        components_table(res, Weights(**{k: v for k, v in res.objective.w.items()}), T).to_excel(xw, sheet_name="objective", index=False)
        ev.sim_b.resources.to_excel(xw, sheet_name="resources_before", index=False)
        ev.sim_a.resources.to_excel(xw, sheet_name="resources_after", index=False)
        ev.cap_a["by_zone"].to_excel(xw, sheet_name="capacity_zone", index=False)
        ev.cap_a["by_level"].to_excel(xw, sheet_name="capacity_level", index=False)
    return buf.getvalue()


def assignment_csv(p: Problem, res: Result) -> bytes:
    ids = p.wh.locations.location_id.to_numpy()
    return pd.DataFrame({"sku": p.skus.index, "location_id": ids[res.locs]}).to_csv(index=False).encode("utf-8-sig")
