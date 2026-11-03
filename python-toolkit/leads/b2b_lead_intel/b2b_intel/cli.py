"""Command line interface:  python -m b2b_intel {run,report,export,stats} ..."""
from __future__ import annotations
import argparse, asyncio, csv, json, sys
from pathlib import Path

from . import __author__, __version__, report as report_mod
from .config import Config
from .pipeline import parse_inputs, run
from .storage import Store

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

GRADE_MIN = {"A": 70, "B": 50, "C": 30, "D": 0}


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(prog="b2b_intel",
                                description=f"B2B Lead Intelligence Engine v{__version__} by {__author__}")
    sub = p.add_subparsers(dest="cmd", required=True)
    db = argparse.ArgumentParser(add_help=False)
    db.add_argument("--db", default="leads.db", help="SQLite database path (default leads.db)")

    r = sub.add_parser("run", parents=[db], help="crawl domains and store scored leads")
    r.add_argument("input", nargs="?", help="file with domains/URLs (.txt/.csv), one or many per line")
    r.add_argument("--domains", nargs="*", default=[], help="domains passed directly")
    r.add_argument("--concurrency", type=int, default=10)
    r.add_argument("--delay", type=float, default=1.0, help="seconds between requests to the same host")
    r.add_argument("--timeout", type=float, default=15.0)
    r.add_argument("--max-pages", type=int, default=4, help="homepage + contact/about pages")
    r.add_argument("--force", action="store_true", help="re-crawl domains already stored")
    r.add_argument("--ignore-robots", action="store_true", help="NOT recommended")
    r.add_argument("--weights", default="", help="JSON file overriding scoring weights")

    rp = sub.add_parser("report", parents=[db], help="build interactive HTML dashboard")
    rp.add_argument("-o", "--out", default="leads-report.html")
    rp.add_argument("--min-score", type=int, default=0)

    ex = sub.add_parser("export", parents=[db], help="export leads to CSV / JSON")
    ex.add_argument("--format", choices=["csv", "json"], default="csv")
    ex.add_argument("--min-grade", choices=list(GRADE_MIN), default="D")
    ex.add_argument("-o", "--out", default="")

    sub.add_parser("stats", parents=[db], help="print database statistics")
    return p


def cmd_run(a) -> int:
    lines = list(a.domains)
    if a.input:
        path = Path(a.input)
        if not path.is_file():
            print(f"ERROR: input file not found: {a.input}", file=sys.stderr)
            return 2
        lines += path.read_text(encoding="utf-8-sig", errors="replace").splitlines()
    domains = parse_inputs(lines)
    if not domains:
        print("ERROR: no valid domains. Pass a file or --domains example.com", file=sys.stderr)
        return 2
    cfg = Config(db_path=a.db, concurrency=a.concurrency, per_host_delay=a.delay, timeout=a.timeout,
                 max_pages=a.max_pages, respect_robots=not a.ignore_robots, force=a.force,
                 weights_path=a.weights)
    print(f"{len(domains)} unique domains • concurrency={cfg.concurrency} • robots={'on' if cfg.respect_robots else 'OFF'}")
    with Store(a.db) as store:
        try:
            summary = asyncio.run(run(domains, cfg, store))
        except KeyboardInterrupt:
            print("\nInterrupted - progress is saved. Re-run the same command to resume.")
            return 130
    print("\nSummary:", json.dumps(summary, ensure_ascii=False))
    print(f"Next:  python -m b2b_intel report --db {a.db}")
    return 0


def cmd_report(a) -> int:
    with Store(a.db) as s:
        leads = s.all(min_score=a.min_score)
    report_mod.build(leads, a.out)
    print(f"Report with {len(leads)} leads -> {a.out}")
    return 0


def cmd_export(a) -> int:
    with Store(a.db) as s:
        leads = [l for l in s.all(only_ok=True) if l.score >= GRADE_MIN[a.min_grade]]
    out = a.out or f"leads.{a.format}"
    if a.format == "json":
        Path(out).write_text(json.dumps([l.to_dict() for l in leads], ensure_ascii=False, indent=2), encoding="utf-8")
    else:
        with open(out, "w", newline="", encoding="utf-8-sig") as f:
            w = csv.writer(f)
            w.writerow(["domain", "name", "grade", "score", "best_email", "all_emails", "phones", "linkedin",
                        "instagram", "telegram", "address", "tech", "opportunities"])
            for l in leads:
                w.writerow([l.domain, l.name, l.grade, l.score, l.best_email,
                            " | ".join(e["address"] for e in l.emails), " | ".join(l.phones),
                            l.socials.get("linkedin", ""), l.socials.get("instagram", ""),
                            l.socials.get("telegram", ""), l.address, " | ".join(l.tech_names()),
                            " | ".join(l.opportunities)])
    print(f"Exported {len(leads)} leads -> {out}")
    return 0


def cmd_stats(a) -> int:
    with Store(a.db) as s:
        print(json.dumps(s.stats(), ensure_ascii=False, indent=2))
    return 0


def main(argv=None) -> int:
    a = build_parser().parse_args(argv)
    return {"run": cmd_run, "report": cmd_report, "export": cmd_export, "stats": cmd_stats}[a.cmd](a)
