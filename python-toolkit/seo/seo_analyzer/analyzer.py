"""کلاس اصلی SEOAnalyzer: دریافت صفحه و اجرای تحلیل‌ها به ترتیب."""

import re

import requests
from bs4 import BeautifulSoup

from .checks import ContentChecks, ElementChecks, MetaChecks, StructureChecks, TechnicalChecks
from .constants import REQUEST_HEADERS
from .scoring import ScoringMixin


class SEOAnalyzer(MetaChecks, StructureChecks, ContentChecks, TechnicalChecks, ElementChecks, ScoringMixin):
    """تحلیل On-Page SEO یک URL."""

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

    def get_report(self):
        """برگرداندن گزارش و پیشنهادات."""
        return self.report, self.suggestions
