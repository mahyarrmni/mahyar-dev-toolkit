"""Transparent lexicon-based search-intent classifier (English + Persian) and keyword-to-page mapping."""
import numpy as np

from .semantic import topk_cosine
from .text import normalize

INTENTS = ["informational", "commercial", "transactional", "navigational"]
LEX = {
    "informational": {"how": 1.5, "what": 1.5, "why": 1.5, "when": 1, "who": 1, "guide": 1.5, "tutorial": 1.5, "tips": 1, "learn": 1,
                      "meaning": 1.5, "definition": 1.5, "examples": 1, "ideas": 1, "explained": 1.5, "vs": 0.3,
                      "چگونه": 2, "چطور": 2, "چیست": 2, "آموزش": 1.5, "راهنما": 1.5, "راهنمای": 1.5, "معنی": 1.5, "چرا": 1.5,
                      "تفاوت": 1, "نحوه": 1.5, "روش": 1, "نکات": 1},
    "commercial": {"best": 2, "top": 1.5, "review": 2, "reviews": 2, "compare": 2, "comparison": 2, "vs": 1.5, "versus": 2,
                   "alternative": 2, "alternatives": 2, "which": 1,
                   "بهترین": 2, "مقایسه": 2, "بررسی": 1.5, "نقد": 1.5, "برتر": 1.5, "مزایا": 1, "معایب": 1, "پیشنهاد": 1},
    "transactional": {"buy": 2.5, "price": 2, "prices": 2, "pricing": 2, "cheap": 2, "discount": 2, "coupon": 2, "order": 1.5,
                      "shop": 1.5, "purchase": 2.5, "deal": 1.5, "cost": 1.5, "near me": 2, "for sale": 2.5, "download": 1,
                      "خرید": 2.5, "قیمت": 2, "سفارش": 2, "تخفیف": 2, "ارزان": 2, "فروش": 2, "فروشگاه": 1.5, "کد تخفیف": 2.5, "نمایندگی": 1.5},
    "navigational": {"login": 2.5, "sign in": 2.5, "official": 2, "website": 1, "contact": 1.5, "careers": 2, "app": 0.5,
                     "ورود": 2.5, "لاگین": 2.5, "رسمی": 2, "سایت": 0.5, "تماس": 1.5, "آدرس": 1},
}
URL_HINTS = {
    "informational": ("/blog", "/article", "/learn", "/guide", "/wiki", "/news", "/faq", "/tutorial", "/مقاله", "/آموزش", "/وبلاگ"),
    "transactional": ("/product", "/shop", "/store", "/cart", "/checkout", "/buy", "/p/", "/محصول", "/فروشگاه", "/خرید"),
    "commercial": ("/category", "/collections", "/compare", "/review", "/best", "/دسته", "/مقایسه"),
    "navigational": ("/contact", "/about", "/login", "/account", "/تماس", "/درباره"),
}
SCHEMA_HINTS = {"Product": "transactional", "Offer": "transactional", "Article": "informational", "BlogPosting": "informational",
                "NewsArticle": "informational", "FAQPage": "informational", "HowTo": "informational",
                "ItemList": "commercial", "CollectionPage": "commercial", "Review": "commercial", "ContactPage": "navigational"}


def classify_query(q: str, brand_terms=()):
    s = f" {normalize(q)} "
    scores, matched = dict.fromkeys(INTENTS, 0.0), []
    for intent, lex in LEX.items():
        for term, w in lex.items():
            if f" {term} " in s:
                scores[intent] += w
                matched.append((intent, term))
    first = s.split()[0] if s.split() else ""
    if first in ("how", "what", "why", "چگونه", "چطور", "چرا"):
        scores["informational"] += 1
    for b in brand_terms:
        if f" {normalize(b)} " in s or normalize(b) in s:
            scores["navigational"] += 3
            matched.append(("navigational", f"brand:{b}"))
    total = sum(scores.values())
    if total == 0:
        return {"intent": "informational", "confidence": 0.34, "scores": {k: 0.0 for k in INTENTS}, "matched": [], "default": True}
    probs = {k: round(v / total, 3) for k, v in scores.items()}
    best = max(probs, key=probs.get)
    return {"intent": best, "confidence": round(probs[best] * min(1.0, total / 2), 3), "scores": probs, "matched": matched, "default": False}


def classify_page(p: dict):
    scores = dict.fromkeys(INTENTS, 0.0)
    ev = []
    for b in p.get("jsonld") or []:
        ts = b.get("@type")
        for t in ts if isinstance(ts, list) else [ts]:
            i = SCHEMA_HINTS.get(str(t).rsplit("/", 1)[-1])
            if i:
                scores[i] += 2
                ev.append(f"schema:{t}")
    path = p["url"].split("://", 1)[-1].partition("/")[2]
    path = "/" + normalize(path)
    for i, hints in URL_HINTS.items():
        for h in hints:
            if h in path:
                scores[i] += 1.5
                ev.append(f"url:{h}")
    q = classify_query(f"{p.get('title') or ''} {(p.get('h1s') or [''])[0]}")
    if not q["default"]:
        scores[q["intent"]] += q["confidence"] * 2
        ev.append(f"title:{q['intent']}")
    total = sum(scores.values())
    if total == 0:
        return {"intent": "informational", "confidence": 0.3, "evidence": [], "default": True}
    best = max(scores, key=scores.get)
    return {"intent": best, "confidence": round(scores[best] / total, 3), "evidence": ev, "default": False}


def compat(a, b):
    if a == b:
        return 1.0
    return 0.5 if {a, b} == {"commercial", "transactional"} else 0.0


def map_keywords(keywords, index, pages, page_intents, current=None, brand_terms=(), k=3):
    """keyword -> best page: 0.7*semantic cosine + 0.2*intent compatibility + 0.1*currently-ranking evidence.
    `current`: {keyword: page_url with most clicks in GSC}. Only indexable pages should be passed in `pages`."""
    if not keywords or not pages:
        return []
    Q = index.transform(keywords)
    ids, sims = topk_cosine(Q, index.E, k=k)
    out = []
    for qi, kw in enumerate(keywords):
        qc = classify_query(kw, brand_terms)
        cands = []
        for pi, s in zip(ids[qi], sims[qi]):
            p = pages[pi]
            comp = compat(qc["intent"], page_intents[p["url"]]["intent"])
            cur = 1.0 if current and current.get(kw) == p["url"] else 0.0
            cands.append({"url": p["url"], "score": round(0.7 * float(s) + 0.2 * comp + 0.1 * cur, 4),
                          "semantic": round(float(s), 4), "intent_match": comp, "current_ranker": bool(cur)})
        cands.sort(key=lambda c: -c["score"])
        out.append({"keyword": kw, "intent": qc["intent"], "intent_confidence": qc["confidence"], "best": cands[0], "mapped": cands[0]["semantic"] >= 0.25,
                    "alternatives": cands[1:], "matched_terms": qc["matched"]})
    return out
