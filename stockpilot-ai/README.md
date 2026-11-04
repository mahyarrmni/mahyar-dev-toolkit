# StockPilot AI

Lightweight, fully local **warehouse intelligence** for B2B teams. Import an Excel/CSV inventory file and get stock analysis, ABC classification, low-stock alerts, dead-stock detection, warehouse capacity analysis, and natural-language reports (Persian) with optional local AI.

**Persian-first:** RTL interface, Persian/English column headers, Persian digits (۱۲۳) and Jalali dates (1403/05/12) are understood on import. No paid APIs, no cloud, data stays in a local SQLite file.

Stack: Python · Streamlit · Pandas · SQLite · Plotly · optional Ollama.

## Features

| Feature | What you get |
|---|---|
| Import | `.xlsx` / `.csv`, validation with clear warnings, duplicate and invalid-row handling |
| ABC | Pareto chart and class per SKU (A/B thresholds adjustable, default 80/95%) |
| Low stock | Reorder point, severity (out / critical / low), suggested order quantity |
| Dead stock | Idle-days threshold, tied-up capital by category, slow-moving list |
| Capacity | Used vs. available m³ per warehouse, editable capacities |
| Reports | Deterministic Persian summary (works offline) + optional Ollama report and Q&A |

## Quick start

```bash
python -m venv venv && source venv/bin/activate     # Windows: venv\Scripts\activate
pip install -r requirements.txt
streamlit run app.py
```

Click **«بارگذاری داده نمونه»** in the sidebar, or upload `sample_data/sample_inventory.xlsx`.

Optional AI: install [Ollama](https://ollama.com), run `ollama pull llama3.2`, then use the buttons in the last tab. Everything else works without it.

## Input format

Headers may be English or Persian (case/space/ZWNJ-insensitive).

| Column | Persian header | Required | Default |
|---|---|---|---|
| `sku` | کد کالا | yes | |
| `name` | نام کالا | yes | |
| `quantity` | موجودی | yes | |
| `unit_cost` | بهای واحد | yes | |
| `category` | گروه | | بدون گروه |
| `warehouse` | انبار | | انبار اصلی |
| `monthly_demand` | فروش ماهانه | | 0 |
| `last_movement` | آخرین گردش | | unknown |
| `lead_time_days` | زمان تامین | | 14 |
| `unit_volume_m3` | حجم واحد | | 0 |

Excel files may include a second sheet named `انبارها` / `warehouses` with `انبار` and `ظرفیت` (m³) columns. Otherwise enter capacities in the sidebar. Rows are one SKU per warehouse.

## Methodology

| Metric | Definition |
|---|---|
| Stock value | `quantity × unit_cost` |
| Daily demand | `monthly_demand / 30` |
| Days of cover | `quantity / daily demand` (empty when demand is 0) |
| ABC | Per SKU (summed across warehouses) by annual consumption value `monthly_demand × 12 × unit_cost`. Sorted descending; an item is **A** if the cumulative share *before* it is < 80%, **B** if < 95%, else **C**. Zero-value items are C. |
| Reorder point | `daily demand × (lead time + safety days)` |
| Low-stock status | **out**: qty = 0 · **critical**: qty < `daily demand × lead time` · **low**: qty ≤ reorder point |
| Suggested order | `ceil(max(0, daily demand × (lead + safety + target cover) − qty))` |
| Dead stock | qty > 0 and idle days ≥ threshold (default 180); if last movement is unknown, demand must be 0 |
| Slow-moving | demand > 0, days of cover > 365, not dead |
| Capacity | `Σ(quantity × unit_volume_m3)` vs. capacity: **high** ≥ 85%, **over** > 100%, **low** < 40% |

## Project structure

```
app.py                 Streamlit UI (RTL, Persian)
stockpilot/
  data.py              import, normalization, Persian digits, Jalali dates
  analysis.py          ABC, low stock, dead stock, capacity (pure functions)
  report.py            Persian report template + Ollama client
  charts.py            Plotly figures
  db.py                SQLite persistence
  sample.py            realistic sample generator (python -m stockpilot.sample)
sample_data/           ready-to-import xlsx/csv
tests/                 pytest suite (calculations, import, DB, UI smoke test)
```

## Configuration

Environment variables (see `.env.example`): `STOCKPILOT_DB`, `OLLAMA_URL`, `OLLAMA_MODEL`, `CURRENCY`.

## Tests

```bash
pip install -r requirements-dev.txt
pytest
```

## Limitations

- Demand is a single monthly average; seasonality and forecasting are out of scope.
- Capacity uses volume only (no weight, racking, or pallet positions).
- The AI report only rephrases computed figures; verify before acting on it.
- Dates in sample files are relative to their generation date; regenerate with `python -m stockpilot.sample`.

## License

MIT
