"""Knowledge-base chunking, ingestion and retrieval."""
import re

from . import llm, repo
from .config import settings


def chunk_text(text: str, size: int = 800, overlap: int = 100) -> list[str]:
    paras = [p.strip() for p in re.sub(r"\n{3,}", "\n\n", text.strip()).split("\n\n") if p.strip()]
    chunks: list[str] = []
    cur = ""
    for p in paras:
        while len(p) > size:  # hard-split oversized paragraphs
            if cur:
                chunks.append(cur)
                cur = ""
            chunks.append(p[:size])
            p = p[size - overlap:]
        if len(cur) + len(p) + 2 <= size:
            cur = f"{cur}\n\n{p}" if cur else p
        else:
            if cur:
                chunks.append(cur)
            cur = p
    if cur:
        chunks.append(cur)
    return chunks


async def embed_all(texts: list[str], batch: int = 16) -> list[list[float]]:
    out: list[list[float]] = []
    for i in range(0, len(texts), batch):
        out += await llm.embed(texts[i:i + batch])
    return out


async def retrieve(query: str) -> list[dict]:
    vec = (await llm.embed([query[:2000]]))[0]
    return await repo.search_kb(vec, settings.rag_top_k)
