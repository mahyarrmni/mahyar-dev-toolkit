from typing import Optional
from pydantic import BaseModel, Field


class AnalyzeRequest(BaseModel):
    text: str = Field(..., min_length=1, max_length=5000)
    trigger: bool = True


class Decision(BaseModel):
    intent: str
    priority: str
    summary: str
    action: str
    confidence: float
    source: str  # "llm" or "heuristic"


class AnalyzeResponse(BaseModel):
    id: int
    decision: Decision
    workflow: Optional[dict] = None
