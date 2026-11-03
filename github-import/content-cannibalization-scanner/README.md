# Content Cannibalization Scanner

WordPress plugin. Finds keyword cannibalization and duplicate intent across your posts, and (v2.2) audits site security.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-2.2-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.8%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟡 Medium — read-only by default, but optional auto-fix actions change content/files (backup + rollback provided)

## What it does

**SEO module**
- Scans published posts for overlapping target keywords and duplicate search intent
- Suggests canonical tags and consolidation actions; flags thin pages that split ranking authority
- Optional cloaking / conditional-redirect comparison (what Googlebot vs. a visitor vs. a Google-referred visitor receives)

**Site Report / security module (v2.2)**
- Malware-signature scan with a remotely updated, Ed25519-signed feed
- Plugin/theme/core vulnerability lookup via WPVulnerability.net (no key) or Wordfence Intelligence
- Google Safe Browsing check (API key required) · WordPress core file integrity check
- **Dry-run preview** before any fix; every remediation makes a compressed ZIP backup and can be rolled back in one click

> **Package note:** the security module loads its classes from an `includes/class-cs-audit*.php` set and a feed tool from `tools/`.
> If these folders are missing from the folder you installed, the SEO scanner still works but the *Site Report* is disabled. Use the full release.

## Requirements

WordPress 5.8+ · PHP 7.4+

## Installation & usage

1. Upload to `/wp-content/plugins/` and activate.
2. Open **Cannibalization Scanner** in the admin sidebar.
3. Run the **SEO scan** → open each conflict group → decide: merge, differentiate, or set canonical.
4. For security, open **Site Report** → run a scan → review findings → use **dry-run** first → apply fixes only after reading the preview.

## Privacy & data flow

Vulnerability lookups send plugin/theme slugs and versions to WPVulnerability.net / Wordfence Intelligence; Safe Browsing sends your site URL to Google; the malware feed is downloaded from the feed URL configured in the plugin. Post content stays on your server.

## Troubleshooting

| Problem | Fix |
|---|---|
| Site Report tab missing/empty | The `includes/` files are not installed — reinstall from the full release |
| Scan times out | Run on fewer posts or raise PHP `max_execution_time` |
| Remote feed fails | Check outbound HTTPS access from the server |

## ⚠️ Notes

Automatic remediation modifies site files. Always keep your own backup, test on staging, and treat scanner output as a lead for manual review, not proof of compromise or of safety.

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
