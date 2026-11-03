# Bulk Image Alt (آلت گروهی تصویر)

Bulk-set ALT text for media-library images that have none, with an audit report and a guarded “delete all ALT” action.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-1.3.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.0%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟡 Medium — bulk metadata changes; includes an irreversible delete action

## What it does

- Adds ALT texts (textarea, one per line, or first CSV column) to **every image in the media library that has no ALT**, in order
- **Report:** total images, with ALT, without ALT, and a list of images without ALT (links to the files)
- **Delete all ALT texts** — irreversible; requires a confirmation checkbox and a nonce

> ⚠️ ALT texts are assigned **in order**, not by image content; the plugin cannot match text to picture. Use it when you have a prepared, ordered list, or as a first pass you then correct.
> It works on the media library as a whole (not only on images used in posts) — use *Smart Alt Setter* if you only want used images.

## Installation & usage

1. Upload and activate. Open **آلت گروهی تصویر** in the sidebar.
2. Check the report numbers.
3. Paste texts or upload a CSV → **افزودن ALTها**.
4. Re-open the report to confirm the "without ALT" count dropped.
5. (Optional, destructive) To wipe all ALT texts, tick the confirmation and press **حذف تمام ALTها** — back up first.

## Changes in 1.3.0

Nonce/CSRF protection on both actions, capability check, sanitised input, escaped output; plugin description aligned with real behaviour.

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
