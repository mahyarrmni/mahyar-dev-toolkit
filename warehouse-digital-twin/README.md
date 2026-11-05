# Warehouse Digital Twin & Optimization Engine

Offline B2B tool that models a warehouse, optimizes SKU placement with real algorithms and measures the effect with
actual picking tours and a discrete-event simulation. Persian (RTL) and English UI. No paid APIs, no cloud calls.

**Stack:** Python · Streamlit · OR-Tools · SimPy · NumPy · Pandas · Plotly (+ openpyxl for Excel).

## What it does
| Capability | Implementation |
|---|---|
| Warehouse model | aisles, bays, levels, sides, zones (general/cold/hazmat), volume & weight capacity per location; generated or imported |
| Data import | CSV/Excel, Persian or English headers, Persian digits, `cp1256` CSVs, validation messages |
| Objectives | travel distance, co-pick affinity, ergonomics, slot fit - measurable, normalised to the baseline (`J = 1`) |
| Optimization | exact min-cost-flow assignment (OR-Tools) as lower bound/seed + genetic algorithm with feasibility-preserving operators and local search; Pareto sweep |
| Picking | real TSP tours per order (OR-Tools routing), picking-time model |
| Simulation | SimPy: arrivals, pickers, aisle blocking, packing stations, queues, utilisation |
| Analysis | capacity by zone/level, ABC, aisle load, bottleneck findings, staffing (Little's law) |
| UI | 2D interactive map (frequency/utilisation/zone, per level, order routes), before/after comparison |
| Reports | self-contained offline HTML report, Excel workbook, CSV of the new assignment |

Mathematics, assumptions and limits: [docs/ALGORITHMS.md](docs/ALGORITHMS.md).

## Quick start
```bash
python -m venv .venv && . .venv/bin/activate      # Python 3.12 recommended
pip install -r requirements.txt
streamlit run app.py                              # http://localhost:8501
pytest -q                                         # optional: run the test suite (requirements-dev.txt)
```
In the app: **1** build the twin (sample data or upload) -> **2** run optimization -> **3** run simulation -> **4-6** compare, analyse, export.
Use the sidebar to switch between فارسی (RTL) and English. Regenerate the sample data with `python -m wdt.sample data/sample`.

## Input files
| File | Required columns (English or Persian aliases) |
|---|---|
| SKUs | `sku` (کد کالا), `unit_volume_m3` (حجم), `unit_weight_kg` (وزن), `stock_qty` (موجودی) or `pickface_qty`; optional `name`, `storage_class` (general/cold/hazmat - عمومی/سرد/خطرناک), `current_location` (baseline layout) |
| Orders (one row per line) | `order_id` (شماره سفارش), `sku`, `qty` (تعداد) |
| Locations (optional) | `location_id, aisle, bay, side, level, x, y, zone, volume_cap, weight_cap` - `x` = aisle centre, `y` = distance from the front cross-aisle. Without it a warehouse is generated from the parameters in the UI |

If `current_location` is missing, the baseline is a sequential first-fit put-away. Decimal separator: `.` or `٫`.

## Sample results (`data/sample`, seed 0, default settings)
Persian FMCG distributor: 464 SKUs, 720 locations (6 aisles; 1 hazmat, 1 cold), 1 500 orders / 6 610 lines.
GA: 150 generations, 2.5 s. Simulation: 8 pickers, 3 packing stations, 400 orders, identical demand (~130 orders/h, set from the baseline).

| Metric | Before | After |
|---|---|---|
| Normalised objective J (exact linear optimum: 0.548, lower bound 0.412) | 1.000 | **0.539** |
| Distance proxy Z1 | 300 510 | 175 758 (-41.5 %) |
| Co-pick affinity Z2 | 242 960 | 151 872 (-37.5 %) |
| Avg real tour distance per order | 95.9 m | 68.8 m (-28.2 %) |
| Orders per picker-hour | 18.6 | 22.4 (+20.7 %) |
| Mean wait for a picker | 1.12 min | 0.26 min |
| Picker utilisation | 90 % | 75 % |

Trade-offs shown honestly by the analysis tabs: slot fit gets 7.7 % worse (weight 0.1), and pick load concentrates in the aisles
nearest the depot (load index up to 2.3x its fair share, flagged as hot aisles).

## Project layout
```
app.py                 Streamlit UI (EN/FA)
wdt/model.py           geometry & distances      wdt/data.py       import, validation, Problem
wdt/objective.py       multi-objective model     wdt/optimize.py   min-cost flow + GA + Pareto
wdt/routing.py         TSP tours, picking time   wdt/sim.py        SimPy simulation
wdt/analysis.py        capacity/ABC/bottlenecks  wdt/pipeline.py   before/after evaluation
wdt/viz.py, report.py  Plotly figures, HTML/Excel  wdt/sample.py   deterministic sample data
tests/  data/sample/  docs/ALGORITHMS.md
```

## Using the engine from Python
```python
from wdt.data import *; from wdt.model import generate_warehouse
from wdt.optimize import optimize; from wdt.objective import Weights
wh = generate_warehouse(aisles=6, bays=20, levels=3)
skus, _ = parse_skus(load_table("skus.csv")); orders, _ = parse_orders(load_table("orders.csv"), skus)
result = optimize(build_problem(wh, skus, orders), Weights(distance=.5, affinity=.2, ergonomics=.2, slotfit=.1))
print(result.cost, result.components, result.locs)   # SKU index -> location index
```

## Limits
Dense cost matrices make ~5 000 SKUs x 5 000 locations the practical ceiling on a laptop. Single-block layout, stationary demand, no
replenishment flows - see section 9 of the algorithm document. License: MIT.

## خلاصه فارسی
ابزار آفلاین برای مدل‌سازی انبار، بهینه‌سازی چیدمان کالا (الگوریتم ژنتیک + حل دقیق با OR-Tools)، شبیه‌سازی رویدادگسسته با SimPy،
تحلیل ظرفیت و گلوگاه و خروجی گزارش فنی. رابط کاربری فارسی راست‌به‌چپ است و ستون‌های فارسی و ارقام فارسی در فایل‌های ورودی پشتیبانی می‌شوند.
اجرا: `pip install -r requirements.txt` سپس `streamlit run app.py` و انتخاب «فارسی» در نوار کناری.
