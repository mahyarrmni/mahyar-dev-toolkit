# ASIGE — Autonomous SEO Intelligence & Growth Engine

An open, self-hostable SEO research platform: **crawl → technical audit → semantic analysis → search-performance modelling → prioritized, explainable recommendations → dependency-aware roadmap → reports**. No paid APIs. Persian and English supported throughout.

Every finding is computed by a real algorithm from your crawl and Search Console data, and every recommendation carries **provenance** (module, algorithm, parameters, metrics, evidence URLs). Runs are **reproducible**: same stored inputs → same output hash. No LLM generates findings; an optional local Ollama model can only *reword* the roadmap summary.

```
python demo.py        # crawls a synthetic bilingual site, runs everything, writes demo_out/*.md  (no network)
```
Sample output: [`docs/sample_strategic_report.md`](docs/sample_strategic_report.md), [`docs/sample_technical_report.md`](docs/sample_technical_report.md) · Design: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) · Measurements: [`docs/BENCHMARKS.md`](docs/BENCHMARKS.md)

## Status of the 13 requested modules

| # | Module | Status | Notes / honest limits |
|---|---|---|---|
| 1 | Crawler | **Done, tested** | Own async crawler (httpx) instead of Scrapy: robots.txt, sitemaps, redirect chains/loops, canonicals, metadata, hreflang, JSON-LD, budgets (pages/depth/time/bytes), per-host delay, SSRF guard. Playwright rendering is an optional hook (`render=true`) — **not tested here** (no browser in sandbox). |
| 2 | Technical SEO | **Done, tested** | 42 rules: indexability, canonicals, redirects, broken links, exact + near duplicates (SimHash/LSH), pagination, structured data (subset validator for common schema.org types, not Google's full rules), hreflang reciprocity, orphans, click depth, health score with a published formula. |
| 3 | Semantic engine | **Done; SBERT untested** | Default: TF-IDF + LSA (offline, deterministic). `ASIGE_EMBEDDINGS=sbert` uses Sentence-Transformers (multilingual) — code path **not run here** (model download unavailable). Entities are **heuristic** (schema.org names, proper-noun runs, NPMI collocations); there is no pretrained NER. |
| 4 | Intent + keyword→page mapping | **Done, tested** | Transparent EN/FA lexicon classifier (ambiguous terms like "price" can misfire; confidence is reported). Mapping = 0.7 semantic + 0.2 intent match + 0.1 current-ranker evidence. |
| 5 | Topical authority, gaps, clusters | **Done, tested** | Authority = weighted mean of coverage/depth/cohesion/performance (shown per topic). Gaps need query demand (GSC or a keyword list). |
| 6 | Cannibalization | **Done, tested** | GSC query→pages share dispersion + semantic + URL similarity; crawl-only fallback (lower confidence). |
| 7 | Internal links | **Done, tested** | PageRank, depth, orphans; suggestions with anchor text and placement; **simulation** recomputes PageRank/depth with the new edges. |
| 8 | Competitors / SERP | **Partial** | Competitor *crawl* topic-gap analysis and share-of-voice/keyword-gap from a **SERP export you supply**. No scraping of Google result pages. |
| 9 | Search Console | **Done; live API untested** | CSV/XLSX import (EN/FA headers, Persian digits). API client with pagination/retry, token request-scoped — verified against a mocked transport only; OAuth flow is not included (supply an access token). |
| 10 | Predictive analytics | **Done, tested** | Monotone CTR curve (isotonic), k-fold error vs baselines, GBM + permutation importance as explainer, bootstrap-interval scenarios. Falls back to a labelled **generic prior** when GSC data is thin. **No traffic time-series forecasting** (needs daily history; not implemented). XGBoost not used (sklearn HistGradientBoosting instead). |
| 11 | Recommendation engine | **Done, tested** | impact × confidence ÷ (hours+1), explicit dependencies (acyclic), provenance on every item. |
| 12 | Roadmap "agent" | **Done, tested** | Deterministic capacity- and dependency-aware planner with KPIs and verification steps. It plans; it does **not** autonomously change your site. |
| 13 | Reports | **Done, tested** | Technical + strategic Markdown, plus full JSON per analysis. |
| — | UI | **Builds, type-checks** | React + TS + Plotly (Setup, Overview, Recommendations w/ provenance, Opportunities, Link graph, Roadmap Gantt, Reports). Not browser-tested here. |
| — | Docker / Postgres / Redis / Celery | **Written, not run** | `docker-compose.yml` and the Celery task path are untested in this environment (no Docker/Redis/Postgres). SQLite + in-process jobs are what the tests cover. |

## Quick start

```bash
pip install -r requirements-dev.txt
pytest                                    # 43 tests
uvicorn asige.api:app --reload            # API on :8000 (SQLite, in-process jobs)
cd frontend && npm install && npm run dev # UI on :5173 (proxies /api)
```

Docker (Postgres + Redis + Celery worker + UI on :8080): `cp .env.example .env`, edit, `docker compose up --build`.

```bash
curl -X POST localhost:8000/projects -H 'Content-Type: application/json' -d '{"name":"mysite","site_url":"https://example.com"}'
curl -X POST localhost:8000/projects/1/crawls -H 'Content-Type: application/json' -d '{"max_pages":500,"delay":0.25}'
curl -F file=@gsc_export.csv localhost:8000/projects/1/gsc/upload          # Search Console → Performance → Export
curl -X POST localhost:8000/crawls/1/analyze -H 'Content-Type: application/json' -d '{"brand_terms":["mybrand"]}'
curl localhost:8000/crawls/1/analysis/recommendations
curl localhost:8000/crawls/1/report/strategic
```

Analyses: `technical semantic topical cannibalization linking predictive competitor recommendations roadmap meta report_technical report_strategic status`.

## Reading a recommendation

```jsonc
{ "id": "R-1f3a…", "type": "cannibalization", "title": "Cannibalization: canonical tags, …",
  "impact": {"score": 91.3, "est_clicks_per_period": 89.2, "basis": "ctr_curve_model"},
  "confidence": {"score": 0.95, "reasons": ["dispersion 0.99", "similarity 0.97", "2 queries affected"]},
  "effort": {"hours": 6.0}, "dependencies": [], "priority": 12.39,
  "provenance": [{"module": "cannibalization", "algorithm": "gsc_share_dispersion+embedding_similarity",
                  "source": "gsc+crawl", "metrics": {…}, "evidence_urls": […], "params": {…}, "engine_version": "0.1.0"}] }
```
`impact.basis` tells you whether the number comes from the click model or a severity heuristic — do not compare them blindly. Click estimates rely on stated assumptions (e.g. 50% of a CTR gap is recoverable) recorded in provenance. `est_clicks_sum` in the roadmap is an upper bound because items overlap.

## Persian support
Normalization (ی/ک, Arabic/Persian digits, ZWNJ, tatweel), Persian stopwords and light plural stripping, Persian intent lexicon, collocation-based phrases, Persian GSC headers/digits, RTL-safe UI cells. LSA embeddings are monolingual per script (Persian and English pages don't match each other); use the SBERT backend for cross-lingual similarity.

## Security & respectful crawling
robots.txt always honoured via the API (5xx ⇒ disallow), identifying User-Agent (set `ASIGE_USER_AGENT` with a contact), per-host delay + `Crawl-delay`, byte/page/time budgets, SSRF guard on every redirect hop, optional `X-API-Key`, CORS allow-list, GSC tokens never persisted/logged. Crawl only sites you are authorised to crawl.

## Known limitations
* Duplicate/quality rules (thin content, title length) are proxies; verify before acting.
* Real crawl speed is limited by politeness (default ≤ 4 pages/s/host), not CPU — see benchmarks.
* No authentication/multi-tenancy beyond a shared API key. No backlink data (needs a licensed source).
* LSA/TF-IDF suits up to roughly 50k pages; beyond that use SBERT with an ANN index (not included).
* The UI and Docker deployment have not been exercised end-to-end in the build environment.

MIT licensed.
