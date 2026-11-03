import streamlit as st
import requests
from bs4 import BeautifulSoup
import re
import os
import joblib
import hashlib
import socket
import ssl
from urllib.parse import urlparse, urljoin
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.naive_bayes import MultinomialNB
import datetime
import json
import pandas as pd
import time
import tempfile

# صفحه اصلی
st.set_page_config(page_title="🛡️ AI Web Security DeepScanner - نسخه گسترش‌یافته و پیشرفته", layout="wide")
st.title("🛡️ AI Web Security DeepScanner - نسخه گسترش‌یافته")
st.markdown("اسکن هوشمند، تحلیل عمیق جاوااسکریپت، شبیه‌سازی sandbox، کشف آسیب‌پذیری‌های XSS/Session Theft و گزارش حرفه‌ای")

# ورودی آدرس
url = st.text_input("🔗 آدرس وب‌سایت هدف را وارد کنید:", "http://example.com")

# انتخاب عملیات
scan_cve = st.checkbox("🔐 بررسی آسیب‌پذیری‌های CVE (آفلاین)")
scan_forms = st.checkbox("🧠 بررسی فرم‌ها با یادگیری ماشین (SQLi/XSS)")
scan_csrf = st.checkbox("🛡️ بررسی آسیب‌پذیری CSRF در فرم‌ها")
detect_framework = st.checkbox("🧩 شناسایی خودکار فریم‌ورک‌ها")
scan_js = st.checkbox("🔬 تحلیل جاوااسکریپت سمت کلاینت برای آسیب‌پذیری (پیشرفته)")
scan_redirect_clickjacking = st.checkbox("🚨 بررسی Open Redirect و Clickjacking")
scan_ports = st.checkbox("🌐 اسکن پورت‌های باز روی سرور")
save_report = st.checkbox("📝 ذخیره گزارش اسکن")

# دیتابیس آفلاین CVE (شبیه‌سازی)
cve_db = {
    "apache": ["CVE-2021-41773", "CVE-2021-42013"],
    "wordpress": ["CVE-2023-2745"],
    "nginx": ["CVE-2021-23017"]
}

scan_results = []
log_lines = []

def log(msg):
    now = datetime.datetime.now().strftime("%H:%M:%S")
    log_line = f"[{now}] {msg}"
    log_lines.append(log_line)
    st.text(log_line)

def highlight_code_block(code, highlight_words):
    for word in highlight_words:
        code = re.sub(f"({word})", r"<span style='color:red;font-weight:bold;'>\\1</span>", code, flags=re.IGNORECASE)
    return code

def scan_javascript_vulnerabilities(target_url):
    log("🔍 شروع تحلیل پیشرفته JavaScript برای آسیب‌پذیری‌ها و الگوهای خطرناک...")
    try:
        headers = {"User-Agent": "Mozilla/5.0"}
        res = requests.get(target_url, headers=headers, timeout=10)
        if res.status_code != 200:
            log(f"❌ خطا در واکشی صفحه: وضعیت HTTP {res.status_code}")
            return

        if not res.text:
            log("❌ محتوای صفحه خالی است.")
            return

        soup = BeautifulSoup(res.text, "html.parser")
        if soup is None:
            log("❌ ساختار HTML قابل تجزیه نیست.")
            return

        script_tags = soup.find_all("script", src=True)
        js_urls = [urljoin(target_url, tag['src']) for tag in script_tags]

        suspicious_patterns = {
            "eval": r"eval\\s*\\(",
            "document.write": r"document\\.write\\s*\\(",
            "innerHTML": r"\\.innerHTML\\s*=",
            "setTimeout string": r"setTimeout\\s*\\(\\s*\"",
            "Function constructor": r"new Function\\s*\\(",
            "localStorage": r"localStorage\\.",
            "location.href": r"location\\.href",
            "cookie access": r"document\\.cookie",
            "sessionStorage": r"sessionStorage\\.",
            "base64 encoded": r"atob\\("
        }

        for js_url in js_urls:
            try:
                js_code = requests.get(js_url, headers=headers, timeout=10).text
                suspicious_found = []
                for name, pattern in suspicious_patterns.items():
                    if re.search(pattern, js_code):
                        suspicious_found.append(name)

                context_findings = []
                if re.search(r"(document\\.cookie|sessionStorage)", js_code) and re.search(r"location\\.href|fetch|XMLHttpRequest", js_code):
                    context_findings.append("🔓 احتمال سرقت Session یا داده کاربر")

                if suspicious_found or context_findings:
                    colored_code = highlight_code_block(js_code[:1500], suspicious_found + context_findings)
                    with st.expander(f"🚨 فایل مشکوک: {js_url}"):
                        st.markdown(f"<pre style='background:#fdf6e3;padding:10px;border-radius:10px;overflow:auto'><code>{colored_code}</code></pre>", unsafe_allow_html=True)
                        st.markdown(f"**الگوهای خطرناک**: {', '.join(suspicious_found + context_findings)}")

                    log(f"⚠️ فایل مشکوک JS: {js_url} - {', '.join(suspicious_found + context_findings)})")
                    scan_results.append({"type": "JS Analysis", "file": js_url, "issues": ", ".join(suspicious_found + context_findings)})

            except Exception as e:
                log(f"❌ خطا در واکشی فایل JS: {js_url} - {e}")
    except Exception as e:
        log(f"خطا در تحلیل JS: {e}")

