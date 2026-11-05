# SEO Strategy Report — http://127.0.0.1:40837/

## Executive summary
Plan: 58 tasks scheduled over up to 12 weeks at 20.0 h/week (0 in backlog). Current technical health score: 81.6/100.
1-stabilize: Redirect loops (1) (priority 22.838, week 1); Internal links pointing to 4xx/5xx pages (2) (priority 22.031, week 1); Crawled pages returning 4xx (2) (priority 21.03, week 1); noindex pages listed in the sitemap (1) (priority 20.014, week 1)
2-quick-wins: Add 3 internal links to /seo/canonical-tags (priority 17.333, week 3); Invalid JSON-LD (parse error) (1) (priority 16.982, week 1); Add 3 internal links to /coffee/espresso (priority 16.857, week 6); Add 3 internal links to /seo/canonical-tag-guide (priority 12.718, week 6)
3-growth: Create content: bitcoin price today (priority 5.421, week 4); Create content: crypto exchange fees (priority 4.691, week 4); Create content: قیمت بیت کوین (priority 4.345, week 4); Link 16 spokes to pillar of topic 'anchor / sitemap / canonical / crawler' (priority 3.889, week 7)

## Top recommendations (priority = impact × confidence ÷ (hours + 1))
| # | Recommendation | Impact | Conf. | Hours | Priority | Est. clicks | Depends on |
|---|---|---|---|---|---|---|---|
| 1 | Redirect loops (1) | 60.1 (severity_heuristic) | 0.95 | 1.5 | 22.838 | n/a | - |
| 2 | Internal links pointing to 4xx/5xx pages (2) | 48.7 (severity_heuristic) | 0.95 | 1.1 | 22.031 | n/a | - |
| 3 | Crawled pages returning 4xx (2) | 48.7 (severity_heuristic) | 0.95 | 1.2 | 21.03 | n/a | - |
| 4 | noindex pages listed in the sitemap (1) | 46.7 (severity_heuristic) | 0.9 | 1.1 | 20.014 | n/a | - |
| 5 | Add 3 internal links to /seo/canonical-tags | 80 (structural) | 0.65 | 2.0 | 17.333 | n/a | R-ac1a4684, R-b78c1341, R-f1d2599d |
| 6 | Invalid JSON-LD (parse error) (1) | 46.7 (severity_heuristic) | 0.8 | 1.2 | 16.982 | n/a | - |
| 7 | Add 3 internal links to /coffee/espresso | 77.8 (structural) | 0.65 | 2.0 | 16.857 | n/a | R-51a62fc6 |
| 8 | Add 3 internal links to /seo/canonical-tag-guide | 58.7 (structural) | 0.65 | 2.0 | 12.718 | n/a | R-51a62fc6, R-ac1a4684 |
| 9 | Cannibalization: canonical tags guide, how to canonical tags guide | 91.3 (ctr_curve_model) | 0.95 | 6.0 | 12.391 | 89.2 | - |
| 10 | Redirect chains (more than one hop) (1) | 26.7 (severity_heuristic) | 0.95 | 1.1 | 12.079 | n/a | - |
| 11 | Cannibalization: canonical tags, what is a canonical tag | 88.4 (ctr_curve_model) | 0.9 | 6.0 | 11.366 | 65.0 | - |
| 12 | Push striking-distance queries on /coffee/espresso | 86.0 (ctr_curve_model) | 0.5 | 3.0 | 10.75 | 52.0 | R-51a62fc6 |

## Topical authority
Authority = weighted mean of available components (coverage .35, depth .25, cohesion .20, performance .20).
| Topic | Pages | Authority | Components | Pillar |
|---|---|---|---|---|
| extraction / foam / latte / barista | 9 | 0.822 | {'depth': 0.568, 'cohesion': 0.648, 'performance': 1.0, 'coverage': 1.0} | /coffee/arabica |
| anchor / sitemap / canonical / crawler | 17 | 0.737 | {'depth': 0.58, 'cohesion': 0.208, 'performance': 1.0, 'coverage': 1.0} | /seo/canonical-copy |
| کافئین / دانه / لاته / عربیکا | 2 | 0.44 | {'depth': 0.345, 'cohesion': 0.0, 'performance': 1.0} | /fa/ghahve-espresso |
| سئو / خزش / رتبه بندی / بندی | 2 | 0.393 | {'depth': 0.359, 'cohesion': 0.0, 'performance': 0.829} | /fa/seo-amoozesh |

### Content gaps
| Gap | Impressions | Example queries | Format |
|---|---|---|---|
| bitcoin price today | 900 | bitcoin price today | product or category page |
| crypto exchange fees | 600 | crypto exchange fees | guide / article |
| قیمت بیت کوین | 500 | قیمت بیت کوین | product or category page |

## Keyword cannibalization (GSC evidence)
| Pages | Queries | Impressions | Similarity | Dispersion | Action |
|---|---|---|---|---|---|
| /seo/canonical-tag-guide, /seo/canonical-tags | 2 | 4000 | 1.0 | 0.99 | consolidate |
| /seo/canonical-copy, /seo/canonical-tags | 3 | 3707 | 1.0 | 0.673 | consolidate |

