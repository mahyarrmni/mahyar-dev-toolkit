"""Technical SEO engine: deterministic rules over crawl data, every issue carries evidence (URLs + values)."""
import re
from collections import defaultdict, deque

from . import dedup
from .text import normalize
from .urls import normalize_url, same_site

# rule: (severity, category, message, fix)
RULES = {
    "http_5xx": ("critical", "crawlability", "Pages returning server errors (5xx)", "Fix server errors; pages must return 200."),
    "http_4xx": ("high", "crawlability", "Crawled pages returning 4xx", "Restore the page, 301 to the closest equivalent, or remove links to it."),
    "fetch_error": ("high", "crawlability", "Pages that failed to load (timeout/DNS/blocked)", "Check server availability and response time."),
    "broken_internal_link": ("high", "links", "Internal links pointing to 4xx/5xx pages", "Update or remove the broken links."),
    "broken_external_link": ("low", "links", "External links that are broken or unreachable", "Replace or remove dead outbound links."),
    "redirect_chain": ("medium", "redirects", "Redirect chains (more than one hop)", "Redirect directly to the final URL."),
    "redirect_loop": ("critical", "redirects", "Redirect loops", "Break the loop; point to a 200 page."),
    "link_to_redirect": ("low", "redirects", "Internal links pointing to redirecting URLs", "Link directly to the final URL."),
    "noindex_in_sitemap": ("high", "indexability", "noindex pages listed in the sitemap", "Remove from sitemap or drop noindex."),
    "robots_blocked_in_sitemap": ("high", "indexability", "robots.txt-blocked URLs listed in the sitemap", "Unblock or remove from sitemap."),
    "canonical_to_non_200": ("high", "indexability", "Canonical points to a non-200 page", "Point canonical to an indexable 200 URL."),
    "canonical_to_noindex": ("high", "indexability", "Canonical points to a noindex page", "Align canonical and robots directives."),
    "canonical_chain": ("medium", "indexability", "Canonical target is itself canonicalized elsewhere", "Point canonicals at the final canonical URL."),
    "missing_canonical": ("low", "indexability", "Indexable pages without a canonical tag", "Add a self-referencing canonical."),
    "title_missing": ("high", "on-page", "Missing <title>", "Write a unique, descriptive title."),
    "title_duplicate": ("medium", "on-page", "Duplicate titles", "Make titles unique per page."),
    "title_long": ("low", "on-page", "Titles longer than 60 characters", "Shorten to avoid truncation."),
    "title_short": ("low", "on-page", "Titles shorter than 20 characters", "Expand with descriptive keywords."),
    "meta_missing": ("medium", "on-page", "Missing meta description", "Add a unique summary (70-160 chars)."),
    "meta_duplicate": ("low", "on-page", "Duplicate meta descriptions", "Write unique descriptions."),
    "meta_long": ("low", "on-page", "Meta descriptions longer than 160 characters", "Shorten to avoid truncation."),
    "meta_short": ("low", "on-page", "Meta descriptions shorter than 70 characters", "Expand the description."),
    "h1_missing": ("medium", "on-page", "Missing <h1>", "Add one descriptive h1."),
    "h1_multiple": ("low", "on-page", "Multiple <h1> elements", "Use a single h1."),
    "thin_content": ("medium", "content", "Thin content (< 200 words)", "Expand or consolidate."),
    "duplicate_content_exact": ("high", "content", "Exact duplicate content", "Consolidate with 301/canonical."),
    "near_duplicate_content": ("medium", "content", "Near-duplicate content (SimHash)", "Differentiate, merge, or canonicalize."),
    "pagination_broken_sequence": ("medium", "pagination", "rel=next/prev pointing to a non-200 page", "Fix the pagination sequence."),
    "pagination_canonical_to_first": ("medium", "pagination", "Paginated pages canonicalized to page 1", "Self-canonicalize paginated pages."),
    "jsonld_parse_error": ("high", "structured-data", "Invalid JSON-LD (parse error)", "Fix JSON syntax."),
    "structured_data_missing_required": ("medium", "structured-data", "Structured data missing required properties", "Add the required properties."),
    "no_structured_data": ("info", "structured-data", "Indexable pages without structured data", "Add relevant schema.org markup."),
    "hreflang_missing_return": ("medium", "international", "hreflang without return link", "Make hreflang annotations reciprocal."),
    "hreflang_invalid_code": ("low", "international", "Invalid hreflang language code", "Use ISO 639-1 / BCP-47 codes."),
    "orphan_page": ("medium", "architecture", "Indexable pages with no internal inlinks", "Link to them from relevant pages."),
    "deep_page": ("low", "architecture", "Pages deeper than 4 clicks from the homepage", "Flatten the architecture / add shortcuts."),
    "no_outlinks": ("low", "architecture", "Indexable pages without outgoing internal links", "Add contextual links."),
    "insecure_page": ("medium", "security", "HTTP pages on an HTTPS site", "301 to HTTPS."),
    "internal_http_links": ("low", "security", "HTTPS pages linking to HTTP URLs", "Update links to HTTPS."),
    "js_dependent_content": ("medium", "rendering", "Little server-rendered text but many scripts", "Verify rendering; use SSR/prerender or enable render mode."),
    "missing_html_lang": ("low", "international", "Missing <html lang>", "Declare the page language."),
    "images_missing_alt": ("low", "accessibility", "Images without alt text", "Add descriptive alt text."),
}
SEV_W = {"critical": 0.30, "high": 0.15, "medium": 0.07, "low": 0.02, "info": 0.0}
SEV_ORDER = ["critical", "high", "medium", "low", "info"]
LANG_RE = re.compile(r"^(x-default|[a-z]{2,3}(-[a-z0-9]{2,8})*)$", re.I)
REQUIRED_PROPS = {
    "Article": ["headline", "datePublished", "author"], "NewsArticle": ["headline", "datePublished", "author"],
    "BlogPosting": ["headline", "datePublished", "author"], "Product": ["name", "offers"], "BreadcrumbList": ["itemListElement"],
    "Organization": ["name"], "FAQPage": ["mainEntity"], "LocalBusiness": ["name", "address"], "Recipe": ["name", "recipeIngredient"],
    "Event": ["name", "startDate", "location"],
}


