import plotly.express as px
import plotly.graph_objects as go
from plotly.subplots import make_subplots

FONT = dict(family="Vazirmatn, Tahoma, sans-serif")
COLORS = {"A": "#2e7d32", "B": "#f9a825", "C": "#c62828"}


def _fin(fig, title):
    fig.update_layout(title=title, font=FONT, margin=dict(l=10, r=10, t=50, b=10), legend_title_text="")
    return fig


def pareto(abc, top=40):
    t = abc.head(top)
    fig = make_subplots(specs=[[{"secondary_y": True}]])
    fig.add_bar(x=t.sku, y=t.annual_value, marker_color=t.abc.map(COLORS), name="ارزش مصرف سالانه",
                customdata=t[["name", "abc"]], hovertemplate="%{customdata[0]}<br>کلاس %{customdata[1]}<br>%{y:,.0f}<extra></extra>")
    fig.add_scatter(x=t.sku, y=t.cum_share * 100, mode="lines+markers", name="سهم تجمعی ٪", secondary_y=True)
    fig.update_yaxes(range=[0, 105], secondary_y=True)
    return _fin(fig, f"نمودار پارتو (۴۰ کالای برتر)")


def value_by_category(d):
    g = d.groupby(["category", "warehouse"], as_index=False).stock_value.sum()
    return _fin(px.bar(g, x="category", y="stock_value", color="warehouse"), "ارزش موجودی به تفکیک گروه و انبار")


def dead_by_category(dead):
    g = dead.groupby("category", as_index=False).stock_value.sum()
    return _fin(px.bar(g, x="category", y="stock_value", color_discrete_sequence=["#c62828"]), "ارزش کالای راکد به تفکیک گروه")


def capacity_bar(cu):
    t = cu.dropna(subset=["utilization"])
    fig = px.bar(t, x="warehouse", y=t.utilization * 100, color_discrete_sequence=["#1565c0"])
    fig.update_yaxes(title="درصد اشغال")
    fig.add_hline(y=100, line_dash="dash", line_color="red")
    fig.add_hline(y=85, line_dash="dot", line_color="orange")
    return _fin(fig, "درصد اشغال انبارها")