## Internal linking
- 35 indexable pages, 111 internal links, 1 orphans, max click depth 6.
- Simulation (top 50 suggestions): mean target PageRank +62.52%, orphans 1→0, mean depth 2.0→1.771.
| From | To | Anchor | Score |
|---|---|---|---|
| /seo/canonical-copy | /seo/canonical-tags | canonical tags guide | 1.39 |
| /seo/canonical-tag-guide | /seo/canonical-tags | canonical tags guide | 1.3075 |
| /seo/canonical-copy | /seo/canonical-tag-guide | canonical tags | 1.193 |
| /seo/canonical-tags | /seo/canonical-tag-guide | canonical tags | 1.1222 |
| /seo/canonical-tags | /seo/canonical-copy | how to canonical tags guide | 0.9619 |
| /seo/canonical-tag-guide | /seo/canonical-copy | how to canonical tags guide | 0.9619 |
| /fa/seo-amoozesh | /fa/seo-chist | how to سئو چیست | 0.8083 |
| /fa/seo-chist | /fa/seo-amoozesh | how to آموزش سئو سایت | 0.6623 |
| /fa/ghahve | /fa/ghahve-espresso | اسپرسو چیست | 0.6226 |
| /d1 | /d5 | Deep page 5 | 0.6222 |

## Predictive analytics
CTR curve source: **generic_prior** (generic prior — import more GSC data for a site-specific curve)
| Scenario | Rows | Baseline clicks | Projected extra clicks | 90% interval |
|---|---|---|---|---|
| Striking distance: +3 positions for queries at 8-20 | 19 | 273.0 | 232.0 | n/a |
| Top-10 queries to position 3 (impressions >= 100) | 35 | 870.0 | 1690.3 | n/a |

## Roadmap
| Week | Hours | Tasks |
|---|---|---|
| 1 | 20.0 | Redirect loops (1) ✔; Internal links pointing to 4xx/5xx pages (2) ✔; Crawled pages returning 4xx (2) ✔; noindex pages listed in the sitemap (1) ✔; Invalid JSON-LD (parse error) (1) ✔; Cannibalization: canonical tags guide, how to canonical tags guide ✔; Redirect chains (more than one hop) (1) ✔; Cannibalization: canonical tags, what is a canonical tag ✔; Push striking-distance queries on /seo/robots |
| 2 | 20.0 | Push striking-distance queries on /seo/robots ✔; hreflang without return link (1) ✔; rel=next/prev pointing to a non-200 page (1) ✔; Intent mismatch for 'bitcoin price today' ✔; Exact duplicate content (2) ✔; Improve title/meta for /coffee/latte-art ✔; Intent mismatch for 'قیمت بیت کوین' ✔; Indexable pages with no internal inlinks (1) ✔; Missing meta description (11) ✔; Improve title/meta for /coffee/crema ✔; Add 3 internal links to /orphan |
| 3 | 20.0 | Add 3 internal links to /orphan ✔; Add 3 internal links to /seo/canonical-tags ✔; Structured data missing required properties (1) ✔; Push striking-distance queries on /fa/ghahve-espresso ✔; Improve title/meta for /seo/canonical-tags ✔; Improve title/meta for /fa/seo-amoozesh ✔; Intent mismatch for 'how to best coffee grinders' ✔; Push striking-distance queries on /coffee/roasting ✔; Create content: bitcoin price today |
| 4 | 20.0 | Create content: bitcoin price today ✔; Push striking-distance queries on /fa/seo-chist ✔; Improve title/meta for /orphan ✔; Create content: crypto exchange fees ✔; Create content: قیمت بیت کوین ✔; Add 3 internal links to /d5 |
| 5 | 20.0 | Add 3 internal links to /d5 ✔; Add 3 internal links to /d6 ✔; Add 3 internal links to /d4 ✔; Push striking-distance queries on /seo/backlinks ✔; Push striking-distance queries on /coffee/grinders ✔; Internal links pointing to redirecting URLs (1) ✔; Near-duplicate content (SimHash) (3) ✔; External links that are broken or unreachable (1) ✔; Pages deeper than 4 clicks from the homepage (2) ✔; Push striking-distance queries on /orphan |
| 6 | 20.0 | Push striking-distance queries on /orphan ✔; Add 3 internal links to /coffee/espresso ✔; Add 3 internal links to /seo/canonical-tag-guide ✔; Push striking-distance queries on /coffee/espresso ✔; Duplicate titles (2) ✔; Link 4 spokes to pillar of topic 'extraction / foam / latte / barista' ✔; Add 3 internal links to /seo/canonical-copy ✔; Improve title/meta for /coffee/espresso ✔; Improve title/meta for /seo/canonical-tag-guide ✔; Push striking-distance queries on /seo/canonical-copy ✔; Link 16 spokes to pillar of topic 'anchor / sitemap / canonical / crawler' |
| 7 | 20.0 | Link 16 spokes to pillar of topic 'anchor / sitemap / canonical / crawler' ✔; Duplicate meta descriptions (2) ✔; Improve title/meta for /seo/canonical-copy ✔; Push striking-distance queries on /seo/canonical-tag-guide ✔; Meta descriptions shorter than 70 characters (12) ✔; Titles shorter than 20 characters (23) ✔; Indexable pages without a canonical tag (35) ✔; Thin content (< 200 words) (33) |
| 8 | 20.0 | Thin content (< 200 words) (33) |
| 9 | 13.8 | Thin content (< 200 words) (33) ✔ |

_Sum of per-item model estimates; items overlap, so treat as an upper bound, not a forecast._

## Method & limitations
- Recommendations are rule/model outputs with provenance (see JSON `provenance` per item); no LLM generates findings.
- Click estimates use the CTR curve and explicit recovery-rate assumptions; they are projections, not guarantees.
- Entity extraction is heuristic (no pretrained NER); LSA embeddings are monolingual per script unless the SBERT backend is enabled.

---
_Generated by ASIGE 0.1.0 · seed 0 · input digest `72e4ec94598fa68a` · GSC digest `d07644fa623e3841` · reproducibility hash `316f7054d36622f1`._
