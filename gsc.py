"""Roadmap planner: dependency-aware, capacity-constrained scheduling of recommendations (deterministic; no LLM needed)."""
import json
import urllib.request

from . import config


def phase(r, quick_h=4):
    if r["type"] in ("http_5xx", "redirect_loop", "fetch_error", "robots_blocked_in_sitemap", "noindex_in_sitemap", "canonical_to_non_200",
                     "canonical_to_noindex", "canonical_chain", "http_4xx", "broken_internal_link", "redirect_chain") or r["impact"]["score"] >= 85:
        return "1-stabilize"
    return "2-quick-wins" if r["effort"]["hours"] <= quick_h else "3-growth"


def kpis(r, gsc_pages):
    ts = [gsc_pages.get(u) for u in r["targets"] if gsc_pages.get(u)]
    base = {"clicks": round(sum(t["clicks"] for t in ts), 1), "impressions": round(sum(t["impressions"] for t in ts), 1)} if ts else None
    if r["category"] == "technical":
        verify = f"Re-crawl: rule '{r['type']}' affected count must be 0."
    elif r["type"] in ("ctr_snippet", "striking_distance"):
        verify = "GSC (4+ weeks): CTR/position of the listed queries vs baseline."
    elif r["type"] == "cannibalization":
        verify = "GSC: single page ranks for the listed queries; combined clicks >= baseline."
    else:
        verify = "Re-crawl for new links/pages; GSC impressions for target pages."
    return {"baseline": base, "verify": verify}


def build_roadmap(recs, gsc_pages=None, hours_per_week=20.0, weeks=12) -> dict:
    gsc_pages = gsc_pages or {}
    left = {r["id"]: float(r["effort"]["hours"]) for r in recs}
    done, sched = {}, []
    by = {r["id"]: r for r in recs}
    for w in range(1, weeks + 1):
        cap, items = hours_per_week, []
        started = sorted((i for i in left if left[i] < by[i]["effort"]["hours"] and left[i] > 0), key=lambda i: -by[i]["priority"])
        ready = [r["id"] for r in recs if left[r["id"]] > 0 and r["id"] not in started and all(d in done and done[d] < w for d in r["dependencies"])]
        for i in started + ready:
            if cap <= 1e-9:
                break
            if i in started and not all(d in done and done[d] < w for d in by[i]["dependencies"]):
                continue
            h = min(left[i], cap)
            left[i] -= h
            cap -= h
            items.append({"id": i, "hours": round(h, 1), "finishes": left[i] <= 1e-9})
            if left[i] <= 1e-9:
                done[i] = w
        sched.append({"week": w, "planned_hours": round(hours_per_week - cap, 1), "tasks": items})
    tasks = {r["id"]: {"id": r["id"], "title": r["title"], "type": r["type"], "phase": phase(r), "priority": r["priority"], "effort_hours": r["effort"]["hours"],
                       "impact_score": r["impact"]["score"], "confidence": r["confidence"]["score"], "est_clicks": r["impact"]["est_clicks_per_period"],
                       "depends_on": r["dependencies"], "finish_week": done.get(r["id"]), "targets": r["targets"][:5], **kpis(r, gsc_pages)} for r in recs}
    backlog = [t["id"] for t in tasks.values() if t["finish_week"] is None]
    est = sum(t["est_clicks"] or 0 for t in tasks.values() if t["finish_week"])
    return {"hours_per_week": hours_per_week, "weeks": weeks, "schedule": [s for s in sched if s["tasks"]], "tasks": tasks, "backlog": backlog,
            "totals": {"scheduled_tasks": len(tasks) - len(backlog), "backlog_tasks": len(backlog),
                       "scheduled_hours": round(sum(s["planned_hours"] for s in sched), 1),
                       "est_clicks_sum": round(est, 1),
                       "est_clicks_note": "Sum of per-item model estimates; items overlap, so treat as an upper bound, not a forecast."}}


def narrate(roadmap, health_score=None) -> str:
    tasks = sorted(roadmap["tasks"].values(), key=lambda t: -t["priority"])
    ph = {}
    for t in tasks:
        ph.setdefault(t["phase"], []).append(t)
    lines = [f"Plan: {roadmap['totals']['scheduled_tasks']} tasks scheduled over up to {roadmap['weeks']} weeks at {roadmap['hours_per_week']} h/week "
             f"({roadmap['totals']['backlog_tasks']} in backlog)." + (f" Current technical health score: {health_score}/100." if health_score is not None else "")]
    for name in sorted(ph):
        lines.append(f"{name}: " + "; ".join(f"{t['title']} (priority {t['priority']}, week {t['finish_week'] or 'backlog'})" for t in ph[name][:4]))
    return "\n".join(lines)


def narrate_llm(roadmap, model=None):
    """Optional: rewrite the deterministic summary with a local Ollama model; returns None on failure. Never adds facts."""
    prompt = ("Rewrite this SEO plan summary for a non-technical stakeholder in 120 words. Use ONLY the facts given.\n" + narrate(roadmap))
    try:
        req = urllib.request.Request(f"{config.OLLAMA_URL}/api/generate", json.dumps({"model": model or config.OLLAMA_MODEL, "prompt": prompt, "stream": False}).encode(),
                                     {"Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=90) as r:
            return json.load(r)["response"].strip()
    except Exception:
        return None
