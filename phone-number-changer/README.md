# Phone Number Changer

Replace a phone number across your whole WordPress site in every common Iranian format.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-1.1-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.0%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟡 Medium — **permanent mode rewrites database content**; always back up first

## What it does

Finds a phone number in all common Iranian formats and replaces it with the new one in the matching format:

| Format | Example |
|---|---|
| Local | `09123456789` |
| Country code | `+989123456789` |
| 0098 | `00989123456789` |
| Without leading zero | `9123456789` |

- **Temporary mode** — substitutes the number in the **HTML output only**; the database is untouched and turning it off reverts instantly.
- **Permanent mode** — rewrites the stored content in **post content/excerpt/title, post meta and options** (not custom tables), with a **dry-run preview** and a confirmation step before applying.

## Requirements

WordPress 5.0+ · PHP 7.4+

## Installation & usage

1. Upload and activate. Open **Tools → Phone Number Changer**.
2. Enter the **old** and **new** numbers.
3. **Try temporary mode first** and check your site (including contact page, header/footer, widgets).
4. For a permanent change: **take a full database backup**, run the **preview (dry-run)**, review the counts and sample lines, then apply.
5. Clear page/object caches afterwards.

## Limits

Numbers stored in custom tables, images, PDFs, JavaScript files or page-builder data in unusual encodings may not be found. Temporary mode only affects what is rendered by WordPress.

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
