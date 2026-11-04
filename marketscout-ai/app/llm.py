"""Optional Ollama summary. Returns None on any failure."""
import json

import requests

from . import config


def summarize(analysis: dict, industry: str, location: str):
    prompt = (f"You are a market analyst. Using ONLY this data, write a 4-6 sentence summary of the "
              f"{industry} market in {location} and the main opportunities. Do not invent facts.\n"
              f"{json.dumps(analysis)[:6000]}")
    try:
        r = requests.post(f"{config.OLLAMA_URL}/api/generate", timeout=90,
                          json={"model": config.OLLAMA_MODEL, "prompt": prompt, "stream": False,
                                "options": {"temperature": 0.2}})
        r.raise_for_status()
        return r.json()["response"].strip()
    except Exception:
        return None
