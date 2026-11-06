from tests.conftest import crawl


def by_url(pages, base):
    return {p["url"].replace(base, ""): p for p in pages}


def test_discovery_and_metadata(crawled, site):
    pages, stats = crawled
    P = by_url(pages, site)
    assert P["/coffee/espresso"]["status"] == 200 and P["/coffee/espresso"]["title"] == "Espresso brewing guide"
    assert P["/print/espresso"]["canonical"] == site + "/coffee/espresso"
    assert "noindex" in P["/private"]["meta_robots"]
    assert P["/missing"]["status"] == 404
    assert P["/fa/seo-chist"]["lang"] == "fa" and P["/fa/seo-chist"]["lang_attr"] == "fa"
    assert len(P["/blog/page/1"]["rel_next"]) > 0 and P["/blog/page/3"]["rel_next"].endswith("/blog/page/4")


def test_robots_sitemap_redirects(crawled, site):
    pages, stats = crawled
    P = by_url(pages, site)
    assert P["/admin/"]["blocked_by_robots"] and P["/admin/"]["status"] is None
    assert P["/orphan"]["in_sitemap"] and P["/orphan"]["status"] == 200  # found only via sitemap
    assert [h["status"] for h in P["/old"]["redirect_chain"]] == [301, 301] and P["/old"]["final_url"].endswith("/coffee/espresso")
    assert P["/loop-a"]["redirect_loop"]
    assert stats["sitemap_urls"] >= 10


def test_external_check(crawled):
    ext = crawled[1]["external"]
    assert "http://external.invalid/x" in ext and ext["http://external.invalid/x"]["status"] is None


def test_budgets(site):
    pages, stats = crawl(site, max_pages=10)
    assert len(pages) <= 12 and stats["budget_exhausted"]
    pages, _ = crawl(site, max_depth=1, use_sitemaps=False)
    urls = {p["url"].replace(site, "") for p in pages}
    assert "/d1" in urls and "/d2" not in urls
    pages, _ = crawl(site, respect_robots=False, use_sitemaps=False, max_pages=100)
    assert {p["url"].replace(site, ""): p for p in pages}["/admin/"]["status"] == 200


def test_ssrf_guard(site):
    import asyncio
    from asige.crawler import CrawlConfig, Crawler
    pages = []
    c = Crawler(CrawlConfig(start_url=site + "/", delay=0, check_external=False), on_page=pages.append)  # allow_private False
    asyncio.run(c.run())
    assert pages and pages[0]["status"] is None and "blocked" in pages[0]["error"]
