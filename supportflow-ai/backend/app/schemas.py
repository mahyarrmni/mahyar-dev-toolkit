from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field


class _Strip(BaseModel):
    model_config = ConfigDict(str_strip_whitespace=True)


class TicketIn(_Strip):
    customer: str = Field("", max_length=200)
    subject: str = Field(..., min_length=1, max_length=300)
    body: str = Field(..., min_length=1, max_length=8000)


class ResolveIn(_Strip):
    reply: str = Field(..., min_length=1, max_length=8000)


class KBIn(_Strip):
    title: str = Field(..., min_length=1, max_length=200)
    content: str = Field(..., min_length=1, max_length=100_000)


class TicketOut(BaseModel):
    id: int
    customer: str
    subject: str
    body: str
    category: str
    sentiment: str
    sentiment_score: float
    urgency: int
    priority: str
    status: str
    escalated: bool
    escalation_reasons: list[str]
    ai_reply: str | None
    confidence: float | None
    human_reply: str | None
    language: str
    created_at: datetime
    resolved_at: datetime | None
    customer_reply: str | None = None
