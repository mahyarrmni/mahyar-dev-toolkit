"""بررسی محتوا: تعداد کلمات، کلمات کلیدی و نسبت متن به HTML."""

import re
from collections import Counter

from bs4 import BeautifulSoup

from ..constants import ENGLISH_STOPWORDS, FARSI_STOPWORDS


class ContentChecks:
    """تحلیل متن و کلمات کلیدی."""

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