def indexability(p) -> list:
    r = []
    if p["blocked_by_robots"]:
        r.append("robots_txt")
    st = p["status"]
    if st is None and not p["blocked_by_robots"]:
        r.append("fetch_error")
    elif st and st != 200:
        r.append(f"status_{st}")
    toks = [t.strip() for t in f"{p['meta_robots']},{p['x_robots']}".split(",")]
    if "noindex" in toks or "none" in toks:
        r.append("noindex")
    if p["canonical"] and p["canonical"] not in (p["url"], p["final_url"]):
        r.append("canonicalized")
    if st == 200 and "html" not in (p["content_type"] or ""):
        r.append("non_html")
    return r


def validate_jsonld(blocks) -> list:
    probs = []
    for b in blocks:
        types = b.get("@type")
        if not types:
            probs.append({"type": None, "missing": ["@type"]})
            continue
        for t in (types if isinstance(types, list) else [types]):
            t = str(t).rsplit("/", 1)[-1].rsplit(":", 1)[-1]
            miss = [k for k in REQUIRED_PROPS.get(t, []) if not b.get(k)]
            if miss:
                probs.append({"type": t, "missing": miss})
    return probs


def health_score(issues, n_pages) -> float:
    pen = sum(SEV_W[i["severity"]] * min(1.0, i["count"] / max(1, n_pages)) for i in issues)
    return round(100 * (1 - min(1.0, pen)), 1)


