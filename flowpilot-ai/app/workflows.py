import json
import urllib.request

from . import config


def trigger(text: str, decision) -> dict:
    """POST the decision to the n8n webhook. Never raises."""
    payload = json.dumps({"text": text, **decision.model_dump()}).encode()
    req = urllib.request.Request(config.N8N_WEBHOOK_URL, payload, {"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=10) as r:
            return {"status": "sent", "http": r.status}
    except Exception as e:
        return {"status": "failed", "error": str(e)[:200]}
