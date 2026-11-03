# Maps Leads Bot

[فارسی](README.fa.md) · part of [Mahyar's Python Toolkit](../../README.md)

Finds businesses of a given category in a city using the **official Google Places API (New)** and saves
**name, phone, address, website, rating, review count and Maps link** to a CSV that opens in Excel.
Example: "clothing store" + "boutique" in Tehran.

| | |
|---|---|
| **Risk level** | 🟡 Medium — paid API, data-use terms, and marketing/privacy laws apply to what you do with the output |
| **Needs** | Python 3.8+, `requests`, a Google Cloud project with **billing** and **Places API (New)** enabled |
| **Output** | CSV (UTF-8 with BOM) |
| **Cost** | Each request is billed by Google. Phone numbers belong to a pricier field tier. Read Google's current pricing |

## 1. Get an API key (one time)

1. Open <https://console.cloud.google.com> and sign in.
2. Create a project (e.g. `maps-leads`) and make sure it is selected.
3. **Billing** → create/attach a billing account (the API fails without it).
4. **APIs & Services → Library** → search **Places API (New)** → **Enable** (the "New" one, not the legacy API).
5. **Credentials → Create credentials → API key**.
6. **Restrict the key** (strongly recommended): API restrictions → *Restrict key* → only **Places API (New)**.
7. **Budgets & alerts**: set a budget alert *before* a big run.
8. Never publish the key (no GitHub, no screenshots).

## 2. Install

```bash
cd leads/maps_leads_bot
pip install -r requirements.txt
```

## 3. Provide the key

Preferred (keeps it out of shell history):
```bash
export GOOGLE_API_KEY="your-key"          # Windows CMD: set GOOGLE_API_KEY=your-key
                                          # PowerShell:  $env:GOOGLE_API_KEY="your-key"
```
or pass `--key "your-key"` (convenient, but visible in your shell history).

## 4. Run

```bash
# cheap test: 2x2 = 4 tiles
python maps_leads.py "clothing store" --city tehran --grid 2 -o test.csv

# fuller run, several categories, only businesses that list a phone
python maps_leads.py "clothing store" "boutique" --city tehran --grid 5 --with-phone-only -o tehran_clothes.csv
```

| Option | Default | Meaning |
|---|:-:|---|
| `queries` | — | One or more business categories (quote each) |
| `--city` | `tehran` | `tehran`, `mashhad`, `isfahan`, `shiraz`, `tabriz` |
| `--grid N` | 4 | City bounding box is split into N×N tiles. Bigger = more results, more requests, more cost. Suggested 4–8 |
| `--with-phone-only` | off | Drop businesses without a phone number |
| `--lang` | `fa` | Language of results |
| `--key` | env var | API key (or `GOOGLE_API_KEY`) |
| `-o` | `leads.csv` | Output CSV |

Requests ≈ `N × N × number_of_categories × up to 3 pages`. Google returns at most 60 results per query, which is why the city is tiled.

**CSV columns:** `name, phone, address, website, rating, reviews, maps_url`. Duplicates (same place id) are removed.

## 5. Add a city

Edit the `CITIES` dict in `maps_leads.py` (south, west, north, east in decimal degrees; right-click a point in Google Maps to copy coordinates):
```python
"karaj": (35.75, 50.85, 35.90, 51.05),
```
then use `--city karaj`.

## 6. Troubleshooting

| Error | Cause / fix |
|---|---|
| `API key not set` | Use `--key` or set `GOOGLE_API_KEY` in the *same* terminal window |
| `API error 403` / `PERMISSION_DENIED` | API not enabled, billing missing, or key restrictions wrong |
| `API error 400` | Bad input/coordinates or empty category |
| `API error 429` | Quota exhausted — wait, or lower `--grid` |
| Few results | Raise `--grid`, add synonyms ("boutique", "fashion") |
| Empty phone | The business has no phone on Google |

## 7. Responsible use

- Collect **business** contact data only for lawful purposes, and follow Google Maps Platform terms (they restrict long-term storage/caching
  and resale of Places data — check the current terms).
- Unsolicited bulk SMS, calls or e-mail is illegal or restricted in many places and gets numbers blocked. Obtain consent, offer opt-out.
- Treat the CSV as business data that may still include personal numbers (sole traders): store securely and delete when no longer needed.

## ⚠️ Disclaimer

This software is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · [MIT](../../LICENSE)
