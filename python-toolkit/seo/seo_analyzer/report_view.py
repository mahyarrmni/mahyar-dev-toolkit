"""نمایش گزارش تحلیل در Streamlit."""

import streamlit as st

from .analyzer import SEOAnalyzer


def display_report(analyzer, column):
    """نمایش گزارش و پیشنهادات یک آنالایزر در ستون مشخص شده."""
    if not isinstance(analyzer, SEOAnalyzer):
        column.error("خطا: شیء آنالایزر نامعتبر است.")
        return

    report, suggestions = analyzer.get_report()

    # نمایش خطا و توقف اگر fetch ناموفق بود
    if analyzer.error and not report:
        column.error(f"❌ **خطا در دریافت/تجزیه:** {analyzer.error}")
        if analyzer.status_code:
             column.info(f"کد وضعیت HTTP: {analyzer.status_code}")
        return

    # نمایش امتیاز کلی
    score_value = report.get('seo_score', 'N/A')
    score_color = "⚪" # Default color
    if isinstance(score_value, int):
        if score_value >= 85: score_color = "🟢" # Stricter threshold for green
        elif score_value >= 50: score_color = "🟠"
        else: score_color = "🔴"
    column.subheader(f"📊 امتیاز تخمینی سئو: {score_value}/100 {score_color}")
    if isinstance(score_value, int):
        column.progress(score_value)

    # نمایش خطاهای جزئی (اگر وجود دارد)
    analysis_errors = [v for k, v in report.items() if k.startswith("error_")]
    if analysis_errors:
        with column.expander("⚠️ خطاهای رخ داده حین تحلیل"):
            for err_key, err_val in report.items():
                 if err_key.startswith("error_"):
                      st.warning(f"**{err_key.replace('error_', '')}:** {err_val}")

    # نمایش پیشنهادات
    if suggestions:
        column.subheader("💡 پیشنهادات:")
        # دسته‌بندی پیشنهادات
        # (منطق دسته‌بندی قبلی حفظ شده)
        high_priority = []
        medium_priority = []
        positive_notes = []
        error_notes = []
        high_keywords = ['title', 'description', 'h1', 'https', 'robots', 'sitemap', 'canonical', 'viewport', 'index', 'schema'] # Added schema

        for s in suggestions:
             s_type = s.get('type', 'medium')
             metric_lower = s.get('metric', '').lower()
             # Check if any high-priority keyword is in the metric name
             is_high = any(f"({keyword})" in metric_lower or keyword == metric_lower or metric_lower.startswith(keyword) for keyword in high_keywords)

             if s_type == 'error': error_notes.append(s)
             elif s_type == 'positive': positive_notes.append(s)
             elif is_high: high_priority.append(s)
             else: medium_priority.append(s)

        if high_priority:
             with column.expander("🔴 اولویت بالا", expanded=True): # باز باشد
                 for sug in high_priority:
                     st.markdown(f"- **{sug.get('metric', '')}:** {sug.get('issue', '')}\n  _{sug.get('recommendation', '')}_")
        if medium_priority:
             with column.expander("🟠 اولویت متوسط", expanded=False): # بسته باشد
                 for sug in medium_priority:
                     st.markdown(f"- **{sug.get('metric', '')}:** {sug.get('issue', '')}\n  _{sug.get('recommendation', '')}_")
        if positive_notes:
             with column.expander("🟢 نکات مثبت", expanded=False): # بسته باشد
                 for sug in positive_notes:
                     st.markdown(f"- **{sug.get('metric', '')}:** {sug.get('issue', '')}\n  _{sug.get('recommendation', '')}_")
        # Error notes already displayed above if present

    column.divider()

    # --- نمایش جزئیات تحلیل در Expanders ---
    column.subheader("📋 جزئیات تحلیل:")

    # Helper functions
    def get_report_val(key_path, default="N/A"):
        value = report
        try:
            for key in key_path.split('.'):
                 if isinstance(value, dict): value = value.get(key)
                 else: return default
            return value if value is not None else default
        except Exception: return default

    def format_text(text, length=60): # Shorter default length
         if not isinstance(text, str) or text == "N/A": return text
         return text[:length] + "..." if len(text) > length else text

    def display_json(data, default_expanded=False):
         if (isinstance(data, dict) and data) or (isinstance(data, list) and data):
             st.json(data, expanded=default_expanded)
         else: st.write("`ندارد`")

    # --- Expander Sections (All closed by default) ---
    with column.expander("۱. اطلاعات پایه", expanded=False):
        st.markdown(f"**URL نهایی:** `{format_text(analyzer.final_url or analyzer.url, 100)}`")
        st.markdown(f"**کد وضعیت HTTP:** `{analyzer.status_code or 'N/A'}`")
        st.markdown(f"**پروتکل HTTPS:** {get_report_val('https_status')}")
        st.markdown(f"**عنوان (Title):** `{format_text(get_report_val('title.text'))}` | طول: {get_report_val('title.length', 0)}")
        st.markdown(f"**توضیحات متا (Desc):** `{format_text(get_report_val('meta_description.text'), 100)}` | طول: {get_report_val('meta_description.length', 0)}")
        st.markdown(f"**زبان (Lang):** `{get_report_val('language_declaration')}`")
        st.markdown(f"**فاوآیکون:** {get_report_val('favicon.status')}")
        st.markdown(f"**تعداد تگ Title:** {get_report_val('duplicate_meta_within_page.title_count', 0)}")
        st.markdown(f"**تعداد تگ Desc:** {get_report_val('duplicate_meta_within_page.description_count', 0)}")

    with column.expander("۲. تیترها", expanded=False):
        for i in range(1, 7):
            count = get_report_val(f"headings.h{i}.count", 0)
            if count > 0: st.markdown(f"**H{i}:** {count} عدد")
        st.markdown(f"**موقعیت H1:** {get_report_val('h1_position')}")
        # نمایش نمونه تیترها می‌تواند اینجا اضافه شود اگر نیاز بود

    with column.expander("۳. محتوا و کلمات کلیدی", expanded=False):
        st.markdown(f"**تعداد کلمات:** {get_report_val('word_count')}")
        st.markdown(f"**نسبت متن به HTML:** {get_report_val('text_html_ratio')}")
        st.markdown("**کلمات کلیدی پرتکرار (تکی):**")
        display_json(get_report_val('keywords.top_single_keywords'))
        st.markdown("**عبارات پرتکرار (دو کلمه‌ای):**")
        display_json(get_report_val('keywords.top_two_word_phrases'))
        emphasized = get_report_val('keywords.emphasized_keywords', [])
        st.markdown(f"**کلمات کلیدی تاکیدی:** {len(emphasized)} عدد")

    with column.expander("۴. تصاویر", expanded=False):
        st.markdown(f"**تعداد کل:** {get_report_val('images.total', 0)}")
        st.markdown(f"**با Alt Text:** {get_report_val('images.with_alt', 0)}")
        st.markdown(f"**بدون Alt یا Alt خالی:** {get_report_val('images.missing_or_empty_alt', 0)}")
        st.markdown(f"**Alt Text های تکراری:** {get_report_val('duplicate_alt_texts.count', 0)} مورد")

    with column.expander("۵. لینک‌ها", expanded=False):
        st.markdown(f"**کل لینک‌های وب:** {get_report_val('links.total_http_links', 0)}")
        st.markdown(f"**داخلی:** {get_report_val('links.internal', 0)}")
        st.markdown(f"**خارجی:** {get_report_val('links.external', 0)}")
        st.markdown(f"**خارجی (nofollow/sponsored/ugc):** {get_report_val('links.nofollow_sponsored_ugc_external', 0)}")
        st.markdown(f"**لینک‌های بدون متن/Alt:** {get_report_val('empty_anchors.empty_text_or_alt', 0)}")
        st.markdown(f"**لینک‌های با Href خالی/#:** {get_report_val('empty_anchors.empty_or_hash_href', 0)}")

    with column.expander("۶. تنظیمات فنی", expanded=False):
        st.markdown(f"**robots.txt:** {get_report_val('robots_txt.status')}")
        st.markdown(f"**Sitemap:** {get_report_val('sitemap.status')} `{format_text(get_report_val('sitemap.url'), 50)}`")
        st.markdown(f"**تگ کنونیکال:** `{format_text(get_report_val('canonical_tag'), 100)}`")
        st.markdown(f"**تگ Viewport:** `{format_text(get_report_val('viewport_tag'), 100)}`")

    with column.expander("۷. اشتراک‌گذاری و داده ساخت‌یافته", expanded=False):
        st.markdown(f"**Open Graph:** {get_report_val('open_graph.count', 0)} تگ")
        st.markdown(f"**Twitter Cards:** {get_report_val('twitter_cards.count', 0)} تگ")
        schema_detected = get_report_val('schema_markup.detected')
        st.markdown(f"**Schema.org:** {'✅ یافت شد' if schema_detected else '❌ یافت نشد'}")
        if schema_detected:
            types = get_report_val('schema_markup.types', [])
            errors = get_report_val('schema_markup.parsing_errors', [])
            st.markdown(f"  - انواع: `{format_text(', '.join(types)) or 'نامشخص'}`")
            if errors: st.error(f"  - خطاهای تجزیه: {len(errors)} مورد")

    with column.expander("۸. عناصر محتوایی", expanded=False):
        st.markdown(f"**جداول:** {get_report_val('content_tables.count', 0)}")
        st.markdown(f"**لیست‌ها:** {get_report_val('content_lists.count', 0)}")
        st.markdown(f"**ویدئو/صوت:** {get_report_val('content_media.total', 0)}")
        st.markdown(f"**آی‌فریم:** {get_report_val('content_iframes.count', 0)}")
        st.markdown(f"**اینفوگرافیک:** {'✅' if get_report_val('content_infographics.detected_count', 0) > 0 else ' خیر'}")
