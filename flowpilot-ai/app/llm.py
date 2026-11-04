import json
import urllib.request

from . import config


def generate(prompt: str, timeout: int = 60) -> str:
    """Call local Ollama; returns raw model text. Raises on failure."""
    body = json.dumps({
        "model": config.OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "format": "json",
        "options": {"temperature": 0},
    }).encode()
    req = urllib.request.Request(
        f"{config.OLLAMA_URL}/api/generate", body, {"Content-Type": "application/json"}
    )
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.load(r)["response"]
