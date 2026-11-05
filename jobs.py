# Technical SEO Report — http://127.0.0.1:40837/

**Health score: 81.6/100** · 42 URLs crawled · 37 HTML 200 · 35 indexable

Score = 100 × (1 − Σ rule weight × min(1, affected / HTML pages)); weights: critical .30, high .15, medium .07, low .02.

## Issues by severity
| Severity | Affected URLs |
|---|---|
| critical | 1 |
| high | 8 |
| medium | 54 |
| low | 76 |
| info | 32 |

## Findings

### [CRITICAL] Redirect loops — 1 URL(s)
*Category: redirects* · **Fix:** Break the loop; point to a 200 page.

- `/loop-a`
- Evidence sample: `{'chain': [{'url': 'http://127.0.0.1:40837/loop-a', 'status': 302}, {'url': 'http://127.0.0.1:40837/loop-b', 'status': 302}]}`

### [HIGH] Crawled pages returning 4xx — 2 URL(s)
*Category: crawlability* · **Fix:** Restore the page, 301 to the closest equivalent, or remove links to it.

- `/blog/page/4`
- `/missing`
- Evidence sample: `{'status': 404, 'inlinks': 1}`

### [HIGH] Internal links pointing to 4xx/5xx pages — 2 URL(s)
*Category: links* · **Fix:** Update or remove the broken links.

- `/about`
- `/blog/page/3`
- Evidence sample: `{'target': 'http://127.0.0.1:40837/missing', 'status': 404, 'anchor': 'broken link'}`

### [HIGH] Exact duplicate content — 2 URL(s)
*Category: content* · **Fix:** Consolidate with 301/canonical.

- `/seo/canonical-copy`
- `/seo/canonical-tags`
- Evidence sample: `{'group': ['http://127.0.0.1:40837/seo/canonical-copy', 'http://127.0.0.1:40837/seo/canonical-tags']}`

### [HIGH] noindex pages listed in the sitemap — 1 URL(s)
*Category: indexability* · **Fix:** Remove from sitemap or drop noindex.

- `/private`
- Evidence sample: `{}`

### [HIGH] Invalid JSON-LD (parse error) — 1 URL(s)
*Category: structured-data* · **Fix:** Fix JSON syntax.

- `/seo/robots`
- Evidence sample: `{'error': 'Expecting property name enclosed in double quotes: line 1 column 2 (char 1)'}`

### [MEDIUM] Thin content (< 200 words) — 33 URL(s)
*Category: content* · **Fix:** Expand or consolidate.

- `/`
- `/about`
- `/blog/page/1`
- `/blog/page/2`
- `/blog/page/3`
- `/coffee/`
- `/coffee/arabica`
- `/coffee/crema`
- … and 25 more
- Evidence sample: `{'words': 18}`

### [MEDIUM] Missing meta description — 11 URL(s)
*Category: on-page* · **Fix:** Add a unique summary (70-160 chars).

- `/about`
- `/blog/page/1`
- `/blog/page/2`
- `/blog/page/3`
- `/d1`
- `/d2`
- `/d3`
- `/d4`
- … and 3 more
- Evidence sample: `{}`

### [MEDIUM] Near-duplicate content (SimHash) — 3 URL(s)
*Category: content* · **Fix:** Differentiate, merge, or canonicalize.

- `/coffee/espresso`
- `/seo/canonical-copy`
- `/seo/canonical-tag-guide`
- Evidence sample: `{'other': 'http://127.0.0.1:40837/coffee/roasting', 'hamming': 3}`

### [MEDIUM] Duplicate titles — 2 URL(s)
*Category: on-page* · **Fix:** Make titles unique per page.

- `/seo/canonical-copy`
- `/seo/canonical-tags`
- Evidence sample: `{'group': ['http://127.0.0.1:40837/seo/canonical-copy', 'http://127.0.0.1:40837/seo/canonical-tags']}`

### [MEDIUM] Redirect chains (more than one hop) — 1 URL(s)
*Category: redirects* · **Fix:** Redirect directly to the final URL.

- `/old`
- Evidence sample: `{'hops': 2, 'chain': ['http://127.0.0.1:40837/old', 'http://127.0.0.1:40837/old2'], 'final': 'http://127.0.0.1:40837/coffee/espresso'}`

