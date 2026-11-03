"""Technology fingerprinting from HTML + response headers (signature based)."""
import re

# name -> (category, [regex, ...]) ; matched case-insensitively on html + headers
SIGNATURES = {
    "WordPress": ("CMS", [r"wp-content/", r"wp-includes/", r'content="WordPress']),
    "Joomla": ("CMS", [r"/media/jui/", r'content="Joomla']),
    "Drupal": ("CMS", [r"drupal-settings-json", r"sites/default/files", r'content="Drupal']),
    "Wix": ("Website builder", [r"wixstatic\.com", r"x-wix-"]),
    "Squarespace": ("Website builder", [r"static1\.squarespace\.com", r"squarespace-cdn"]),
    "Webflow": ("Website builder", [r"assets\.website-files\.com", r"data-wf-page"]),
    "Shopify": ("E-commerce", [r"cdn\.shopify\.com", r"Shopify\.theme"]),
    "WooCommerce": ("E-commerce", [r"woocommerce", r"wc-ajax"]),
    "Magento": ("E-commerce", [r"Mage\.Cookies", r"/static/frontend/Magento"]),
    "PrestaShop": ("E-commerce", [r'content="PrestaShop', r"prestashop"]),
    "OpenCart": ("E-commerce", [r"catalog/view/theme", r"route=common/home"]),
    "Google Analytics": ("Analytics", [r"google-analytics\.com/(?:analytics|ga)\.js", r"gtag\(", r"googletagmanager\.com/gtag/js"]),
    "Google Tag Manager": ("Analytics", [r"googletagmanager\.com/gtm\.js", r"GTM-[A-Z0-9]{4,}"]),
    "Meta Pixel": ("Advertising", [r"connect\.facebook\.net/[a-z_A-Z]+/fbevents\.js", r"\bfbq\("]),
    "Google Ads": ("Advertising", [r"googleadservices\.com", r"AW-\d{6,}"]),
    "Hotjar": ("Analytics", [r"static\.hotjar\.com", r"hotjar\.com"]),
    "Yandex Metrica": ("Analytics", [r"mc\.yandex\.ru/metrika", r"ym\(\d+"]),
    "Matomo": ("Analytics", [r"matomo\.js", r"piwik\.js"]),
    "HubSpot": ("Marketing automation", [r"js\.hs-scripts\.com", r"hs-banner", r"hubspot\.com/"]),
    "Mailchimp": ("Marketing automation", [r"chimpstatic\.com", r"list-manage\.com"]),
    "ActiveCampaign": ("Marketing automation", [r"activehosted\.com", r"trackcmp\.net"]),
    "Intercom": ("Live chat / CRM", [r"widget\.intercom\.io", r"intercomSettings"]),
    "Zendesk": ("Live chat / CRM", [r"zdassets\.com", r"zopim"]),
    "Crisp": ("Live chat / CRM", [r"client\.crisp\.chat"]),
    "Tawk.to": ("Live chat / CRM", [r"embed\.tawk\.to"]),
    "Cloudflare": ("CDN / Security", [r"cf-ray:", r"server:\s*cloudflare", r"__cf_bm"]),
    "Next.js": ("Framework", [r"__NEXT_DATA__", r"/_next/static/"]),
    "React": ("Framework", [r"data-reactroot", r"react(?:-dom)?(?:\.min)?\.js"]),
    "Vue.js": ("Framework", [r"vue(?:\.min)?\.js", r"data-v-[0-9a-f]{6,}"]),
    "Angular": ("Framework", [r"ng-version=", r"<app-root"]),
    "Bootstrap": ("UI", [r"bootstrap(?:\.min)?\.(?:css|js)"]),
    "jQuery": ("UI", [r"jquery(?:[-.]\d[\d.]*)?(?:\.min)?\.js"]),
}
_COMPILED = {n: (cat, [re.compile(p, re.I) for p in pats]) for n, (cat, pats) in SIGNATURES.items()}


def detect(html: str, headers: dict | None = None) -> list:
    """Return [{'name','category'}] sorted by category then name."""
    haystack = html
    if headers:
        haystack += "\n" + "\n".join(f"{k}: {v}" for k, v in headers.items())
    found = [{"name": n, "category": cat}
             for n, (cat, pats) in _COMPILED.items() if any(p.search(haystack) for p in pats)]
    return sorted(found, key=lambda t: (t["category"], t["name"]))
