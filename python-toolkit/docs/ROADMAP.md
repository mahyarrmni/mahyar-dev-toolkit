# Roadmap & known limitations

Honest list of what is incomplete, ordered roughly by value.

## Correctness / honesty

- [ ] **Crypto on-chain panel** returns fixed demo numbers. Implement real Etherscan (or another
      provider) calls — several useful endpoints need a paid plan — or remove the panel.
- [ ] **Deep Scanner** — implement the three stubbed modules (form ML, CSRF, redirect/clickjacking) by sharing code with
      `ai_web_vulnerability_scanner.py`, or merge the two apps into one.
- [ ] **ML form tester** is trained on five strings. Replace with rule-based detection (reflected payload
      in response, SQL error signatures) or a real labelled dataset.
- [ ] **Framework detection** in Deep Scanner only looks at the domain name.
- [ ] **SEO score** ignores page speed (the original PageSpeed call was removed) and backlink data.

## Code quality

- [ ] Replace the remaining bare `except:` blocks with specific exceptions (`ruff` rule `E722` is currently ignored).
- [ ] Convert SEO checks from mixins to pure functions: `check(soup, ctx) -> Findings`.
- [ ] Separate scanning logic from Streamlit UI in `security/` so it can be unit-tested.
- [ ] Add `timeout=` to the remaining network calls in `ai-apps/` (e.g. Replicate polling).
- [ ] Type hints and `mypy` in CI for the `seo_analyzer` package.
- [ ] Optional: publish `seo_analyzer` to PyPI with an `__main__` CLI (`python -m seo_analyzer <url> --json`).

## Features

- [ ] SEO Analyzer: export report as JSON / CSV / PDF.
- [ ] SEO Analyzer: crawl mode (sitemap → many URLs) with a shared rate limiter.
- [ ] SEO Analyzer: Core Web Vitals via the official PageSpeed Insights API (user-supplied key).
- [ ] Auto Image Downloader: switch to the official Unsplash API with attribution output.
- [ ] Crypto Analyzer: more coins, selectable indicator windows, caching layer.
- [ ] i18n: language switch (fa/en) for all Streamlit UIs.

## Newly imported tools

- [ ] Bring `seo_audit_bot`, `keyword_miner`, `maps_leads_bot` and `filesage` under `ruff` (they are excluded in `pyproject.toml` for now) and add offline tests.
- [ ] `seo_audit_bot`: optional `robots.txt` compliance and a per-host delay; JavaScript rendering is out of scope.
- [ ] `keyword_miner`: pluggable sources and a documented fallback if an endpoint changes.
- [ ] `maps_leads_bot`: field-mask/cost estimator before a run; configurable cities from a JSON file.
- [ ] `b2b_lead_intel`: run its end-to-end tests in CI with `aiohttp` installed (already in `leads/requirements.txt`).

## Packaging

- [ ] Docker image per app group.
- [ ] Apply `wordpress-plugins/fix_plugin_headers.py` and the CI templates to the nine plugin repositories.
- [ ] Screenshots / GIFs in each README section.
- [ ] GitHub Releases with changelog (see `CHANGELOG.md`).
