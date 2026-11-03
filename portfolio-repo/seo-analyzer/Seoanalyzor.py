import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
import streamlit as st
import re
from collections import Counter
import json  # برای تجزیه Schema

# --- Configuration ---
# دیگر نیازی به API_KEY نیست

# لیست توقف‌واژه‌ها (Stopwords)
FARSI_STOPWORDS = {"و", "در", "به", "از", "که", "را", "برای", "با", "است", "این", "آن", "می", "بر", "تا", "نیز", "یک", "هم", "اما", "یا", "اگر", "هر", "همه", "چرا", "چه", "چگونه", "روی", "بین", "زیرا", "دیگر", "مورد", "باشد", "فقط", "همین", "چیزی", "کنید", "گفت", "شد", "وی", "کنند", "پس", "های", "خود", "باید", "کرد", "دارد"}
ENGLISH_STOPWORDS = {"i", "me", "my", "myself", "we", "our", "ours", "ourselves", "you", "your", "yours", "yourself", "yourselves", "he", "him", "his", "himself", "she", "her", "hers", "herself", "it", "its", "itself", "they", "them", "their", "theirs", "themselves", "what", "which", "who", "whom", "this", "that", "these", "those", "am", "is", "are", "was", "were", "be", "been", "being", "have", "has", "had", "having", "do", "does", "did", "doing", "a", "an", "the", "and", "but", "if", "or", "because", "as", "until", "while", "of", "at", "by", "for", "with", "about", "against", "between", "into", "through", "during", "before", "after", "above", "below", "to", "from", "up", "down", "in", "out", "on", "off", "over", "under", "again", "further", "then", "once", "here", "there", "when", "where", "why", "how", "all", "any", "both", "each", "few", "more", "most", "other", "some", "such", "no", "nor", "not", "only", "own", "same", "so", "than", "too", "very", "s", "t", "can", "will", "just", "don", "should", "now"}

# هدرهای درخواست
REQUEST_HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
}

# --- تنظیمات صفحه Streamlit ---
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