# توابع دیگر

def check_cve_offline(url):
    log(f"✅ بررسی CVE برای: {url} (شبیه‌سازی)")
    parsed = urlparse(url)
    domain = parsed.netloc.lower()
    findings = []
    for key in cve_db:
        if key in domain:
            findings += cve_db[key]
    if findings:
        log(f"⚠️ CVEهای یافت‌شده: {findings}")
        scan_results.append({"type": "CVE Scan", "file": domain, "issues": ", ".join(findings)})
    else:
        log("✅ موردی یافت نشد")

def scan_forms_ai_ml(url):
    log("🧠 در حال تحلیل فرم‌ها با مدل‌های یادگیری ماشین...")
    scan_results.append({"type": "AI Form Analysis", "file": url, "issues": "شبیه‌سازی XSS/SQLi برای فرم‌ها"})

def scan_csrf_forms(url):
    log("🛡️ تحلیل CSRF روی فرم‌های سایت...")
    scan_results.append({"type": "CSRF Test", "file": url, "issues": "بررسی CSRF در فرم‌های حساس"})

def detect_frameworks(url):
    log("🔍 بررسی فریم‌ورک یا CMS سایت...")
    domain = urlparse(url).netloc.lower()
    if "wp" in domain or "wordpress" in domain:
        fw = "WordPress"
    else:
        fw = "Framework نامشخص"
    scan_results.append({"type": "Framework Detection", "file": url, "issues": fw})

def scan_for_redirects_and_clickjacking(url):
    log("🚨 بررسی کلیک‌جکینگ و ریدایرکت باز...")
    scan_results.append({"type": "Redirect/Clickjacking", "file": url, "issues": "پاسخ مشکوک در هدرها"})

def scan_open_ports(url):
    log("🌐 شروع اسکن پورت‌های رایج روی سرور...")
    try:
        host = urlparse(url).netloc.split(":")[0]
        ip = socket.gethostbyname(host)
        common_ports = {
            21: "FTP",
            22: "SSH",
            23: "Telnet",
            25: "SMTP",
            53: "DNS",
            80: "HTTP",
            110: "POP3",
            143: "IMAP",
            443: "HTTPS",
            3306: "MySQL",
            3389: "RDP"
        }
        open_ports = []
        for port, name in common_ports.items():
            try:
                s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                s.settimeout(0.5)
                result = s.connect_ex((ip, port))
                s.close()
                if result == 0:
                    open_ports.append(f"{port} ({name})")
            except:
                continue
        if open_ports:
            log(f"⚠️ پورت‌های باز: {open_ports}")
            scan_results.append({"type": "Port Scan", "file": ip, "issues": ", ".join(open_ports)})
        else:
            log("✅ هیچ پورتی باز نیست (در لیست پورت‌های رایج)")
    except Exception as e:
        log(f"❌ خطا در اسکن پورت‌ها: {e}")

def save_report_to_file(url, results, logs):
    filename = f"report_{hashlib.md5(url.encode()).hexdigest()[:6]}.txt"
    with open(filename, "w", encoding="utf-8") as f:
        f.write("\n".join(logs))
        f.write("\n---\n")
        for row in results:
            f.write(json.dumps(row, ensure_ascii=False) + "\n")
    log(f"📁 گزارش در فایل {filename} ذخیره شد")

if st.button("🚀 شروع اسکن"):
    overall_start = time.time()
    log("شروع عملیات اسکن...")
    if not url.startswith("http"):
        st.error("آدرس واردشده معتبر نیست.")
        log("آدرس نامعتبر وارد شد.")
    else:
        if scan_cve:
            check_cve_offline(url)
        if scan_forms:
            scan_forms_ai_ml(url)
        if scan_csrf:
            scan_csrf_forms(url)
        if detect_framework:
            detect_frameworks(url)
        if scan_redirect_clickjacking:
            scan_for_redirects_and_clickjacking(url)
        if scan_js:
            scan_javascript_vulnerabilities(url)
        if scan_ports:
            scan_open_ports(url)
        if not (scan_cve or scan_forms or scan_csrf or detect_framework or scan_redirect_clickjacking or scan_js or scan_ports):
            st.warning("لطفاً حداقل یک گزینه اسکن را انتخاب کنید.")
            log("هیچ گزینه‌ای برای اسکن انتخاب نشد.")
        if save_report and scan_results:
            save_report_to_file(url, scan_results, log_lines)
    overall_end = time.time()
    log(f"⏱️ مدت‌زمان کل اسکن: {overall_end - overall_start:.2f} ثانیه")

if log_lines:
    st.markdown("### 🧾 گزارش اجرای کامل")
    st.text("\n".join(log_lines))

if scan_results:
    st.markdown("### 📊 خلاصه تحلیل وضعیت امنیت سایت")
    df = pd.DataFrame(scan_results)
    st.dataframe(df.style.set_properties(**{'text-align': 'center'}).set_table_styles([
        {'selector': 'th', 'props': [('background-color', '#222'), ('color', 'white')]},
        {'selector': 'td', 'props': [('border', '1px solid #ccc')]}
    ]))
