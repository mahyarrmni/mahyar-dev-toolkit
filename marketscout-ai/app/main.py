import json
from typing import List, Optional

from fastapi import BackgroundTasks, FastAPI, HTTPException
from fastapi.responses import PlainTextResponse
from pydantic import BaseModel, Field

from . import db, pipeline

app = FastAPI(title="MarketScout AI", version="0.1.0")
db.init()


class ResearchRequest(BaseModel):
    industry: str = Field(..., min_length=2, max_length=100)
    location: str = Field(..., min_length=2, max_length=100)
    max_businesses: int = Field(10, ge=1, le=25)
    use_llm: bool = False
    urls: Optional[List[str]] = Field(None, max_length=25)


@app.get("/health")
def health():
    return {"ok": True}


@app.post("/research", status_code=202)
def research(req: ResearchRequest, bg: BackgroundTasks):
    rid = db.create(req.industry, req.location)
    bg.add_task(pipeline.run_job, rid, req)
    return {"id": rid, "status": "running"}


@app.get("/reports")
def reports():
    return db.list_runs()


def _get(rid: int) -> dict:
    r = db.get(rid)
    if not r:
        raise HTTPException(404, "report not found")
    return r


@app.get("/reports/{rid}")
def report(rid: int):
    r = _get(rid)
    return {"id": r["id"], "status": r["status"], "error": r["error"],
            "report": json.loads(r["report"]) if r["report"] else None}


@app.get("/reports/{rid}/markdown", response_class=PlainTextResponse)
def markdown(rid: int):
    r = _get(rid)
    if r["status"] != "done":
        raise HTTPException(409, f"report is {r['status']}")
    return r["markdown"]
