"""Thin async client for the local Ollama server."""
import json

import httpx

from .config import settings

_client = httpx.AsyncClient(timeout=180)


async def chat_json(system: str, user: str) -> dict:
    r = await _client.post(
        f"{settings.ollama_url}/api/chat",
        json={
            "model": settings.chat_model,
            "stream": False,
            "format": "json",
            "options": {"temperature": 0.2, "num_ctx": settings.num_ctx},
            "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}],
        },
    )
    r.raise_for_status()
    data = json.loads(r.json()["message"]["content"])
    if not isinstance(data, dict):
        raise ValueError("LLM did not return a JSON object")
    return data


async def embed(texts: list[str]) -> list[list[float]]:
    r = await _client.post(f"{settings.ollama_url}/api/embed", json={"model": settings.embed_model, "input": texts})
    r.raise_for_status()
    return r.json()["embeddings"]


async def ping() -> None:
    (await _client.get(f"{settings.ollama_url}/api/tags", timeout=5)).raise_for_status()
