# 📊 SEO Analyzer — تحلیلگر حرفه‌ای سئو و مقایسه URL

<div align="center">

![Python](https://img.shields.io/badge/Python-3.9%2B-blue?logo=python)
![Streamlit](https://img.shields.io/badge/Streamlit-Web%20UI-FF4B4B?logo=streamlit)
![BeautifulSoup](https://img.shields.io/badge/BeautifulSoup4-Scraping-green)
![License](https://img.shields.io/badge/License-MIT-green)

**تحلیل On-Page SEO | مقایسه دو URL | امتیازدهی | پیشنهادات بهبود**

</div>

---

## 🇮🇷 درباره این ابزار

یک ابزار جامع تحلیل سئو با رابط فارسی RTL. هر صفحه وب را از دیدگاه سئوی On-Page بررسی می‌کند، امتیاز می‌دهد و پیشنهادات اولویت‌بندی‌شده ارائه می‌دهد. قابلیت مقایسه دو URL با هم را نیز دارد.

### ✨ قابلیت‌های تحلیل

**متا و ساختار:** Title، Meta Description، تگ‌های H1-H6، Canonical، Viewport، زبان صفحه

**محتوا:** تعداد کلمات، کلمات کلیدی پرتکرار، نسبت متن به HTML

**تصاویر و لینک:** Alt Text، لینک‌های داخلی/خارجی، لینک‌های خالی

**فنی:** robots.txt، Sitemap، HTTPS، Open Graph، Twitter Cards، Schema.org (JSON-LD)

### 📊 سیستم امتیازدهی

امتیاز ۰ تا ۱۰۰ بر اساس ۱۵+ معیار با اولویت‌بندی 🔴 بالا / 🟠 متوسط / 🟢 مثبت

---

## 🇬🇧 About

A comprehensive Persian-language On-Page SEO analyzer built with Streamlit. Analyzes meta tags, headings, images, links, technical files, social tags, Schema markup, and content. Provides a 0-100 SEO score with prioritized recommendations. Supports side-by-side URL comparison.

**Keywords:** python SEO analyzer, on-page SEO checker python, streamlit SEO tool, meta tag analyzer python, schema markup checker, open graph checker python, robots.txt sitemap checker, SEO score python, persian SEO tool, URL comparison SEO

---

## 🚀 نصب و اجرا / Installation

```bash
pip install streamlit requests beautifulsoup4 lxml

streamlit run Seoanalyzor.py
```

---

## 🛠️ فناوری‌های استفاده‌شده / Tech Stack

| کتابخانه | کاربرد |
|----------|--------|
| `Streamlit` | رابط کاربری RTL فارسی |
| `BeautifulSoup4` + `lxml` | تجزیه HTML |
| `requests` | دریافت صفحات |
| `re` | استخراج الگوها |
| `collections.Counter` | تحلیل فراوانی کلمات |
| `json` | تجزیه Schema JSON-LD |

---

## 📜 License

MIT — آزاد برای استفاده شخصی و آموزشی.
