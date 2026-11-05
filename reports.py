import hmac
import os
from typing import List, Optional

import pandas as pd
from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import PlainTextResponse
from pydantic import BaseModel, Field

from . import config, db, gsc, jobs
from .urls import normalize_url


def auth(x_api_key: Optional[str] = Header(None)):
    if config.API_KEY and not hmac.compare_digest(x_api_key or "", config.API_KEY):
        raise HTTPException(401, "invalid or missing X-API-Key")


app = FastAPI(title="ASIGE", version=config.VERSION, dependencies=[Depends(auth)])
app.add_middleware(CORSMiddleware, allow_origins=os.getenv("ASIGE_CORS", "http://localhost:5173").split(","), allow_methods=["*"], allow_headers=["*"])


class ProjectIn(BaseModel):
    name: str = Field(..., min_length=1, max_length=200)
    site_url: str


class CrawlIn(BaseModel):
    max_pages: int = Field(500, ge=1, le=50000)
    max_depth: int = Field(8, ge=0, le=30)
    concurrency: int = Field(8, ge=1, le=32)
    delay: float = Field(0.25, ge=0.05, le=10)
    check_external: bool = True
    render: bool = False
    include_subdomains: bool = False


class AnalyzeIn(BaseModel):
    brand_terms: List[str] = []
    competitor_crawl_id: Optional[int] = None
    serp_csv: Optional[str] = Field(None, max_length=5_000_000)
    hours_per_week: float = Field(20, gt=0, le=200)
    weeks: int = Field(12, ge=1, le=52)
    n_boot: int = Field(100, ge=0, le=1000)
    embeddings: Optional[str] = Field(None, pattern="^(tfidf|sbert)$")


class GscSyncIn(BaseModel):
    site_url: str
    start: str
    end: str
    access_token: str  # used for this request only; never stored or logged


def _crawl(s, cid):
    c = s.get(db.Crawl, cid)
    if not c:
        raise HTTPException(404, "crawl not found")
    return c


@app.get("/health")
def health():
    return {"ok": True, "version": config.VERSION}


@app.post("/projects", status_code=201)
def create_project(p: ProjectIn):
    url = normalize_url(p.site_url)
    if not url:
        raise HTTPException(422, "site_url must be a valid http(s) URL")
    with db.session() as s:
        row = db.Project(name=p.name, site_url=url)
        s.add(row)
        s.flush()
        return {"id": row.id, "name": row.name, "site_url": row.site_url}


@app.get("/projects")
def projects():
    with db.session() as s:
        return [{"id": p.id, "name": p.name, "site_url": p.site_url} for p in s.query(db.Project).order_by(db.Project.id)]


@app.post("/projects/{pid}/crawls", status_code=202)
def start_crawl(pid: int, c: CrawlIn):
    with db.session() as s:
        if not s.get(db.Project, pid):
            raise HTTPException(404, "project not found")
        row = db.Crawl(project_id=pid, status="queued", config=c.model_dump())
        s.add(row)
        s.flush()
        cid = row.id
    jobs.dispatch("crawl", cid)
    return {"id": cid, "status": "queued"}


@app.get("/projects/{pid}/crawls")
def crawls(pid: int):
    with db.session() as s:
        return [{"id": c.id, "status": c.status, "stats": c.stats and {k: v for k, v in c.stats.items() if k != "external"}} for c in
                s.query(db.Crawl).filter_by(project_id=pid).order_by(db.Crawl.id.desc())]


@app.get("/crawls/{cid}")
def crawl(cid: int):
    with db.session() as s:
        c = _crawl(s, cid)
        return {"id": c.id, "status": c.status, "error": c.error, "stats": c.stats and {k: v for k, v in c.stats.items() if k != "external"}}


def _store_gsc(pid, df, replace):
    with db.session() as s:
        if not s.get(db.Project, pid):
            raise HTTPException(404, "project not found")
        if replace:
            s.query(db.GscRow).filter_by(project_id=pid).delete()
        s.bulk_insert_mappings(db.GscRow, [{"project_id": pid, "date": r.get("date"), "query": r["query"], "page": r["page"], "clicks": r["clicks"],
                                            "impressions": r["impressions"], "position": r["position"]}
                                           for r in df.astype(object).where(df.notna(), None).to_dict("records")])
    return {"rows": len(df), "clicks": float(df.clicks.sum()), "impressions": float(df.impressions.sum())}


@app.post("/projects/{pid}/gsc/upload")
async def gsc_upload(pid: int, file: UploadFile = File(...), replace: bool = True):
    data = await file.read()
    if len(data) > 50_000_000:
        raise HTTPException(413, "file too large")
    try:
        df = gsc.load_gsc(data, file.filename or "gsc.csv")
    except ValueError as e:
        raise HTTPException(422, str(e))
    return _store_gsc(pid, df, replace)


@app.post("/projects/{pid}/gsc/sync")
def gsc_sync(pid: int, body: GscSyncIn):
    try:
        df = gsc.GSCClient(body.access_token).query(body.site_url, body.start, body.end)
    except gsc.GSCError as e:
        raise HTTPException(502, str(e))
    return _store_gsc(pid, df, True)


@app.post("/crawls/{cid}/analyze", status_code=202)
def analyze(cid: int, a: AnalyzeIn):
    with db.session() as s:
        c = _crawl(s, cid)
        if c.status != "done":
            raise HTTPException(409, f"crawl is {c.status}")
    jobs.dispatch("analyze", cid, a.model_dump())
    return {"id": cid, "status": "queued"}


@app.get("/crawls/{cid}/analysis")
def analysis_index(cid: int):
    with db.session() as s:
        return sorted(r.kind for r in s.query(db.Analysis).filter_by(crawl_id=cid))


@app.get("/crawls/{cid}/analysis/{kind}")
def analysis(cid: int, kind: str):
    with db.session() as s:
        r = s.query(db.Analysis).filter_by(crawl_id=cid, kind=kind).first()
        if not r:
            raise HTTPException(404, f"no '{kind}' analysis for crawl {cid}")
        return r.data


@app.get("/crawls/{cid}/report/{which}", response_class=PlainTextResponse)
def report(cid: int, which: str):
    if which not in ("technical", "strategic"):
        raise HTTPException(404, "report must be technical|strategic")
    return analysis(cid, f"report_{which}")
