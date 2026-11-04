from datetime import datetime, timezone

import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient

from app import pipeline, repo, security
from app.config import settings
from app.main import app

client = TestClient(app)
H = {"X-API-Key": "test-key"}


def test_health_and_dashboard():
    assert client.get("/health").json() == {"status": "ok"}
    r = client.get("/")
    assert r.status_code == 200 and "default-src 'self'" in r.headers["content-security-policy"]


def test_api_requires_key():
    assert client.get("/api/stats").status_code == 401
    assert client.get("/api/stats", headers={"X-API-Key": "wrong"}).status_code == 401


@pytest.mark.parametrize("payload", [{}, {"subject": "", "body": "x"}, {"subject": "s", "body": "x" * 8001}])
def test_ticket_validation(payload):
    assert client.post("/api/tickets", json=payload, headers=H).status_code == 422


def test_bad_status_filter_rejected():
    assert client.get("/api/tickets?status=hacked", headers=H).status_code == 422


def test_create_ticket(monkeypatch):
    result = {"category": "billing", "sentiment": "neutral", "sentiment_score": 0.0, "urgency": 1,
              "language": "fa", "priority": "low", "escalated": False, "escalation_reasons": [],
              "status": "auto_resolved", "ai_reply": "ok", "confidence": 0.9, "customer_reply": "ok"}

    async def fake_process(subject, body):
        return result

    async def fake_create(customer, subject, body, r):
        return {**r, "id": 1, "customer": customer, "subject": subject, "body": body,
                "human_reply": None, "created_at": datetime.now(timezone.utc), "resolved_at": None}

    monkeypatch.setattr(pipeline, "process", fake_process)
    monkeypatch.setattr(repo, "create_ticket", fake_create)
    r = client.post("/api/tickets", json={"subject": " hi ", "body": "text"}, headers=H)
    assert r.status_code == 201
    assert r.json()["subject"] == "hi" and r.json()["customer_reply"] == "ok"


def test_resolve_unknown_ticket_404(monkeypatch):
    async def none(*_):
        return None
    monkeypatch.setattr(repo, "resolve_ticket", none)
    assert client.post("/api/tickets/9/resolve", json={"reply": "x"}, headers=H).status_code == 404


def test_rate_limit(monkeypatch):
    monkeypatch.setattr(settings, "rate_limit_per_min", 2)
    security.rate_limit("k")
    security.rate_limit("k")
    with pytest.raises(HTTPException) as e:
        security.rate_limit("k")
    assert e.value.status_code == 429
