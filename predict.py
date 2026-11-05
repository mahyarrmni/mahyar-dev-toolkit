"""Persian/English text normalization and tokenization."""
import re
import unicodedata

DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩٫٬", "01234567890123456789.,")
_TOK = re.compile(r"[^\W_]+", re.UNICODE)
STOP_EN = set("""a an and are as at be but by for from has have he her his i if in into is it its of on or our she so that the
their them they this to was we were what when where which who why will with you your not no do does did can could should would
about after all also any been being both each how more most other out over some such than then there these those up very just
""".split())
STOP_FA = set("""و در به از که این را با است برای آن یک هم یا تا بر هر نیز اما اگر باید شود می های ها کرد کنید کنند بود شده شد خود همه
دیگر چه چی کنم کند کرده شوند دارد دارند داشت بین پس روی زیر بدون ولی همین آنها ما شما من او ان یه نه هیچ باشد بسیار
""".split())


def normalize(s: str) -> str:
    s = unicodedata.normalize("NFKC", s or "")
    s = s.translate(DIGITS).replace("ي", "ی").replace("ك", "ک").replace("ـ", "")
    s = s.replace("\u200c", " ").replace("\u200f", "").replace("\u200e", "")
    return re.sub(r"\s+", " ", s).strip().lower()


def _is_arabic(t: str) -> bool:
    return "\u0600" <= t[0] <= "\u06ff"


def _stem_fa(t: str) -> str:
    for suf in ("های", "ها"):
        if t.endswith(suf) and len(t) - len(suf) >= 3:
            return t[: -len(suf)]
    return t


def _stem_en(t: str) -> str:
    """Plural folding only (tags -> tag, studies -> study); deliberately not a full stemmer."""
    if len(t) > 4 and t.endswith("ies"):
        return t[:-3] + "y"
    if len(t) > 3 and t.endswith("s") and not t.endswith(("ss", "us", "is")):
        return t[:-1]
    return t


def tokenize(s: str, stop: bool = True) -> list:
    out = []
    for t in _TOK.findall(normalize(s)):
        if len(t) < 2 or t.isdigit():
            continue
        if stop and (t in STOP_EN or t in STOP_FA):
            continue
        if _is_arabic(t):
            t = _stem_fa(t)
        elif t.isascii() and t.isalpha():
            t = _stem_en(t)
        out.append(t)
    return out


def detect_lang(s: str) -> str:
    """'fa' = Arabic-script (Persian/Arabic), 'en' = Latin-script, 'mixed' otherwise."""
    letters = [c for c in s if c.isalpha()]
    if not letters:
        return "en"
    ar = sum("\u0600" <= c <= "\u06ff" for c in letters) / len(letters)
    return "fa" if ar > 0.6 else "en" if ar < 0.1 else "mixed"
