# Benchmarks

Measured with `python -m benchmarks.bench N` on a **1-core sandbox VM, Python 3.12.3**. Crawler and the synthetic web server share that single core, 
pages are ~3 KB synthetic articles on localhost (no network latency). Treat numbers as *relative engine cost*, not as what you will see crawling the real web. Single runs on a shared VM: micro-timings vary a lot between runs (parse time was 3.7 ms in one run and 1.9 ms in another), so only order-of-magnitude comparisons are meaningful.

| Metric | 2,000 pages | 10,000 pages |
|---|---|---|
| Crawl (8 workers, delay 0, robots off) | 12.91 s (155.0 pages/s) | 60.19 s (166.2 pages/s) |
| HTML parse + extract (per page) | 3.7 ms | 1.93 ms |
| TF-IDF + LSA fit (all pages) | 6.81 s | 18.65 s |
| Top-5 cosine neighbours, all pages | 0.05 s | 1.77 s |
| Clustering (exact ≤3000 else MiniBatchKMeans) | 0.2 s | 0.22 s |
| Full pipeline, no GSC (technical → semantic → graph → topics → links → recs → roadmap → reports) | 12.23 s | 43.28 s |
|   of which semantic stage (embeddings, intents, entities) | 10.331 s | 34.057 s |
|   technical rules | 0.409 s | 1.441 s |
|   topics + link suggestions | 0.705 s | 4.046 s |

A `crawl_c32` run at 2,000 pages gave 153.0 pages/s vs 155.0 at 8 workers: on one core the crawler is CPU-bound, so more workers do not help.

## Near-duplicate detection (SimHash + LSH)

5500 fingerprints (5,000 independent docs + 500 planted 1-bit-apart copies): LSH found **500 / 500** planted pairs in 0.037 s, and returned exactly the same pairs as brute force on a 1500-doc subset (True). Brute force on the subset took 0.118 s; extrapolated (quadratic) to all docs ≈ 1.6 s. The gap is small at this size and grows quadratically: going from 5.5k to 100k fingerprints multiplies brute-force cost by ≈ 330× (quadratic) while LSH grows roughly linearly (extrapolation, not measured).

## What these numbers do not say

* **Real crawls are bound by politeness, not CPU.** With the default 0.25 s per-host delay a single host yields ≤ 4 pages/s, so 10,000 pages ≈ 42 minutes whatever the worker count. Lower the delay only for sites you own.
* Scaling is roughly linear 2k → 10k for crawl and the pipeline (×5 pages → ×3.5 pipeline time); exact LSA/TF-IDF memory grows with vocabulary (capped at 80k features). Beyond ~50k pages move embeddings to SBERT + an ANN index (not implemented).
* Not benchmarked here: PostgreSQL/Celery/Redis (not available in the sandbox), Playwright rendering, SBERT embeddings (model download not possible offline).
* Quality metrics (not just speed) are covered by tests: LSH == brute force; PageRank sums to 1; CTR curve recovers a known synthetic curve within 15% at positions 2–20; cross-validated error beats constant-CTR baseline; topic clustering ARI > 0.8 on a bilingual synthetic site.
