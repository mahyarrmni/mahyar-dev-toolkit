# 📈 Crypto Analyzer — تحلیلگر ارزهای دیجیتال

<div align="center">

![Python](https://img.shields.io/badge/Python-3.9%2B-blue?logo=python)
![Streamlit](https://img.shields.io/badge/Streamlit-Web%20UI-FF4B4B?logo=streamlit)
![CoinGecko](https://img.shields.io/badge/CoinGecko-API-green)
![License](https://img.shields.io/badge/License-MIT-green)

**داشبورد تحلیل تکنیکال | اندیکاتورهای SMA/EMA/RSI | پیش‌بینی ۵ روزه**

</div>

---

## 🇮🇷 درباره این ابزار

داشبورد تحلیل ارزهای دیجیتال با Streamlit. داده‌های ۹۰ روز اخیر را از CoinGecko دریافت می‌کند، اندیکاتورهای تکنیکال محاسبه می‌کند و پیش‌بینی ۵ روزه با رگرسیون خطی ارائه می‌دهد.

### ✨ قابلیت‌ها

- 📊 **داده‌های ۹۰ روزه** از CoinGecko API (رایگان)
- 📉 **اندیکاتورهای تکنیکال:**
  - SMA (میانگین متحرک ساده) — ۷، ۲۵، ۹۹ روزه
  - EMA (میانگین متحرک نمایی) — ۱۲، ۲۶ روزه
  - RSI (شاخص قدرت نسبی) — ۱۴ روزه
  - Momentum
- 🔮 **پیش‌بینی ۵ روزه** با Linear Regression
- 📰 **اخبار ترجمه‌شده** با googletrans
- 🪙 **پشتیبانی از:** BTC، ETH، BNB، SOL، XRP، NOT

---

## 🇬🇧 About

A Streamlit crypto analysis dashboard using CoinGecko's free API. Displays 90 days of price data with technical indicators (SMA, EMA, RSI, Momentum) and generates 5-day Linear Regression price predictions. Includes auto-translated Persian news.

**Keywords:** python crypto analyzer, bitcoin technical analysis python, streamlit crypto dashboard, CoinGecko API python, RSI EMA SMA python, cryptocurrency prediction python, linear regression crypto, BTC ETH price analysis

---

## 🚀 نصب و اجرا / Installation

```bash
pip install streamlit pandas numpy scikit-learn requests googletrans==4.0.0rc1

streamlit run BTC.py
```

---

## 🛠️ فناوری‌های استفاده‌شده / Tech Stack

| کتابخانه | کاربرد |
|----------|--------|
| `Streamlit` | داشبورد تعاملی |
| `pandas` | پردازش داده‌های سری زمانی |
| `numpy` | محاسبات ریاضی |
| `scikit-learn` | Linear Regression |
| `requests` | دریافت داده از CoinGecko |
| `googletrans` | ترجمه اخبار به فارسی |

---

## 📜 License

MIT — آزاد برای استفاده شخصی و آموزشی.
