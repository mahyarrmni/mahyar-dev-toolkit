import asyncio

import pytest

from asige import config, db
from asige.crawler import CrawlConfig, Crawler
from tests.site import build_site, serve


@pytest.fixture(scope="session")
def site():
    routes = {}
    base, srv = serve(routes)
    routes.update(build_site(base))
    yield base
    srv.shutdown()


def crawl(base, **kw):
    pages = []
    cfg = CrawlConfig(start_url=base + "/", delay=0, allow_private=True, check_external=kw.pop("check_external", False), **kw)
    c = Crawler(cfg, on_page=pages.append)
    stats = asyncio.run(c.run())
    return pages, stats


@pytest.fixture(scope="session")
def crawled(site):
    return crawl(site, max_pages=200, check_external=True)


@pytest.fixture()
def tmpdb(tmp_path):
    db.configure(f"sqlite:///{tmp_path}/t.db")
    yield


@pytest.fixture(scope="session")
def kit(crawled, site):
    """Shared analysis inputs built from the crawled synthetic site."""
    import numpy as np
    from asige import intent, linking, semantic, technical
    from tests.synth import make_gsc
    pages, stats = crawled
    tech = technical.analyze(pages, start_url=site + "/", external=stats["external"])
    flags = tech["page_flags"]
    ipages = [p for p in pages if flags[p["url"]]["indexable"] and p["status"] == 200]
    index = semantic.SemanticIndex().fit([semantic.page_text(p) for p in ipages])
    G = linking.build_graph(pages, flags)
    M = linking.metrics(G, site + "/")
    intents = {p["url"]: intent.classify_page(p) for p in ipages}
    return {"pages": pages, "stats": stats, "tech": tech, "flags": flags, "ipages": ipages, "index": index, "G": G, "M": M,
            "intents": intents, "gsc": make_gsc(ipages, site), "start": site + "/"}
