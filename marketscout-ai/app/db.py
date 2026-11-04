import json
import sqlite3
from contextlib import closing

from . import config


def _conn():
    c = sqlite3.connect(config.DB_PATH)
    c.row_factory = sqlite3.Row
    return c


def init():
    with closing(_conn()) as c:
        c.execute("""CREATE TABLE IF NOT EXISTS runs(
            id INTEGER PRIMARY KEY AUTOINCREMENT, industry TEXT, location TEXT,
            status TEXT, report TEXT, markdown TEXT, error TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)""")
        c.commit()


def create(industry, location) -> int:
    with closing(_conn()) as c:
        cur = c.execute("INSERT INTO runs(industry,location,status) VALUES(?,?,'running')", (industry, location))
        c.commit()
        return cur.lastrowid


def finish(rid, rep, md):
    with closing(_conn()) as c:
        c.execute("UPDATE runs SET status='done', report=?, markdown=? WHERE id=?", (json.dumps(rep), md, rid))
        c.commit()


def fail(rid, err):
    with closing(_conn()) as c:
        c.execute("UPDATE runs SET status='failed', error=? WHERE id=?", (err[:500], rid))
        c.commit()


def get(rid):
    with closing(_conn()) as c:
        r = c.execute("SELECT * FROM runs WHERE id=?", (rid,)).fetchone()
        return dict(r) if r else None


def list_runs(limit=50):
    with closing(_conn()) as c:
        return [dict(r) for r in c.execute(
            "SELECT id,industry,location,status,created_at FROM runs ORDER BY id DESC LIMIT ?", (limit,))]
