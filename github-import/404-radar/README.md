# 404 Radar

Catches 404 errors, suggests the closest matching page and fixes them with one-click 301 redirects. Minimal, fast, bilingual (EN/FA).

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-1.1.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.9%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟢 Low — logs 404 requests; redirects are created by you (auto-redirect is optional)

## Features

- Logs 404 URLs with hit count and the **referrer URL** (no IP addresses are stored by the plugin)
- Suggests the **closest existing page** for each 404 (slug similarity matching)
- One-click **301 redirect**; optional **auto-redirect** to the best match, and a **regex rule list** for URL patterns
- Optionally skips **bot traffic** (on by default) and static-file requests; keeps the log for a configurable number of days (default 30)
- Optional **weekly e-mail digest** to the admin; 14-day trend counter
- Clean admin UI in English and Persian

## Requirements

WordPress 5.9+ (uses `str_starts_with`, polyfilled from 5.9) · PHP 7.4+

## Installation & usage

1. Upload and activate. Open **404 Radar** in the admin sidebar.
2. Let it collect for a few days (or visit a missing URL to test).
3. Open an entry → review the suggested page → **Redirect** (301) if it is a good match.
4. For retired content with no good replacement, leave the 404 (or use a 410) instead of redirecting.
5. Use *wipe* to clear entries with no target, and adjust retention/e-mail in Settings.

## SEO notes

Redirecting many unrelated URLs to the home page or to a weakly related page is treated by search engines as a *soft 404*. Auto-redirect is **off by default**; keep it off unless you understand the effect.

## Privacy

Logged data: requested URL, referrer URL (may contain query strings), timestamp, hit count. Mention it in your privacy policy if required. The log table and options are removed when the plugin is **deleted** (`uninstall.php`), not merely deactivated.

## Troubleshooting

| Problem | Fix |
|---|---|
| Nothing is logged | Logging disabled in Settings, or a cache/CDN serves the 404 page before WordPress runs |
| Redirect not working | Another redirect plugin or server rule takes precedence; clear caches |

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
