"""Streamlit UI (English / Persian RTL). Run: streamlit run app.py"""
from __future__ import annotations

import json
from pathlib import Path

import pandas as pd
import plotly.graph_objects as go
import streamlit as st

from wdt import viz
from wdt.analysis import abc_classes, required_pickers
from wdt.data import DataError, build_problem, legacy_assignment, load_table, parse_orders, parse_skus
from wdt.i18n import tr
from wdt.model import Warehouse, generate_warehouse
from wdt.objective import Weights
from wdt.optimize import GAConfig, optimize, pareto_sweep
from wdt.pipeline import evaluate, kpi_table
from wdt.report import assignment_csv, build_html, build_xlsx, components_table, kpi_localized
from wdt.routing import PickingConfig
from wdt.sim import SimConfig

SAMPLE = Path(__file__).parent / "data" / "sample"
RTL = ("<style>.stApp,[data-testid=stSidebar]{direction:rtl;text-align:right}"
       "[data-testid=stDataFrame],.stPlotlyChart,.js-plotly-plot{direction:ltr}</style>")

st.set_page_config(page_title="Warehouse Digital Twin", page_icon="🏭", layout="wide")
lang = st.sidebar.radio("Language / زبان", ["fa", "en"], format_func=lambda x: {"fa": "فارسی", "en": "English"}[x], key="lang")
T = tr(lang)
if lang == "fa":
    st.markdown(RTL, unsafe_allow_html=True)
st.title(T["title"])
st.caption(T["subtitle"])
S = st.session_state
tabs = st.tabs([T[k] for k in ("tab_data", "tab_opt", "tab_sim", "tab_cmp", "tab_ana", "tab_rep")])
PLOT = dict(width="stretch")


def clear(*keys):
    for k in keys:
        if k in S:
            del S[k]


def make_wh(a, b, lv, shelf, haz, cold, bw, aw, rd) -> Warehouse:
    zones = {a - 1 - i: "cold" for i in range(cold)} | {a - 1 - cold - i: "hazmat" for i in range(haz)}
    return generate_warehouse(a, b, lv, bw, aw, rd, zones=zones, shelf_aisles=range(shelf))


