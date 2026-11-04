import os
import tempfile

os.environ["DB_PATH"] = os.path.join(tempfile.mkdtemp(), "t.db")

from fastapi.testclient import TestClient

from app import analyze, discover, fetch, scrape
from app.main import app

HTML = """<html><head><title>Smile Dental</title><meta name="description" content="Family dentist"></head>
<body><nav><li>Home</li></nav><h2>Teeth Whitening</h2><h2>Dental Implants</h2><h3>Contact us</h3>
<a href="mailto:info@smile.de">mail</a><a href="tel:+4930123456">call</a>
<a href="https://www.instagram.com/smile">ig</a><a href="/book">Book appointment</a></body></html>"""


def test_parse():
    p = scrape.parse(HTML, "https://smile.de/", False)
    assert p["services"] == ["teeth whitening", "dental implants"]
    assert p["emails"] == ["info@smile.de"] and p["phones"] == ["+4930123456"]
    assert len(p["socials"]) == 1 and p["booking"] and p["description"] == "Family dentist"


def test_safe_url_blocks_local():
    assert not fetch.safe_url("http://127.0.0.1/admin")
    assert not fetch.safe_url("file:///etc/passwd")


def _biz(name, services, booking=False):
    return {"name": name, "website": "http://x", "sources": [],
            "scraped": {"services": services, "emails": [], "socials": [], "booking": booking}}


def test_analyze():
    bs = [_biz("A", ["whitening", "implants"]), _biz("B", ["whitening", "braces"]),
          _biz("C", ["whitening", "implants"]), {"name": "D", "website": None, "sources": []}]
    a = analyze.analyze(bs)
    assert a["confidence"] == "medium" and a["businesses_scraped"] == 3
    assert a["table_stakes"][0]["service"] == "whitening"
    assert a["niche_services"][0]["service"] == "braces"
    types = {o["type"] for o in a["opportunities"]}
    assert {"digital_presence", "online_booking", "differentiation"} <= types


def test_discover_parse(monkeypatch):
    class R:
        def raise_for_status(self): pass
        def json(self):
            return [{"name": "Zahn", "osm_type": "node", "osm_id": 1, "display_name": "Berlin",
                     "extratags": {"website": "zahn.de"}}, {"name": "NoSite", "osm_type": "node", "osm_id": 2}]
    monkeypatch.setattr(discover.requests, "get", lambda *a, **k: R())
    out = discover.search("dentist", "Berlin", 5)
    assert out[0]["website"] == "http://zahn.de" and out[1]["website"] is None
    assert out[0]["sources"][0].endswith("/node/1")


def test_api_flow(monkeypatch):
    monkeypatch.setattr(discover, "search", lambda *a: [
        {"name": "Smile", "address": "Berlin", "website": "http://smile.de", "sources": ["https://osm/1"]}])
    monkeypatch.setattr(scrape, "scrape_site", lambda u: {
        "url": u, "pages": [u], "services": ["whitening"], "emails": [], "phones": [], "socials": [],
        "booking": False, "errors": [], "title": "", "description": ""})
    c = TestClient(app)
    r = c.post("/research", json={"industry": "dentist", "location": "Berlin"})
    assert r.status_code == 202
    rid = r.json()["id"]
    body = c.get(f"/reports/{rid}").json()
    assert body["status"] == "done" and body["report"]["businesses"][0]["sources"][-1] == "http://smile.de"
    assert "# Market Research: dentist in Berlin" in c.get(f"/reports/{rid}/markdown").text


def test_failure_is_reported(monkeypatch):
    def boom(*a):
        raise RuntimeError("net down")
    monkeypatch.setattr(discover, "search", boom)
    c = TestClient(app)
    rid = c.post("/research", json={"industry": "bakery", "location": "Köln"}).json()["id"]
    body = c.get(f"/reports/{rid}").json()
    assert body["status"] == "failed" and "net down" in body["error"]
    assert c.get(f"/reports/{rid}/markdown").status_code == 409
    assert c.get("/reports/9999").status_code == 404
