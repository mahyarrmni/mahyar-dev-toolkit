import unittest
from bs4 import BeautifulSoup
from b2b_intel import extractors as ex
from b2b_intel.techdetect import detect


def enc_cf(email: str, key: int = 0x42) -> str:
    return f"{key:02x}" + "".join(f"{b ^ key:02x}" for b in email.encode())


class ExtractorTests(unittest.TestCase):
    def test_cloudflare_decode(self):
        self.assertEqual(ex.decode_cf_email(enc_cf("sales@acme.com")), "sales@acme.com")
        self.assertIsNone(ex.decode_cf_email("zz"))

    def test_deobfuscate(self):
        self.assertEqual(ex.deobfuscate("john [at] acme [dot] com"), "john@acme.com")
        self.assertEqual(ex.deobfuscate("john (at) acme (dot) com"), "john@acme.com")

    def test_emails_all_sources_and_filters(self):
        html = f"""<a href="mailto:Info@Acme.com?subject=hi">x</a>
        <a class="__cf_email__" data-cfemail="{enc_cf('ceo@acme.com')}">[protected]</a>
        <p>write: bob [at] acme [dot] com or logo@2x.png or a@example.com or help@gmail.com</p>"""
        soup = BeautifulSoup(html, "lxml")
        got = {e["address"]: e for e in ex.extract_emails(soup, soup.get_text(" "), "acme.com")}
        self.assertEqual(set(got), {"info@acme.com", "ceo@acme.com", "bob@acme.com", "help@gmail.com"})
        self.assertEqual(got["info@acme.com"]["kind"], "generic")
        self.assertEqual(got["ceo@acme.com"]["kind"], "personal")
        self.assertTrue(got["bob@acme.com"]["same_domain"])
        self.assertFalse(got["help@gmail.com"]["same_domain"])

    def test_email_ranking_prefers_company_domain_then_personal(self):
        soup = BeautifulSoup("<p>info@acme.com ali@acme.com me@gmail.com</p>", "lxml")
        order = [e["address"] for e in ex.extract_emails(soup, soup.get_text(" "), "acme.com")]
        self.assertEqual(order[0], "ali@acme.com")
        self.assertEqual(order[-1], "me@gmail.com")

    def test_phone_normalisation(self):
        n = ex.normalize_phone
        self.assertEqual(n("0912 345 6789"), "+989123456789")
        self.assertEqual(n("۰۹۱۲۳۴۵۶۷۸۹"), "+989123456789")
        self.assertEqual(n("021-88776655"), "+982188776655")
        self.assertEqual(n("+44 20 7946 0958"), "+442079460958")
        self.assertEqual(n("0098 21 88776655"), "+982188776655")
        self.assertIsNone(n("12345"))

    def test_phone_extraction(self):
        html = '<a href="tel:+98-21-88776655">call</a><p>موبایل: ۰۹۱۲۱۲۳۴۵۶۷ | Fax +1 (415) 555-2671 | order 2024-11-03</p>'
        soup = BeautifulSoup(html, "lxml")
        got = ex.extract_phones(soup, soup.get_text(" "))
        self.assertIn("+982188776655", got)
        self.assertIn("+989121234567", got)
        self.assertIn("+14155552671", got)
        self.assertEqual(len(got), len(set(got)))

    def test_socials_ignore_share_links(self):
        html = """<a href="https://www.linkedin.com/company/acme/">l</a>
        <a href="https://twitter.com/intent/tweet?x=1">s</a><a href="https://x.com/acme_hq">x</a>
        <a href="https://instagram.com/acme.official/">i</a><a href="https://t.me/acme_channel">t</a>"""
        s = ex.extract_socials(BeautifulSoup(html, "lxml"))
        self.assertEqual(s["linkedin"], "https://linkedin.com/company/acme")
        self.assertEqual(s["twitter"], "https://x.com/acme_hq")
        self.assertEqual(s["instagram"], "https://instagram.com/acme.official")
        self.assertEqual(s["telegram"], "https://t.me/acme_channel")

    def test_jsonld_org(self):
        html = """<script type="application/ld+json">{"@graph":[{"@type":"Organization","name":"Acme Co",
        "telephone":"+98 21 1234 5678","address":{"streetAddress":"Valiasr","addressLocality":"Tehran"},
        "numberOfEmployees":{"value":42},"sameAs":["https://linkedin.com/company/acme"]}]}</script>"""
        org = ex.extract_jsonld_org(BeautifulSoup(html, "lxml"))
        self.assertEqual(org["name"], "Acme Co")
        self.assertEqual(org["employees"], 42)
        self.assertIn("Tehran", org["address"])

    def test_contact_links_ranked_and_same_host_only(self):
        html = """<a href="/about-us">About</a><a href="/contact">Contact</a><a href="/تماس-با-ما">x</a>
        <a href="https://other.com/contact">no</a><a href="/brochure.pdf">pdf</a><a href="/blog">blog</a>"""
        links = ex.find_contact_links(BeautifulSoup(html, "lxml"), "https://www.acme.com/")
        self.assertTrue(all("acme.com" in l for l in links))
        self.assertNotIn("https://www.acme.com/blog", links)
        self.assertFalse(any(l.endswith(".pdf") for l in links))
        self.assertEqual(len(links), 3)

    def test_tech_detection(self):
        html = '<link href="/wp-content/x.css"><script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC123"></script><script>fbq("init")</script>'
        names = {t["name"] for t in detect(html, {"Server": "cloudflare", "CF-RAY": "abc"})}
        self.assertTrue({"WordPress", "Google Tag Manager", "Meta Pixel", "Cloudflare"} <= names)

    def test_lang(self):
        self.assertEqual(ex.detect_lang("شرکت ما در زمینه نرم افزار فعالیت میکند"), "fa")
        self.assertEqual(ex.detect_lang("We build software"), "en")


if __name__ == "__main__":
    unittest.main()
