"""Offline tests for the SEO analyzer: HTML is injected, so no network is used."""

from bs4 import BeautifulSoup
from seo_analyzer.analyzer import SEOAnalyzer

HTML = """<!doctype html><html lang="fa"><head><meta charset="utf-8">
<title>عنوان آزمایشی برای صفحه‌ی تست سئو</title>
<meta name="description" content="توضیحات کوتاه">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="canonical" href="https://example.com/">
<meta property="og:title" content="x"><meta name="twitter:card" content="summary">
<script type="application/ld+json">{"@type":"Article","name":"x"}</script></head>
<body><h1>سلام</h1><h1>دوباره</h1><h2>بخش</h2>
<p>متن آزمایشی <strong>سئو سایت</strong> و <em>محتوا</em>.</p>
<img src="/a.png"><img src="/b.png" alt="تصویر">
<a href="/x">داخلی</a><a href="https://other.org/" rel="nofollow">خارجی</a>
<ul><li>1</li></ul><table><tr><td>1</td></tr></table></body></html>"""


def make_analyzer() -> SEOAnalyzer:
    a = SEOAnalyzer("example.com")
    a.soup = BeautifulSoup(HTML, "lxml")
    a.final_url = "https://example.com/"
    return a


def test_url_gets_default_scheme():
    assert SEOAnalyzer("example.com").url == "http://example.com"
    assert SEOAnalyzer("https://example.com").url == "https://example.com"


def test_meta_and_headings():
    a = make_analyzer()
    a.analyze_meta()
    a.analyze_headings()
    assert a.report["title"]["length"] > 10
    assert a.report["meta_description"]["length"] < 70
    assert a.report["headings"]["h1"]["count"] == 2
    metrics = {s["metric"] for s in a.suggestions}
    assert "Headings (H1)" in metrics  # multiple H1
    assert "Meta Description" in metrics  # too short


def test_images_and_links():
    a = make_analyzer()
    a.analyze_images()
    a.analyze_links()
    assert a.report["images"]["total"] == 2
    assert a.report["images"]["missing_or_empty_alt"] == 1
    assert a.report["links"]["internal"] == 1
    assert a.report["links"]["external"] == 1
    assert a.report["links"]["nofollow_sponsored_ugc_external"] == 1


def test_run_analysis_safe_isolates_failures():
    a = make_analyzer()

    def boom():
        raise RuntimeError("x")

    a._run_analysis_safe(boom)
    assert any(s.get("type") == "error" for s in a.suggestions)


def test_keywords_ignore_stopwords():
    a = make_analyzer()
    a.analyze_keywords()
    top = a.report["keywords"]["top_single_keywords"]
    assert "the" not in top and "و" not in top
