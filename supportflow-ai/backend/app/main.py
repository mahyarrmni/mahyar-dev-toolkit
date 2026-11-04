"""SupportFlow AI - FastAPI entrypoint."""
import logging
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Literal

import httpx
from fastapi import APIRouter, Depends, FastAPI, HTTPException, Query, Response
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles

from . import llm, pipeline, rag, repo
from .config import settings
from .schemas import KBIn, ResolveIn, TicketIn, TicketOut
from .security import require_key

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
STATIC = Path(__file__).parent / "static"
PROD = settings.env == "production"
Status = Literal["auto_resolved", "escalated", "resolved"]


@asynccontextmanager
async def lifespan(_: FastAPI):
    if PROD and (len(settings.api_key) < 24 or settings.api_key.startswith(("change-me", "dev-"))):
        raise RuntimeError("Set a strong API_KEY (>= 24 chars) when ENV=production")
    await repo.init()
    yield
    await repo.close()


app = FastAPI(title="SupportFlow AI", version="1.0.0", lifespan=lifespan,
              docs_url=None if PROD else "/docs", redoc_url=None, openapi_url=None if PROD else "/openapi.json")
app.mount("/static", StaticFiles(directory=STATIC), name="static")


@app.middleware("http")
async def security_headers(request, call_next):
    resp = await call_next(request)
    resp.headers["X-Content-Type-Options"] = "nosniff"
    resp.headers["X-Frame-Options"] = "DENY"
    resp.headers["Referrer-Policy"] = "no-referrer"
    if request.url.path == "/" or request.url.path.startswith("/static"):
        resp.headers["Content-Security-Policy"] = "default-src 'self'; frame-ancestors 'none'"
    return resp


@app.get("/", include_in_schema=False)
async def dashboard():
    return FileResponse(STATIC / "index.html")


@app.get("/health")
async def health():
    return {"status": "ok"}


@app.get("/ready")
async def ready():
    try:
        await repo.ping()
        await llm.ping()
    except Exception:
        raise HTTPException(503, "not ready")
    return {"status": "ready"}


def _found(row):
    if row is None:
        raise HTTPException(404, "not found")
    return row


api = APIRouter(prefix="/api", dependencies=[Depends(require_key)])


@api.post("/tickets", response_model=TicketOut, status_code=201)
async def create_ticket(t: TicketIn):
    res = await pipeline.process(t.subject, t.body)
    row = await repo.create_ticket(t.customer, t.subject, t.body, res)
    return {**row, "customer_reply": res["customer_reply"]}


@api.get("/tickets", response_model=list[TicketOut])
async def list_tickets(status: Status | None = None, limit: int = Query(50, ge=1, le=200),
                       offset: int = Query(0, ge=0)):
    return await repo.list_tickets(status, limit, offset)


@api.get("/tickets/{ticket_id}", response_model=TicketOut)
async def get_ticket(ticket_id: int):
    return _found(await repo.get_ticket(ticket_id))


@api.post("/tickets/{ticket_id}/resolve", response_model=TicketOut)
async def resolve_ticket(ticket_id: int, r: ResolveIn):
    return _found(await repo.resolve_ticket(ticket_id, r.reply))


@api.get("/stats")
async def stats():
    return await repo.stats()


@api.get("/kb")
async def list_kb():
    return await repo.list_docs()


@api.post("/kb", status_code=201)
async def add_kb(d: KBIn):
    chunks = rag.chunk_text(d.content)
    try:
        vecs = await rag.embed_all(chunks)
    except httpx.HTTPError:
        raise HTTPException(503, "embedding service unavailable")
    return {"id": await repo.add_doc(d.title, chunks, vecs), "title": d.title, "chunks": len(chunks)}


@api.delete("/kb/{doc_id}", status_code=204)
async def delete_kb(doc_id: int):
    if not await repo.delete_doc(doc_id):
        raise HTTPException(404, "not found")
    return Response(status_code=204)


app.include_router(api)
