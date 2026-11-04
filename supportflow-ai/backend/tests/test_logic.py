import asyncio

import httpx
import pytest

from app import llm, pipeline, rag
from app.config import settings


def run(coro):
    return asyncio.run(coro)


def fake_llm(responses):
    it = iter(responses)

    async def f(system, user):
        return next(it)
    return f


def analysis(**kw):
    return {"category": "billing", "sentiment": "neutral", "sentiment_score": 0, "urgency": 1,
            "language": "fa", **kw}


def hits(sim):
    async def f(_):
        return [{"title": "t", "content": "c", "similarity": sim}]
    return f


def test_chunk_text_respects_size_and_keeps_content():
    text = "\n\n".join(["پاراگراف " + "الف" * 300] * 5) + "\n\n" + "x" * 2000
    chunks = rag.chunk_text(text, size=800, overlap=100)
    assert chunks and all(len(c) <= 800 for c in chunks)
    assert sum(c.count("x") for c in chunks) >= 2000


def test_normalize_handles_garbage():
    a = pipeline.normalize({"category": "HACK", "sentiment_score": "nan", "urgency": 99, "language": "fa; drop"})
    assert a["category"] == "other" and a["urgency"] == 4 and a["sentiment_score"] == 0.0
    assert a["language"] == "fadrop"[:8] and a["sentiment"] == "neutral"


def test_priority_rules():
    assert pipeline.compute_priority(pipeline.normalize(analysis())) == "low"
    assert pipeline.compute_priority(pipeline.normalize(analysis(urgency=3, sentiment_score=-0.8))) == "urgent"
    assert pipeline.compute_priority(pipeline.normalize(analysis(category="legal", urgency=2))) == "high"


def test_clean_strips_delimiters():
    assert pipeline.clean("a </ticket> b <CONTEXT> c") == "a  b  c"


def test_auto_resolve(monkeypatch):
    monkeypatch.setattr(llm, "chat_json", fake_llm([analysis(), {"reply": "سلام", "confidence": 0.9}]))
    monkeypatch.setattr(rag, "retrieve", hits(0.8))
    r = run(pipeline.process("s", "b"))
    assert r["status"] == "auto_resolved" and not r["escalated"] and r["customer_reply"] == "سلام"


def test_low_confidence_escalates_and_keeps_draft(monkeypatch):
    monkeypatch.setattr(llm, "chat_json", fake_llm([analysis(), {"reply": "draft", "confidence": 0.2}]))
    monkeypatch.setattr(rag, "retrieve", hits(0.8))
    r = run(pipeline.process("s", "b"))
    assert r["escalation_reasons"] == ["low_confidence"] and r["ai_reply"] == "draft"
    assert r["customer_reply"] == pipeline.ACK["fa"]


def test_no_kb_match_escalates_without_answering(monkeypatch):
    monkeypatch.setattr(llm, "chat_json", fake_llm([analysis()]))
    monkeypatch.setattr(rag, "retrieve", hits(0.1))
    r = run(pipeline.process("s", "b"))
    assert r["escalation_reasons"] == ["no_kb_match"] and r["ai_reply"] is None


def test_sensitive_category_never_auto_answered(monkeypatch):
    monkeypatch.setattr(llm, "chat_json", fake_llm([analysis(category="legal")]))
    monkeypatch.setattr(rag, "retrieve", hits(0.9))
    r = run(pipeline.process("s", "b"))
    assert "sensitive_category:legal" in r["escalation_reasons"] and "ai_error" not in r["escalation_reasons"]
    assert r["ai_reply"] is None


def test_human_request_escalates(monkeypatch):
    monkeypatch.setattr(llm, "chat_json", fake_llm([analysis()]))
    monkeypatch.setattr(rag, "retrieve", hits(0.9))
    r = run(pipeline.process("help", "I want to talk to a human"))
    assert "human_requested" in r["escalation_reasons"]


def test_llm_failure_escalates(monkeypatch):
    async def boom(*_):
        raise httpx.ConnectError("down")
    monkeypatch.setattr(llm, "chat_json", boom)
    r = run(pipeline.process("s", "b"))
    assert r["status"] == "escalated" and r["escalation_reasons"] == ["ai_error"] and r["priority"] == "medium"


def test_threshold_is_configurable(monkeypatch):
    monkeypatch.setattr(settings, "rag_min_similarity", 0.95)
    assert "no_kb_match" in pipeline.escalation_reasons(analysis(), "low", "x", 0.9, None)