### [MEDIUM] rel=next/prev pointing to a non-200 page — 1 URL(s)
*Category: pagination* · **Fix:** Fix the pagination sequence.

- `/blog/page/3`
- Evidence sample: `{'next': 'http://127.0.0.1:40837/blog/page/4', 'status': 404}`

### [MEDIUM] hreflang without return link — 1 URL(s)
*Category: international* · **Fix:** Make hreflang annotations reciprocal.

- `/seo/`
- Evidence sample: `{'target': 'http://127.0.0.1:40837/fa/seo-chist', 'lang': 'fa'}`

### [MEDIUM] Structured data missing required properties — 1 URL(s)
*Category: structured-data* · **Fix:** Add the required properties.

- `/seo/canonical-tags`
- Evidence sample: `{'type': 'Article', 'missing': ['datePublished', 'author']}`

### [MEDIUM] Indexable pages with no internal inlinks — 1 URL(s)
*Category: architecture* · **Fix:** Link to them from relevant pages.

- `/orphan`
- Evidence sample: `{'in_sitemap': True}`

### [LOW] Indexable pages without a canonical tag — 35 URL(s)
*Category: indexability* · **Fix:** Add a self-referencing canonical.

- `/`
- `/about`
- `/blog/page/1`
- `/blog/page/2`
- `/blog/page/3`
- `/coffee/`
- `/coffee/arabica`
- `/coffee/crema`
- … and 27 more
- Evidence sample: `{}`

### [LOW] Titles shorter than 20 characters — 23 URL(s)
*Category: on-page* · **Fix:** Expand with descriptive keywords.

- `/`
- `/about`
- `/blog/page/1`
- `/blog/page/2`
- `/blog/page/3`
- `/coffee/`
- `/d1`
- `/d2`
- … and 15 more
- Evidence sample: `{'length': 4}`

### [LOW] Meta descriptions shorter than 70 characters — 12 URL(s)
*Category: on-page* · **Fix:** Expand the description.

- `/`
- `/coffee/`
- `/fa/`
- `/fa/ghahve`
- `/fa/ghahve-espresso`
- `/fa/seo-amoozesh`
- `/fa/seo-chist`
- `/orphan`
- … and 4 more
- Evidence sample: `{'length': 55}`

### [LOW] Duplicate meta descriptions — 2 URL(s)
*Category: on-page* · **Fix:** Write unique descriptions.

- `/seo/canonical-copy`
- `/seo/canonical-tags`
- Evidence sample: `{'group': ['http://127.0.0.1:40837/seo/canonical-copy', 'http://127.0.0.1:40837/seo/canonical-tags']}`

### [LOW] Pages deeper than 4 clicks from the homepage — 2 URL(s)
*Category: architecture* · **Fix:** Flatten the architecture / add shortcuts.

- `/d5`
- `/d6`
- Evidence sample: `{'depth': 5}`

### [LOW] Internal links pointing to redirecting URLs — 1 URL(s)
*Category: redirects* · **Fix:** Link directly to the final URL.

- `/`
- Evidence sample: `{'target': 'http://127.0.0.1:40837/old', 'final': 'http://127.0.0.1:40837/coffee/espresso'}`

### [LOW] External links that are broken or unreachable — 1 URL(s)
*Category: links* · **Fix:** Replace or remove dead outbound links.

- `/about`
- Evidence sample: `{'target': 'http://external.invalid/x', 'status': None}`

### [INFO] Indexable pages without structured data — 32 URL(s)
*Category: structured-data* · **Fix:** Add relevant schema.org markup.

- `/`
- `/about`
- `/blog/page/1`
- `/blog/page/2`
- `/blog/page/3`
- `/coffee/`
- `/coffee/arabica`
- `/coffee/crema`
- … and 24 more
- Evidence sample: `{}`

## Crawl
- Pages: 42 · errors: 0 · blocked by robots.txt: 1 · elapsed 0.169 s (248.05 pages/s)
- Budget exhausted: False (if true, counts for orphan/broken-link rules may be incomplete)

---
_Generated by ASIGE 0.1.0 · seed 0 · input digest `72e4ec94598fa68a` · GSC digest `d07644fa623e3841` · reproducibility hash `316f7054d36622f1`._
