# SEO Intelligence

WordPress plugin. Turns your real Google Search Console data into actionable SEO opportunities. Read-only.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-2.2.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-6.0%2B-21759b) ![php](https://img.shields.io/badge/PHP-8.1%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟢 Low — reads data from your own Search Console property

## What it does

- Connects to Google Search Console (OAuth or Service Account)
- Surfaces queries with high impressions but low CTR — the quickest wins
- Finds pages stuck in positions 4–15 that are one push from page one
- Groups semantically related queries for keyword-clustering decisions
- Shows trends for clicks, impressions, average position and CTR over configurable ranges

**Read-only.** The plugin never generates fake traffic, clicks or simulated search behaviour; all data comes from your own property.

## Requirements

WordPress 6.0+ · **PHP 8.1+** · a Search Console property you own · a Google Cloud project with the Search Console API enabled

## Installation & usage

1. Upload to `/wp-content/plugins/` (use the **complete** plugin folder) and activate.
2. Open **SEO Intelligence** in the admin sidebar.
3. Connect your Google account / Service Account and select the property.
4. Start with the CTR-gap and position 4–15 lists; optimise titles/descriptions and content for those pages first.

> **Integrity guard:** the main file needs the `includes/` (and `admin/`) folders. If they are missing the plugin now shows an admin notice instead of causing a fatal error — reinstall from the full release.

## Privacy & data flow

Data is fetched from Google's Search Console API with credentials you provide and stored in your WordPress database. Nothing is sent to the author. On uninstall the plugin's own tables/options can be removed (see `uninstall.php`).

## Troubleshooting

| Problem | Fix |
|---|---|
| "plugin files are incomplete" notice | Reinstall from the full release ZIP |
| No data | Property not verified, wrong property selected, or Service-Account e-mail not added as a user |
| Fatal error on activation | PHP lower than 8.1 |

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
