# SEO Analyzer — architecture

The analyzer started life as a single 1,320-line `seo_analyzor.py`. It is now a small package whose
behaviour is **byte-for-byte identical** to the original: the refactor only moved code. (Verified by running
the old and new `SEOAnalyzer` against the same page and comparing the full report and suggestion list.)

## Module map

```
seo/seo_analyzer/
├── app.py            Streamlit page: inputs, spinner, calls analyzer + report_view
├── analyzer.py       SEOAnalyzer — URL normalisation, fetch(), analyze(), safe-run wrapper
├── scoring.py        ScoringMixin.calculate_score() — 86 points → 0–100
├── report_view.py    display_report() — suggestion grouping and detail expanders
├── constants.py      FARSI_STOPWORDS, ENGLISH_STOPWORDS, REQUEST_HEADERS
└── checks/
    ├── meta.py       MetaChecks
    ├── structure.py  StructureChecks
    ├── content.py    ContentChecks
    ├── technical.py  TechnicalChecks
    └── elements.py   ElementChecks
```

`SEOAnalyzer` inherits from all the check mixins, so every check keeps using `self.soup`,
`self.report`, `self.suggestions`, `self.final_url` exactly as before. Mixins were chosen over
free functions on purpose: it kept the refactor mechanical and risk-free. Converting checks to pure
functions is on the [roadmap](ROADMAP.md).

## Data flow

```
SEOAnalyzer(url)
   └─ fetch()                    # requests.get → BeautifulSoup(lxml); stores status_code, final_url
   └─ analyze()                  # runs groups in a fixed order, each through _run_analysis_safe
        ├─ analyze_basic_meta        meta, https, language, favicon
        ├─ analyze_basic_structure   headings, images, links
        ├─ analyze_content_details   word count, keywords          (word count must come first)
        ├─ analyze_technical_files   robots.txt, sitemap
        ├─ analyze_advanced          text/HTML ratio, OG, Twitter, schema, canonical, viewport, …
        ├─ analyze_content_elements  iframes, tables, lists, media, infographics
        └─ calculate_score           reads everything above
   └─ get_report() → (report: dict, suggestions: list[dict])
```

Order matters in two places:

- `analyze_word_count` runs before the checks that make word-count-dependent suggestions.
- `check_for_infographics` runs after `analyze_images`.

A suggestion is a dict: `{"metric", "issue", "recommendation"}` plus an optional `"type"`
(`"error"` or `"positive"`). `report_view` sorts them into high / medium / positive buckets.

## Adding a new check

1. Put a method in the mixin that fits (or add a new mixin). Write results to `self.report[...]`
   and append to `self.suggestions`:

   ```python
   # seo/seo_analyzer/checks/meta.py  (inside class MetaChecks)
   def check_robots_meta(self):
       tag = self.soup.find("meta", attrs={"name": "robots"})
       content = tag.get("content", "").lower() if tag else ""
       self.report["robots_meta"] = content or "N/A"
       if "noindex" in content:
           self.suggestions.append({
               "metric": "Robots meta",
               "issue": "The page is set to noindex.",
               "recommendation": "Remove noindex if this page should appear in search results.",
           })
   ```

2. Register it in the right group inside `analyzer.py`, for example in `analyze_basic_meta`:

   ```python
   def analyze_basic_meta(self):
       self.analyze_meta()
       self.check_https()
       ...
       self.check_robots_meta()
   ```

3. (Optional) give it points in `scoring.py` and a line in `report_view.py`.
4. Add a test in `tests/test_seo_analyzer.py` that injects HTML:

   ```python
   a = SEOAnalyzer("example.com")
   a.soup = BeautifulSoup('<meta name="robots" content="noindex">', "lxml")
   a.check_robots_meta()
   assert a.suggestions
   ```

Because every group runs through `_run_analysis_safe`, a bug in your check shows up as an
"Analysis Error" suggestion instead of crashing the page.

## Testing approach

Tests never touch the network: they build a `SEOAnalyzer`, assign a parsed `soup`, and call individual
checks. `fetch()` and the two network helpers (`check_files`, `check_favicon`) are the only parts that
perform I/O; mock `requests.get` if you need to test them.
