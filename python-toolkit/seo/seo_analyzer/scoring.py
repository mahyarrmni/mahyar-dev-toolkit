"""محاسبه‌ی امتیاز تخمینی سئو."""

import re
from urllib.parse import urlparse


class ScoringMixin:
    """محاسبه‌ی امتیاز نهایی بر اساس گزارش."""

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
