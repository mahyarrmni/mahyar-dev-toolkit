from contextlib import contextmanager
from datetime import datetime, timezone

from sqlalchemy import JSON, Boolean, Column, DateTime, Float, ForeignKey, Index, Integer, String, Text, create_engine
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import declarative_base, sessionmaker
from sqlalchemy.pool import StaticPool

from . import config

J = JSON().with_variant(JSONB(), "postgresql")
Base = declarative_base()
_state = {"engine": None, "Session": None}


def now():
    return datetime.now(timezone.utc)


class Project(Base):
    __tablename__ = "projects"
    id = Column(Integer, primary_key=True)
    name = Column(String(200), nullable=False)
    site_url = Column(String(500), nullable=False)
    created_at = Column(DateTime, default=now)


class Crawl(Base):
    __tablename__ = "crawls"
    id = Column(Integer, primary_key=True)
    project_id = Column(Integer, ForeignKey("projects.id"), index=True)
    status = Column(String(20), default="queued")  # queued|running|done|failed
    config = Column(J)
    stats = Column(J)
    error = Column(Text)
    started_at = Column(DateTime)
    finished_at = Column(DateTime)


class Page(Base):
    __tablename__ = "pages"
    id = Column(Integer, primary_key=True)
    crawl_id = Column(Integer, ForeignKey("crawls.id"), index=True)
    url = Column(Text, nullable=False)
    final_url = Column(Text)
    status = Column(Integer)
    depth = Column(Integer)
    content_type = Column(String(100))
    blocked_by_robots = Column(Boolean, default=False)
    in_sitemap = Column(Boolean, default=False)
    error = Column(Text)
    fetch_ms = Column(Float)
    size_bytes = Column(Integer)
    redirect_chain = Column(J)
    redirect_loop = Column(Boolean, default=False)
    title = Column(Text)
    meta_description = Column(Text)
    meta_robots = Column(String(200))
    x_robots = Column(String(200))
    canonical = Column(Text)
    h1s = Column(J)
    h2s = Column(J)
    lang_attr = Column(String(20))
    lang = Column(String(10))
    hreflang = Column(J)
    rel_next = Column(Text)
    rel_prev = Column(Text)
    text = Column(Text)
    word_count = Column(Integer)
    content_hash = Column(String(40))
    simhash = Column(String(16))
    links = Column(J)
    jsonld = Column(J)
    jsonld_errors = Column(J)
    images_total = Column(Integer)
    images_missing_alt = Column(Integer)
    script_count = Column(Integer)
    rendered = Column(Boolean, default=False)
    __table_args__ = (Index("ix_pages_crawl_url", "crawl_id", "url"),)


PAGE_COLS = [c.name for c in Page.__table__.columns if c.name not in ("id",)]


class GscRow(Base):
    __tablename__ = "gsc_rows"
    id = Column(Integer, primary_key=True)
    project_id = Column(Integer, ForeignKey("projects.id"), index=True)
    date = Column(String(10))
    query = Column(Text)
    page = Column(Text)
    clicks = Column(Float)
    impressions = Column(Float)
    position = Column(Float)


class Analysis(Base):
    __tablename__ = "analyses"
    id = Column(Integer, primary_key=True)
    crawl_id = Column(Integer, ForeignKey("crawls.id"), index=True)
    kind = Column(String(40), index=True)
    data = Column(J)
    meta = Column(J)
    created_at = Column(DateTime, default=now)


def configure(url: str = None):
    url = url or config.DATABASE_URL
    kw = {}
    if url.startswith("sqlite"):
        kw["connect_args"] = {"check_same_thread": False}
        if ":memory:" in url:
            kw["poolclass"] = StaticPool
    eng = create_engine(url, future=True, pool_pre_ping=not url.startswith("sqlite"), **kw)
    Base.metadata.create_all(eng)
    _state.update(engine=eng, Session=sessionmaker(eng, expire_on_commit=False))
    return eng


@contextmanager
def session():
    if _state["Session"] is None:
        configure()
    s = _state["Session"]()
    try:
        yield s
        s.commit()
    except Exception:
        s.rollback()
        raise
    finally:
        s.close()


def page_to_dict(p: Page) -> dict:
    return {c: getattr(p, c) for c in PAGE_COLS}
