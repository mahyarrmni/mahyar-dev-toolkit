# Changelog

All notable changes to this project. Format: [Keep a Changelog](https://keepachangelog.com), versioning: [SemVer](https://semver.org).

## [1.1.0] — 2026-11-03

### Added
- **SEO Audit Bot** (`seo/seo_audit_bot`) — site crawler with scored HTML + CSV report, sample report included.
- **Keyword Miner** (`seo/keyword_miner`) — autocomplete keyword ideas with intent tags, clusters, HTML + CSV report.
- **Maps Leads Bot** (`leads/maps_leads_bot`) — Google Places API (New) business lists to CSV.
- **B2B Lead Intelligence** (`leads/b2b_lead_intel`) — async lead crawler, scoring, SQLite, HTML dashboard, unit + end-to-end tests.
- **FileSage** (`utilities/filesage`) — organiser, duplicate/similar-image finder, HTML report, journaled undo.
- English **and** Persian README for every new tool, with install, step-by-step usage, troubleshooting, risk level and disclaimer.
- Risk levels (🟢/🟡/🔴) in the main README, `docs/RESPONSIBLE_USE(.fa).md` with a risk matrix, `SECURITY.md`.
- Step-by-step "How to use" notes for every existing app.
- `GOOGLE_API_KEY` in `.env.example`; output files (`*.db`, lead/keyword/report files) added to `.gitignore`.

### Changed
- Auto Image Downloader: neutral default title and keywords. Image Optimizer: removed hot-linked logo and the default watermark text.
- CI also compiles `leads/` and runs the `b2b_lead_intel` test-suite. New tool folders are excluded from `ruff` for now (see roadmap).
- Single author attribution (Mahyar Momeni) across all tools.

## [1.0.0] — 2026-11-03

### Changed
- Reorganised the flat `python-bots/` folder into `seo/`, `security/`, `ai-apps/`, `utilities/`.
- Split the 1,320-line `seo_analyzor.py` into the `seo_analyzer` package (`analyzer`, `scoring`, `report_view`, `checks/*`).
  Output verified identical to the original.
- Renamed `seo_analyzor` → `seo_analyzer` (spelling).
- Converted all sources from CRLF to LF.
- Streamlit SEO app: logo is now optional (`SEO_ANALYZER_LOGO_URL`) instead of hot-linking a fixed image.

### Added
- `LICENSE` (MIT), `.gitignore`, `.gitattributes`, `.editorconfig`, `.env.example`.
- Per-group `requirements.txt` files, `pyproject.toml` (ruff + pytest config).
- Offline pytest suite and GitHub Actions CI (ruff, compile check, tests on Python 3.10 / 3.12).
- Authorization checkbox that gates the scan button in both security scanners.
- Documentation: README (en/fa), architecture, roadmap, responsible use, contributing.
- `wordpress-plugins/`: header fixer script and repo templates (`.gitignore`, `.gitattributes`, `phpcs.xml.dist`, CI) for the PHP plugins.

### Fixed
- Added request timeouts to the vulnerability scanner and to image fetching in the optimizer.
- Deep Scanner no longer reports canned findings for modules that are not implemented.
- Crypto Analyzer: the on-chain panel is explicitly labelled as demo data.
- Image Optimizer: removed the advertised "background removal" feature that was never implemented.

### Security
- API keys (`REPLICATE_API_TOKEN`, `NEWSAPI_KEY`) are read from environment variables instead of source code.
