# 🤖 Decor Chatbot LLM — دستیار دکوراسیون با مدل زبانی فارسی

<div align="center">

![Python](https://img.shields.io/badge/Python-3.9%2B-blue?logo=python)
![Flet](https://img.shields.io/badge/Flet-Desktop%20UI-purple)
![HuggingFace](https://img.shields.io/badge/HuggingFace-Transformers-yellow?logo=huggingface)
![License](https://img.shields.io/badge/License-MIT-green)

**چت‌بات آفلاین دکوراسیون | مدل GPT-2 فارسی | بدون اینترنت**

</div>

---

## 🇮🇷 درباره این ابزار

نسخه آفلاین چت‌بات دکوراسیون که از مدل زبانی `HooshvareLab/gpt2-fa` (GPT-2 فارسی) استفاده می‌کند. بدون نیاز به اینترنت پس از دانلود مدل — کاملاً روی دستگاه شما اجرا می‌شود.

### ✨ قابلیت‌ها

- 🧠 **مدل زبانی فارسی** — HooshvareLab/gpt2-fa
- 📡 **آفلاین** — پس از دانلود مدل به اینترنت نیاز ندارد
- 🗣️ **گفتگوی فارسی** — رابط کاملاً RTL
- 🔍 **تشخیص موضوع** — فقط به سوالات دکوراسیون پاسخ می‌دهد
- 🧵 **پردازش غیرهمزمان** — UI هنگام پردازش قفل نمی‌شود

> **📦 توجه:** اولین اجرا حدود ۵۰۰MB مدل را دانلود می‌کند.

---

## 🇬🇧 About

An offline Persian-language chatbot for interior design using the `HooshvareLab/gpt2-fa` model (~500MB). After the initial model download, runs completely offline. Built with Flet for a native desktop experience with RTL Persian UI.

**Keywords:** persian GPT-2 python, HooshvareLab gpt2-fa, offline persian chatbot, huggingface transformers flet, persian language model python, interior design chatbot offline, RTL chatbot python

---

## 🚀 نصب و اجرا / Installation

```bash
pip install flet transformers torch

python decor2_AI.py
```

> اولین اجرا: مدل `HooshvareLab/gpt2-fa` دانلود می‌شود (~500MB)

---

## 🛠️ فناوری‌های استفاده‌شده / Tech Stack

| کتابخانه | کاربرد |
|----------|--------|
| `Flet` | رابط کاربری دسکتاپ RTL |
| `transformers` | بارگذاری و اجرای مدل |
| `torch` | موتور پردازش مدل |
| `threading` | پردازش موازی |

---

## 📜 License

MIT — آزاد برای استفاده شخصی و آموزشی.
