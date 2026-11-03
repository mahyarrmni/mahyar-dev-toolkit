"""SQLite persistence: crash-safe, resumable, queryable with any SQL tool."""
from __future__ import annotations
import json, sqlite3
from contextlib import contextmanager

from .models import Lead

SCHEMA = """
CREATE TABLE IF NOT EXISTS leads(
    domain     TEXT PRIMARY KEY,
    status     TEXT NOT NULL,
    score      INTEGER NOT NULL DEFAULT 0,
    grade      TEXT NOT NULL DEFAULT 'D',
    crawled_at TEXT,
    data       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_leads_score ON leads(score DESC);
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
"""


class Store:
    def __init__(self, path: str):
        self.path = path
        self._conn = sqlite3.connect(path)
        self._conn.row_factory = sqlite3.Row
        self._conn.execute("PRAGMA journal_mode=WAL")
        self._conn.executescript(SCHEMA)

    def close(self) -> None:
        self._conn.close()

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        self.close()

    @contextmanager
    def _tx(self):
        try:
            yield self._conn
            self._conn.commit()
        except Exception:
            self._conn.rollback()
            raise

    def upsert(self, lead: Lead) -> None:
        with self._tx() as c:
            c.execute(
                """INSERT INTO leads(domain,status,score,grade,crawled_at,data) VALUES(?,?,?,?,?,?)
                   ON CONFLICT(domain) DO UPDATE SET status=excluded.status, score=excluded.score,
                   grade=excluded.grade, crawled_at=excluded.crawled_at, data=excluded.data""",
                (lead.domain, lead.status, lead.score, lead.grade, lead.crawled_at,
                 json.dumps(lead.to_dict(), ensure_ascii=False)))

    def done_domains(self) -> set:
        rows = self._conn.execute("SELECT domain FROM leads WHERE status='ok'").fetchall()
        return {r["domain"] for r in rows}

    def all(self, min_score: int = 0, only_ok: bool = False) -> list[Lead]:
        q = "SELECT data FROM leads WHERE score>=?" + (" AND status='ok'" if only_ok else "") + " ORDER BY score DESC, domain"
        return [Lead.from_dict(json.loads(r["data"])) for r in self._conn.execute(q, (min_score,))]

    def stats(self) -> dict:
        c = self._conn
        by_status = {r["status"]: r["n"] for r in c.execute("SELECT status, COUNT(*) n FROM leads GROUP BY status")}
        by_grade = {r["grade"]: r["n"] for r in c.execute("SELECT grade, COUNT(*) n FROM leads WHERE status='ok' GROUP BY grade")}
        total = sum(by_status.values())
        return {"total": total, "by_status": by_status, "by_grade": by_grade}
