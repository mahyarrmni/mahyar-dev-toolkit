from fastapi import FastAPI

from . import db, engine, workflows
from .models import AnalyzeRequest, AnalyzeResponse

app = FastAPI(title="FlowPilot AI", version="0.1.0")
db.init()


@app.get("/health")
def health():
    return {"ok": True}


@app.post("/analyze", response_model=AnalyzeResponse)
def analyze(req: AnalyzeRequest):
    decision = engine.decide(req.text)
    wf = workflows.trigger(req.text, decision) if req.trigger else None
    rid = db.save(req.text, decision, wf["status"] if wf else "skipped")
    return AnalyzeResponse(id=rid, decision=decision, workflow=wf)


@app.get("/decisions")
def decisions(limit: int = 50):
    return db.list_all(min(limit, 200))
