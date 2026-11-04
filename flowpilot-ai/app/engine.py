import json
import re

from . import llm
from .models import Decision

INTENTS = ["support", "sales", "billing", "complaint", "bug_report", "feedback", "spam", "other"]
PRIORITIES = ["low", "medium", "high", "critical"]
ACTIONS = {
    "support": "route_to_support",
    "sales": "notify_sales",
    "billing": "route_to_finance",
    "complaint": "escalate_to_manager",
    "bug_report": "create_ticket",
    "feedback": "log_feedback",
    "spam": "discard",
    "other": "manual_review",
}

PROMPT = """You are a decision engine. Analyze the text and reply with JSON only:
{{"intent": one of {intents}, "priority": one of {priorities}, "summary": "<=20 words", "confidence": 0-1}}
Text: \"\"\"{text}\"\"\""""

KEYWORDS = {
    "spam": ["unsubscribe", "winner", "free money", "click here", "lottery"],
    "bug_report": ["bug", "error", "crash", "exception", "not working", "broken"],
    "billing": ["invoice", "refund", "charge", "payment", "billing"],
    "complaint": ["angry", "unacceptable", "terrible", "worst", "complaint"],
    "sales": ["pricing", "quote", "demo", "purchase", "buy"],
    "feedback": ["suggest", "feedback", "would be nice", "feature request"],
    "support": ["help", "how do i", "cannot", "can't", "issue", "problem"],
}
URGENT = {"critical": ["outage", "down", "data loss", "security", "breach"],
          "high": ["urgent", "asap", "immediately", "angry", "unacceptable", "crash"]}


def heuristic(text: str) -> dict:
    t = text.lower()
    intent = next((i for i, kws in KEYWORDS.items() if any(k in t for k in kws)), "other")
    priority = "low" if intent in ("spam", "feedback", "other") else "medium"
    for p in ("critical", "high"):
        if any(k in t for k in URGENT[p]) and intent != "spam":
            priority = p
            break
    return {"intent": intent, "priority": priority, "summary": text[:100], "confidence": 0.5}


def _parse(raw: str) -> dict:
    m = re.search(r"\{.*\}", raw, re.S)
    data = json.loads(m.group(0) if m else raw)
    if data.get("intent") not in INTENTS or data.get("priority") not in PRIORITIES:
        raise ValueError("invalid enum value")
    data["confidence"] = max(0.0, min(1.0, float(data.get("confidence", 0.7))))
    data["summary"] = str(data.get("summary", ""))[:200]
    return data


def decide(text: str) -> Decision:
    source = "llm"
    try:
        raw = llm.generate(PROMPT.format(intents=INTENTS, priorities=PRIORITIES, text=text))
        data = _parse(raw)
    except Exception:
        data, source = heuristic(text), "heuristic"
    return Decision(**data, action=ACTIONS[data["intent"]], source=source)
