# Smart Alt Setter (آلت تصویر مهیار)

Bulk-add ALT text only to images actually used in your published posts and pages — ignoring orphaned media-library files.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-1.1.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.0%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟡 Medium — writes metadata in bulk; texts are applied **in order**

## What it does

- Scans published **posts and pages** for images inserted with the standard `wp-image-ID` class
- Takes ALT texts from a textarea (one per line) or the **first column of a CSV**
- Assigns them, in order, **only to used images that have no ALT yet** — existing ALT texts are never overwritten
- Shows how many used images are still missing ALT, so you know how many lines to prepare

> ⚠️ **Important:** the plugin does not look at image content. Line 1 goes to the first image found, line 2 to the second, and so on. Prepare your list in that order, run on a staging copy, and spot-check results.
> Images added by page builders or as featured images only (without the `wp-image-ID` class in the content) are not detected.

## Installation & usage

1. Upload and activate. Open **آلت تصویر مهیار** in the sidebar (requires an administrator).
2. Read the "images without ALT" counter.
3. Paste your ALT texts (one per line) **or** upload a CSV.
4. Press **افزودن ALTها**, then verify a few images in the media library / front end.

## Changes in 1.1.0

Nonce/CSRF protection, capability check, input sanitisation and escaped output; removed an externally hot-linked menu icon; empty lines are skipped; live counter added.

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
