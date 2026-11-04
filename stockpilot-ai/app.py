import pandas as pd
import streamlit as st

from stockpilot import analysis as an, charts, config, data, db, report, sample

st.set_page_config(page_title="StockPilot AI", page_icon="📦", layout="wide")
st.markdown("""<style>
.stApp, [data-testid="stSidebar"] {direction: rtl; font-family: Vazirmatn, Tahoma, 'Segoe UI', sans-serif;}
[data-testid="stDataFrame"], .js-plotly-plot {direction: ltr;}
</style>""", unsafe_allow_html=True)

LABELS = {"sku": "کد کالا", "name": "نام کالا", "category": "گروه", "warehouse": "انبار", "quantity": "موجودی",
          "unit_cost": "بهای واحد", "monthly_demand": "فروش ماهانه", "last_movement": "آخرین گردش",
          "lead_time_days": "زمان تامین (روز)", "stock_value": "ارزش موجودی", "days_cover": "روز پوشش",
          "annual_value": "ارزش مصرف سالانه", "share": "سهم", "cum_share": "سهم تجمعی", "abc": "کلاس",
          "status": "وضعیت", "reorder_point": "نقطه سفارش", "suggested_order": "سفارش پیشنهادی",
          "days_idle": "روز بدون گردش", "used_m3": "حجم اشغال (m³)", "capacity_m3": "ظرفیت (m³)",
          "utilization": "درصد اشغال", "free_m3": "فضای آزاد (m³)", "skus": "تعداد کالا", "units": "تعداد واحد"}
STATUS = {"out": "تمام‌شده", "critical": "بحرانی", "low": "کم‌موجودی", "over": "سرریز",
          "high": "نزدیک به ظرفیت", "ok": "مطلوب", "unknown": "نامشخص"}


def show(df, cols, csv_name=None):
    t = df[cols].copy()
    if "status" in t:
        t["status"] = t["status"].map(STATUS)
    for c in ("share", "cum_share", "utilization"):
        if c in t:
            t[c] = (t[c] * 100).round(1)
    st.dataframe(t.rename(columns=LABELS), hide_index=True)
    if csv_name:
        st.download_button("دانلود CSV", t.rename(columns=LABELS).to_csv(index=False).encode("utf-8-sig"),
                           csv_name, "text/csv", key=f"dl_{csv_name}")


if "inv" not in st.session_state:
    inv0, cap0 = db.load()
    st.session_state.update(inv=inv0, cap=cap0, last_file=None, warns=[])


def set_data(inv, cap):
    cap = cap if cap is not None else st.session_state.cap
    st.session_state.inv, st.session_state.cap = inv, cap
    db.save(inv, cap)


st.title("📦 StockPilot AI — هوش انبار")
with st.sidebar:
    st.header("داده")
    up = st.file_uploader("فایل موجودی (Excel/CSV)", type=["xlsx", "xls", "csv"])
    if up is not None and st.session_state.last_file != (up.name, up.size):
        try:
            inv_, cap_, warns_ = data.load_bytes(up.getvalue(), up.name)
            set_data(inv_, cap_)
            st.session_state.last_file, st.session_state.warns = (up.name, up.size), warns_
        except Exception as e:
            st.error(str(e))
    if st.button("بارگذاری داده نمونه", key="load_sample"):
        set_data(*sample.make_sample())
        st.session_state.warns = []
    for w in st.session_state.warns:
        st.warning(w)
    st.header("پارامترها")
    dead_days = st.slider("آستانه کالای راکد (روز)", 30, 720, 180)
    safety = st.slider("موجودی اطمینان (روز)", 0, 30, 7)
    cover = st.slider("پوشش هدف سفارش (روز)", 7, 90, 30)
    a_thr = st.number_input("آستانه کلاس A (٪)", 50, 95, 80)
    b_thr = st.number_input("آستانه کلاس B (٪)", int(a_thr) + 1, 99, max(95, int(a_thr) + 1))

inv = st.session_state.inv
if inv is None:
    st.info("یک فایل Excel/CSV بارگذاری کنید یا «بارگذاری داده نمونه» را بزنید. ستون‌های فارسی و انگلیسی پشتیبانی می‌شوند.")
    st.stop()

