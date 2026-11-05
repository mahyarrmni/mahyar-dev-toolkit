import asyncio
import os
import threading

from . import config, db
from .crawler import CrawlConfig, Crawler

ALLOWED = {"max_pages", "max_depth", "concurrency", "delay", "timeout", "max_time", "include_subdomains", "check_external", "render", "use_sitemaps"}


def _set(crawl_id, **kw):
    with db.session() as s:
        c = s.get(db.Crawl, crawl_id)
        for k, v in kw.items():
            setattr(c, k, v)


def run_crawl_job(crawl_id: int):
    try:
        with db.session() as s:
            c = s.get(db.Crawl, crawl_id)
            site, user = s.get(db.Project, c.project_id).site_url, dict(c.config or {})
        _set(crawl_id, status="running", started_at=db.now())
        cfg = CrawlConfig(start_url=site, allow_private=config.ALLOW_PRIVATE, **{k: v for k, v in user.items() if k in ALLOWED})  # respect_robots/allow_private never user-controlled
        buf = []

        def flush():
            if buf:
                with db.session() as s:
                    s.add_all(db.Page(crawl_id=crawl_id, **{k: r.get(k) for k in db.PAGE_COLS if k != "crawl_id"}) for r in buf)
                buf.clear()

        def on_page(rec):
            buf.append(rec)
            if len(buf) >= 100:
                flush()

        stats = asyncio.run(Crawler(cfg, on_page).run())
        flush()
        _set(crawl_id, status="done", stats=stats, finished_at=db.now())
    except Exception as e:
        _set(crawl_id, status="failed", error=f"{type(e).__name__}: {e}"[:500], finished_at=db.now())


def run_analysis_job(crawl_id: int, params: dict = None):
    from .pipeline import run_analysis
    _status(crawl_id, "running")
    try:
        _status(crawl_id, "done", run_analysis(crawl_id, params))
    except Exception as e:
        _status(crawl_id, "failed", {"error": f"{type(e).__name__}: {e}"[:500]})


def _status(crawl_id, state, extra=None):
    with db.session() as s:
        s.query(db.Analysis).filter_by(crawl_id=crawl_id, kind="status").delete()
        s.add(db.Analysis(crawl_id=crawl_id, kind="status", data={"state": state, **(extra or {})}))


def dispatch(kind: str, *args):
    """Celery when REDIS_URL is set; inline when ASIGE_SYNC_JOBS=1 (tests); otherwise a daemon thread."""
    fn = {"crawl": run_crawl_job, "analyze": run_analysis_job}[kind]
    if config.REDIS_URL:
        from .tasks import app
        app.send_task(f"asige.{kind}", args=list(args))
    elif os.getenv("ASIGE_SYNC_JOBS") == "1":
        fn(*args)
    else:
        threading.Thread(target=fn, args=args, daemon=True).start()
