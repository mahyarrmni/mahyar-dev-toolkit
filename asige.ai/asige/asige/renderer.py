"""Optional JS rendering via Playwright (pip install playwright && playwright install chromium)."""


async def render(url: str, user_agent: str, timeout: float = 30.0) -> str:
    from playwright.async_api import async_playwright  # lazy: optional dependency

    async with async_playwright() as p:
        browser = await p.chromium.launch()
        try:
            page = await browser.new_page(user_agent=user_agent)
            await page.goto(url, wait_until="networkidle", timeout=timeout * 1000)
            return await page.content()
        finally:
            await browser.close()
