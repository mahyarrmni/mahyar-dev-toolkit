"""رابط Streamlit ابزار تحلیل سئو.

اجرا:  streamlit run seo/seo_analyzer/app.py
"""

import os
import sys
from pathlib import Path

import streamlit as st

# اجازه‌ی اجرای مستقیم با `streamlit run` (بدون نصب پکیج)
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from seo_analyzer.analyzer import SEOAnalyzer  # noqa: E402
from seo_analyzer.report_view import display_report  # noqa: E402

# لوگوی اختیاری: با متغیر محیطی SEO_ANALYZER_LOGO_URL تنظیم می‌شود
LOGO_URL = os.environ.get("SEO_ANALYZER_LOGO_URL", "")

# !!! این خط باید اولین دستور Streamlit باشد !!!
st.set_page_config(page_title="تحلیلگر سئو", layout="wide", initial_sidebar_state="collapsed")
st.markdown(
    """
    <style>
    /* ایمپورت فونت Vazir از CDN */
    @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v28.1.0/dist/font-face.css');

    body, .stApp, .stMarkdown, .stText, .css-1d391kg {
        direction: rtl !important;
        text-align: right !important;
        font-family: 'Vazir', sans-serif !important;
    }
    </style>
    """,
    unsafe_allow_html=True
)


# --- رابط کاربری اصلی Streamlit ---

col_logo, col_title = st.columns([1, 6])
with col_logo:
    # Placeholder for logo - replace with your image URL if needed
    if LOGO_URL:
        st.image(LOGO_URL, width=80)
with col_title:
    st.title("ابزار تحلیل سئو")
    st.caption("تحلیل On-Page SEO و مقایسه URL ها")

st.divider()

analysis_mode = st.radio("حالت تحلیل:", ("تحلیل یک URL", "مقایسه دو URL"), horizontal=True, label_visibility="collapsed")

if analysis_mode == "تحلیل یک URL":
    url1 = st.text_input("🔗 آدرس صفحه:", placeholder="https://example.com", key="url1_single")
    url2 = None
    analyze_button_text = "🚀 تحلیل کن"
else:
    col_url1, col_url2 = st.columns(2)
    with col_url1:
        url1 = st.text_input("🔗 URL اول:", placeholder="https://site1.com", key="url1_compare")
    with col_url2:
        url2 = st.text_input("🔗 URL دوم:", placeholder="https://site2.com", key="url2_compare")
    analyze_button_text = "⚖️ مقایسه کن"

analyze_clicked = st.button(analyze_button_text, type="primary", use_container_width=True)

# --- اجرای تحلیل و نمایش نتایج ---
if analyze_clicked:
    valid_input = True
    if not url1:
        st.warning("⚠️ URL اول را وارد کنید.")
        valid_input = False
    if analysis_mode == "مقایسه دو URL" and not url2:
         st.warning("⚠️ برای مقایسه، URL دوم را نیز وارد کنید.")
         valid_input = False

    if valid_input:
        if url2:
            res_col1, res_col2 = st.columns(2)
        else:
            res_col1, _ = st.columns([3, 1]) # ستون بزرگتر برای تک URL

        # --- تحلیل URL اول ---
        with res_col1:
            st.markdown(f"### `{url1}`")
            analyzer1 = None
            with st.spinner(f"در حال تحلیل {url1}..."):
                try:
                    analyzer1 = SEOAnalyzer(url1)
                    fetch_success = analyzer1.fetch()
                    if fetch_success:
                         analyzer1.analyze() # شامل محاسبه امتیاز
                         st.success("✅ تحلیل کامل شد.")
                    #else: # خطا در fetch در display_report نمایش داده می‌شود
                    #    st.error(f"❌ دریافت اطلاعات ناموفق.")

                except Exception as e:
                    st.error(f"❌ خطای کلی در تحلیل URL اول: {e}")
                    st.exception(e) # نمایش جزئیات خطا برای دیباگ

            # نمایش گزارش (حتی اگر fetch ناموفق بوده تا خطا نمایش داده شود)
            if analyzer1:
                 display_report(analyzer1, res_col1)
            # else: # اگر ساخت آنالایزر خطا داد
            #      st.error("امکان ایجاد آنالایزر برای URL اول وجود نداشت.")

        # --- تحلیل URL دوم (اگر در حالت مقایسه هستیم) ---
        if url2:
            with res_col2:
                st.markdown(f"### `{url2}`")
                analyzer2 = None
                with st.spinner(f"در حال تحلیل {url2}..."):
                    try:
                        analyzer2 = SEOAnalyzer(url2)
                        fetch_success_2 = analyzer2.fetch()
                        if fetch_success_2:
                             analyzer2.analyze()
                             st.success("✅ تحلیل کامل شد.")
                        #else:
                        #    st.error(f"❌ دریافت اطلاعات ناموفق.")

                    except Exception as e:
                        st.error(f"❌ خطای کلی در تحلیل URL دوم: {e}")
                        st.exception(e)

                # نمایش گزارش
                if analyzer2:
                    display_report(analyzer2, res_col2)
                # else:
                #     st.error("امکان ایجاد آنالایزر برای URL دوم وجود نداشت.")

st.divider()
st.caption("تیم برنامه نویسی توپ گیم")
