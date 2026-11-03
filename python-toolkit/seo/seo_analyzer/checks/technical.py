"""بررسی‌های فنی: robots.txt، sitemap، Open Graph، Twitter Cards و Schema."""

import json
import re
from urllib.parse import urljoin, urlparse

import requests

from ..constants import REQUEST_HEADERS


class TechnicalChecks:
    """تحلیل فایل‌های فنی و داده‌ی ساخت‌یافته."""

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
