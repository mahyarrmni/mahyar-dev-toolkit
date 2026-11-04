from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from urllib.parse import urlparse

from . import analyze, config, db, discover, llm, report, scrape

LIMITATIONS = [
    "Discovery relies on OpenStreetMap data, which is incomplete for many regions and industries.",
    "Services are extracted heuristically from public page headings; verify before relying on them.",
    "Opportunities are rule-based signals, not validated market sizing.",
]


def research(req) -> dict:
    errors = []
    if req.urls:
        biz = [{"name": urlparse(u).netloc, "address": None, "website": u, "sources": [u]}
               for u in req.urls[: req.max_businesses]]
        src = "user-supplied URLs"
    else:
        try:
            biz = discover.search(req.industry, req.location, req.max_businesses)
        except Exception as e:
            biz = []
            errors.append(f"discovery failed: {e}")
        src = "OpenStreetMap Nominatim"
    if not biz:
        raise RuntimeError("; ".join(errors) or "No businesses found. Try broader terms or supply 'urls'.")

    def work(b):
        if not b.get("website"):
            return
        try:
            s = scrape.scrape_site(b["website"])
            errors.extend(s["errors"])
            if s["pages"]:
                b["scraped"] = s
                b["sources"] = list(dict.fromkeys(b["sources"] + s["pages"]))
        except Exception as e:
            errors.append(f"{b['website']}: {e}")

    with ThreadPoolExecutor(config.WORKERS) as ex:
        list(ex.map(work, biz))
    an = analyze.analyze(biz)
    return {
        "meta": {"industry": req.industry, "location": req.location, "data_source": src,
                 "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
                 "limitations": LIMITATIONS},
        "businesses": biz, "analysis": an,
        "summary": llm.summarize(an, req.industry, req.location) if req.use_llm else None,
        "errors": errors,
    }


def run_job(run_id: int, req):
    try:
        rep = research(req)
        db.finish(run_id, rep, report.to_markdown(rep))
    except Exception as e:
        db.fail(run_id, str(e))
