"""One-command demo on a synthetic local site (no network needed): crawl -> synthetic GSC -> full analysis -> reports in ./demo_out.
Run: python demo.py"""
import asyncio
import os
import tempfile

import pandas as pd

os.environ["ASIGE_ALLOW_PRIVATE"] = "1"
from asige import db, pipeline, technical
from asige.crawler import CrawlConfig, Crawler
from tests.site import build_site, serve
from tests.synth import make_gsc

routes = {}
base, srv = serve(routes)
routes.update(build_site(base))
pages = []
cfg = CrawlConfig(start_url=base + "/", delay=0, max_pages=200, allow_private=True, check_external=True)
stats = asyncio.run(Crawler(cfg, pages.append).run())
tech = technical.analyze(pages, start_url=base + "/", external=stats["external"])
gsc = make_gsc([p for p in pages if tech["page_flags"][p["url"]]["indexable"]], base)
db.configure(f"sqlite:///{tempfile.mkdtemp()}/demo.db")
with db.session() as s:
    pr = db.Project(name="demo", site_url=base + "/"); s.add(pr); s.flush()
    cr = db.Crawl(project_id=pr.id, status="done", stats=stats, config={}); s.add(cr); s.flush()
    s.add_all(db.Page(crawl_id=cr.id, **{k: r.get(k) for k in db.PAGE_COLS if k != "crawl_id"}) for r in pages)
    s.add_all(db.GscRow(project_id=pr.id, query=r.query, page=r.page, clicks=r.clicks, impressions=r.impressions, position=r.position) for r in gsc.itertuples())
    cid = cr.id
res = pipeline.run_analysis(cid, {"n_boot": 50})
with db.session() as s:
    d = {r.kind: r.data for r in s.query(db.Analysis).filter_by(crawl_id=cid)}
os.makedirs("demo_out", exist_ok=True)
open("demo_out/technical.md", "w").write(d["report_technical"]); open("demo_out/strategic.md", "w").write(d["report_strategic"])
print(f"health {res['health_score']}/100 · {res['recommendations']} recommendations · hash {res['repro_hash']}\nreports in ./demo_out")
for r in d["recommendations"][:8]:
    print(f"  {r['priority']:6.2f}  {r['title'][:80]}")
srv.shutdown()