# ---------------------------------------------------------------- 1 data
with tabs[0]:
    src = st.radio(T["source"], ["sample", "upload"], format_func=lambda x: T[f"src_{x}"], horizontal=True, key="src")
    files = [None, None, None]
    if src == "upload":
        c = st.columns(3)
        files = [c[0].file_uploader(T["up_skus"], type=["csv", "xlsx"], key="f_skus"),
                 c[1].file_uploader(T["up_orders"], type=["csv", "xlsx"], key="f_orders"),
                 c[2].file_uploader(T["up_locs"], type=["csv", "xlsx"], key="f_locs")]
    d = dict(aisles=6, bays=20, levels=3, shelf=2, haz=1, cold=1)
    with st.expander(T["wh_params"], expanded=src == "upload"):
        c = st.columns(6)
        a = c[0].number_input(T["aisles"], 1, 40, d["aisles"], key="n_aisles")
        b = c[1].number_input(T["bays"], 1, 100, d["bays"], key="n_bays")
        lv = c[2].number_input(T["levels"], 1, 6, d["levels"], key="n_levels")
        shelf = c[3].number_input(T["shelf_aisles"], 0, 40, d["shelf"], key="n_shelf")
        haz = c[4].number_input(T["haz_aisles"], 0, 10, d["haz"], key="n_haz")
        cold = c[5].number_input(T["cold_aisles"], 0, 10, d["cold"], key="n_cold")
        c = st.columns(3)
        bw = c[0].number_input(T["bay_w"], 0.5, 5.0, 1.2, key="bw")
        aw = c[1].number_input(T["aisle_w"], 1.0, 8.0, 3.0, key="aw")
        rd = c[2].number_input(T["rack_d"], 0.5, 3.0, 1.1, key="rd")
    fill = st.slider(T["fill"], 0.5, 1.0, 0.75, 0.05, key="fill")
    if st.button(T["build"], key="btn_build", type="primary"):
        try:
            if src == "sample":
                sk_df, od_df, loc_df = load_table(SAMPLE / "skus.csv"), load_table(SAMPLE / "orders.csv"), None
            else:
                if not (files[0] and files[1]):
                    raise DataError(T["need_data"])
                sk_df, od_df = load_table(files[0]), load_table(files[1])
                loc_df = load_table(files[2]) if files[2] else None
            skus, w1 = parse_skus(sk_df)
            orders, w2 = parse_orders(od_df, skus)
            if loc_df is not None:
                num = loc_df.copy()
                for col in ("aisle", "bay", "side", "level"):
                    num[col] = pd.to_numeric(num[col]).astype(int)
                for col in ("x", "y", "volume_cap", "weight_cap"):
                    num[col] = pd.to_numeric(num[col])
                wh = Warehouse(num, float(num.y.max() + 3.0), float(num.x.mean()), bw, aw, rd)
            else:
                wh = make_wh(a, b, lv, shelf, haz, cold, bw, aw, rd)
            p = build_problem(wh, skus, orders, fill)
            S.p, S.base, S.warn = p, legacy_assignment(p), w1 + w2 + p.warnings
            clear("res", "ev", "pareto")
        except (DataError, ValueError, KeyError) as e:
            st.error(str(e))
    if "p" in S:
        p = S.p
        st.success(T["built"])
        for w in S.warn:
            st.warning(w)
        c = st.columns(4)
        c[0].metric(T["skus"], p.S)
        c[1].metric(T["locations"], p.wh.n)
        c[2].metric(T["lines"], len(p.orders))
        c[3].metric(T["orders"], p.orders.order_id.nunique())
        c = st.columns(2)
        metric = c[0].selectbox(T["metric"], ["frequency", "utilization", "zone"], format_func=lambda x: T[f"m_{x}"], key="m0")
        lvl = c[1].selectbox(T["level"], [None] + list(range(p.wh.level.max() + 1)),
                             format_func=lambda x: T["all_levels"] if x is None else str(x + 1), key="l0")
        st.plotly_chart(viz.layout_figure(p, S.base, metric, lvl, T["before"], T=T), **PLOT)

# ---------------------------------------------------------------- 2 optimisation
with tabs[1]:
    if "p" not in S:
        st.info(T["need_data"])
    else:
        c = st.columns(4)
        w = Weights(c[0].slider(T["w_distance"], 0.0, 1.0, 0.5, 0.05, key="w1"), c[1].slider(T["w_affinity"], 0.0, 1.0, 0.2, 0.05, key="w2"),
                    c[2].slider(T["w_ergonomics"], 0.0, 1.0, 0.2, 0.05, key="w3"), c[3].slider(T["w_slotfit"], 0.0, 1.0, 0.1, 0.05, key="w4"))
        c = st.columns(3)
        pop = c[0].number_input(T["ga_pop"], 10, 400, 80, key="pop")
        gens = c[1].number_input(T["ga_gens"], 10, 1000, 150, key="gens")
        seed = c[2].number_input(T["seed"], 0, 10_000, 0, key="seed")
        if st.button(T["run_opt"], key="btn_opt", type="primary"):
            try:
                w.normalized()
                bar = st.progress(0.0, text=T["running"])
                S.res = optimize(S.p, w, GAConfig(pop=int(pop), gens=int(gens), seed=int(seed), patience=int(gens)), S.base,
                                 progress=lambda g, n, best: bar.progress(g / n, text=f"{T['running']} J={best:.4f}"))
                S.w = w
                bar.empty()
                clear("ev", "pareto")
            except (ValueError, DataError) as e:
                st.error(str(e))
        if "res" in S:
            r = S.res
            c = st.columns(5)
            for col, (k, v) in zip(c, [("j_base", 1.0), ("j_seed", r.seed_cost), ("j_ga", r.cost), ("lower_bound", r.lower_bound), ("gap", r.gap)]):
                col.metric(T[k], f"{v:.1%}" if k == "gap" else f"{v:.4f}")
            st.dataframe(components_table(r, S.w, T).round(3), hide_index=True, **PLOT)
            st.plotly_chart(viz.convergence_figure(r.history, T), **PLOT)
            if st.button(T["pareto_btn"], key="btn_pareto"):
                ws = [Weights(.7, .1, .1, .1), Weights(.2, .6, .1, .1), Weights(.2, .1, .6, .1), Weights(.2, .1, .1, .6), Weights(.4, .3, .2, .1)]
                S.pareto = pareto_sweep(S.p, ws, GAConfig(pop=30, gens=40, seed=int(seed)), S.base)
            if "pareto" in S:
                st.plotly_chart(viz.pareto_figure(S.pareto, T), **PLOT)
                st.dataframe(S.pareto.round(3), hide_index=True, **PLOT)

