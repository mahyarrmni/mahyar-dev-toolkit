import os

DB_PATH = os.getenv("DB_PATH", "marketscout.db")
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "llama3.2")
NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
# Nominatim policy requires an identifying User-Agent; set CONTACT to your email/URL.
USER_AGENT = f"MarketScoutAI/0.1 (research bot; {os.getenv('CONTACT', 'set CONTACT env var')})"
TIMEOUT = 8        # seconds per request
MAX_PAGES = 3      # pages fetched per website
MAX_BYTES = 1_500_000
DELAY = 0.5        # politeness delay between pages of one site
WORKERS = 4
