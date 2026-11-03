"""بررسی ساختار: تیترها، تصاویر، لینک‌ها و anchorها."""

from collections import Counter
from urllib.parse import urljoin, urlparse


class StructureChecks:
    """تحلیل ساختار صفحه (تیتر، تصویر، لینک)."""

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
