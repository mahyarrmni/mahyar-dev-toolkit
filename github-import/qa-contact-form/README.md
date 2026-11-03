# QA Contact Form

Production-ready WordPress contact form with a drag-and-drop builder and Iranian validators.

[فارسی](README.fa.md) · [mahyarhub.ir](https://mahyarhub.ir) · [Report a bug](https://t.me/mahyar_mmi)

![version](https://img.shields.io/badge/version-2.1.0-blue) ![wordpress](https://img.shields.io/badge/WordPress-5.8%2B-21759b) ![php](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![license](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> **Risk level:** 🟢 Low — stores submissions in your own database

[qarticleproject.ir](https://qarticleproject.ir)

## Features

- **Drag-and-drop field builder** — text, email, phone, textarea, select, checkbox, radio, file upload, date
- **Iranian validators** — mobile number, national ID (code melli), postal code
- **File upload** — configurable max size and allowed types
- **Auto-reply** — customisable confirmation e-mail to the sender
- **Telegram notification** and **Webhook** (Zapier / n8n / Make…) on every submission
- **Multi-layer anti-spam** — honeypot, rate limiting, optional reCAPTCHA
- **CSV export** of submissions · **live design editor** (colours, fonts, layout)

## Requirements

WordPress 5.8+ · PHP 7.4+ · working outgoing e-mail (an SMTP plugin is recommended for reliable delivery)

## Installation & usage

1. Upload and activate.
2. Open **QA Contact Form** in the admin sidebar.
3. Build the form (drag fields, set labels/validation), adjust design, configure auto-reply / Telegram / webhook if needed.
4. Copy the generated **shortcode** and paste it into any page or post.
5. Submit a test message; check the submissions list and your inbox.
6. Export submissions to CSV from the admin panel when needed.

## Privacy & data

Submissions (including uploaded files) are stored on your server. Telegram notifications and webhooks send the submitted data to the services you configure; reCAPTCHA (if enabled) contacts Google.
If you collect personal data from visitors, add a privacy notice/consent text and handle retention according to your local law (GDPR etc.).

## Troubleshooting

| Problem | Fix |
|---|---|
| E-mails not arriving | Configure SMTP; check spam folder and the sender address |
| Telegram not working | Verify bot token and chat ID; the bot must be allowed in the chat/channel |
| Uploads rejected | Check allowed types and PHP `upload_max_filesize` / `post_max_size` |

## ⚠️ Disclaimer

This plugin is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · License: GPL-2.0-or-later
