import sqlite3
from contextlib import closing

from . import config


def _conn():
    c = sqlite3.connect(config.DB_PATH)
    c.row_factory = sqlite3.Row
    return c


def init():
    with closing(_conn()) as c:
        c.execute("""CREATE TABLE IF NOT EXISTS decisions(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text TEXT, intent TEXT, priority TEXT, summary TEXT, action TEXT,
            confidence REAL, source TEXT, workflow_status TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)""")
        c.commit()


def save(text: str, d, workflow_status: str) -> int:
    with closing(_conn()) as c:
        cur = c.execute(
            "INSERT INTO decisions(text,intent,priority,summary,action,confidence,source,workflow_status)"
            " VALUES(?,?,?,?,?,?,?,?)",
            (text, d.intent, d.priority, d.summary, d.action, d.confidence, d.source, workflow_status),
        )
        c.commit()
        return cur.lastrowid


def list_all(limit: int = 50):
    with closing(_conn()) as c:
        return [dict(r) for r in c.execute("SELECT * FROM decisions ORDER BY id DESC LIMIT ?", (limit,))]
