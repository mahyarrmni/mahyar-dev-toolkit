"""Reproducible benchmarks on a synthetic local site (generated on the fly). Run: python -m benchmarks.bench [N_PAGES]
Measures: crawl throughput, parse cost, SimHash+LSH vs brute force, embedding/clustering, graph analytics, full pipeline."""
import asyncio
import os
import json
import platform
import random
import sys
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import numpy as np

from asige import db, dedup, linking, pipeline, semantic
from asige.crawler import CrawlConfig, Crawler
from asige.parse import parse_html

VOCAB = [f"term{i}" for i in range(3000)]
TOPICS = 40


def article(i):
    rng = random.Random(i)
    topic = i % TOPICS
    words = [VOCAB[topic * 60 + rng.randrange(60)] if rng.random() < 0.5 else rng.choice(VOCAB) for _ in range(400)]
    links = "".join(f'<a href="/p/{rng.randrange(N)}">link {rng.randrange(99)}</a> ' for _ in range(12))
    return (f'<html lang="en"><head><title>Article {i} about {VOCAB[topic * 60]}</title><meta name="description" content="Description for article {i} with enough characters to be valid and unique"></head>'
            f'<body><h1>Article {i}</h1><p>{" ".join(words)}</p><div>{links}</div></body></html>')


def serve(n):
    class H(BaseHTTPRequestHandler):
        protocol_version = "HTTP/1.1"
        def log_message(self, *a): pass
        def do_GET(self):
            if self.path == "/":
                body = "<html><title>Home</title><body>" + "".join(f'<a href="/p/{i}">a{i}</a>' for i in range(min(n, 200))) + "</body></html>"
            elif self.path.startswith("/p/"):
                body = article(int(self.path.split("/")[2]) % n)
            else:
                self.send_response(404); self.send_header("Content-Length", "0"); self.end_headers(); return
            b = body.encode()
            self.send_response(200); self.send_header("Content-Type", "text/html; charset=utf-8"); self.send_header("Content-Length", str(len(b))); self.end_headers(); self.wfile.write(b)
    srv = ThreadingHTTPServer(("127.0.0.1", 0), H)
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    return f"http://127.0.0.1:{srv.server_address[1]}", srv


def timed(f, *a, **k):
    t = time.perf_counter(); r = f(*a, **k); return r, time.perf_counter() - t


if __name__ == "__main__":
    N = int(sys.argv[1]) if len(sys.argv) > 1 else 2000
    out = {"machine": {"python": platform.python_version(), "cpu": platform.processor() or platform.machine()}, "pages": N}
    out["machine"]["cores"] = os.cpu_count()
    base, srv = serve(N)
    pages = []
    for conc in [int(c) for c in os.environ.get('BENCH_CONC', '8,32').split(',')]:
        pages = []
        cfg = CrawlConfig(start_url=base + "/", max_pages=N + 5, concurrency=conc, delay=0, allow_private=True, check_external=False, use_sitemaps=False, respect_robots=False)
        t = time.perf_counter(); st = asyncio.run(Crawler(cfg, pages.append).run()); el = time.perf_counter() - t
        out[f"crawl_c{conc}"] = {"pages": st["pages"], "seconds": round(el, 2), "pages_per_s": round(st["pages"] / el, 1)}
    srv.shutdown()
    html = article(1)
    _, t = timed(lambda: [parse_html(html, "http://x/p/1") for _ in range(200)])
    out["parse_ms_per_page"] = round(t / 200 * 1000, 2)

    rr = random.Random(7)
    toks = [[f"w{rr.randrange(5000)}" for _ in range(300)] for _ in range(5000)]  # independent docs
    fps = {i: dedup.simhash(t) for i, t in enumerate(toks)}
    for i in range(500): fps[f"dup{i}"] = fps[i] ^ (1 << (i % 64))  # 500 planted near-duplicates (1 bit apart)
    lsh, t_lsh = timed(dedup.find_near_duplicates, fps, 3)
    sub = dict(list(fps.items())[:1500])
    brute, t_b = timed(dedup.brute_force_pairs, sub, 3)
    lsh_sub, _ = dedup.find_near_duplicates(sub, 3)
    out["simhash"] = {"docs": len(fps), "lsh_seconds": round(t_lsh, 3), "lsh_pairs": len(lsh[0]), "planted_pairs": 500, "brute_docs": len(sub), "brute_seconds": round(t_b, 3),
                      "lsh_equals_brute_on_subset": lsh_sub == brute,
                      "brute_extrapolated_full_seconds": round(t_b * (len(fps) / len(sub)) ** 2, 1)}

    texts = [semantic.page_text(p) for p in pages if p["status"] == 200]
    idx, t = timed(lambda: semantic.SemanticIndex().fit(texts)); out["embed_fit_seconds"] = round(t, 2); out["embed_docs"] = len(texts)
    _, t = timed(semantic.topk_cosine, idx.E, idx.E, 5); out["topk5_seconds"] = round(t, 2)
    _, t = timed(semantic.cluster_vectors, idx.E, 0.8); out["cluster_seconds"] = round(t, 2)

    tmp = tempfile.mkdtemp(); db.configure(f"sqlite:///{tmp}/b.db")
    with db.session() as s:
        p = db.Project(name="b", site_url=base + "/"); s.add(p); s.flush()
        c = db.Crawl(project_id=p.id, status="done", stats={}, config={}); s.add(c); s.flush()
        s.add_all(db.Page(crawl_id=c.id, **{k: r.get(k) for k in db.PAGE_COLS if k != "crawl_id"}) for r in pages); cid = c.id
    res, t = timed(pipeline.run_analysis, cid, {"n_boot": 10})
    out["pipeline_total_seconds"] = round(t, 2); out["pipeline_stage_seconds"] = res["stage_seconds"]
    print(json.dumps(out, indent=2))
