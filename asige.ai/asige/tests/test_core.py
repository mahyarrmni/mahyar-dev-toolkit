from asige import dedup, parse
from asige.text import detect_lang, normalize, tokenize
from asige.urls import normalize_url, same_site
import random


def test_normalize_and_tokenize_persian():
    assert normalize("۱۲۳ كتاب") == "123 کتاب"
    t = tokenize("آموزش‌های سئو و ۱۲۳ لینک‌سازی")
    assert "سئو" in t and "های" not in t and "123" not in t and "آموزش" in t


def test_detect_lang():
    assert detect_lang("آموزش سئو سایت") == "fa" and detect_lang("seo guide") == "en"


def test_normalize_url():
    assert normalize_url("HTTP://Example.COM:80/a b?utm_source=x&b=2&a=1#frag") == "http://example.com/a%20b?a=1&b=2"
    assert normalize_url("/x/", base="https://e.com/y") == "https://e.com/x/"
    assert normalize_url("https://e.com/آموزش") == normalize_url("https://e.com/%D8%A2%D9%85%D9%88%D8%B2%D8%B4")
    assert normalize_url("mailto:a@b.c") is None and normalize_url("javascript:void(0)") is None
    assert same_site("https://www.a.com/x", "http://a.com/y") and not same_site("https://b.a.com", "https://a.com")
    assert same_site("https://b.a.com", "https://a.com", include_subdomains=True)


HTML = """<html lang="en"><head><title> Hello  World </title><meta name="description" content="Desc here">
<meta name="robots" content="NoIndex, follow"><link rel="canonical" href="/canon"><link rel="next" href="/p2">
<link rel="alternate" hreflang="fa" href="/fa/"><script type="application/ld+json">{"@graph":[{"@type":"Article","headline":"x"}]}</script>
</head><body><nav><a href="/nav">navlink</a></nav><h1>Main</h1><h2>Sub</h2><p>Some body text for the page content.</p>
<a href="/a#frag">Anchor A</a><a href="https://ext.com/z" rel="nofollow">Ext</a><a href="mailto:x@y.z">mail</a><img src="i.png"><img src="j.png" alt="ok"></body></html>"""


def test_parse_html():
    p = parse.parse_html(HTML, "https://site.com/page")
    assert p["title"] == "Hello World" and p["meta_description"] == "Desc here" and "noindex" in p["meta_robots"]
    assert p["canonical"] == "https://site.com/canon" and p["rel_next"] == "https://site.com/p2"
    assert p["h1s"] == ["Main"] and p["h2s"] == ["Sub"] and p["hreflang"][0]["url"] == "https://site.com/fa/"
    dsts = {l["url"]: l for l in p["links"]}
    assert "https://site.com/a" in dsts and dsts["https://ext.com/z"]["nofollow"] and len(dsts) == 3  # nav link kept, mailto dropped
    assert p["jsonld"][0]["@type"] == "Article" and p["images_missing_alt"] == 1 and "navlink" not in p["text"]
    assert p["word_count"] > 5 and len(p["simhash"]) == 16


def test_lsh_matches_brute_force():
    rng = random.Random(3)
    vocab = [f"w{i}" for i in range(400)]
    docs = {f"d{i}": [rng.choice(vocab) for _ in range(300)] for i in range(120)}
    for i in range(20):  # near duplicates: ~2% of tokens edited
        src = list(docs[f"d{i}"])
        for _ in range(2):
            src[rng.randrange(300)] = rng.choice(vocab)
        docs[f"n{i}"] = src
    fps = {k: dedup.simhash(v) for k, v in docs.items()}
    fast, skipped = dedup.find_near_duplicates(fps, max_dist=3)
    brute = dedup.brute_force_pairs(fps, max_dist=3)
    assert skipped == 0 and fast == brute and len(brute) >= 10
