"""Runtime configuration (immutable dataclass shared by every component)."""
from dataclasses import dataclass


@dataclass(frozen=True)
class Config:
    db_path: str = "leads.db"
    concurrency: int = 10          # domains processed in parallel
    per_host_delay: float = 1.0    # polite delay between requests to the SAME host
    timeout: float = 15.0          # total seconds per request
    retries: int = 2               # extra attempts on timeouts / 5xx / 429
    max_pages: int = 4             # homepage + (max_pages-1) contact/about pages
    max_bytes: int = 1_500_000     # hard cap on downloaded body per page
    respect_robots: bool = True
    force: bool = False            # re-crawl domains that are already stored
    weights_path: str = ""         # optional JSON file overriding score weights
    user_agent: str = ("Mozilla/5.0 (compatible; B2BLeadIntel/1.0; "
                       "+business-contact-research)")
