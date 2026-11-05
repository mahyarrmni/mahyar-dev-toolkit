"""Plotly figures: 2D warehouse map, convergence, KPI comparison, utilization, queues, Pareto front."""
from __future__ import annotations

import html

import numpy as np
import pandas as pd
import plotly.graph_objects as go

from .data import Problem
from .i18n import tr

FONT = "Vazirmatn, Tahoma, Arial, sans-serif"
B, A = "#94a3b8", "#6366f1"


def _fmt(fig: go.Figure, title: str, h: int = 380) -> go.Figure:
    fig.update_layout(title=title, height=h, font_family=FONT, margin=dict(l=40, r=20, t=50, b=40),
                      legend=dict(orientation="h", y=-0.2))
    return fig


def layout_figure(p: Problem, locs, metric="frequency", level: int | None = None, title="", route=None, T=None):
    """2D map. metric: frequency | utilization | zone. level: 0-based rack level or None (aggregate all levels)."""
    T, wh = T or tr("en"), p.wh
    occ = np.full(wh.n, -1)
    occ[np.asarray(locs)] = np.arange(p.S)
    has, sk = occ >= 0, occ.clip(0)
    freq = np.where(has, p.freq[sk], 0.0)
    phys = (p.skus.pickface_qty * p.skus.unit_volume_m3).to_numpy(float)[sk]
    util = np.where(has, phys / wh.vol_cap, 0.0)
    zone = pd.Series(wh.zone).map({"general": 0, "cold": 1, "hazmat": 2}).fillna(0).to_numpy(float)
    val, agg = {"frequency": (freq, "sum"), "utilization": (util, "mean"), "zone": (zone, "max")}[metric]
    names = p.skus.name.to_numpy()
    txt = [f"{html.escape(str(i))}: {html.escape(str(names[s]))} ({int(f)})" if h else f"{html.escape(str(i))}: —"
           for i, s, f, h in zip(wh.locations.location_id, sk, freq, has)]
    aisles = np.unique(wh.aisle)
    apos = {a: i for i, a in enumerate(aisles)}
    ys = np.unique(np.round(wh.y, 3))
    off = wh.aisle_width / 2 + wh.rack_depth / 2
    xs = []
    for a in aisles:
        ax = float(wh.x[wh.aisle == a].mean())
        xs += [ax - off, ax, ax + off]
    df = pd.DataFrame({"r": np.searchsorted(ys, np.round(wh.y, 3)), "c": np.array([3 * apos[a] for a in wh.aisle]) + 2 * wh.side,
                       "v": val, "t": txt})
    if level is not None:
        df = df[wh.level == level]
    g = df.groupby(["r", "c"]).agg(v=("v", agg), t=("t", "<br>".join)).reset_index()
    z = np.full((len(ys), len(xs)), np.nan)
    hover = np.full((len(ys), len(xs)), "", dtype=object)
    z[g.r, g.c] = g.v
    hover[g.r, g.c] = g.t
    scale = {"frequency": "YlOrRd", "utilization": "Blues",
             "zone": [[0, "#94a3b8"], [0.5, "#38bdf8"], [1, "#f97316"]]}[metric]
    zr = {"utilization": (0, 1), "zone": (0, 2)}.get(metric, (0, None))
    fig = go.Figure(go.Heatmap(x=xs, y=ys, z=z, text=hover, hovertemplate="%{text}<extra></extra>", colorscale=scale,
                               zmin=zr[0], zmax=zr[1], xgap=1, ygap=1, showscale=metric != "zone", colorbar=dict(thickness=10)))
    fig.add_trace(go.Scatter(x=[wh.depot_x], y=[0], mode="markers", name=T["depot"],
                             marker=dict(symbol="star", size=16, color="#16a34a", line=dict(width=1, color="white"))))
    if route:
        pts = np.array(wh.route_points([int(r) for r in route]))
        fig.add_trace(go.Scatter(x=pts[:, 0], y=pts[:, 1], mode="lines+markers", name=T["route_order"],
                                 line=dict(color="#2563eb", width=3), marker=dict(size=5)))
    fig.update_yaxes(title="y (m)", scaleanchor="x", scaleratio=1)
    fig.update_xaxes(title="x (m)")
    return _fmt(fig, title, 520)


def convergence_figure(hist: pd.DataFrame, T=None):
    T = T or tr("en")
    fig = go.Figure([go.Scatter(x=hist.generation, y=hist.best, name=T["best"], line=dict(color=A)),
                     go.Scatter(x=hist.generation, y=hist["mean"], name=T["mean"], line=dict(color=B, dash="dot"))])
    fig.update_xaxes(title=T["gen"]).update_yaxes(title="J")
    return _fmt(fig, T["convergence"])


def delta_figure(rows: list[tuple[str, float]], T=None):
    """rows: (label, % improvement; positive = better)."""
    T = T or tr("en")
    fig = go.Figure(go.Bar(x=[r[1] for r in rows], y=[r[0] for r in rows], orientation="h",
                           marker_color=[A if r[1] >= 0 else "#ef4444" for r in rows], text=[f"{r[1]:.1f}%" for r in rows]))
    fig.update_yaxes(autorange="reversed").update_xaxes(title=T["improvement"])
    return _fmt(fig, T["improvement"], 360)


def util_figure(rb: pd.DataFrame, ra: pd.DataFrame, T=None, top: int = 10):
    T = T or tr("en")
    d = rb.merge(ra, on="resource", suffixes=("_b", "_a")).sort_values("utilization_b", ascending=False).head(top)
    fig = go.Figure([go.Bar(x=d.resource, y=d.utilization_b, name=T["before"], marker_color=B),
                     go.Bar(x=d.resource, y=d.utilization_a, name=T["after"], marker_color=A)])
    fig.update_layout(barmode="group").update_yaxes(tickformat=".0%", range=[0, 1.05])
    return _fmt(fig, T["util_chart"])


def queue_figure(sb: pd.DataFrame, sa: pd.DataFrame, T=None):
    T = T or tr("en")
    fig = go.Figure([go.Scatter(x=sb.t / 3600, y=sb.pick_queue, name=T["before"], line=dict(color=B)),
                     go.Scatter(x=sa.t / 3600, y=sa.pick_queue, name=T["after"], line=dict(color=A))])
    fig.update_xaxes(title="h").update_yaxes(title=T["queue"])
    return _fmt(fig, T["queue"])


def pareto_figure(df: pd.DataFrame, T=None):
    T = T or tr("en")
    fig = go.Figure()
    for flag, col in ((False, B), (True, A)):
        d = df[df.pareto == flag]
        fig.add_trace(go.Scatter(x=d.distance, y=d.affinity, mode="markers", name="Pareto" if flag else "dominated",
                                 marker=dict(size=11, color=col)))
    fig.update_xaxes(title=T["w_distance"]).update_yaxes(title=T["w_affinity"])
    return _fmt(fig, "Pareto")
