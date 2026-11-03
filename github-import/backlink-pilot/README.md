# Backlink Pilot

**Free WordPress plugin.** Plans, publishes and tracks backlinks from inside the WordPress dashboard.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-1.10.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.5%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🔴 High — automated link building can trigger search-engine penalties and platform bans (read *Risk notice*)

## What it does

1. **Sourcing** — loads a maintained list of guest-post / reportage sites (1,600+ across 32 niches) from GitHub into a local domain database.
2. **Scoring** — rates each domain on authority signals, niche relevance and your own history of link success.
3. **Planning** — an AI agent (Gemini / Groq / OpenRouter-compatible, with fallback) reads your site's pillar and support pages and proposes a campaign: anchors, target URLs, platforms, order.
4. **Publishing** — the autopilot publishes SEO-structured articles to Telegra.ph, Write.as, DEV.to, Hashnode and WordPress REST endpoints you control. Tone, structure and angle vary per article to keep content distinct.
5. **Tracking** — *Radar* re-checks every live link on a schedule: HTTP status, `noindex`/`canonical`, follow/nofollow, Bing index, IndexNow ping, and Search Console impressions/clicks via a Service Account.
6. **Learning** — weekly re-weighting of platforms and anchor mix based on what the radar measured.

## Features

- **Autopilot profiles:** *Cautious* (2 posts/day, 21-day ramp-up) · *Balanced* (3/day, 14 days — recommended) · *Fast* (6/day, 10 days) · *Custom*
- **Review mode:** by default the first **5** articles wait for your approval (`first`); you can choose *always review* or *off*
- Self-adjusting publish rate (slows on errors or dropped links), duplicate-content check, publish-hour window, daily cap per campaign, atomic lock against overlapping cron runs
- Anchor-text mix control (brand / naked URL / generic / partial / exact) — defaults favour brand and naked anchors
- Daily e-mail digest and weekly performance report; failure notifications
- Persian RTL admin UI. API keys and the Service-Account private key are **stored encrypted** (AES-256-CBC via OpenSSL when available)

## Requirements

WordPress 5.5+ · PHP 7.4+ (OpenSSL recommended) · at least one AI API key · working WP-Cron (or a real server cron hitting `wp-cron.php`)

## Installation

1. Upload the plugin folder (or ZIP) via **Plugins → Add New → Upload Plugin**, then **Activate**.
2. Open **Backlink Pilot** in the admin sidebar.

## Step-by-step usage

1. **AI connection (`اتصال AI`)** — paste a Gemini / Groq / OpenRouter key and run the connection test.
2. **GitHub sources (`منابع گیتهاب`, under *Advanced*)** — press *Load list* to build the domain database.
3. **Site & pillars (`سایت و پیلارها`)** — describe your site and mark your pillar/support pages.
4. **Campaigns (`کمپین‌ها`)** — create a campaign: target pages, anchors, daily cap.
5. **Autopilot (`اتوپایلوت`)** — choose a profile (start with *Cautious* or *Balanced*), choose platforms (default: Telegra.ph, Write.as; DEV.to/Hashnode need their own keys), keep **review mode** on, then enable.
6. **Report & Radar (`گزارش لینک‌ها`، `رادار`)** — watch link status; optionally connect Search Console (Service-Account JSON) to measure impact.
7. **Log (`لاگ`)** — every publish/check/error is listed here.

## Privacy & data flow

Data leaves your site to: your chosen AI provider (page content/excerpts for planning and writing), GitHub (domain lists), the publishing platforms you enable,
Bing/IndexNow, and Google APIs (if Search Console is connected). Nothing is sent to the author. Keys are encrypted at rest but are still on your server — protect your database and backups.

## ⚠️ Risk notice (please read)

Automatically publishing content on third-party sites in order to create links to your own site is **link building at scale**. Search engines (e.g. Google's spam policies on *link schemes* and *scaled content abuse*)
may classify it as manipulation, which can lead to ranking loss, manual actions or de-indexing. Platforms such as Telegra.ph, DEV.to, Hashnode or Write.as can remove content or suspend accounts that violate their terms.
Paid or sponsored placements must be marked appropriately (`rel="sponsored"` / disclosure). AI-generated text may need disclosure and review where the law or platform rules require it.

Reduce risk: publish only genuinely useful content, keep *review mode* on, prefer the *Cautious* profile, avoid exact-match anchors to money pages, test on a non-critical site first and watch the Radar.
**All consequences of using this plugin are entirely your own responsibility.**

## Troubleshooting

| Problem | Fix |
|---|---|
| Nothing is published | Autopilot disabled, review queue waiting for approval, or WP-Cron not running (use a real cron) |
| AI test fails | Wrong key, quota exhausted, or outbound requests blocked by hosting |
| Radar shows no Search Console data | Add the Service-Account e-mail as a user on the property; enable the Search Console API |
| Many failures | Lower the profile; check the Log tab for the platform error |

## Changelog

Full history (v1.0 → v1.10.0) is in the plugin file header.

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