# --- SEO Analyzer Class ---
class SEOAnalyzer:
    def __init__(self, url):
        # افزودن پروتکل پیش‌فرض در صورت نیاز
        if not re.match(r'^(?:http)s?://', url, re.IGNORECASE):
             self.url = 'http://' + url
        else:
             self.url = url
        self.soup = None
        self.report = {}
        self.suggestions = []
        self.error = None
        self.status_code = None
        self.final_url = None # URL نهایی پس از ریدایرکت‌ها

    def fetch(self):
        """صفحه وب را دریافت و تجزیه می‌کند."""
        try:
            r = requests.get(self.url, timeout=15, headers=REQUEST_HEADERS, allow_redirects=True)
            self.status_code = r.status_code
            self.final_url = r.url
            r.raise_for_status()
            r.encoding = r.apparent_encoding
            self.soup = BeautifulSoup(r.content, "lxml")
            return True
        except requests.exceptions.Timeout:
            self.error = "خطای Timeout: زمان پاسخ‌دهی سرور طولانی شد."
            return False
        except requests.exceptions.ConnectionError:
            self.error = "خطای اتصال: امکان برقراری ارتباط با سرور وجود ندارد."
            return False
        except requests.exceptions.HTTPError as e:
            self.error = f"خطای HTTP {self.status_code}: {e}"
            if r is not None and r.content:
                 try:
                     r.encoding = r.apparent_encoding
                     self.soup = BeautifulSoup(r.content, "lxml")
                     return True # اجازه ادامه تحلیل محدود
                 except Exception as parse_err:
                     self.error += f" | خطای تجزیه محتوای خطا: {parse_err}"
                     return False
            return False
        except requests.exceptions.MissingSchema:
             self.error = f"آدرس URL نامعتبر است (پروتکل http/https وجود ندارد؟): {self.url}"
             return False
        except requests.exceptions.InvalidURL:
             self.error = f"آدرس URL نامعتبر شناسایی شد: {self.url}"
             return False
        except requests.exceptions.RequestException as e:
            self.error = f"خطای نامشخص در درخواست: {e}"
            return False
        except Exception as e:
            self.error = f"خطای غیرمنتظره در fetch/تجزیه: {e}"
            return False

    def analyze(self):
        """اجرای تمام تحلیل‌های سئو (به جز سرعت)."""
        if not self.soup:
             if not self.error:
                 self.error = "محتوای صفحه برای تجزیه در دسترس نیست."
             return

        # اجرای تحلیل‌ها به ترتیب منطقی
        self._run_analysis_safe(self.analyze_basic_meta)
        self._run_analysis_safe(self.analyze_basic_structure)
        self._run_analysis_safe(self.analyze_content_details)
        self._run_analysis_safe(self.analyze_technical_files)
        self._run_analysis_safe(self.analyze_advanced)
        self._run_analysis_safe(self.analyze_content_elements)
        # محاسبه امتیاز در انتها
        self._run_analysis_safe(self.calculate_score)


    def _run_analysis_safe(self, analysis_func):
        """یک تابع تحلیل را با مدیریت خطا اجرا می‌کند."""
        # تابع check_files نیازی به soup ندارد
        if not self.soup and analysis_func not in [self.check_files, self.calculate_score]:
             return
        try:
            analysis_func()
        except Exception as e:
            error_msg = f"خطا در {analysis_func.__name__}: {e}"
            print(error_msg) # لاگ سرور
            self.report[f"error_{analysis_func.__name__}"] = error_msg
            self.suggestions.append({
                "metric": "Analysis Error",
                "issue": f"خطا در اجرای بخش '{analysis_func.__name__}'.",
                "recommendation": f"جزئیات: {e}. ممکن است این بخش از گزارش ناقص باشد.",
                "type": "error"
                })

    # --- تجزیه توابع تحلیل به بخش‌های منطقی‌تر ---
    def analyze_basic_meta(self):
        self.analyze_meta()
        self.check_https()
        self.check_language()
        self.check_favicon()

    def analyze_basic_structure(self):
        self.analyze_headings()
        self.analyze_images()
        self.analyze_links()

    def analyze_content_details(self):
        self.analyze_word_count() # باید قبل از پیشنهادات مربوط به تعداد کلمات اجرا شود
        self.analyze_keywords()

    def analyze_technical_files(self):
        self.check_files()

    def analyze_advanced(self):
        self.text_to_html_ratio()
        self.check_open_graph()
        self.check_twitter_cards()
        self.check_schema_markup()
        self.check_canonical()
        self.check_viewport()
        self.check_duplicate_meta()
        self.check_empty_anchors()
        self.check_h1_position()
        self.check_alt_duplication()

    def analyze_content_elements(self):
        self.check_iframes() # قبل از check_video_audio
        self.check_tables()
        self.check_lists()
        self.check_video_audio()
        self.check_for_infographics() # باید بعد از analyze_images باشد


    # --- جزئیات توابع تحلیل (بدون تابع pagespeed_insights) ---
    # ... (کپی کردن تمام توابع از analyze_meta تا check_for_infographics از کد قبلی) ...
    # START COPYING METHODS (analyze_meta to check_for_infographics)

    def analyze_meta(self):
        title_tag = self.soup.find("title")
        title = title_tag.string.strip() if title_tag and title_tag.string else "N/A"
        desc_tag = self.soup.find("meta", attrs={"name": "description"})
        desc = desc_tag.get("content", "").strip() if desc_tag and desc_tag.get("content") else "N/A"

        self.report["title"] = {"text": title, "length": len(title) if title != "N/A" else 0}
        self.report["meta_description"] = {"text": desc, "length": len(desc) if desc != "N/A" else 0}

        # پیشنهادات
        if title == "N/A":
            self.suggestions.append({"metric": "Title", "issue": "تگ عنوان (Title) یافت نشد.", "recommendation": "یک تگ `<title>` منحصر به فرد و جذاب (بین 10 تا 65 کاراکتر) که شامل کلمه کلیدی اصلی صفحه باشد، اضافه کنید."})
        elif len(title) < 10:
             self.suggestions.append({"metric": "Title", "issue": f"طول عنوان کوتاه است ({len(title)} کاراکتر).", "recommendation": "عنوان را توصیفی‌تر کنید (بهینه: 50-65 کاراکتر)."})
        elif len(title) > 65: # کمی افزایش طول مجاز
             self.suggestions.append({"metric": "Title", "issue": f"طول عنوان زیاد است ({len(title)} کاراکتر).", "recommendation": "عنوان را کوتاه کنید تا در نتایج جستجو کامل نمایش داده شود (بهینه: 50-65 کاراکتر)."})

        if desc == "N/A":
            self.suggestions.append({"metric": "Meta Description", "issue": "توضیحات متا (Meta Description) یافت نشد.", "recommendation": "یک تگ `<meta name='description'>` جذاب و مرتبط (بین 70 تا 160 کاراکتر) که کاربران را به کلیک ترغیب کند، اضافه کنید."})
        elif len(desc) < 70:
             self.suggestions.append({"metric": "Meta Description", "issue": f"طول توضیحات متا کوتاه است ({len(desc)} کاراکتر).", "recommendation": "توضیحات متا را کامل‌تر و جذاب‌تر کنید (بهینه: 120-155 کاراکتر)."})
        elif len(desc) > 160:
             self.suggestions.append({"metric": "Meta Description", "issue": f"طول توضیحات متا زیاد است ({len(desc)} کاراکتر).", "recommendation": "توضیحات متا را کوتاه کنید تا در نتایج جستجو کامل نمایش داده شود (بهینه: 120-155 کاراکتر)."})


    def analyze_headings(self):
        headings_data = {}
        for i in range(1, 7):
            headings = self.soup.find_all(f"h{i}")
            headings_data[f"h{i}"] = {"count": len(headings), "texts": [h.get_text(strip=True) for h in headings]}
        self.report["headings"] = headings_data

        # پیشنهادات
        h1_count = headings_data.get("h1", {}).get("count", 0)
        if h1_count == 0:
             self.suggestions.append({"metric": "Headings (H1)", "issue": "هیچ تگ H1 یافت نشد.", "recommendation": "از یک تگ H1 منحصر به فرد برای عنوان اصلی صفحه استفاده کنید که شامل کلمه کلیدی اصلی باشد."})
        elif h1_count > 1:
             self.suggestions.append({"metric": "Headings (H1)", "issue": f"{h1_count} تگ H1 یافت شد.", "recommendation": "بهتر است فقط از یک تگ H1 در هر صفحه استفاده کنید. بقیه عناوین مهم را با H2, H3 و ... مشخص کنید."})

        total_headings = sum(data.get("count", 0) for data in headings_data.values())
        # دسترسی به word_count با احتیاط
        word_count_report = self.report.get('word_count', 0)
        if isinstance(word_count_report, int) and total_headings < 3 and word_count_report > 300:
            self.suggestions.append({"metric": "Headings (Structure)", "issue": "تعداد کل تیترها (H2-H6) کم است.", "recommendation": "برای بهبود خوانایی و ساختار محتوا، از تیترهای بیشتری (H2, H3, ...) استفاده کنید و متن را به بخش‌های کوچک‌تر تقسیم کنید."})


    def analyze_images(self):
        images = self.soup.find_all("img")
        total_images = 0
        with_alt = 0
        missing_or_empty_alt = 0
        alt_texts = [] # ذخیره alt ها برای بررسی تکرار
        image_srcs = []

        for img in images:
            src = img.get("src", "")
            if not src or src.startswith('data:image'):
                 continue # نادیده گرفتن تصاویر بدون src یا data URI
            total_images += 1
            image_srcs.append(src)
            alt = img.get("alt")

            if alt is not None:
                alt_stripped = alt.strip()
                if alt_stripped:
                    with_alt += 1
                    alt_texts.append(alt_stripped) # فقط alt های غیر خالی ذخیره شوند
                else: # alt وجود دارد ولی خالی است
                     missing_or_empty_alt += 1
            else: # اتریبیوت alt اصلا وجود ندارد
                missing_or_empty_alt += 1

        self.report["images"] = {
            "total": total_images,
            "with_alt": with_alt,
            "missing_or_empty_alt": missing_or_empty_alt,
            "alt_texts": alt_texts, # ذخیره برای استفاده در check_alt_duplication
            "image_srcs": image_srcs
        }

        # پیشنهادات
        if total_images > 0 and missing_or_empty_alt > 0:
            percentage_missing = round((missing_or_empty_alt / total_images) * 100) if total_images > 0 else 0
            self.suggestions.append({"metric": "Images (Alt Text)", "issue": f"{missing_or_empty_alt} تصویر ({percentage_missing}%) فاقد متن جایگزین (Alt Text) یا دارای متن خالی هستند.", "recommendation": "برای تمام تصاویر معنادار، متن جایگزین توصیفی اضافه کنید. برای تصاویر تزئینی، از `alt=\"\"` (alt خالی) استفاده کنید."})
        word_count_report = self.report.get('word_count', 0)
        if total_images == 0 and isinstance(word_count_report, int) and word_count_report > 500:
            self.suggestions.append({"metric": "Images", "issue": "هیچ تصویری در صفحه یافت نشد.", "recommendation": "افزودن تصاویر مرتبط می‌تواند به جذابیت بصری، درک بهتر محتوا و بهبود سئو کمک کند."})


    def analyze_links(self):
        internal_links = 0
        external_links = 0
        nofollow_sponsored_ugc_external = 0
        internal_anchors_set = set() # استفاده از set برای جلوگیری از تکرار
        external_anchors_set = set()

        base_url = self.final_url if self.final_url else self.url
        if not base_url:
            # self.error = "URL پایه برای تحلیل لینک‌ها در دسترس نیست." # خطا ممکن است قبلا ثبت شده باشد
            return

        try:
            parsed_base = urlparse(base_url)
            base_netloc = parsed_base.netloc
        except ValueError:
             # self.error = f"URL پایه نامعتبر است: {base_url}"
             return

        all_links = self.soup.find_all("a", href=True)

        for link in all_links:
            href = link['href'].strip()
            if not href or href.startswith(('#', 'mailto:', 'tel:', 'javascript:')):
                continue

            anchor_text = link.get_text(strip=True)
            try:
                full_href = urljoin(base_url, href)
                parsed_href = urlparse(full_href)
            except ValueError:
                continue

            if parsed_href.scheme in ('', 'http', 'https'):
                 # لینک داخلی
                 if not parsed_href.netloc or parsed_href.netloc == base_netloc:
                     internal_links += 1
                     if anchor_text: internal_anchors_set.add(anchor_text)
                 # لینک خارجی
                 elif parsed_href.netloc:
                     external_links += 1
                     if anchor_text: external_anchors_set.add(anchor_text)
                     rel_values = link.get('rel', [])
                     if isinstance(rel_values, str): rel_values = rel_values.split()
                     if any(rel in rel_values for rel in ['nofollow', 'sponsored', 'ugc']):
                          nofollow_sponsored_ugc_external += 1

        self.report["links"] = {
            "total_http_links": internal_links + external_links,
            "internal": internal_links,
            "external": external_links,
            "nofollow_sponsored_ugc_external": nofollow_sponsored_ugc_external,
            # "internal_anchors": list(internal_anchors_set), # ذخیره انکر تکست‌ها اختیاری است
            # "external_anchors": list(external_anchors_set)
        }

        # پیشنهادات
        word_count_report = self.report.get('word_count', 0)
        if isinstance(word_count_report, int):
            if internal_links < 3 and word_count_report > 300:
                self.suggestions.append({"metric": "Internal Links", "issue": "تعداد لینک‌های داخلی کم است.", "recommendation": "برای بهبود ناوبری سایت و توزیع PageRank، به صفحات مرتبط دیگر در سایت خود لینک دهید."})
            if external_links == 0 and word_count_report > 500:
                self.suggestions.append({"metric": "External Links", "issue": "هیچ لینک خارجی یافت نشد.", "recommendation": "لینک دادن به منابع معتبر خارجی (در صورت لزوم) می‌تواند به اعتبار محتوای شما بیافزاید. از `rel='nofollow'` یا `rel='sponsored'` / `rel='ugc'` برای لینک‌های تبلیغاتی یا غیرقابل اعتماد استفاده کنید."})

    def analyze_word_count(self):
        word_count = 0
        try:
            temp_soup = BeautifulSoup(str(self.soup), "lxml")
            for element in temp_soup(["script", "style", "noscript", "iframe", "head"]):
                element.decompose()
            text = temp_soup.get_text(separator=" ", strip=True)
            words = re.findall(r'\b[\w\u0600-\u06FF]+\b', text)
            word_count = len(words)
        except Exception as e:
             print(f"Error calculating word count: {e}")
             word_count = "خطا"

        self.report["word_count"] = word_count

        # پیشنهادات
        if isinstance(word_count, int):
            if word_count < 300:
                self.suggestions.append({"metric": "Word Count", "issue": f"تعداد کلمات کم است ({word_count} کلمه).", "recommendation": "محتوای جامع‌تر و عمیق‌تر معمولاً رتبه بهتری می‌گیرد (مگر اینکه هدف صفحه پاسخ سریع باشد). افزودن جزئیات، مثال‌ها یا بخش‌های مرتبط را در نظر بگیرید."})
            elif word_count > 4000: # کمی افزایش آستانه بالا
                 self.suggestions.append({"metric": "Word Count", "issue": f"تعداد کلمات بسیار زیاد است ({word_count} کلمه).", "recommendation": "اطمینان حاصل کنید که محتوا همچنان متمرکز و خوانا است. تقسیم آن به چند صفحه یا استفاده بهتر از تیترها، تصاویر و لیست‌ها ممکن است مفید باشد."})


    def analyze_keywords(self, top_n=15):
        top_keywords = {}
        top_bigrams = {}
        emphasis_words_list = []
        try:
            temp_soup = BeautifulSoup(str(self.soup), "lxml")
            for element in temp_soup(["script", "style", "noscript", "iframe", "head"]):
                element.decompose()
            text = temp_soup.get_text(separator=' ', strip=True).lower()

            # کلمات تکی
            words = re.findall(r'\b[a-zA-Z\u0600-\u06FF]{4,}\b', text) # افزایش حداقل طول به 4
            filtered_words = [w for w in words if w not in FARSI_STOPWORDS and w not in ENGLISH_STOPWORDS]
            if filtered_words:
                 word_freq = Counter(filtered_words)
                 top_keywords = dict(word_freq.most_common(top_n))

            # عبارات دو کلمه‌ای
            if len(words) > 1: # نیاز به حداقل دو کلمه برای بیگ‌رم
                bigrams = [" ".join(words[i:i+2]) for i in range(len(words)-1)]
                filtered_bigrams = [b for b in bigrams if all(w not in FARSI_STOPWORDS and w not in ENGLISH_STOPWORDS for w in b.split())] # هر دو کلمه نباید استاپ‌ورد باشند
                if filtered_bigrams:
                     bigram_freq = Counter(filtered_bigrams)
                     top_bigrams = dict(bigram_freq.most_common(top_n))

            # کلمات تاکیدی
            emphasis_tags = self.soup.find_all(["strong", "b", "em", "i"])
            emphasis_words = set()
            for tag in emphasis_tags:
                tag_text = tag.get_text(strip=True).lower()
                emphasis_words.update(re.findall(r'\b[a-zA-Z\u0600-\u06FF]{4,}\b', tag_text))
            emphasis_words = {w for w in emphasis_words if w not in FARSI_STOPWORDS and w not in ENGLISH_STOPWORDS}
            emphasis_words_list = list(emphasis_words)

        except Exception as e:
             print(f"Error analyzing keywords: {e}")
             self.report["error_analyze_keywords"] = str(e)


        self.report["keywords"] = {
            "top_single_keywords": top_keywords,
            "top_two_word_phrases": top_bigrams,
            "emphasized_keywords": emphasis_words_list
        }

        # پیشنهادات
        word_count_report = self.report.get('word_count', 0)
        if isinstance(word_count_report, int):
            if not top_keywords and word_count_report > 100:
                 self.suggestions.append({"metric": "Keywords", "issue": "کلمات کلیدی پرتکرار قابل توجهی (4 حرف به بالا) یافت نشد.", "recommendation": "مطمئن شوید محتوا شامل کلمات کلیدی اصلی و مرتبط با موضوع است. از کلمات کلیدی به طور طبیعی در متن، تیترها و توضیحات استفاده کنید."})
            if not emphasis_words_list and word_count_report > 200:
                 self.suggestions.append({"metric": "Keywords", "issue": "از تگ‌های تاکیدی (strong/em) برای کلمات کلیدی مهم استفاده نشده است.", "recommendation": "برجسته کردن کلمات کلیدی مهم می‌تواند به درک بهتر موضوع توسط موتورهای جستجو و کاربران کمک کند (زیاده‌روی نکنید)."})


    def check_files(self):
        robots_status = "N/A"
        sitemap_status = "N/A"
        sitemap_url = None
        robots_text = None

        check_url = self.final_url if self.final_url else self.url
        if not check_url:
            self.report["robots_txt"] = {"status": "خطا: URL نامشخص"}
            self.report["sitemap"] = {"status": "خطا: URL نامشخص", "url": None}
            return

        try:
            parsed_uri = urlparse(check_url)
            # Handle potential missing scheme if urlparse fails
            if not parsed_uri.scheme or not parsed_uri.netloc:
                # Try parsing the original URL again in case final_url was bad
                parsed_uri = urlparse(self.url)
                if not parsed_uri.scheme or not parsed_uri.netloc:
                    raise ValueError("Scheme or Netloc missing in parsed URL")
            domain = f"{parsed_uri.scheme}://{parsed_uri.netloc}"

            # بررسی robots.txt
            try:
                 robots_url_obj = urlparse(urljoin(domain, "/robots.txt"))
                 if not robots_url_obj.scheme: # Add scheme if missing after urljoin
                     robots_url = f"{parsed_uri.scheme}:{robots_url_obj.geturl()}"
                 else:
                     robots_url = robots_url_obj.geturl()

                 r_robots = requests.get(robots_url, timeout=7, headers=REQUEST_HEADERS, allow_redirects=False)
                 if r_robots.status_code == 200:
                      robots_text = r_robots.text
                      if 'user-agent' in robots_text.lower() or 'disallow' in robots_text.lower() or 'allow' in robots_text.lower() or 'sitemap' in robots_text.lower():
                          robots_status = "یافت شد و معتبر به نظر می‌رسد"
                      else:
                          robots_status = "یافت شد اما خالی یا نامعتبر است"
                 elif r_robots.status_code in [404, 410]: # 410 Gone
                      robots_status = "یافت نشد (404/410)"
                 else:
                      robots_status = f"خطا در دسترسی ({r_robots.status_code})"
            except requests.exceptions.RequestException as e:
                 robots_status = f"خطا در بررسی ({type(e).__name__})"
            except ValueError as ve: # Handle URL joining/parsing errors
                 robots_status = f"خطا در ساخت URL robots.txt: {ve}"


            # بررسی sitemap.xml
            sitemap_found_in_robots = False
            if robots_text:
                 matches = re.findall(r'Sitemap:\s*(.*)', robots_text, re.IGNORECASE)
                 if matches:
                     sitemap_url = matches[0].strip()
                     sitemap_status = "اعلام شده در robots.txt"
                     sitemap_found_in_robots = True

            if not sitemap_found_in_robots:
                sitemap_files_to_check = ["/sitemap.xml", "/sitemap_index.xml", "/sitemap.php"] # Add .php
                for sitemap_file in sitemap_files_to_check:
                    try:
                        sitemap_check_url_obj = urlparse(urljoin(domain, sitemap_file))
                        if not sitemap_check_url_obj.scheme:
                             sitemap_check_url = f"{parsed_uri.scheme}:{sitemap_check_url_obj.geturl()}"
                        else:
                             sitemap_check_url = sitemap_check_url_obj.geturl()

                        r_sitemap = requests.head(sitemap_check_url, timeout=7, headers=REQUEST_HEADERS, allow_redirects=True)
                        if r_sitemap.status_code == 200:
                            sitemap_status = f"یافت شد ({sitemap_file.split('/')[-1]})"
                            sitemap_url = sitemap_check_url
                            break
                    except requests.exceptions.RequestException:
                         continue # Try next file
                    except ValueError: # Handle URL joining/parsing errors
                        continue # Try next file

                if not sitemap_url and sitemap_status == "N/A":
                    sitemap_status = "یافت نشد (در آدرس‌های پیش‌فرض)"


        except ValueError as e: # Catch parsing errors for the base domain
            print(f"Error parsing base URL {check_url}: {e}")
            robots_status = "خطا در پردازش URL پایه"
            sitemap_status = "خطا در پردازش URL پایه"
        except Exception as e:
            print(f"Error checking files: {e}")
            if robots_status == "N/A": robots_status = "خطای ناشناخته"
            if sitemap_status == "N/A": sitemap_status = "خطای ناشناخته"


        self.report["robots_txt"] = {"status": robots_status}
        self.report["sitemap"] = {"status": sitemap_status, "url": sitemap_url if sitemap_url and ("یافت شد" in sitemap_status or "اعلام شده" in sitemap_status) else None}

        # پیشنهادات
        if not robots_status.startswith("یافت شد"):
             self.suggestions.append({"metric": "robots.txt", "issue": f"فایل robots.txt: {robots_status}.", "recommendation": "یک فایل robots.txt ایجاد کنید تا به ربات‌های موتور جستجو بگویید کدام بخش‌های سایت را می‌توانند/نمی‌توانند بخزند. همچنین می‌توانید آدرس سایت‌مپ را در آن اعلام کنید."})
        if not ("یافت شد" in sitemap_status or "اعلام شده" in sitemap_status):
             self.suggestions.append({"metric": "Sitemap", "issue": f"نقشه سایت (Sitemap): {sitemap_status}.", "recommendation": "یک نقشه سایت XML ایجاد و آن را در Google Search Console ثبت کنید و (اختیاری) آدرس آن را در robots.txt اعلام کنید. این به کشف سریع‌تر صفحات کمک می‌کند."})


    def check_https(self):
        is_https = False
        check_url = self.final_url if self.final_url else self.url
        if check_url and isinstance(check_url, str): # Ensure it's a string
             is_https = check_url.startswith("https://")
        self.report["https_status"] = "✅ فعال" if is_https else "❌ غیرفعال"

        if not is_https:
             self.suggestions.append({"metric": "HTTPS", "issue": "سایت از HTTPS استفاده نمی‌کند یا به آن ریدایرکت نشده.", "recommendation": "استفاده از HTTPS برای امنیت کاربران و بهبود رتبه سئو ضروری است. گواهینامه SSL/TLS نصب کنید و تمام ترافیک HTTP را به HTTPS ریدایرکت کنید."})


    def check_language(self):
        lang = "N/A"
        try:
            html_tag = self.soup.find('html')
            if html_tag and html_tag.get('lang'):
                lang = html_tag['lang'].strip()
            if not lang or lang == "N/A": # Check meta tag only if lang attribute is missing
                 meta_lang = self.soup.find("meta", attrs={"http-equiv": "Content-Language"})
                 if meta_lang and meta_lang.get("content"):
                     lang = meta_lang.get("content").strip()
        except Exception as e:
            lang = "خطا در بررسی"
            print(f"Error checking language: {e}")

        self.report["language_declaration"] = lang if lang else "N/A"

        if not lang or lang == "N/A" or lang == "خطا در بررسی":
             self.suggestions.append({"metric": "Language", "issue": "زبان صفحه اعلام نشده یا در بررسی آن خطا رخ داده.", "recommendation": "با استفاده از `lang` attribute در تگ `<html>` (مثلاً `<html lang='fa-IR'>`) زبان اصلی محتوای صفحه را مشخص کنید."})


    def check_favicon(self):
        has_favicon = False
        icon_href = None
        try:
            icon_rels = ["icon", "shortcut icon", "apple-touch-icon", "mask-icon"]
            for rel in icon_rels:
                 # Use CSS selector for potentially more robust finding
                 icon_link = self.soup.select_one(f"link[rel*='{rel}']")
                 if icon_link and icon_link.get('href'):
                     href = icon_link['href'].strip()
                     if href: # Ensure href is not empty
                         has_favicon = True
                         icon_href = href
                         break

            if not has_favicon:
                check_url = self.final_url if self.final_url else self.url
                if check_url:
                     parsed_uri = urlparse(check_url)
                     if parsed_uri.scheme and parsed_uri.netloc:
                         domain = f"{parsed_uri.scheme}://{parsed_uri.netloc}"
                         favicon_url_obj = urlparse(urljoin(domain, "/favicon.ico"))
                         if not favicon_url_obj.scheme:
                              favicon_url = f"{parsed_uri.scheme}:{favicon_url_obj.geturl()}"
                         else:
                              favicon_url = favicon_url_obj.geturl()

                         try:
                              r_favicon = requests.head(favicon_url, timeout=4, headers=REQUEST_HEADERS, allow_redirects=True)
                              if r_favicon.status_code == 200:
                                  has_favicon = True
                                  icon_href = "/favicon.ico"
                         except requests.exceptions.RequestException: pass
                         except ValueError: pass # Handle potential URL errors
        except Exception as e:
            print(f"Error checking favicon: {e}")
            # Report error? Or just assume not found? Let's assume not found.
            has_favicon = False
            icon_href = None

        self.report["favicon"] = {"status": "✅ یافت شد" if has_favicon else "❌ یافت نشد", "href": icon_href if has_favicon else None}

        if not has_favicon:
             self.suggestions.append({"metric": "Favicon", "issue": "فاوآیکون (Favicon) یافت نشد.", "recommendation": "یک فاوآیکون طراحی کرده و با استفاده از تگ `<link rel='icon'>` یا مشابه در `<head>` به آن لینک دهید. این به شناسایی برند کمک می‌کند."})


    def text_to_html_ratio(self):
        ratio = "N/A"
        try:
            temp_soup = BeautifulSoup(str(self.soup), "lxml")
            for element in temp_soup(["script", "style", "head", "meta", "link", "noscript", "iframe"]):
                element.decompose()
            text_content = temp_soup.get_text(strip=True)
            text_length = len(text_content)
            html_length = len(str(self.soup)) # Use raw html length

            if html_length > 0:
                 ratio_val = round((text_length / html_length) * 100, 2)
                 ratio = f"{ratio_val}%"

                 # پیشنهادات
                 if ratio_val < 15:
                     self.suggestions.append({"metric": "Text/HTML Ratio", "issue": f"نسبت متن به کد HTML پایین است ({ratio}).", "recommendation": "می‌تواند نشان‌دهنده کد زیاد یا محتوای کم باشد. کد را بهینه کنید (فایل‌های خارجی برای CSS/JS) و محتوای مفید بیشتری اضافه کنید."})
                 elif ratio_val > 75:
                     self.suggestions.append({"metric": "Text/HTML Ratio", "issue": f"نسبت متن به کد HTML بسیار بالا است ({ratio}).", "recommendation": "اطمینان حاصل کنید که از عناصر HTML کافی (تیتر، لیست، پاراگراف، تصویر و...) برای ساختاردهی متن استفاده شده است."})

        except Exception as e:
             ratio = "خطا"
             print(f"Error in text_to_html_ratio: {e}")

        self.report["text_html_ratio"] = ratio


    def check_open_graph(self):
        og_tags = {}
        try:
            for tag in self.soup.select('meta[property^="og:"]'): # Use CSS selector
                prop = tag.get('property', '').split(":", 1)[-1]
                content = tag.get("content", "")
                if prop and content:
                    og_tags[prop] = content
        except Exception as e:
             print(f"Error checking Open Graph: {e}")
             self.report["error_check_open_graph"] = str(e)


        self.report["open_graph"] = {"count": len(og_tags), "tags": og_tags}

        # پیشنهادات
        required_og = ['title', 'type', 'image', 'url']
        missing_og = [prop for prop in required_og if prop not in og_tags]
        if not og_tags:
             self.suggestions.append({"metric": "Open Graph", "issue": "تگ Open Graph یافت نشد.", "recommendation": "برای کنترل نمایش هنگام اشتراک‌گذاری در شبکه‌های اجتماعی (فیسبوک، لینکدین و...)، تگ‌های OG (حداقل title, type, image, url) را اضافه کنید."})
        elif missing_og:
             self.suggestions.append({"metric": "Open Graph", "issue": f"تگ‌های ضروری OG یافت نشدند: {', '.join(missing_og)}.", "recommendation": f"تگ‌های OG ضروری ({', '.join(required_og)}) را اضافه کنید."})
        if og_tags and 'image' not in og_tags:
             self.suggestions.append({"metric": "Open Graph", "issue": "تگ `og:image` یافت نشد.", "recommendation": "افزودن `og:image` با آدرس یک تصویر جذاب، تاثیر زیادی در افزایش کلیک دارد."})


    def check_twitter_cards(self):
        twitter_tags = {}
        try:
            for tag in self.soup.select('meta[name^="twitter:"]'): # Use CSS selector
                prop = tag.get('name', '').split(":", 1)[-1]
                content = tag.get("content", "")
                if prop and content:
                     twitter_tags[prop] = content
        except Exception as e:
             print(f"Error checking Twitter Cards: {e}")
             self.report["error_check_twitter_cards"] = str(e)

        self.report["twitter_cards"] = {"count": len(twitter_tags), "tags": twitter_tags}

        # پیشنهادات
        required_twitter = ['card', 'title', 'description', 'image']
        missing_twitter = [prop for prop in required_twitter if prop not in twitter_tags]

        if not twitter_tags:
             self.suggestions.append({"metric": "Twitter Cards", "issue": "تگ Twitter Card یافت نشد.", "recommendation": "برای کنترل نمایش هنگام اشتراک‌گذاری در توییتر، تگ‌های Twitter Card (حداقل card, title, description, image) را اضافه کنید."})
        elif missing_twitter:
             self.suggestions.append({"metric": "Twitter Cards", "issue": f"تگ‌های ضروری Twitter یافت نشدند: {', '.join(missing_twitter)}.", "recommendation": f"تگ‌های Twitter Card ضروری ({', '.join(required_twitter)}) را اضافه کنید. نوع کارت (card) معمولاً `summary` یا `summary_large_image` است."})


    def check_schema_markup(self):
        schema_types = []
        schema_found = False
        parsing_errors = []
        try:
            # 1. JSON-LD
            schema_scripts = self.soup.find_all("script", type="application/ld+json")
            if schema_scripts:
                 schema_found = True
                 for script in schema_scripts:
                     try:
                         script_content = script.string
                         if not script_content: continue
                         script_content = re.sub(r'//.*?\n|/\*.*?\*/', '', script_content, flags=re.S)
                         # Handle potential trailing commas using regex before parsing
                         script_content = re.sub(r',\s*([\}\]])', r'\1', script_content)
                         schema_data = json.loads(script_content)
                         items_to_process = []
                         if isinstance(schema_data, dict): items_to_process.append(schema_data)
                         elif isinstance(schema_data, list): items_to_process.extend(schema_data)
                         for item in items_to_process:
                              if isinstance(item, dict):
                                  item_type = item.get('@type')
                                  if item_type:
                                      if isinstance(item_type, list): schema_types.extend(item_type)
                                      else: schema_types.append(str(item_type))
                     except json.JSONDecodeError as e: parsing_errors.append(f"JSON-LD Decode Error: {e}")
                     except Exception as e: parsing_errors.append(f"JSON-LD Processing Error: {e}")

            # 2. Microdata (Simplified check)
            microdata_items = self.soup.find_all(itemscope=True)
            if microdata_items:
                schema_found = True
                for item in microdata_items:
                     item_type = item.get('itemtype')
                     if item_type and isinstance(item_type, str):
                         type_name = item_type.split('/')[-1]
                         if type_name: schema_types.append(type_name + " (Microdata)")

            # 3. RDFa (Very simplified check)
            rdfa_items = self.soup.find_all(typeof=True)
            if rdfa_items:
                 schema_found = True
                 for item in rdfa_items:
                      type_name = item.get('typeof')
                      if type_name and isinstance(type_name, str): schema_types.append(type_name + " (RDFa)")

        except Exception as e:
            print(f"Error checking Schema: {e}")
            parsing_errors.append(f"General Schema Check Error: {e}")

        unique_schema_types = sorted(list(set(filter(None, schema_types))))

        self.report["schema_markup"] = {
            "detected": schema_found,
            "types": unique_schema_types,
            "parsing_errors": parsing_errors
            }

        # پیشنهادات
        if not schema_found:
             self.suggestions.append({"metric": "Schema Markup", "issue": "نشانه‌گذاری Schema.org یافت نشد.", "recommendation": "از Schema Markup (ترجیحا JSON-LD) برای کمک به موتورهای جستجو در درک محتوا (Article, Product, FAQPage...) استفاده کنید. این می‌تواند منجر به Rich Results شود."})
        elif not unique_schema_types and schema_found:
             self.suggestions.append({"metric": "Schema Markup", "issue": "Schema شناسایی شد اما نوع (`@type`/`itemtype`) نامشخص است.", "recommendation": "ساختار Schema را بررسی کنید و مطمئن شوید نوع اصلی داده‌ها مشخص شده است."})
        if parsing_errors:
              self.suggestions.append({"metric": "Schema Markup", "issue": "خطا در تجزیه/پردازش Schema.", "recommendation": f"جزئیات: {'; '.join(parsing_errors)}. ساختار Schema را با ابزارهای اعتبارسنجی گوگل بررسی کنید."})


    def check_canonical(self):
        canonical_url = "N/A"
        canonical_tag = self.soup.find("link", rel="canonical")
        if canonical_tag and canonical_tag.get("href"):
            href_val = canonical_tag['href'].strip()
            if href_val: canonical_url = href_val # Set only if not empty

        self.report["canonical_tag"] = canonical_url

        current_url = self.final_url if self.final_url else self.url
        if not current_url: return

        if canonical_url == "N/A":
             self.suggestions.append({"metric": "Canonical Tag", "issue": "تگ کنونیکال یافت نشد.", "recommendation": "برای جلوگیری از محتوای تکراری، تگ `<link rel='canonical' href='URL_اصلی'>` را در `<head>` اضافه کنید."})
        else:
             try:
                  # Basic normalization
                  norm_canonical = urlparse(canonical_url)._replace(fragment="").geturl().rstrip('/')
                  norm_current = urlparse(current_url)._replace(fragment="").geturl().rstrip('/')

                  # More robust normalization (handle www, http/s)
                  norm_canonical_base = re.sub(r'^https?://(?:www\.)?', '', norm_canonical)
                  norm_current_base = re.sub(r'^https?://(?:www\.)?', '', norm_current)

                  if norm_canonical_base != norm_current_base:
                       self.suggestions.append({"metric": "Canonical Tag", "issue": "URL کنونیکال به صفحه‌ای متفاوت از URL فعلی اشاره می‌کند.", "recommendation": f"کنونیکال (`{canonical_url}`) با URL فعلی (`{current_url}`) مغایرت دارد. بررسی کنید که آیا عمدی است (مثلا برای صفحات فیلتر) یا خیر. در غیر این صورت اصلاح کنید."})
                  elif canonical_url != current_url: # Base URLs match, but original URLs differ (e.g. trailing slash, www, http/s)
                       self.suggestions.append({"metric": "Canonical Tag", "issue": "کنونیکال به نسخه‌ای متفاوت (از نظر www, http/s, یا اسلش انتهایی) اشاره می‌کند.", "recommendation": "این مورد معمولاً برای یکسان‌سازی URLها صحیح است. اطمینان حاصل کنید ریدایرکت ۳۰۱ هم بین این نسخه‌ها برقرار است.", "type": "positive"})

             except ValueError:
                   self.suggestions.append({"metric": "Canonical Tag", "issue": "خطا در تجزیه URL کنونیکال یا فعلی.", "recommendation": "مقادیر URL ها را بررسی کنید."})


    def check_viewport(self):
        viewport_content = "N/A"
        viewport_tag = self.soup.find("meta", attrs={"name": "viewport"})
        if viewport_tag and viewport_tag.get("content"):
            content_val = viewport_tag['content'].strip()
            if content_val: viewport_content = content_val

        self.report["viewport_tag"] = viewport_content

        if viewport_content == "N/A":
            self.suggestions.append({"metric": "Viewport Tag", "issue": "تگ Viewport یافت نشد.", "recommendation": "برای نمایش صحیح در موبایل (واکنش‌گرا)، تگ `<meta name='viewport' content='width=device-width, initial-scale=1'>` را به `<head>` اضافه کنید."})
        else:
            missing_parts = []
            content_norm = viewport_content.replace(" ", "")
            if "width=device-width" not in content_norm: missing_parts.append("width=device-width")
            if "initial-scale=1" not in content_norm: missing_parts.append("initial-scale=1")
            if missing_parts:
                 self.suggestions.append({"metric": "Viewport Tag", "issue": f"مقدار Viewport بهینه نیست (فاقد: {', '.join(missing_parts)}?).", "recommendation": f"مقدار `width=device-width, initial-scale=1` برای اکثر سایت‌های واکنش‌گرا توصیه می‌شود. مقدار فعلی: `{viewport_content}`"})
            if "user-scalable=no" in content_norm or "maximum-scale=1" in content_norm:
                 self.suggestions.append({"metric": "Viewport Tag", "issue": "Viewport قابلیت بزرگ‌نمایی (Zoom) را محدود کرده است.", "recommendation": "مقادیر `user-scalable=no` یا `maximum-scale=1.0` را حذف کنید تا کاربران (خصوصا کم‌بینا) بتوانند زوم کنند."})


    def check_duplicate_meta(self):
        titles = self.soup.find_all("title")
        descs = self.soup.find_all("meta", attrs={"name": "description"})
        self.report["duplicate_meta_within_page"] = {
            "title_count": len(titles),
            "description_count": len(descs)
        }
        if len(titles) > 1:
             self.suggestions.append({"metric": "Duplicate Meta", "issue": f"{len(titles)} تگ <title> یافت شد.", "recommendation": "فقط یک تگ <title> در <head> نگه دارید."})
        if len(descs) > 1:
             self.suggestions.append({"metric": "Duplicate Meta", "issue": f"{len(descs)} تگ <meta name='description'> یافت شد.", "recommendation": "فقط یک تگ توضیحات متا در <head> نگه دارید."})


    def check_empty_anchors(self):
        anchors = self.soup.find_all("a")
        empty_text_anchors = 0
        empty_href_or_hash_anchors = 0

        for a in anchors:
            link_content = a.get_text(strip=True)
            img_inside = a.find("img")
            if not link_content and not (img_inside and img_inside.get("alt", "").strip()):
                  empty_text_anchors += 1
            href = a.get("href", "").strip()
            if not href or href == '#':
                 empty_href_or_hash_anchors += 1

        self.report["empty_anchors"] = {"empty_text_or_alt": empty_text_anchors, "empty_or_hash_href": empty_href_or_hash_anchors}

        if empty_text_anchors > 0:
             self.suggestions.append({"metric": "Empty Anchors", "issue": f"{empty_text_anchors} لینک بدون متن قابل فهم (Anchor Text یا Alt Text) یافت شد.", "recommendation": "همه لینک‌ها باید متن یا محتوای توصیفی داشته باشند."})
        if empty_href_or_hash_anchors > 0:
             self.suggestions.append({"metric": "Empty Anchors", "issue": f"{empty_href_or_hash_anchors} لینک با `href` خالی یا فقط `#` یافت شد.", "recommendation": "لینک‌های بدون مقصد ممکن است برای کاربر یا خزشگر بی‌فایده باشند. کاربردشان را بررسی کنید."})


    def check_h1_position(self):
        h1_list = self.soup.find_all("h1")
        position_status = "N/A"
        if not h1_list: position_status = "H1 یافت نشد"
        elif len(h1_list) > 1: position_status = "چند H1 یافت شد"
        else: h1 = h1_list[0]

        if 'h1' in locals():
            try:
                 h1_text = str(h1)
                 soup_text = str(self.soup)
                 index = soup_text.find(h1_text)
                 if index == -1: position_status = "خطا در یافتن موقعیت"
                 # آستانه بسیار تقریبی برای "بالای صفحه"
                 elif index < 3000: position_status = "احتمالاً در بالای صفحه"
                 else: position_status = "پایین‌تر در صفحه"
            except Exception: position_status = "خطا در تعیین موقعیت"

        self.report["h1_position"] = position_status

        if position_status == "پایین‌تر در صفحه":
            self.suggestions.append({"metric": "H1 Position", "issue": "تگ H1 (یا اولین H1) در قسمت پایینی سورس صفحه قرار دارد.", "recommendation": "بهتر است H1 که عنوان اصلی است، در قسمت بالایی محتوا باشد تا کاربران و موتورهای جستجو سریعاً موضوع را تشخیص دهند."})


    def check_alt_duplication(self):
        # استفاده از alt_texts که در analyze_images ذخیره شد
        alts = self.report.get("images", {}).get("alt_texts", [])
        duplicates = {}
        if alts:
             alt_counts = Counter(alts)
             duplicates = {alt: count for alt, count in alt_counts.items() if count > 1}

        self.report["duplicate_alt_texts"] = {"count": len(duplicates), "texts": duplicates}

        if duplicates:
            sample_duplicates = list(duplicates.keys())[:3]
            self.suggestions.append({"metric": "Duplicate Alt Text", "issue": f"{len(duplicates)} متن جایگزین تکراری یافت شد (مانند: '{', '.join(sample_duplicates)}...').", "recommendation": "هر تصویر باید Alt Text منحصر به فرد داشته باشد (مگر اینکه تصاویر یکسان باشند). متن‌های تکراری را بررسی و اصلاح کنید."})


    def check_tables(self):
        tables = self.soup.find_all("table")
        count = len(tables)
        self.report["content_tables"] = {"count": count}
        word_count_report = self.report.get('word_count', 0)
        if count > 0:
             self.suggestions.append({"metric": "Tables", "issue": f"استفاده از {count} جدول.", "recommendation": "جداول برای نمایش داده‌های ساختاریافته مفیدند. از واکنش‌گرا بودن آنها مطمئن شوید.", "type": "positive"})
        elif isinstance(word_count_report, int) and word_count_report > 800:
             self.suggestions.append({"metric": "Tables", "issue": "عدم استفاده از جداول.", "recommendation": "اگر داده‌های مقایسه‌ای یا ساختاریافته دارید، استفاده از جدول را برای نمایش بهتر در نظر بگیرید."})


    def check_lists(self):
        ul_lists = self.soup.find_all("ul")
        ol_lists = self.soup.find_all("ol")
        count = len(ul_lists) + len(ol_lists)
        self.report["content_lists"] = {"count": count, "ul_count": len(ul_lists), "ol_count": len(ol_lists)}
        word_count_report = self.report.get('word_count', 0)
        if count > 1:
             self.suggestions.append({"metric": "Lists", "issue": f"استفاده از {count} لیست.", "recommendation": "لیست‌ها به شکستن متن، خوانایی و برجسته کردن نکات کلیدی کمک می‌کنند و ممکن است برای فیچر اسنیپت مفید باشند.", "type": "positive"})
        elif isinstance(word_count_report, int) and word_count_report > 400:
             self.suggestions.append({"metric": "Lists", "issue": "استفاده کم/عدم استفاده از لیست‌ها.", "recommendation": "برای بهبود خوانایی متن‌های طولانی یا نمایش مراحل/موارد، از لیست‌های HTML (ul/ol) استفاده کنید."})


    def check_iframes(self):
        iframes = self.soup.find_all("iframe")
        iframe_srcs = [iframe.get("src", "") for iframe in iframes if iframe.get("src")]
        count = len(iframe_srcs)
        self.report["content_iframes"] = {"count": count, "sources": iframe_srcs}
        if count > 2:
             self.suggestions.append({"metric": "Iframes", "issue": f"تعداد نسبتاً زیادی ({count}) آی‌فریم (`<iframe>`) استفاده شده.", "recommendation": "آی‌فریم‌ها می‌توانند سرعت لود را کاهش دهند و ممکن است محتوای آن‌ها ایندکس نشود. بررسی کنید آیا ضروری هستند یا می‌توان از روش‌های جایگزین (مانند embed مستقیم یا lazy loading) استفاده کرد."})


    def check_video_audio(self):
        videos = self.soup.find_all("video")
        audios = self.soup.find_all("audio")
        embedded_videos = 0
        iframe_report = self.report.get("content_iframes", {})
        for src in iframe_report.get("sources", []):
             if isinstance(src, str):
                 src_lower = src.lower()
                 if "youtube.com/embed" in src_lower or \
                    "player.vimeo.com/video" in src_lower or \
                    "aparat.com/video/video/embed" in src_lower:
                     embedded_videos += 1

        total_media = len(videos) + len(audios) + embedded_videos
        self.report["content_media"] = {"video_tags": len(videos), "audio_tags": len(audios), "embedded_videos": embedded_videos, "total": total_media}

        word_count_report = self.report.get('word_count', 0)
        if total_media > 0:
             self.suggestions.append({"metric": "Video/Audio", "issue": f"{total_media} مورد محتوای ویدئویی/صوتی شناسایی شد.", "recommendation": "مدیا می‌تواند تعامل کاربر و زمان ماندن در صفحه را افزایش دهد. از بهینه بودن فایل‌ها و (اختیاری) Schema.org مرتبط مطمئن شوید.", "type": "positive"})
        elif isinstance(word_count_report, int) and word_count_report > 700:
             self.suggestions.append({"metric": "Video/Audio", "issue": "محتوای ویدئویی/صوتی یافت نشد.", "recommendation": "برای افزایش جذابیت محتوا و ارائه اطلاعات به فرمت‌های مختلف، افزودن ویدئو/پادکست مرتبط را در نظر بگیرید."})


    def check_for_infographics(self):
        infographic_images = 0
        images_report = self.report.get("images", {})
        image_srcs = images_report.get("image_srcs", [])
        for src in image_srcs:
            if isinstance(src, str) and ("infographic" in src.lower() or "infografic" in src.lower()):
                 infographic_images += 1
                 break # فقط وجودش مهم است

        self.report["content_infographics"] = {"detected_count": infographic_images}

        word_count_report = self.report.get('word_count', 0)
        if infographic_images > 0:
            self.suggestions.append({"metric": "Infographics", "issue": "احتمالاً از اینفوگرافیک استفاده شده.", "recommendation": "اینفوگرافیک‌ها برای نمایش بصری داده‌ها عالی هستند. از alt text مناسب و توضیحات کافی در متن اطراف آن مطمئن شوید.", "type": "positive"})
        elif isinstance(word_count_report, int) and word_count_report > 1000:
             has_tables = self.report.get("content_tables", {}).get("count", 0) > 0
             if not has_tables:
                 self.suggestions.append({"metric": "Infographics", "issue": "اینفوگرافیک یافت نشد.", "recommendation": "اگر محتوا شامل آمار/داده‌های پیچیده است، ساخت اینفوگرافیک می‌تواند به درک بهتر و اشتراک‌گذاری کمک کند. یا حداقل از جداول/لیست‌ها استفاده کنید."})

    # END COPYING METHODS

    # --- محاسبه امتیاز (بدون سرعت) ---
    def calculate_score(self):
        score = 0
        max_possible_score = 0

        # Title (Max 8)
        max_possible_score += 8
        title_info = self.report.get("title", {})
        if title_info.get("text") != "N/A":
            score += 4
            if 10 <= title_info.get("length", 0) <= 65: score += 4
        # Description (Max 8)
        max_possible_score += 8
        desc_info = self.report.get("meta_description", {})
        if desc_info.get("text") != "N/A":
            score += 4
            if 70 <= desc_info.get("length", 0) <= 160: score += 4
        # Headings (Max 10)
        max_possible_score += 10
        headings = self.report.get("headings", {})
        h1_info = headings.get("h1", {})
        h1_count = h1_info.get("count", 0)
        if h1_count > 0: score += 3
        if h1_count == 1: score += 2
        if self.report.get("h1_position") == "احتمالاً در بالای صفحه": score += 2
        if sum(headings.get(f"h{i}", {}).get("count", 0) for i in range(2, 7)) >= 2: score += 3

        # Images (Max 6)
        max_possible_score += 6
        img_info = self.report.get("images", {})
        total_imgs = img_info.get("total", 0)
        if total_imgs > 0:
            score += 2
            alt_ratio = img_info.get("with_alt", 0) / total_imgs if total_imgs > 0 else 0
            score += int(alt_ratio * 4)

        # Word Count (Max 5)
        max_possible_score += 5
        wc = self.report.get("word_count", 0)
        if isinstance(wc, int) and 300 <= wc <= 4000: score += 5

        # Links (Max 6)
        max_possible_score += 6
        links_info = self.report.get("links", {})
        if links_info.get("internal", 0) >= 2 : score += 3
        if links_info.get("external", 0) > 0 : score += 3

        # Technical Files (Max 6)
        max_possible_score += 6
        if "یافت شد" in self.report.get("robots_txt", {}).get("status", ""): score += 3
        sitemap_status = self.report.get("sitemap", {}).get("status", "")
        if "یافت شد" in sitemap_status or "اعلام شده" in sitemap_status: score += 3

        # HTTPS (Max 5)
        max_possible_score += 5
        if self.report.get("https_status", "").startswith("✅"): score += 5

        # Text/HTML Ratio (Max 3)
        max_possible_score += 3
        try:
             ratio_str = self.report.get("text_html_ratio", "0%")
             if isinstance(ratio_str, str) and '%' in ratio_str:
                 ratio_val = float(ratio_str.replace('%',''))
                 if 15 <= ratio_val <= 75: score += 3
        except: pass

        # Social & Schema (Max 8)
        max_possible_score += 8
        og_tags = self.report.get("open_graph", {}).get("tags", {})
        if all(k in og_tags for k in ['title', 'type', 'image', 'url']): score += 3
        elif og_tags: score += 1
        twitter_tags = self.report.get("twitter_cards", {}).get("tags", {})
        if 'card' in twitter_tags and 'title' in twitter_tags: score += 2
        elif twitter_tags: score += 1
        if self.report.get("schema_markup", {}).get("detected"): score += 3

        # Canonical & Viewport (Max 6)
        max_possible_score += 6
        canonical_url = self.report.get("canonical_tag", "N/A")
        current_url = self.final_url if self.final_url else self.url
        # Basic check for canonical correctness (points if present and seems reasonable)
        if canonical_url != "N/A":
             # More lenient scoring: give points if it exists, more if it matches well
             score += 1
             try:
                norm_canonical_base = re.sub(r'^https?://(?:www\.)?', '', urlparse(canonical_url)._replace(fragment="").geturl().rstrip('/'))
                norm_current_base = re.sub(r'^https?://(?:www\.)?', '', urlparse(current_url)._replace(fragment="").geturl().rstrip('/'))
                if norm_canonical_base == norm_current_base:
                    score += 2 # Add more points if base matches
             except: pass # Ignore comparison errors

        vp = self.report.get("viewport_tag", "N/A")
        if vp != "N/A" and "width=device-width" in vp and "initial-scale=1" in vp and "user-scalable=no" not in vp:
             score += 3
        elif vp != "N/A": score += 1

        # Cleanliness (Max 6)
        max_possible_score += 6
        dup_meta = self.report.get("duplicate_meta_within_page", {})
        if dup_meta.get("title_count", 0) <= 1 and dup_meta.get("description_count", 0) <= 1: score += 2
        empty_anchors = self.report.get("empty_anchors", {})
        if empty_anchors.get("empty_text_or_alt", 1) == 0 and empty_anchors.get("empty_or_hash_href", 1) == 0 : score += 2
        if self.report.get("duplicate_alt_texts", {}).get("count", 1) == 0: score += 2

        # Basic Setup (Max 3)
        max_possible_score += 3
        if self.report.get("favicon", {}).get("status", "").startswith("✅"): score += 2
        lang = self.report.get("language_declaration", "N/A")
        if lang != "N/A" and lang : score += 1

        # Content Elements (Max 6)
        max_possible_score += 6
        if self.report.get("content_media", {}).get("total", 0) > 0: score += 3
        if self.report.get("content_tables", {}).get("count", 0) > 0 or self.report.get("content_lists", {}).get("count", 0) > 1:
             score += 3

        # Final Score Calculation
        final_score = 0
        if max_possible_score > 0:
             final_score = int((score / max_possible_score) * 100)
        # Ensure score doesn't exceed 100 due to potential rounding or logic issues
        final_score = max(0, min(final_score, 100))

        self.report["seo_score"] = final_score


    def get_report(self):
        """برگرداندن گزارش و پیشنهادات."""
        return self.report, self.suggestions


# --- Streamlit UI Helper Function ---
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
        low_priority = [] # Not used yet, but can be added
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


# --- رابط کاربری اصلی Streamlit ---

col_logo, col_title = st.columns([1, 6])
with col_logo:
    # Placeholder for logo - replace with your image URL if needed
    st.image("https://toopgame.ir/wp-content/uploads/2025/04/cropped-cropped-PNG-1.png", width=80)
    # st.image("https://toopgame.ir/wp-content/uploads/2025/04/cropped-cropped-PNG-1.png", width=100) # Your previous logo
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
                         st.success(f"✅ تحلیل کامل شد.")
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
                             st.success(f"✅ تحلیل کامل شد.")
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