# 📥 Auto Image Downloader — دانلودر خودکار تصاویر Unsplash

<div align="center">

![Python](https://img.shields.io/badge/Python-3.9%2B-blue?logo=python)
![Unsplash](https://img.shields.io/badge/Unsplash-No%20API%20Key-black)
![requests](https://img.shields.io/badge/requests-HTTP-blue)
![License](https://img.shields.io/badge/License-MIT-green)

**دانلود دسته‌ای از Unsplash | بدون API Key | سازماندهی خودکار پوشه**

</div>

---

## 🇮🇷 درباره این ابزار

ابزاری برای دانلود انبوه تصاویر با کیفیت از Unsplash بدون نیاز به API Key. تصاویر را بر اساس کلمه کلیدی در پوشه‌های جداگانه ذخیره می‌کند.

### ✨ قابلیت‌ها

- 🔑 **بدون API Key** — مستقیم از Unsplash
- 📁 **سازماندهی خودکار** — هر کلمه کلیدی = یک پوشه جداگانه
- 🔢 **تعداد قابل تنظیم** — ۱ تا ۵۰ تصویر به ازای هر کلمه
- 🏷️ **نام‌گذاری هوشمند** — `keyword_001.jpg`, `keyword_002.jpg`, ...
- 🔄 **پردازش چند کلمه کلیدی** — در یک اجرا

---

## 🇬🇧 About

A Python script that downloads bulk high-quality images from Unsplash without requiring an API key. Organizes downloads into keyword-named folders with sequential filenames. Supports multiple keywords and configurable download count (1-50 per keyword).

**Keywords:** unsplash bulk download python, download images without API python, python image scraper unsplash, bulk image downloader python, keyword image download python, free stock photos python

---

## 🚀 نصب و اجرا / Installation

```bash
pip install requests beautifulsoup4

python autoimagedownloader.py
```

---

## ⚙️ تنظیمات / Configuration

در ابتدای فایل:

```python
keywords = ["nature", "architecture", "food"]  # کلمات کلیدی
num_images = 10  # تعداد تصویر به ازای هر کلمه (1-50)
output_dir = "downloaded_images"  # پوشه خروجی
```

---

## 🛠️ فناوری‌های استفاده‌شده / Tech Stack

| کتابخانه | کاربرد |
|----------|--------|
| `requests` | دانلود تصاویر |
| `BeautifulSoup4` | استخراج لینک تصاویر |
| `os` | مدیریت پوشه‌ها |

---

## 📜 License

MIT — آزاد برای استفاده شخصی و آموزشی.
