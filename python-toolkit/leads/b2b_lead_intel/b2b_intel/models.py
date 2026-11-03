"""Plain data containers. Everything is JSON-serialisable for SQLite/exports."""
from __future__ import annotations
from dataclasses import dataclass, field, asdict
from typing import Optional


@dataclass
class FetchResult:
    url: str
    final_url: str = ""
    status: int = 0
    text: str = ""
    headers: dict = field(default_factory=dict)
    error: str = ""

    @property
    def ok(self) -> bool:
        return self.status == 200 and bool(self.text) and not self.error


@dataclass
class PageData:
    """What we learn from ONE page."""
    url: str = ""
    title: str = ""
    description: str = ""
    site_name: str = ""
    lang: str = ""
    emails: list = field(default_factory=list)     # [{address, kind, same_domain}]
    phones: list = field(default_factory=list)     # ["+98..."]
    socials: dict = field(default_factory=dict)    # platform -> url
    tech: list = field(default_factory=list)       # [{name, category}]
    org: dict = field(default_factory=dict)        # merged JSON-LD organisation info
    contact_links: list = field(default_factory=list)


@dataclass
class Lead:
    """What we know about ONE company (merged across pages)."""
    domain: str
    url: str = ""
    name: str = ""
    description: str = ""
    lang: str = ""
    address: str = ""
    employees: Optional[int] = None
    emails: list = field(default_factory=list)
    phones: list = field(default_factory=list)
    socials: dict = field(default_factory=dict)
    tech: list = field(default_factory=list)
    pages_crawled: int = 0
    status: str = "pending"      # ok | unreachable | blocked | error
    error: str = ""
    score: int = 0
    grade: str = "D"
    score_reasons: list = field(default_factory=list)
    opportunities: list = field(default_factory=list)
    crawled_at: str = ""

    def to_dict(self) -> dict:
        return asdict(self)

    @classmethod
    def from_dict(cls, d: dict) -> "Lead":
        known = {k: v for k, v in d.items() if k in cls.__dataclass_fields__}
        return cls(**known)

    @property
    def best_email(self) -> str:
        if not self.emails:
            return ""
        # prefer same-domain, then personal over generic
        ranked = sorted(self.emails, key=lambda e: (not e["same_domain"], e["kind"] == "generic"))
        return ranked[0]["address"]

    def tech_names(self) -> list:
        return [t["name"] for t in self.tech]
