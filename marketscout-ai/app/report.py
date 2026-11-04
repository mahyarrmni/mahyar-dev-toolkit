def _c(s) -> str:
    return str(s or "").replace("|", "/").replace("\n", " ")


def to_markdown(r: dict) -> str:
    m, a = r["meta"], r["analysis"]
    L = [f"# Market Research: {m['industry']} in {m['location']}",
         f"_Generated {m['generated_at']} · confidence **{a['confidence']}** · "
         f"{a['businesses_found']} businesses found, {a['businesses_scraped']} analyzed_", ""]
    if r.get("summary"):
        L += ["## Summary", r["summary"], ""]
    L += ["## Opportunities"]
    L += [f"- **{o['type']}**: {o['insight']} (e.g. {', '.join(o['evidence'])})" for o in a["opportunities"]] or ["- None detected"]
    L += ["", "## Common services (table stakes)"]
    L += [f"- {t['service']} ({t['count']} businesses, {int(t['share'] * 100)}%)" for t in a["table_stakes"]] or ["- Not enough data"]
    L += ["", "## Competitors", "| Name | Website | Top services | Contact |", "|---|---|---|---|"]
    for b in r["businesses"]:
        s = b.get("scraped") or {}
        contact = ", ".join((s.get("emails") or [])[:1] + (s.get("phones") or [])[:1]) or "-"
        L.append(f"| {_c(b['name'])} | {_c(b.get('website') or '-')} | {_c(', '.join((s.get('services') or [])[:5]) or '-')} | {_c(contact)} |")
    L += ["", "## Sources"]
    for b in r["businesses"]:
        L.append(f"- **{b['name']}**: " + ", ".join(b["sources"]))
    if r["errors"]:
        L += ["", "## Errors"] + [f"- {e}" for e in r["errors"][:20]]
    L += ["", "## Limitations"] + [f"- {x}" for x in m["limitations"]]
    return "\n".join(L) + "\n"
