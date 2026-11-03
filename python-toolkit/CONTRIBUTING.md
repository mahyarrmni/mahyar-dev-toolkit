# Contributing

Thanks for helping! Small, focused pull requests are easiest to review.

## Setup

```bash
python -m venv .venv && source .venv/bin/activate
pip install -r requirements-dev.txt
```

## Before opening a PR

```bash
ruff check .        # must pass
pytest -q           # must pass
```

## Guidelines

- Keep UI strings in Persian where the app is Persian; code, comments for new code and docs in English are welcome.
- Never commit secrets — read them from environment variables and document them in `.env.example`.
- New SEO checks: add a method to the matching mixin, register it in `analyzer.py`, add a test.
  Walkthrough: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#adding-a-new-check).
- Features that automate interaction with third-party sites (posting, clicking, bulk requests) will not
  be accepted unless they are clearly for sites you control and rate-limited by design.

## Commit style

Short imperative subject (`Add robots meta check`), body explains *why* if it is not obvious.
