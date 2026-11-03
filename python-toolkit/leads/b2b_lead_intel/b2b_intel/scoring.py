"""Transparent, configurable lead scoring + sales-angle ("opportunity") hints.

Every rule is (key, weight, label, predicate). Weights can be overridden from a JSON
file ({"has_domain_email": 25, ...}) so agencies can tune scoring to their ICP."""
from __future__ import annotations
import json
from typing import Callable

from .models import Lead

TECH_BY_CAT = lambda lead, cat: [t["name"] for t in lead.tech if t["category"] == cat]  # noqa: E731

RULES: list[tuple[str, int, str, Callable[[Lead], bool]]] = [
    ("has_domain_email", 22, "ایمیل رسمی با دامنه شرکت", lambda l: any(e["same_domain"] for e in l.emails)),
    ("has_personal_email", 10, "ایمیل شخصی/مستقیم (غیر عمومی)", lambda l: any(e["kind"] == "personal" for e in l.emails)),
    ("has_email_any", 6, "حداقل یک ایمیل", lambda l: bool(l.emails)),
    ("has_phone", 14, "شماره تماس", lambda l: bool(l.phones)),
    ("has_mobile", 6, "شماره موبایل", lambda l: any(p.startswith("+989") or len(p) >= 12 and p[3:4] != "2" for p in l.phones)),
    ("has_linkedin", 10, "صفحه لینکدین", lambda l: "linkedin" in l.socials),
    ("has_social_2plus", 6, "حضور فعال در ۲+ شبکه اجتماعی", lambda l: len(l.socials) >= 2),
    ("has_address", 4, "آدرس فیزیکی", lambda l: bool(l.address)),
    ("has_description", 3, "توضیحات شرکت", lambda l: bool(l.description)),
    ("is_ecommerce", 8, "فروشگاه آنلاین", lambda l: bool(TECH_BY_CAT(l, "E-commerce"))),
    ("uses_marketing_stack", 8, "ابزار بازاریابی/CRM فعال", lambda l: bool(TECH_BY_CAT(l, "Marketing automation") or TECH_BY_CAT(l, "Live chat / CRM"))),
    ("has_employees_info", 3, "اطلاعات اندازه شرکت", lambda l: l.employees is not None),
]
MAX_RAW = sum(w for _, w, _, _ in RULES)


def load_weights(path: str = "") -> dict:
    if not path:
        return {}
    with open(path, encoding="utf-8") as f:
        data = json.load(f)
    return {k: int(v) for k, v in data.items() if isinstance(v, (int, float))}


def grade_for(score: int) -> str:
    return "A" if score >= 70 else "B" if score >= 50 else "C" if score >= 30 else "D"


def opportunities(lead: Lead) -> list:
    """Concrete outreach angles derived from what the site is MISSING."""
    names = set(lead.tech_names())
    out = []
    if not (names & {"Google Analytics", "Google Tag Manager", "Matomo", "Yandex Metrica"}):
        out.append("فاقد آنالیتیکس - فرصت فروش راه‌اندازی GA4/GTM و گزارش‌دهی")
    if TECH_BY_CAT(lead, "E-commerce") and not (names & {"Meta Pixel", "Google Ads"}):
        out.append("فروشگاه آنلاین بدون پیکسل تبلیغاتی - فرصت ریمارکتینگ")
    if not TECH_BY_CAT(lead, "Marketing automation"):
        out.append("بدون ایمیل مارکتینگ/اتوماسیون - فرصت کمپین و CRM")
    if not TECH_BY_CAT(lead, "Live chat / CRM"):
        out.append("بدون چت آنلاین/پشتیبانی - فرصت افزودن چت‌بات")
    if "WordPress" in names and "Cloudflare" not in names:
        out.append("وردپرس بدون CDN/امنیت - فرصت بهینه‌سازی سرعت و امنیت")
    if "jQuery" in names and not (names & {"React", "Vue.js", "Angular", "Next.js"}):
        out.append("سایت با فناوری قدیمی - فرصت بازطراحی/مدرن‌سازی")
    if "linkedin" not in lead.socials:
        out.append("بدون لینکدین شرکتی - فرصت برندسازی B2B")
    if not lead.emails and lead.phones:
        out.append("فقط شماره تماس موجود - مناسب تماس تلفنی مستقیم")
    return out


def score_lead(lead: Lead, weights: dict | None = None) -> Lead:
    """Mutates and returns the lead with score (0-100), grade and reasons."""
    weights = weights or {}
    got, reasons, ws = 0, [], [weights.get(k, w) for k, w, _, _ in RULES]
    total = sum(ws) or 1
    for (key, _, label, pred), w in zip(RULES, ws):
        try:
            hit = pred(lead)
        except Exception:
            hit = False
        if hit and w:
            got += w
            reasons.append(f"+{w} {label}")
    lead.score = min(100, round(got / total * 100)) if lead.status == "ok" else 0
    lead.grade = grade_for(lead.score)
    lead.score_reasons = reasons
    lead.opportunities = opportunities(lead) if lead.status == "ok" else []
    return lead
