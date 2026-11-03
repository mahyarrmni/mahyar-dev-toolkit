# 🏠 Interior Design AI — بازطراحی فضای داخلی با هوش مصنوعی

<div align="center">

![Python](https://img.shields.io/badge/Python-3.9%2B-blue?logo=python)
![Flet](https://img.shields.io/badge/Flet-Desktop%20UI-purple)
![Replicate](https://img.shields.io/badge/Replicate-AI%20API-black)
![License](https://img.shields.io/badge/License-MIT-green)

**یک عکس از خانه‌ات بگیر — هوش مصنوعی آن را بازطراحی می‌کند**

</div>

---

## 🇮🇷 درباره این ابزار

یک اپلیکیشن دسکتاپ ساخته‌شده با Flet که از مدل هوش مصنوعی Replicate برای بازطراحی فضای داخلی خانه استفاده می‌کند. کافیست عکسی از اتاق، آشپزخانه یا هر فضای دیگری آپلود کنی — مدل AI سبک مدرن و دنج را روی آن پیاده می‌کند.

### ✨ قابلیت‌ها

- 📷 **آپلود تصویر** از سیستم
- 🤖 **بازطراحی با AI** — مدل Stable Diffusion / ControlNet
- 🎨 **سبک مدرن و دنج** — `modern interior design, cozy and stylish`
- 🖼️ **نمایش همزمان** تصویر اصلی و بازطراحی‌شده
- ⏳ **نمایش وضعیت** پردازش در لحظه

---

## 🇬🇧 About

A Flet desktop application that uses the Replicate API (Stable Diffusion / ControlNet) to redesign interior spaces. Upload a photo of any room and get an AI-generated modern redesign instantly.

**Keywords:** interior design AI python, room redesign AI, stable diffusion interior, flet desktop app python, AI home decorator, replicate API python example, controlnet interior design

---

## 🚀 نصب و اجرا / Installation

```bash
pip install flet requests

# اجرا
python "IMG DECOR.py"
```

> **⚠️ توجه:** قبل از اجرا، کلید API خود را از [replicate.com](https://replicate.com) دریافت کرده و در فایل جایگذاری کنید:
> ```python
> REPLICATE_API_TOKEN = "your_token_here"
> ```

---

## 🛠️ فناوری‌های استفاده‌شده / Tech Stack

| کتابخانه | کاربرد |
|----------|--------|
| `Flet` | رابط کاربری دسکتاپ |
| `requests` | ارتباط با Replicate API |
| `base64` | انکد کردن تصویر |
| `Replicate API` | مدل AI بازطراحی |

---

## 🔑 دریافت API Key

1. به [replicate.com](https://replicate.com) بروید
2. ثبت‌نام کنید
3. از بخش Account > API Tokens کلید خود را دریافت کنید

---

## 📜 License

MIT — آزاد برای استفاده شخصی و آموزشی.
