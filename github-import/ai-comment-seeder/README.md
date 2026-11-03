# AI Comment Seeder Pro

WordPress plugin. Generates and schedules AI-written comments for posts with the Gemini API.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-5.2.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.5%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🔴 High — synthetic comments can be deceptive and may violate spam policies and consumer-protection law

## What it does

- Reads a post and generates on-topic comments via **Gemini** (model configurable in settings; default language Persian)
- **Bulk job queue** driven by WP-Cron: create a job for selected posts and follow it in the job log and stats pages
- **Smart scheduling:** random delay between comments, allowed hours window, and optional back-dated timestamps (default: 1–60 days)
- **Comment types** (toggle each): compliment · question · critique · experience · recommendation · disagreement · follow-up, plus a configurable share of replies
- Per-post tone control (formal, casual, question, praise, critical), length limits, daily budget, skip posts that already have comments
- Debug log with rotation (capped at 5 MB)

## Safer defaults (since this version)

New comments are created as **pending** (`auto_approve` is **off** by default). Turn it on only if you understand the risk below.

## Requirements

WordPress 5.5+ · PHP 7.4+ · Gemini API key · working WP-Cron

## Installation & usage

1. Upload and activate; open **💬 Seeder** in the admin sidebar (sub-pages: Dashboard · Create job · Settings · Job log · Stats).
2. **Settings (تنظیمات):** enter the Gemini API key, choose language, limits, hour window and whether comments need approval (keep approval **required**).
3. **Create job (ایجاد جاب):** select posts, choose tone/count and queue the job.
4. Follow progress in **Job log (لاگ جاب‌ها)** and **Stats**; review pending comments under *Comments → Pending* before approving anything.

## ⚠️ Risk notice (please read)

This plugin produces comments that look like they were written by visitors, optionally with back-dated times and varied "experience" or "critique" content.
Presenting machine-generated text as genuine reader opinions can **mislead visitors**, and fabricated reviews/testimonials are prohibited or regulated in many jurisdictions
(consumer-protection and advertising law, e.g. FTC rules in the US, the EU's Unfair Commercial Practices rules, and local equivalents). Search engines treat auto-generated or fake user content as spam, and it can harm the reputation and rankings of your site.

Legitimate, lower-risk uses: **staging/demo sites**, UI testing, or drafting suggested replies that a human edits and publishes openly under a real identity. Do not use it to fake social proof,
reviews or engagement on a live public site, and do not post synthetic comments on sites you do not own.
**All consequences of using this plugin are entirely your own responsibility.**

## Privacy & data flow

Post content/excerpts are sent to Google's Gemini API to generate text. The API key is stored in the WordPress options table.

## Troubleshooting

| Problem | Fix |
|---|---|
| Jobs stay queued | WP-Cron is not firing — use a real server cron for `wp-cron.php` |
| API error | Check key, model name and quota in Google AI Studio |
| Stuck job | Jobs auto-reset after the timeout (default 600 s) |

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
