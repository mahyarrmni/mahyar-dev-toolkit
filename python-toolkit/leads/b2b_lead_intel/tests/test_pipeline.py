"""End-to-end test against a real local aiohttp server (no internet needed)."""
import asyncio, os, tempfile, unittest
from aiohttp import web

from b2b_intel.config import Config
from b2b_intel.pipeline import run
from b2b_intel.storage import Store
from tests.test_extractors import enc_cf

HOME = """<html lang=fa><head><title>Acme | شرکت نمونه</title><meta name="description" content="ما نرم افزار می‌سازیم">
<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script></head><body>
<a href="/contact">تماس با ما</a> <a href="/private/secret">secret contact</a> <a href="https://www.linkedin.com/company/acme">in</a>
<p>شرکت ما در تهران</p></body></html>"""
CONTACT = f"""<html><body><a data-cfemail="{enc_cf('sales@acme.test')}">[email protected]</a>
<a href="mailto:ceo@acme.test">CEO</a><p>تلفن: ۰۲۱-۸۸۷۷۶۶۵۵ موبایل 09121234567</p></body></html>"""
SECRET = "<html><body>hidden@acme.test</body></html>"


def make_app(hits):
    async def home(r): hits.append("/"); return web.Response(text=HOME, content_type="text/html")
    async def contact(r): hits.append("/contact"); return web.Response(text=CONTACT, content_type="text/html")
    async def secret(r): hits.append("/private/secret"); return web.Response(text=SECRET, content_type="text/html")
    async def robots(r): return web.Response(text="User-agent: *\nDisallow: /private/\n")
    flaky = {"n": 0}
    async def flaky_h(r):
        flaky["n"] += 1
        if flaky["n"] < 2:
            return web.Response(status=503)
        return web.Response(text="<html><body>ok info@x.io</body></html>", content_type="text/html")
    app = web.Application()
    app.add_routes([web.get("/", home), web.get("/contact", contact), web.get("/private/secret", secret),
                    web.get("/robots.txt", robots)])
    return app, flaky_h


class PipelineE2E(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        self.hits = []
        app, _ = make_app(self.hits)
        self.runner = web.AppRunner(app); await self.runner.setup()
        site = web.TCPSite(self.runner, "127.0.0.1", 0); await site.start()
        self.port = site._server.sockets[0].getsockname()[1]
        self.tmp = tempfile.TemporaryDirectory()
        self.cfg = Config(db_path=os.path.join(self.tmp.name, "t.db"), per_host_delay=0, concurrency=3, retries=1)

    async def asyncTearDown(self):
        await self.runner.cleanup(); self.tmp.cleanup()

    def _domain(self):
        return f"127.0.0.1:{self.port}"

    async def test_full_crawl_extracts_scores_and_respects_robots(self):
        # the pipeline normalises domains, but here we call run() directly with host:port
        with Store(self.cfg.db_path) as store:
            summary = await run([self._domain()], self.cfg, store, log=lambda *_: None)
            self.assertEqual(summary["ok"], 1)
            lead = store.all(only_ok=True)[0]
        emails = {e["address"] for e in lead.emails}
        self.assertTrue({"sales@acme.test", "ceo@acme.test"} <= emails)
        self.assertNotIn("hidden@acme.test", emails)             # robots.txt honoured
        self.assertNotIn("/private/secret", self.hits)
        self.assertIn("+982188776655", lead.phones)
        self.assertIn("+989121234567", lead.phones)
        self.assertIn("linkedin", lead.socials)
        self.assertIn("Google Analytics", lead.tech_names())
        self.assertEqual(lead.lang, "fa")
        self.assertEqual(lead.pages_crawled, 2)
        self.assertGreater(lead.score, 40)

    async def test_resume_skips_done_and_force_recrawls(self):
        with Store(self.cfg.db_path) as store:
            await run([self._domain()], self.cfg, store, log=lambda *_: None)
            n = len(self.hits)
            s2 = await run([self._domain()], self.cfg, store, log=lambda *_: None)
            self.assertEqual((s2["skipped"], len(self.hits)), (1, n))
            from dataclasses import replace
            await run([self._domain()], replace(self.cfg, force=True), store, log=lambda *_: None)
            self.assertGreater(len(self.hits), n)

    async def test_unreachable_domain_is_recorded_not_fatal(self):
        with Store(self.cfg.db_path) as store:
            from dataclasses import replace
            cfg = replace(self.cfg, timeout=2, retries=0)
            summary = await run(["127.0.0.1:1", self._domain()], cfg, store, log=lambda *_: None)
            self.assertEqual((summary["ok"], summary["unreachable"]), (1, 1))

    async def test_retry_on_503(self):
        from b2b_intel.fetcher import Fetcher
        app = web.Application()
        _, flaky = make_app([])
        app.add_routes([web.get("/", flaky)])
        r = web.AppRunner(app); await r.setup()
        s = web.TCPSite(r, "127.0.0.1", 0); await s.start()
        port = s._server.sockets[0].getsockname()[1]
        try:
            async with Fetcher(self.cfg) as f:
                res = await f.fetch(f"http://127.0.0.1:{port}/")
            self.assertTrue(res.ok)
        finally:
            await r.cleanup()


if __name__ == "__main__":
    unittest.main()
