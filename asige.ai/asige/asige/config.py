import os

VERSION = "0.1.0"
SEED = 0
DATABASE_URL = os.getenv("DATABASE_URL", "sqlite:///asige.db")
REDIS_URL = os.getenv("REDIS_URL")  # unset -> jobs run in-process (no Celery worker needed)
API_KEY = os.getenv("ASIGE_API_KEY")  # unset -> API open (dev only)
USER_AGENT = os.getenv("ASIGE_USER_AGENT", "ASIGEBot/0.1 (+https://example.com/asigebot; respectful SEO crawler)")
ALLOW_PRIVATE = os.getenv("ASIGE_ALLOW_PRIVATE", "0") == "1"  # SSRF guard override (tests/intranet)
EMBEDDINGS = os.getenv("ASIGE_EMBEDDINGS", "tfidf")  # tfidf (LSA, offline) | sbert (sentence-transformers)
SBERT_MODEL = os.getenv("ASIGE_SBERT_MODEL", "paraphrase-multilingual-MiniLM-L12-v2")
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "llama3.2")
