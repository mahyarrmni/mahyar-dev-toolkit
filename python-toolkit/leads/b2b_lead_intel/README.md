# B2B Lead Intelligence Engine

[فارسی](README.fa.md) · part of [Mahyar's Python Toolkit](../../README.md) · v1.0.0

Takes a list of company domains and, for each, extracts **publicly published contact details, social profiles and site
technologies**, **scores and grades the lead (A–D)**, suggests a **sales angle** (e.g. "no analytics detected → GA4 opportunity")
and stores everything in **SQLite**, with an interactive **HTML dashboard**, **CSV** and **JSON** export.
No API key, free, resumable, respects `robots.txt` by default.

| | |
|---|---|
| **Risk level** | 🟡 Medium — scraping and contact data are regulated (GDPR/ePrivacy, anti-spam, site terms) |
| **Needs** | Python 3.9+, `aiohttp`, `beautifulsoup4`, `lxml` |
| **Output** | `leads.db`, `leads-report.html`, CSV/JSON |
| **Tests** | `python -m unittest discover -s tests -t .` (needs `aiohttp` for the end-to-end tests) |

## Features

- Async crawler: bounded concurrency, polite per-host delay, exponential back-off retries, `Retry-After` support, download size cap.
- Obeys **robots.txt** (on by default).
- **E-mail** extraction from `mailto:`, text and obfuscated forms (`name [at] site [dot] com`), Cloudflare e-mail decoding;
  separates generic (info/sales) from personal addresses and same-domain from other domains.
- **Phone** extraction from `tel:`, text and WhatsApp links; normalisation to international format; Persian/Arabic digits; Iranian mobile & landline.
- **Social links:** LinkedIn, Instagram, Telegram, X/Twitter, Facebook, YouTube, WhatsApp, GitHub, Aparat (share links filtered out).
- **JSON-LD** parsing (company name, address, employee count, `sameAs`).
- **30+ technologies** (WordPress, Shopify, GA4, GTM, Meta Pixel, HubSpot, Cloudflare, React …) from HTML and headers.
- Finds contact/about pages automatically (Persian and English).
- **Transparent, configurable scoring** with reasons shown per lead.
- Every result is saved immediately → Ctrl+C loses nothing; re-running resumes.

## Install

```bash
cd leads/b2b_lead_intel
python -m venv .venv && source .venv/bin/activate     # Windows: .venv\Scripts\activate
pip install -r requirements.txt
python -m unittest discover -s tests -t .             # expect OK
```

## Usage

**1. Prepare a domain list** (`domains.txt`; one domain/URL per line; CSV also works; `#` lines ignored; duplicates removed). See `domains.example.txt`.

**2. Run**
```bash
python -m b2b_intel run domains.txt
python -m b2b_intel run --domains acme.com beta.io          # without a file
```

**3. Report & export**
```bash
python -m b2b_intel report -o report.html                   # interactive dashboard
python -m b2b_intel export --format csv --min-grade B -o hot-leads.csv
python -m b2b_intel stats
```
Open `report.html` in a browser; click a row for details and the suggested sales angle.

### `run` options

| Option | Default | Meaning |
|---|:-:|---|
| `--db` | `leads.db` | SQLite path |
| `--concurrency` | 10 | Domains processed in parallel |
| `--delay` | 1.0 | Seconds between requests to the **same host** |
| `--timeout` | 15 | Seconds per request |
| `--max-pages` | 4 | Homepage + contact/about pages |
| `--force` | off | Re-crawl domains already stored |
| `--weights FILE` | — | JSON overriding score weights (`weights.example.json`) |
| `--ignore-robots` | off | **Not recommended** — turns robots.txt compliance off |

`report` also accepts `--min-score N`; `export` accepts `--format csv|json`, `--min-grade A|B|C|D`, `-o FILE`.

## Scoring

Final score = share of satisfied weights (0–100): same-domain e-mail 22 · personal e-mail 10 · any e-mail 6 · phone 14 · mobile 6 · LinkedIn 10 ·
2+ social networks 6 · address 4 · description 3 · online shop 8 · marketing/CRM tools 8 · company size 3.
Grades: **A ≥ 70 · B ≥ 50 · C ≥ 30 · D** below. Override with a JSON file, e.g. `{"has_domain_email": 30, "has_linkedin": 15}`.

## Architecture

```
b2b_intel/
├── cli.py         command line (run / report / export / stats)
├── pipeline.py    input → async crawl → merge → score → store (queue + worker pool)
├── fetcher.py     HTTP layer: semaphore, per-host throttle, robots cache, retry, size cap
├── extractors.py  pure functions (no I/O) for e-mail/phone/social/JSON-LD/contact links
├── techdetect.py  regex signatures for technology detection (HTML + headers)
├── scoring.py     scoring rules + sales angles
├── storage.py     SQLite (WAL, upsert, indexes)
├── report.py      self-contained HTML dashboard
├── models.py      dataclasses
└── config.py      immutable Config
tests/             unit tests + end-to-end test against a local aiohttp server
```

## Troubleshooting

| Problem | Fix |
|---|---|
| `ModuleNotFoundError` | `pip install -r requirements.txt` in the active venv |
| Many `unreachable` | Check internet/VPN; try `--timeout 30 --concurrency 5` |
| `blocked` status | The site's robots.txt disallows bots — respect it |
| No e-mail/phone found | JavaScript-rendered site, or details intentionally hidden |
| Start over | Delete `leads.db` or use `--force` |
| Persian broken in Excel | Import CSV with Data → From Text/CSV, UTF-8 |

## Limits

JavaScript-only (SPA) content is not seen; the tool does not discover companies (you supply the list); e-mails are *publicly published addresses* and are not SMTP-verified.

## Ethics & law (important)

- Collect **business contact details that organisations publish themselves**, not private data about individuals.
- Keep the defaults (robots.txt, polite delay) and respect each site's terms.
- Sending bulk marketing e-mail/SMS without a lawful basis is prohibited under GDPR, CAN-SPAM and many local laws: personalise, identify yourself,
  always include an opt-out, and keep records. Store `leads.db` securely and delete data you no longer need.

## ⚠️ Disclaimer

This software is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · [MIT](../../LICENSE)
