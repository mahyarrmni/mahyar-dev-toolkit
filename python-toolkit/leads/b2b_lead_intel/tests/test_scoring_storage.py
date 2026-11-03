import os, tempfile, unittest
from b2b_intel.models import Lead
from b2b_intel.pipeline import normalize_domain, parse_inputs
from b2b_intel.scoring import score_lead, grade_for
from b2b_intel.storage import Store


def strong_lead():
    return Lead(domain="acme.com", status="ok", name="Acme", description="d", address="Tehran", employees=20,
                emails=[{"address": "ali@acme.com", "kind": "personal", "same_domain": True}],
                phones=["+989121234567"], socials={"linkedin": "x", "instagram": "y"},
                tech=[{"name": "Shopify", "category": "E-commerce"}, {"name": "HubSpot", "category": "Marketing automation"}])


class ScoringTests(unittest.TestCase):
    def test_strong_lead_gets_grade_a(self):
        l = score_lead(strong_lead())
        self.assertEqual(l.grade, "A")
        self.assertGreaterEqual(l.score, 90)
        self.assertTrue(l.score_reasons)

    def test_failed_crawl_scores_zero(self):
        l = strong_lead(); l.status = "unreachable"
        self.assertEqual(score_lead(l).score, 0)

    def test_weights_override(self):
        l = strong_lead(); l.socials = {"instagram": "y"}          # no linkedin -> misses a rule
        base = score_lead(l).score
        heavy = score_lead(l, {"has_linkedin": 60}).score          # missing rule now costs more
        self.assertLess(heavy, base)

    def test_opportunities(self):
        l = score_lead(strong_lead())
        self.assertTrue(any("آنالیتیکس" in o for o in l.opportunities))
        self.assertTrue(any("پیکسل" in o for o in l.opportunities))

    def test_grade_boundaries(self):
        self.assertEqual([grade_for(s) for s in (100, 70, 69, 50, 49, 30, 29, 0)], list("AABBCCDD"))


class InputTests(unittest.TestCase):
    def test_normalize(self):
        self.assertEqual(normalize_domain("https://www.Acme.com/path?x=1"), "acme.com")
        self.assertEqual(normalize_domain("shop.acme.ir:8080/x"), "shop.acme.ir")
        self.assertIsNone(normalize_domain("not a domain"))
        self.assertIsNone(normalize_domain("# comment.com"))
        self.assertIsNone(normalize_domain("localhost"))

    def test_parse_inputs_dedupes_and_handles_csv(self):
        lines = ["domain,name", "acme.com,Acme", "https://www.acme.com/about; beta.io", "", "junk"]
        self.assertEqual(parse_inputs(lines), ["acme.com", "beta.io"])


class StorageTests(unittest.TestCase):
    def test_roundtrip_upsert_resume_stats(self):
        with tempfile.TemporaryDirectory() as d, Store(os.path.join(d, "t.db")) as s:
            a = score_lead(strong_lead()); s.upsert(a)
            b = Lead(domain="dead.com", status="unreachable", error="ClientError"); score_lead(b); s.upsert(b)
            s.upsert(a)  # idempotent upsert
            self.assertEqual(s.done_domains(), {"acme.com"})
            self.assertEqual(s.stats()["total"], 2)
            back = s.all(only_ok=True)[0]
            self.assertEqual(back.best_email, "ali@acme.com")
            self.assertEqual(sorted(back.tech_names()), ["HubSpot", "Shopify"])


if __name__ == "__main__":
    unittest.main()
