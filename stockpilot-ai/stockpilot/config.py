import os

DB_PATH = os.getenv("STOCKPILOT_DB", "stockpilot.db")
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "llama3.2")
CURRENCY = os.getenv("CURRENCY", "تومان")