# ---------------------------------------------------------------- 3 simulation
with tabs[2]:
    if "res" not in S:
        st.info(T["need_opt"])
    else:
        c = st.columns(5)
        pickers = c[0].number_input(T["pickers"], 1, 100, 8, key="pickers")
        packers = c[1].number_input(T["packers"], 1, 50, 3, key="packers")
        acap = c[2].number_input(T["aisle_cap"], 1, 10, 2, key="acap")
        n_ord = c[3].number_input(T["n_orders"], 20, 5000, 400, key="n_ord")
        tu = c[4].slider(T["target_util"], 0.3, 1.2, 0.85, 0.05, key="tu")
        c = st.columns(6)
        speed, setup, pick_s = c[0].number_input(T["speed"], 0.3, 3.0, 1.1, key="sp"), c[1].number_input(T["setup"], 0.0, 300.0, 30.0, key="su"), c[2].number_input(T["pick_s"], 1.0, 120.0, 10.0, key="ps")
        unit_s, pk_b, pk_l = c[3].number_input(T["unit_s"], 0.0, 30.0, 1.5, key="us"), c[4].number_input(T["pack_base"], 0.0, 600.0, 40.0, key="pb"), c[5].number_input(T["pack_line"], 0.0, 120.0, 6.0, key="pl")
        if st.button(T["run_sim"], key="btn_sim", type="primary"):
            with st.spinner(T["running"]):
                S.ev = evaluate(S.p, S.res, PickingConfig(speed, setup, pick_s, unit_s),
                                SimConfig(int(pickers), int(packers), int(acap), int(n_ord), tu, pk_b, pk_l, 1))
        if "ev" in S:
            ev = S.ev
            st.dataframe(kpi_localized(ev, T).round(3), hide_index=True, **PLOT)
            st.plotly_chart(viz.util_figure(ev.sim_b.resources, ev.sim_a.resources, T), **PLOT)
            st.plotly_chart(viz.queue_figure(ev.sim_b.series, ev.sim_a.series, T), **PLOT)

# ---------------------------------------------------------------- 4 before / after
with tabs[3]:
    if "ev" not in S:
        st.info(T["need_sim"])
    else:
        p, r, ev = S.p, S.res, S.ev
        kt = kpi_table(ev)
        st.plotly_chart(viz.delta_figure([(T[f"k_{k}"], v) for k, v in zip(kt.key, kt.improvement_pct)], T), **PLOT)
        c = st.columns(3)
        metric = c[0].selectbox(T["metric"], ["frequency", "utilization", "zone"], format_func=lambda x: T[f"m_{x}"], key="m1")
        lvl = c[1].selectbox(T["level"], [None] + list(range(p.wh.level.max() + 1)), format_func=lambda x: T["all_levels"] if x is None else str(x + 1), key="l1")
        oi = c[2].selectbox(T["route_order"], list(range(min(300, len(ev.plans_b)))), format_func=lambda i: ev.plans_b[i].order_id, key="oi")
        c = st.columns(2)
        c[0].plotly_chart(viz.layout_figure(p, r.baseline, metric, lvl, f"{T['before']} · {ev.plans_b[oi].distance:.0f} m", ev.plans_b[oi].stops, T), **PLOT)
        c[1].plotly_chart(viz.layout_figure(p, r.locs, metric, lvl, f"{T['after']} · {ev.plans_a[oi].distance:.0f} m", ev.plans_a[oi].stops, T), **PLOT)