def analyze(pages, start_url=None, external=None, sitemap=None, near_dup_dist=3) -> dict:
    by = {p["url"]: p for p in pages}
    start = normalize_url(start_url) if start_url else min(pages, key=lambda p: p["depth"])["url"]
    sitemap = set(sitemap or [p["url"] for p in pages if p["in_sitemap"]])
    internal = lambda u: same_site(u, start)
    ok = [p for p in pages if p["status"] == 200 and "html" in (p["content_type"] or "")]
    flags = {p["url"]: indexability(p) for p in pages}
    idx = [p for p in ok if not flags[p["url"]]]
    issues = {}

    def add(rule, url, **ev):
        sev, cat, msg, fix = RULES[rule]
        i = issues.setdefault(rule, {"rule": rule, "severity": sev, "category": cat, "message": msg, "fix": fix,
                                     "count": 0, "urls": [], "evidence": []})
        i["count"] += 1
        if len(i["urls"]) < 100 and url not in i["urls"]:
            i["urls"].append(url)
        if len(i["evidence"]) < 50:
            i["evidence"].append({"url": url, **ev})

    # ---- link graph basics
    inl, adj = defaultdict(list), defaultdict(list)
    for p in ok:
        for l in p["links"]:
            if not internal(l["url"]):
                continue
            inl[l["url"]].append((p["url"], l["anchor"], l["nofollow"]))
            if not l["nofollow"]:
                t = by.get(l["url"])
                adj[p["url"]].append(t["final_url"] if t and t["redirect_chain"] and t["final_url"] else l["url"])
    depth, dq = {start: 0}, deque([start])
    while dq:
        u = dq.popleft()
        for v in adj.get(u, []):
            if v not in depth:
                depth[v] = depth[u] + 1
                dq.append(v)

    # ---- crawlability / redirects / links
    for p in pages:
        u, st = p["url"], p["status"]
        if st and st >= 500:
            add("http_5xx", u, status=st)
        elif st and 400 <= st < 500:
            add("http_4xx", u, status=st, inlinks=len(inl.get(u, [])))
        if p["error"] and st is None and not p["blocked_by_robots"]:
            add("fetch_error", u, error=p["error"])
        if p["redirect_loop"]:
            add("redirect_loop", u, chain=p["redirect_chain"])
        elif len(p["redirect_chain"]) > 1:
            add("redirect_chain", u, hops=len(p["redirect_chain"]), chain=[c["url"] for c in p["redirect_chain"]], final=p["final_url"])
        if p["blocked_by_robots"] and u in sitemap:
            add("robots_blocked_in_sitemap", u)
        if "noindex" in flags[u] and u in sitemap:
            add("noindex_in_sitemap", u)
    for p in ok:
        for l in p["links"]:
            t = by.get(l["url"])
            if not t:
                continue
            if t["status"] and t["status"] >= 400:
                add("broken_internal_link", p["url"], target=t["url"], status=t["status"], anchor=l["anchor"])
            elif t["redirect_chain"] and not t["redirect_loop"]:
                add("link_to_redirect", p["url"], target=t["url"], final=t["final_url"])
    for u, e in (external or {}).items():
        if e.get("status") is None or e["status"] >= 400:
            for src in e.get("sources", [])[:3]:
                add("broken_external_link", src, target=u, status=e.get("status"))

    # ---- canonicals
    for p in ok:
        c, u = p["canonical"], p["url"]
        if not c:
            if not flags[u]:
                add("missing_canonical", u)
            continue
        if c in (u, p["final_url"]):
            continue
        t = by.get(c)
        if t:
            if t["status"] != 200:
                add("canonical_to_non_200", u, canonical=c, status=t["status"])
            elif "noindex" in flags[c]:
                add("canonical_to_noindex", u, canonical=c)
            elif t["canonical"] and t["canonical"] not in (c, t["final_url"]):
                add("canonical_chain", u, canonical=c, next=t["canonical"])

    # ---- on-page (indexable pages only)
    groups = {"title": defaultdict(list), "meta": defaultdict(list)}
    for p in idx:
        u, t, m = p["url"], p["title"] or "", p["meta_description"] or ""
        if not t:
            add("title_missing", u)
        else:
            groups["title"][normalize(t)].append(u)
            if len(t) > 60:
                add("title_long", u, length=len(t))
            elif len(t) < 20:
                add("title_short", u, length=len(t))
        if not m:
            add("meta_missing", u)
        else:
            groups["meta"][normalize(m)].append(u)
            if len(m) > 160:
                add("meta_long", u, length=len(m))
            elif len(m) < 70:
                add("meta_short", u, length=len(m))
        if not p["h1s"]:
            add("h1_missing", u)
        elif len(p["h1s"]) > 1:
            add("h1_multiple", u, count=len(p["h1s"]))
        if p["word_count"] < 200:
            add("thin_content", u, words=p["word_count"])
        if not p["lang_attr"]:
            add("missing_html_lang", u)
        if p["images_missing_alt"]:
            add("images_missing_alt", u, count=p["images_missing_alt"], of=p["images_total"])
        if not p["jsonld"] and not p["jsonld_errors"]:
            add("no_structured_data", u)
        if p["word_count"] < 50 and p["script_count"] >= 5 and not p.get("rendered"):
            add("js_dependent_content", u, words=p["word_count"], scripts=p["script_count"])
    for kind, rule in (("title", "title_duplicate"), ("meta", "meta_duplicate")):
        for g in groups[kind].values():
            for u in g if len(g) > 1 else []:
                add(rule, u, group=g[:10])

    # ---- duplicate / near-duplicate content
    exact = defaultdict(list)
    for p in idx:
        if p["word_count"] >= 50 and p["content_hash"]:
            exact[p["content_hash"]].append(p["url"])
    in_exact = set()
    for g in exact.values():
        if len(g) > 1:
            in_exact.update(g)
            for u in g:
                add("duplicate_content_exact", u, group=g[:10])
    fps = {p["url"]: int(p["simhash"], 16) for p in idx if p["word_count"] >= 100 and p["simhash"]}
    pairs, skipped = dedup.find_near_duplicates(fps, max_dist=near_dup_dist)
    for a, b, d in pairs:
        if not (a in in_exact and b in in_exact and any(a in g and b in g for g in exact.values())):
            add("near_duplicate_content", a, other=b, hamming=d)

    # ---- pagination
    for p in ok:
        u = p["url"]
        if p["rel_next"] and by.get(p["rel_next"]) and by[p["rel_next"]]["status"] != 200:
            add("pagination_broken_sequence", u, next=p["rel_next"], status=by[p["rel_next"]]["status"])
        if (p["rel_next"] or p["rel_prev"]) and p["canonical"] and p["canonical"] != u:
            first = by.get(p["canonical"])
            if first and not first["rel_prev"]:
                add("pagination_canonical_to_first", u, canonical=p["canonical"])

    # ---- structured data / hreflang
    for p in ok:
        u = p["url"]
        for e in p["jsonld_errors"]:
            add("jsonld_parse_error", u, error=e)
        for pr in validate_jsonld(p["jsonld"]):
            add("structured_data_missing_required", u, **pr)
        for h in p["hreflang"]:
            if not LANG_RE.match(h["lang"] or ""):
                add("hreflang_invalid_code", u, lang=h["lang"])
            t = by.get(h["url"])
            if t and t["url"] != u and t["status"] == 200 and u not in {x["url"] for x in t["hreflang"]}:
                add("hreflang_missing_return", u, target=t["url"], lang=h["lang"])

    # ---- architecture / security
    https_site = start.startswith("https://")
    for p in idx:
        u = p["url"]
        if u != start and not [x for x in inl.get(u, []) if x[0] != u]:
            add("orphan_page", u, in_sitemap=u in sitemap)
        if depth.get(u, 0) > 4:
            add("deep_page", u, depth=depth[u])
        if not adj.get(u):
            add("no_outlinks", u)
        if https_site and u.startswith("http://"):
            add("insecure_page", u)
        n_http = sum(1 for l in p["links"] if l["url"].startswith("http://") and internal(l["url"]))
        if u.startswith("https://") and n_http:
            add("internal_http_links", u, count=n_http)

    out = sorted(issues.values(), key=lambda i: (SEV_ORDER.index(i["severity"]), -i["count"]))
    return {
        "issues": out, "health_score": health_score(out, len(ok)),
        "summary": {"pages_crawled": len(pages), "html_ok": len(ok), "indexable": len(idx),
                    "by_severity": {s: sum(i["count"] for i in out if i["severity"] == s) for s in SEV_ORDER},
                    "by_category": {c: sum(i["count"] for i in out if i["category"] == c) for c in sorted({i["category"] for i in out})},
                    "near_dup_skipped_buckets": skipped},
        "page_flags": {p["url"]: {"indexable": not flags[p["url"]], "reasons": flags[p["url"]], "click_depth": depth.get(p["url"]),
                                  "inlinks": len({x[0] for x in inl.get(p["url"], []) if x[0] != p["url"]})} for p in pages},
        "params": {"title_max": 60, "meta_max": 160, "thin_words": 200, "near_dup_hamming": near_dup_dist, "deep_clicks": 4},
    }
