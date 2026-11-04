# MarketScout AI

Lightweight autonomous industry research agent. Enter an **industry** and a **location**; MarketScout discovers local businesses, reads their public websites, extracts services and contact details, compares competitors, flags market opportunities, and produces **JSON + Markdown reports** with source URLs for every business.

Free data only: no paid APIs, no keys. Local AI (Ollama) is optional.

## How it works

```
industry + location
   → discover   OpenStreetMap Nominatim (or your own URL list)
   → scrape     homepage + up to 2 service/about/contact pages (BeautifulSoup)
   → extract    services, emails, phones, social links, booking signals
   → analyze    table-stakes services, niche services, digital gaps, opportunities
   → report     JSON + Markdown, stored in SQLite
   → (optional) Ollama writes a short summary from the computed data only
```

| Module | Role |
|---|---|
| `app/discover.py` | Nominatim business discovery |
| `app/fetch.py` | robots.txt, SSRF guard, size/type limits |
| `app/scrape.py` | HTML parsing and extraction |
| `app/analyze.py` | Competitor analysis, opportunities |
| `app/llm.py` | Optional Ollama summary |
| `app/report.py` | Markdown rendering |
| `app/pipeline.py` / `db.py` / `main.py` | Orchestration, SQLite, API |

## Quick start

```bash
python -m venv venv && source venv/bin/activate   # Windows: venv\Scripts\activate
pip install -r requirements.txt
export CONTACT=you@example.com                    # identifies your bot (Nominatim policy)
uvicorn app.main:app --reload
```

```bash
# 1. start a run (returns immediately)
curl -X POST localhost:8000/research -H 'Content-Type: application/json' \
  -d '{"industry": "dentist", "location": "Berlin", "max_businesses": 8}'
# {"id": 1, "status": "running"}

# 2. poll, then fetch
curl localhost:8000/reports/1
curl localhost:8000/reports/1/markdown
```

### Request fields

| Field | Default | Notes |
|---|---|---|
| `industry`, `location` | required | Free text |
| `max_businesses` | 10 | 1–25 |
| `use_llm` | false | Needs Ollama (`ollama pull llama3.2`); silently skipped if unavailable |
| `urls` | none | Skip discovery and analyze these sites (useful when OSM coverage is thin) |

### Report JSON (abridged)

```json
{
  "meta": {"industry": "dentist", "location": "Berlin", "data_source": "OpenStreetMap Nominatim", "limitations": ["..."]},
  "businesses": [{"name": "...", "website": "...", "address": "...",
                  "sources": ["https://www.openstreetmap.org/node/1", "https://example.de/"],
                  "scraped": {"services": ["teeth whitening"], "emails": [], "socials": [], "booking": false}}],
  "analysis": {"confidence": "medium", "table_stakes": [], "niche_services": [],
               "digital_gaps": {"online_booking": 5, "no_website": 2},
               "opportunities": [{"type": "online_booking", "insight": "5 of 6 ...", "evidence": ["..."]}]},
  "summary": null,
  "errors": []
}
```

## Responsible use

- Respects `robots.txt`, uses an identifying User-Agent, limits pages per site, and pauses between requests.
- Blocks non-HTTP(S) and private/loopback addresses (SSRF guard).
- Follows the [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/): one request per run, no bulk use. Set `CONTACT`.
- Collects only business-level public information.

## Limitations

- OpenStreetMap coverage varies; sparse results are normal for small towns or niche industries. Use `urls` as a fallback.
- Service extraction is heuristic (page headings/lists) and may include noise. JavaScript-rendered sites are not executed.
- Opportunities are rule-based signals, not market sizing. The report states a `confidence` level based on how many sites were analyzed.
- Runs execute in-process background tasks; restarting the server abandons runs in progress.

## Tests

```bash
pip install -r requirements-dev.txt
pytest
```

Tests mock all network calls.

## License

MIT
