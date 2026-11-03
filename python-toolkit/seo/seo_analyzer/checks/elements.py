"""بررسی عناصر محتوایی: جدول، لیست، آی‌فریم، ویدئو/صوت و اینفوگرافیک."""



class ElementChecks:
    """تحلیل عناصر غیرمتنی صفحه."""

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
