"""PostgreSQL access (asyncpg). Parameterised queries only."""
import asyncpg

from .config import settings

pool: asyncpg.Pool | None = None
COLS = ("id, customer, subject, body, category, sentiment, sentiment_score, urgency, priority, status, "
        "escalated, escalation_reasons, ai_reply, confidence, human_reply, language, created_at, resolved_at")


async def init() -> None:
    global pool
    pool = await asyncpg.create_pool(settings.database_url, min_size=1, max_size=10, command_timeout=30)


async def close() -> None:
    if pool:
        await pool.close()


async def ping() -> None:
    await pool.fetchval("SELECT 1")


def _vec(v: list[float]) -> str:
    return "[" + ",".join(f"{x:.6f}" for x in v) + "]"


async def create_ticket(customer: str, subject: str, body: str, r: dict) -> dict:
    row = await pool.fetchrow(
        f"""INSERT INTO tickets (customer, subject, body, category, sentiment, sentiment_score, urgency,
              priority, status, escalated, escalation_reasons, ai_reply, confidence, language)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14) RETURNING {COLS}""",
        customer, subject, body, r["category"], r["sentiment"], r["sentiment_score"], r["urgency"],
        r["priority"], r["status"], r["escalated"], r["escalation_reasons"], r["ai_reply"],
        r["confidence"], r["language"])
    return dict(row)


async def list_tickets(status: str | None, limit: int, offset: int) -> list[dict]:
    rows = await pool.fetch(
        f"""SELECT {COLS} FROM tickets WHERE ($1::text IS NULL OR status = $1)
            ORDER BY (CASE WHEN $1::text = 'escalated'
                      THEN array_position(ARRAY['urgent','high','medium','low'], priority) END) NULLS LAST,
                     created_at DESC
            LIMIT $2 OFFSET $3""", status, limit, offset)
    return [dict(r) for r in rows]


async def get_ticket(ticket_id: int) -> dict | None:
    row = await pool.fetchrow(f"SELECT {COLS} FROM tickets WHERE id = $1", ticket_id)
    return dict(row) if row else None


async def resolve_ticket(ticket_id: int, reply: str) -> dict | None:
    row = await pool.fetchrow(
        f"UPDATE tickets SET human_reply = $2, status = 'resolved', resolved_at = now() "
        f"WHERE id = $1 RETURNING {COLS}", ticket_id, reply)
    return dict(row) if row else None


async def stats() -> dict:
    t = await pool.fetchrow(
        """SELECT count(*) AS total,
                  count(*) FILTER (WHERE status = 'auto_resolved') AS auto_resolved,
                  count(*) FILTER (WHERE status = 'escalated') AS escalated,
                  count(*) FILTER (WHERE status = 'resolved') AS resolved,
                  count(*) FILTER (WHERE sentiment = 'negative') AS negative FROM tickets""")
    cats = await pool.fetch("SELECT category, count(*) AS n FROM tickets GROUP BY 1")
    pris = await pool.fetch("SELECT priority, count(*) AS n FROM tickets GROUP BY 1")
    return {**dict(t), "by_category": {r["category"]: r["n"] for r in cats},
            "by_priority": {r["priority"]: r["n"] for r in pris}}


async def add_doc(title: str, chunks: list[str], vecs: list[list[float]]) -> int:
    async with pool.acquire() as c, c.transaction():
        doc_id = await c.fetchval("INSERT INTO kb_docs (title) VALUES ($1) RETURNING id", title)
        await c.executemany("INSERT INTO kb_chunks (doc_id, content, embedding) VALUES ($1, $2, $3::vector)",
                            [(doc_id, t, _vec(v)) for t, v in zip(chunks, vecs)])
    return doc_id


async def list_docs() -> list[dict]:
    rows = await pool.fetch(
        """SELECT d.id, d.title, d.created_at, count(c.id) AS chunks FROM kb_docs d
           LEFT JOIN kb_chunks c ON c.doc_id = d.id GROUP BY d.id ORDER BY d.id DESC""")
    return [dict(r) for r in rows]


async def delete_doc(doc_id: int) -> bool:
    return (await pool.execute("DELETE FROM kb_docs WHERE id = $1", doc_id)) == "DELETE 1"


async def search_kb(vec: list[float], k: int) -> list[dict]:
    rows = await pool.fetch(
        """SELECT d.title, c.content, 1 - (c.embedding <=> $1::vector) AS similarity
           FROM kb_chunks c JOIN kb_docs d ON d.id = c.doc_id
           ORDER BY c.embedding <=> $1::vector LIMIT $2""", _vec(vec), k)
    return [dict(r) for r in rows]