with st.sidebar.expander("ظرفیت انبارها (m³)"):
    cap_in = data.complete_capacity(inv, st.session_state.cap)
    cap = st.data_editor(cap_in, hide_index=True, disabled=["warehouse"], key="cap_editor",
                         column_config={"warehouse": "انبار", "capacity_m3": st.column_config.NumberColumn("ظرفیت (m³)", min_value=0)})
    if not cap.equals(cap_in) or st.session_state.cap is None:
        st.session_state.cap = cap.dropna()
        db.save_capacity(cap.dropna())

today = pd.Timestamp.today().normalize()
d = an.enrich(inv, today)
abc = an.abc_classification(d, a_thr / 100, b_thr / 100)
d = d.merge(abc[["sku", "abc"]], on="sku", how="left")
low, dead = an.low_stock(d, safety, cover), an.dead_stock(d, dead_days)
slow = an.slow_moving(d, dead_days)
cu = an.capacity_usage(d, cap.dropna(subset=["capacity_m3"]))
facts = report.build_facts(d, abc, low, dead, cu, today)
cur = config.CURRENCY

t_dash, t_abc, t_low, t_dead, t_cap, t_ai = st.tabs(
    ["داشبورد", "طبقه‌بندی ABC", "کم‌موجودی", "کالای راکد", "ظرفیت انبار", "گزارش هوشمند"])

with t_dash:
    c = st.columns(5)
    c[0].metric("تعداد کالا", f"{facts['skus']:,}")
    c[1].metric(f"ارزش موجودی ({cur})", f"{facts['stock_value']:,}")
    c[2].metric("ردیف کم‌موجودی", facts["low_stock"]["rows"])
    c[3].metric("ارزش کالای راکد", f"{facts['dead_stock']['value']:,}", f"{facts['dead_stock']['pct_of_stock_value']}٪", delta_color="inverse")
    c[4].metric("کالای کند‌گردش", len(slow))
    st.plotly_chart(charts.value_by_category(d))
    show(d, ["sku", "name", "category", "warehouse", "quantity", "stock_value", "days_cover", "abc"])

with t_abc:
    st.plotly_chart(charts.pareto(abc))
    show(abc, ["sku", "name", "category", "annual_value", "share", "cum_share", "abc"], "abc.csv")

with t_low:
    st.caption("نقطه سفارش = فروش روزانه × (زمان تامین + موجودی اطمینان). بحرانی: موجودی کمتر از مصرف دوره تامین.")
    if low.empty:
        st.success("کالای کم‌موجودی وجود ندارد.")
    else:
        show(low, ["sku", "name", "warehouse", "quantity", "reorder_point", "days_cover", "suggested_order", "status"], "low_stock.csv")

with t_dead:
    if dead.empty:
        st.success("کالای راکد وجود ندارد.")
    else:
        st.plotly_chart(charts.dead_by_category(dead))
        show(dead, ["sku", "name", "category", "warehouse", "quantity", "days_idle", "stock_value"], "dead_stock.csv")
    if not slow.empty:
        st.subheader("کالای کند‌گردش (پوشش بیش از ۳۶۵ روز)")
        show(slow, ["sku", "name", "warehouse", "quantity", "days_cover", "stock_value"])

with t_cap:
    if cu.utilization.notna().any():
        st.plotly_chart(charts.capacity_bar(cu))
    else:
        st.info("ظرفیت انبارها را در نوار کناری وارد کنید.")
    show(cu, ["warehouse", "skus", "units", "used_m3", "capacity_m3", "utilization", "free_m3", "status"])
    if (inv.unit_volume_m3 <= 0).all():
        st.warning("حجم واحد کالاها در داده وجود ندارد؛ نتایج ظرفیت قابل اتکا نیست.")

with t_ai:
    st.markdown(report.template_fa(facts))
    model = st.text_input("مدل Ollama", config.OLLAMA_MODEL)
    if st.button("تولید گزارش با هوش مصنوعی (Ollama)", key="ai_report"):
        with st.spinner("در حال تولید..."):
            try:
                st.markdown(report.ai_report(facts, model))
            except RuntimeError as e:
                st.error(str(e))
    q = st.text_input("از انبار بپرسید (مثلاً: کدام انبار پرتر است؟)")
    if st.button("بپرس", key="ask") and q:
        with st.spinner("در حال پاسخ..."):
            try:
                st.markdown(report.ask(q, facts, model))
            except RuntimeError as e:
                st.error(str(e))
