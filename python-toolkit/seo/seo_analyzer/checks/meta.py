"""بررسی‌های متا: عنوان، توضیحات، HTTPS، زبان، فاوآیکون، کنونیکال، ویوپورت."""

import re
from urllib.parse import urljoin, urlparse

import requests

from ..constants import REQUEST_HEADERS


class MetaChecks:
    """تحلیل تگ‌ها و تنظیمات متا."""

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
