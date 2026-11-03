# Keyword Miner

[فارسی](README.fa.md) · part of [Mahyar's Python Toolkit](../../README.md)

Turns one seed word into **hundreds of real search ideas** using the public **autocomplete suggestions** of Google, YouTube
and Bing. Each keyword is tagged with a **search intent** (buy / compare / informational), grouped into **clusters**, and written to an
interactive **HTML report** (search, filters, copy buttons) and a **CSV**. No API key, free.

| | |
|---|---|
| **Risk level** | 🟡 Low–medium — uses unofficial, undocumented suggestion endpoints (see disclaimer) |
| **Needs** | Python 3.8+, `requests`, internet access to the suggestion services |
| **Output** | `keywords.html` + `keywords.csv` |
| **Does not provide** | Search volume, difficulty or CPC — only ideas and intent |

## Install

```bash
cd seo/keyword_miner
pip install -r requirements.txt
```

## Usage

```bash
python keyword_miner.py "dog training" --lang en -o dog.html          # English
python keyword_miner.py "<seed in Persian>" --lang fa -o ideas.html   # Persian (default language)
python keyword_miner.py "coffee" --deep -o coffee.html                # deep mode: also expands with every letter (slower)
python keyword_miner.py "seo" "web design" "ads" -o marketing.html    # several seeds
python keyword_miner.py "coffee" --sources google,youtube --delay 0.5 # choose sources / be gentler
```

| Option | Default | Meaning |
|---|:-:|---|
| `seeds` | — | One or more seed keywords (quote each) |
| `--lang` | `fa` | Language code: `fa`, `en`, `ar`, `tr`, … |
| `--sources` | `google,youtube,bing` | Comma-separated subset |
| `--deep` | off | Adds alphabet expansion → many more results, many more requests |
| `--delay` | 0.15 | Seconds between requests. Increase if you see errors or blocks |
| `-o` | `keywords.html` | Report name (the CSV is written next to it) |

## Reading the report

- **Cards:** total keywords, buy-intent, informational and currently displayed.
- **Intent filter:** buy (green) · compare (yellow) · informational (purple).
- **Cluster filter:** keywords grouped by a shared topic word.
- **Live search, sortable columns, ⧉ copy one / "copy all" for the filtered list.**

How to use the results: informational → blog/video ideas · buy → product & service pages and ad campaigns ·
compare → "best …" and review articles · big clusters → site menu and content hubs.

## Notes & limits

- The suggestion endpoints are public but **not an official API**; they can change, rate-limit or block you without notice.
  Do not run `--deep` repeatedly in a short time.
- Suggestions come from the search engines; filter odd or irrelevant ones by hand.
- Validate promising keywords with a real tool (Google Search Console, Keyword Planner, etc.) before investing.
- Keep your own research results private — the generated files are git-ignored by default in this repository.

## Troubleshooting

| Problem | Fix |
|---|---|
| `no results` | Check internet/access to Google; try `--delay 0.5` |
| Few results | Use `--deep` or a shorter, more general seed |
| `ModuleNotFoundError` | `pip install -r requirements.txt` |
| Garbled Persian in Excel | Import CSV via Data → From Text/CSV with UTF-8 |

## ⚠️ Disclaimer

This software is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

For this tool specifically: automated queries to search-engine services may conflict with their terms of use. Keep request
rates low and use the results for research only.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · [MIT](../../LICENSE)
