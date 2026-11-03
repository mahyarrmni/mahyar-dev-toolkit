# Mahyar's Python Toolkit

SEO, lead-generation, security-audit, AI and utility tools built with **Python**, **Streamlit** and **Flet**.
Persian-first user interfaces, bilingual (EN/FA) documentation. Every tool has its own README with install and step-by-step usage.

**Author:** Mahyar Momeni

[فارسی](README.fa.md) · [Architecture](docs/ARCHITECTURE.md) · [Roadmap & known limitations](docs/ROADMAP.md) · [Responsible use & disclaimer](docs/RESPONSIBLE_USE.md) · [Security policy](SECURITY.md) · [mahyarhub.ir](https://mahyarhub.ir)

![python](https://img.shields.io/badge/python-3.10%2B-blue) ![license](https://img.shields.io/badge/license-MIT-green) ![style](https://img.shields.io/badge/lint-ruff-informational)

---

## Table of contents

1. [What's inside & risk levels](#whats-inside)
2. [Repository layout](#repository-layout)
3. [Quick start](#quick-start)
4. [Configuration & secrets](#configuration--secrets)
5. [SEO tools](#seo-analyzer) — Analyzer · Audit Bot · Keyword Miner
6. [Lead-generation tools](#lead-generation-tools)
7. [Security tools](#security-tools)
8. [AI apps](#ai-apps)
9. [Utilities](#utilities) — Image Optimizer · Auto Image Downloader · FileSage
10. [Development](#development)
11. [Responsible use & disclaimer](#responsible-use)
12. [Known limitations](#known-limitations)
13. [License & contact](#license--contact)

---

## What's inside

**Risk levels:** 🟢 low (normal use) · 🟡 medium (touches third-party services, personal data, paid APIs or modifies files/data — read the tool's disclaimer) ·
🔴 high (can harm others or break the law if misused — authorised, written-permission use only).

| Group | App | Stack | Risk | Status |
|---|---|---|:-:|---|
| `seo/` | **SEO Analyzer** | Streamlit · requests · BeautifulSoup · lxml | 🟢 | Modular package, unit-tested |
| `seo/` | **SEO Audit Bot** ([guide](seo/seo_audit_bot/README.md)) | CLI · requests · BeautifulSoup | 🟢 | Site crawl → HTML + CSV report with score |
| `seo/` | **Keyword Miner** ([guide](seo/keyword_miner/README.md)) | CLI · requests | 🟡 | Autocomplete-based keyword ideas, intent & clusters |
| `leads/` | **Maps Leads Bot** ([guide](leads/maps_leads_bot/README.md)) | CLI · Google Places API (New) | 🟡 | Business lists to CSV (paid API) |
| `leads/` | **B2B Lead Intelligence** ([guide](leads/b2b_lead_intel/README.md)) | asyncio · aiohttp · SQLite | 🟡 | Contact/tech extraction, scoring, dashboard; unit + e2e tests |
| `security/` | **AI Web Vulnerability Scanner** | Streamlit · scikit-learn · requests | 🔴 | Works; ML part is a demo-grade toy model; authorization gate |
| `security/` | **Deep Scanner** | Streamlit · BeautifulSoup · sockets | 🔴 | JS analysis + port scan work; other checks are stubs |
| `ai-apps/` | **Crypto Analyzer** | Streamlit · Plotly · scikit-learn | 🟢 | Price/indicators work; on-chain panel is demo data; not financial advice |
| `ai-apps/` | **Decor AI – Image Redesign** | Flet · Replicate API | 🟡 | Needs your own Replicate token (paid per run) |
| `ai-apps/` | **Decor AI – Chat** | Flet · DuckDuckGo search | 🟢 | Working |
| `ai-apps/` | **Decor AI – Local LLM chat** | Flet · Transformers (GPT-2 fa) | 🟢 | Large model download on first run |
| `utilities/` | **Image Optimizer** | Streamlit · Pillow | 🟢 | Working (no background removal) |
| `utilities/` | **Auto Image Downloader** | Streamlit · BeautifulSoup | 🟡 | Working (scrapes Unsplash HTML; see licensing note) |
| `utilities/` | **FileSage** ([guide](utilities/filesage/README.md)) | CLI · Pillow | 🟢 | Organise files, find duplicates / similar images, full undo |

Related repositories (WordPress plugins, each with its own README and risk level) are listed on the
[author's profile](https://github.com/mahyarrmni).

Every app is a standalone script you can run on its own; the SEO Analyzer is
additionally a small importable package (`from seo_analyzer import SEOAnalyzer`).

## Repository layout

```
.
├── seo/
│   ├── requirements.txt
│   ├── seo_audit_bot/          # site crawler → HTML/CSV report
│   ├── keyword_miner/          # autocomplete keyword ideas
│   └── seo_analyzer/
│       ├── app.py              # Streamlit UI entry point
│       ├── analyzer.py         # SEOAnalyzer: fetch + orchestration
│       ├── scoring.py          # 0–100 score
│       ├── report_view.py      # report rendering (Streamlit)
│       ├── constants.py        # stop-words, request headers
│       └── checks/             # one mixin per concern
│           ├── meta.py         # title, description, https, lang, favicon, canonical, viewport
│           ├── structure.py    # headings, images, links, anchors, H1 position, duplicate ALT
│           ├── content.py      # word count, keywords, text/HTML ratio
│           ├── technical.py    # robots.txt, sitemap, Open Graph, Twitter cards, schema.org
│           └── elements.py     # tables, lists, iframes, video/audio, infographics
├── leads/                      # lead-generation tools
│   ├── maps_leads_bot/         #   Google Places API → CSV
│   └── b2b_lead_intel/         #   async crawler + scoring + SQLite + HTML dashboard
├── security/                   # vulnerability scanners
├── ai-apps/                    # crypto analysis + Decor AI apps
├── utilities/                  # image tools + FileSage
├── wordpress-plugins/          # plugin header fixer + repo templates (gitignore, phpcs, CI)
├── tests/                      # offline pytest suite
├── docs/                       # architecture, roadmap, responsible use, migration notes
├── .github/workflows/ci.yml    # ruff + compile check + pytest on 3.10 / 3.12
└── pyproject.toml              # ruff + pytest config
```

## Quick start

```bash
git clone https://github.com/mahyarrmni/python-toolkit.git
cd python-toolkit

python -m venv .venv
source .venv/bin/activate            # Windows: .venv\Scripts\activate

# install only what you need
pip install -r seo/requirements.txt
# or everything:
pip install -r requirements.txt

streamlit run seo/seo_analyzer/app.py
```

Run any other app the same way:

```bash
streamlit run security/ai_web_vulnerability_scanner.py
streamlit run utilities/image_optimizer.py
streamlit run ai-apps/crypto_analyzer.py
python ai-apps/decor_ai_chat.py          # Flet desktop app
```

Command-line tools:

```bash
python seo/seo_audit_bot/seo_audit.py https://yoursite.com --max 50
python seo/keyword_miner/keyword_miner.py "coffee" --lang en
python leads/maps_leads_bot/maps_leads.py "clothing store" --city tehran --grid 2
(cd leads/b2b_lead_intel && python -m b2b_intel run domains.txt)
python utilities/filesage/filesage.py scan ~/Downloads
```

Python 3.10 or newer is required (the individual CLI tools run on 3.8/3.9 as noted in their READMEs).

## Configuration & secrets

Secrets are read from environment variables — never from source code.
Copy `.env.example` to `.env` (git-ignored) or export the variables in your shell.

| Variable | Used by | Required |
|---|---|---|
| `REPLICATE_API_TOKEN` | `ai-apps/decor_ai_image.py` | Yes, for that app |
| `NEWSAPI_KEY` | `ai-apps/crypto_analyzer.py` | Optional — without it the news panel is empty |
| `GOOGLE_API_KEY` | `leads/maps_leads_bot` | Yes, for that tool (or pass `--key`) |
| `SEO_ANALYZER_LOGO_URL` | SEO Analyzer header | Optional |

> Streamlit does not load `.env` by itself. Use `export $(grep -v '^#' .env | xargs)` on Linux/macOS,
> or Streamlit's own `.streamlit/secrets.toml` (also git-ignored) if you prefer.

---

## SEO Analyzer

Analyzes the **on-page SEO** of one URL, or compares two URLs side by side.
Persian and English content are both supported (stop-word lists, RTL layout).

```bash
streamlit run seo/seo_analyzer/app.py
```

**How to use:** open the local URL Streamlit prints → choose *single URL* or *compare two URLs* → paste the page address(es) with `https://` →
press the analyze button → read the score, then work through the *high-priority* suggestions first.

### What it checks

| Area | Checks |
|---|---|
| **Meta** | `<title>` presence and length (10–65 chars), meta description (70–160), HTTPS, `lang` attribute, favicon, canonical tag (and whether it matches the final URL), viewport tag, duplicate title/description tags |
| **Structure** | H1–H6 counts, single-H1 rule, H1 position, images and ALT coverage, duplicate ALT texts, internal / external / `nofollow`·`sponsored`·`ugc` links, empty or `#` anchors |
| **Content** | Word count (flags < 300 and > 4000), top single keywords, top two-word phrases, emphasized keywords (`strong`/`em`), text-to-HTML ratio |
| **Technical** | `robots.txt` presence, sitemap discovery (including the `Sitemap:` directive), Open Graph tags, Twitter Cards, Schema.org JSON-LD (types and parsing errors) |
| **Elements** | Tables, lists, iframes, video/audio, infographic detection |

Suggestions are grouped into **high priority**, **medium priority** and **positive notes**.
A failure in one check never aborts the others: each check runs inside `_run_analysis_safe`,
which records the error and continues.

### Scoring

The score is the share of points earned out of **86 possible**, scaled to 0–100.

| Category | Max | Category | Max |
|---|:-:|---|:-:|
| Title | 8 | HTTPS | 5 |
| Meta description | 8 | Text/HTML ratio | 3 |
| Headings | 10 | Social & schema | 8 |
| Images | 6 | Canonical & viewport | 6 |
| Word count | 5 | Cleanliness (dupes, empty anchors) | 6 |
| Links | 6 | Basic setup (favicon, lang) | 3 |
| Technical files | 6 | Content elements | 6 |

The score is a **heuristic**, not a ranking prediction. Use it to spot regressions between
two versions of a page, not as an absolute truth.

### Use it as a library

```python
from seo_analyzer import SEOAnalyzer

a = SEOAnalyzer("https://example.com")
if a.fetch():
    a.analyze()
    report, suggestions = a.get_report()
    print(report["seo_score"], len(suggestions))
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for how to add a new check (about ten lines).

---

### SEO Audit Bot — `seo/seo_audit_bot/`

Crawls a whole site (same host), scores every page, finds broken links and writes an HTML report + CSV.
`python seo_audit.py https://yoursite.com --max 100 -o report.html`. Full guide: [seo/seo_audit_bot/README.md](seo/seo_audit_bot/README.md) ·
[sample report](seo/seo_audit_bot/examples/sample-report.html).

### Keyword Miner — `seo/keyword_miner/`

Seed word → hundreds of autocomplete-based ideas with intent tags and clusters, in an interactive HTML report + CSV.
`python keyword_miner.py "coffee" --lang en --deep -o coffee.html`. Full guide: [seo/keyword_miner/README.md](seo/keyword_miner/README.md).

---

## Lead-generation tools

> 🟡 These tools produce **business contact data**. Collecting, storing and — above all — *using* it is regulated (GDPR/ePrivacy, anti-spam and
> e-commerce laws, platform terms). Read the tool's *Responsible use* section before running anything at scale.

| Tool | What it does | Guide |
|---|---|---|
| **Maps Leads Bot** | Official Google Places API (New): businesses by category and city → CSV (name, phone, website, rating…). Billed by Google | [leads/maps_leads_bot](leads/maps_leads_bot/README.md) |
| **B2B Lead Intelligence** | From a list of domains: public e-mails, phones, social profiles, technologies → scored A–D leads in SQLite + HTML dashboard. Obeys robots.txt by default | [leads/b2b_lead_intel](leads/b2b_lead_intel/README.md) |

---

## Security tools

> **Only scan systems you own or have written permission to test.**
> Both apps keep the *Start scan* button disabled until you confirm authorization.
> Read [docs/RESPONSIBLE_USE.md](docs/RESPONSIBLE_USE.md).

### AI Web Vulnerability Scanner — `security/ai_web_vulnerability_scanner.py`

| Module | What it really does |
|---|---|
| CVE lookup | Offline, domain-keyword lookup against a small built-in table |
| Form tester | Submits two probe payloads (an XSS string and a SQLi string) to every form it finds, then runs a Naive-Bayes classifier over the **response text**. The classifier is trained on only five toy samples, so treat this as a **demonstration of the idea**, not a reliable detector |
| CSRF audit | Flags forms that have no input whose name contains `csrf` |
| Framework fingerprint | Header and page-content heuristics (WordPress detection) |
| Open redirect & clickjacking | Probes `/redirect?next=…` and checks whether the `X-Frame-Options` header is set |

Reports can be saved to a JSON file. All HTTP calls use a 10-second timeout.

**How to use:** `streamlit run security/ai_web_vulnerability_scanner.py` → enter the target URL → tick the checks you want →
tick the **authorization** checkbox (confirms you own the target or have written permission) → press *🚀 شروع اسکن* (start scan).
Start with a staging copy of your own site.

### Deep Scanner — `security/deep_scanner.py`

| Module | Status |
|---|---|
| JavaScript analysis (`eval`, `innerHTML`, session/cookie access patterns) | ✅ implemented |
| Common-port scan (21, 22, 23, 25, 53, 80, 110, 143, 443, 3306, 3389) | ✅ implemented |
| CVE lookup | ✅ simple domain-keyword table |
| Form ML / CSRF / redirect-clickjacking | ⏳ **not implemented** — they now report "not implemented" instead of fabricating findings; use the scanner above for those |
| Framework detection | ⚠️ domain-name heuristic only |

**How to use:** `streamlit run security/deep_scanner.py` → same flow as above; the extra checkbox enables the port scan.

Neither tool replaces a professional penetration test or a maintained scanner such as
OWASP ZAP. Treat the output as a starting point for manual review.

---

## AI apps

### Crypto Analyzer — `ai-apps/crypto_analyzer.py`

- 90-day price history from CoinGecko, drawn with Plotly
- Indicators: SMA-14, EMA-14, RSI-14, momentum
- A **5-day linear-regression trend line**. This is a statistical extrapolation of recent
  prices, *not* a prediction and *not* financial advice.
- News from NewsAPI (needs `NEWSAPI_KEY`), translated to Persian with `googletrans`
- The **on-chain panel shows fixed demo values**; it is labelled as such in the UI and tracked in the roadmap

**How to use:** `streamlit run ai-apps/crypto_analyzer.py` → pick a coin from the list → read the chart, indicators and trend line. Optional: `export NEWSAPI_KEY=...` for the news panel. Not financial advice.

### Decor AI — Image Redesign — `ai-apps/decor_ai_image.py`

Flet desktop app. Sends a room photo to a Replicate image-to-image model and shows the
original next to the redesigned result. Requires `REPLICATE_API_TOKEN`; Replicate bills per run.

**How to use:** `export REPLICATE_API_TOKEN=...` → `python ai-apps/decor_ai_image.py` → press *📂 انتخاب تصویر* and choose a room photo → wait for the result.

### Decor AI — Chat — `ai-apps/decor_ai_chat.py`

Interior-design assistant. It first checks the question against a list of Persian design keywords
(it refuses off-topic questions), then runs a DuckDuckGo text search and shows the top three results in the Flet UI.

**How to use:** `python ai-apps/decor_ai_chat.py` → type a Persian interior-design question and send.

### Decor AI — Local LLM — `ai-apps/decor_ai_llm.py`

The same chat idea, but answers come from a locally running Persian GPT-2
(`HooshvareLab/gpt2-fa`). Install `transformers` and `torch` first (see
`ai-apps/requirements.txt`); the model is downloaded on first launch. Quality is that of a small
GPT-2 model — good for demos, not for reliable design advice.

**How to use:** `pip install transformers torch` → `python ai-apps/decor_ai_llm.py` → wait for the first-run model download → chat.

---

## Utilities

### Image Optimizer — `utilities/image_optimizer.py`

Batch-process up to 20 images: convert format, resize, compress, sharpen / boost colour, add a text or
logo watermark, fetch images straight from a page URL, and download everything as a ZIP.
Background removal is **not** implemented (the option was advertised in the old UI text and has been removed).

**How to use:** `streamlit run utilities/image_optimizer.py` → either paste a page address and press *📥 دریافت تصاویر*, or upload up to 20 images →
choose output format, optional manual size, quality slider (default 85), sharpen / colour boost → optionally enable the watermark (text or logo; the
text box is empty by default) → press *🚀 انجام بهینه‌سازی* → download single files or the ZIP.

### Auto Image Downloader — `utilities/auto_image_downloader.py`

Downloads *N* images per keyword from Unsplash into one folder per keyword, with a 1-second delay
between requests. It parses Unsplash's public HTML, which can break at any time and may conflict
with their terms; the official [Unsplash API](https://unsplash.com/developers) is the recommended
replacement (see roadmap). Always respect each photo's license and attribution requirements.

**How to use:** `streamlit run utilities/auto_image_downloader.py` → one keyword per line → choose images per keyword (1–50) → press *🚀 شروع دانلود*.
Downloaded photos stay subject to their own licenses.

### FileSage — `utilities/filesage/`

Organise a messy folder by type/date, find exact duplicates and similar images, build an HTML report, and undo everything. Dry-run by default, never deletes.
`python filesage.py scan ~/Downloads -o report.html` → `organize ... --by type` → add `--apply`. Full guide: [utilities/filesage/README.md](utilities/filesage/README.md) ·
[sample report](utilities/filesage/examples/sample-report.html).

---

## Development

```bash
pip install -r requirements-dev.txt

ruff check .          # lint (imports, unused code, bugbear)
pytest -q             # offline tests — no network needed
```

Continuous integration (`.github/workflows/ci.yml`) runs lint, byte-compiles every app and runs the
tests on Python 3.10 and 3.12 for each push and pull request.

Conventions:

- LF line endings (enforced by `.gitattributes`), UTF-8, 4-space indent (`.editorconfig`)
- No secrets in code — read them from environment variables
- New SEO checks need a test in `tests/`

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Responsible use

These tools make network requests on your behalf and some handle contact data or modify files. **You alone are responsible for how you use
them.** Use them only against sites, systems and data you own or are explicitly authorised to work with, respect `robots.txt` and rate limits,
and comply with the laws of your jurisdiction and the terms of every third-party service you call.

The software is provided **"as is", without warranty**. The author (Mahyar Momeni) and contributors accept **no liability** for damage, data
loss, penalties, bans, legal claims or fines arising from its use. Full text, per-tool risk notes and the list of tools deliberately not
published here: [docs/RESPONSIBLE_USE.md](docs/RESPONSIBLE_USE.md).

## Known limitations

A short, honest list (full version in [docs/ROADMAP.md](docs/ROADMAP.md)):

- Streamlit apps are single-user, session-scoped tools — not hardened multi-tenant services
- Deep Scanner has three stubbed modules (reported as "not implemented"), and the ML form tester in the other scanner is trained on five samples
- Crypto on-chain panel is demo data
- Several legacy scripts still use bare `except:`; cleanup is planned
- The SEO score is a heuristic and ignores page speed and real backlink data
- `seo_audit_bot` does not read robots.txt for crawl decisions and has no per-host delay; `keyword_miner` relies on unofficial endpoints that can change
- The newly imported tools (`seo_audit_bot`, `keyword_miner`, `leads/*`, `filesage`) are not yet covered by the ruff lint step; `b2b_lead_intel` has its own tests

## License & contact

[MIT](LICENSE) © Mahyar Momeni

**Creator & maintainer:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com)
