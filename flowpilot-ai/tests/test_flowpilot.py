import json
import os

os.environ["DB_PATH"] = "test_flowpilot.db"

import pytest
from fastapi.testclient import TestClient

from app import engine, llm
from app.main import app


@pytest.fixture(autouse=True)
def cleanup():
    yield
    if os.path.exists("test_flowpilot.db"):
        os.remove("test_flowpilot.db")


def test_heuristic_priority():
    assert engine.heuristic("URGENT: production is down, outage!")["priority"] == "critical"


def test_llm_valid(monkeypatch):
    monkeypatch.setattr(llm, "generate", lambda p: json.dumps(
        {"intent": "billing", "priority": "high", "summary": "refund", "confidence": 0.9}))
    d = engine.decide("I want a refund")
    assert (d.intent, d.action, d.source) == ("billing", "route_to_finance", "llm")


def test_llm_invalid_falls_back(monkeypatch):
    monkeypatch.setattr(llm, "generate", lambda p: "not json")
    d = engine.decide("the app crashes with an error")
    assert d.source == "heuristic" and d.intent == "bug_report"


def test_api_flow(monkeypatch):
    def boom(p):
        raise RuntimeError("down")
    monkeypatch.setattr(llm, "generate", boom)
    from app import db
    db.init()
    c = TestClient(app)
    r = c.post("/analyze", json={"text": "Need a quote for pricing", "trigger": False})
    assert r.status_code == 200 and r.json()["decision"]["intent"] == "sales"
    assert len(c.get("/decisions").json()) == 1
