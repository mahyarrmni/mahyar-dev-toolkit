# 🛡️ Deep Security Scanner — اسکنر عمیق امنیت وب

<div align="center">

![Python](https://img.shields.io/badge/Python-3.9%2B-blue?logo=python)
![Streamlit](https://img.shields.io/badge/Streamlit-Web%20UI-FF4B4B?logo=streamlit)
![License](https://img.shields.io/badge/License-MIT-green)

**تحلیل JavaScript | اسکن پورت | تشخیص کد مشکوک با هایلایت**

</div>

---

## 🇮🇷 درباره این ابزار

نسخه پیشرفته‌تر اسکنر امنیتی با قابلیت تحلیل عمیق JavaScript و اسکن پورت‌های باز. کدهای مشکوک را با رنگ قرمز هایلایت می‌کند.

### ✨ قابلیت‌ها

**تحلیل JavaScript:**
- 🔴 تشخیص `eval()` — اجرای کد دلخواه
- 🔴 تشخیص `innerHTML` — تزریق HTML
- 🔴 دسترسی به `document.cookie` — سرقت کوکی
- 🔴 تشخیص Session Hijacking

**اسکن پورت:**
- 🔌 پورت‌های: FTP(21)، SSH(22)، Telnet(23)، SMTP(25)، HTTP(80)، HTTPS(443)، MySQL(3306)، RDP(3389)

**سایر قابلیت‌ها:**
- 🎨 **هایلایت کد مشکوک** با رنگ قرمز در خروجی
- 📊 گزارش کامل امنیتی

> ⚠️ **تنها برای تست امنیتی سایت‌های خودتان استفاده کنید.**

---

## 🇬🇧 About

An advanced web security scanner with deep JavaScript analysis and port scanning. Detects dangerous JS patterns (eval, innerHTML, cookie access, session theft), scans common ports, and highlights suspicious code in red for easy identification.

**Keywords:** javascript security scanner python, eval detection python, port scanner python, web security deep scan, session hijacking detection, streamlit security scanner, XSS javascript analysis python

---

## 🚀 نصب و اجرا / Installation

```bash
pip install streamlit requests beautifulsoup4

streamlit run DeepScanner.py
```

---

## 🛠️ فناوری‌های استفاده‌شده / Tech Stack

| کتابخانه | کاربرد |
|----------|--------|
| `Streamlit` | رابط کاربری وب |
| `BeautifulSoup4` | تجزیه HTML و JS |
| `requests` | ارتباط با سرور هدف |
| `socket` | اسکن پورت |
| `re` | تشخیص الگوهای مشکوک |

---

## 📜 License

MIT — آزاد برای استفاده شخصی و آموزشی.