# ---------------------------------------------------------------- 5 analysis
with tabs[4]:
    if "ev" not in S:
        st.info(T["need_sim"])
    else:
        p, r, ev = S.p, S.res, S.ev
        st.subheader(T["capacity"])
        c = st.columns(2)
        for col, name, cap in ((c[0], T["before"], ev.cap_b), (c[1], T["after"], ev.cap_a)):
            s = cap["summary"]
            col.markdown(f"**{name}** — {T['occupied']}: {s['occupied']}/{s['locations']} · {T['volume_utilization']}: {s['cube_utilization']:.1%}")
            col.caption(T["by_zone"])
            col.dataframe(cap["by_zone"].round(3), hide_index=True, **PLOT)
            col.caption(T["by_level"])
            col.dataframe(cap["by_level"].round(3), hide_index=True, **PLOT)
        cls = abc_classes(p.freq)
        st.caption(T["abc"])
        st.dataframe(pd.DataFrame({"ABC": list("ABC"), T["skus"]: [int((cls == x).sum()) for x in "ABC"],
                                   T["lines"]: [int(p.freq[cls == x].sum()) for x in "ABC"]}), hide_index=True, **PLOT)
        st.subheader(T["aisle_load"])
        fig = go.Figure([go.Bar(x=ev.load_b.aisle, y=ev.load_b.share, name=T["before"], marker_color=viz.B),
                         go.Bar(x=ev.load_a.aisle, y=ev.load_a.share, name=T["after"], marker_color=viz.A)])
        fig.update_layout(barmode="group").update_yaxes(tickformat=".0%")
        st.plotly_chart(viz._fmt(fig, T["aisle_load"], 320), **PLOT)
        st.subheader(T["bottlenecks"])
        c = st.columns(2)
        for col, name, fs in ((c[0], T["before"], ev.find_b), (c[1], T["after"], ev.find_a)):
            col.markdown(f"**{name}**")
            for f in fs or [None]:
                col.write(T["no_bottleneck"] if f is None else f"{T['sev_' + f['severity']]} — " + T["f_" + f["kind"]].format(r=f["resource"], v=f["value"]))
        st.caption(T["stage_cap"])
        st.dataframe(ev.stage_b.merge(ev.stage_a, on="stage", suffixes=("_before", "_after")).round(1), hide_index=True, **PLOT)
        st.subheader(T["staffing"])
        c = st.columns(3)
        rate = c[0].number_input(T["arrival_rate"], 1.0, 5000.0, float(round(ev.rate, 1)), key="rate_in")
        tgt = c[1].slider(T["target_util"], 0.3, 1.0, 0.85, 0.05, key="tu2")
        c[2].metric(f"{T['required']} · {T['before']}", required_pickers(rate, ev.sum_b["avg_service_s"], tgt))
        c[2].metric(f"{T['required']} · {T['after']}", required_pickers(rate, ev.sum_a["avg_service_s"], tgt))

# ---------------------------------------------------------------- 6 reports
with tabs[5]:
    if "ev" not in S:
        st.info(T["need_sim"])
    else:
        p, r, ev, w = S.p, S.res, S.ev, S.w
        c = st.columns(3)
        c[0].download_button(T["dl_html"], build_html(p, r, ev, w, lang), "warehouse_report.html", "text/html", key="dl1")
        c[1].download_button(T["dl_xlsx"], build_xlsx(p, r, ev, lang), "warehouse_results.xlsx", key="dl2")
        c[2].download_button(T["dl_csv"], assignment_csv(p, r), "new_assignment.csv", "text/csv", key="dl3")
