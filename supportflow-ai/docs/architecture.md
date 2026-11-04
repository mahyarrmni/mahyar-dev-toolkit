# Architecture

## Pipeline (`backend/app/pipeline.py`)
1. **Sanitise** – delimiter tags are stripped; text is passed to the LLM as untrusted data.
2. **Analyse** (1 LLM call, JSON mode) – category, sentiment score (-1..1), urgency (1-4), language. Output is validated and clamped (`normalize`).
3. **Prioritise** (deterministic) – `urgency + (sentiment ≤ -0.6) + (legal/security)` → low / medium / high / urgent.
4. **Retrieve** – query embedding (`bge-m3`) → top-k cosine search in pgvector (HNSW).
5. **Answer** (1 LLM call) – only when best similarity ≥ threshold, category not sensitive, and no human requested. Returns `reply` + `confidence`.
6. **Escalate** if any reason applies: `sensitive_category`, `urgent_priority`, `very_negative_sentiment`, `human_requested`, `no_kb_match`, `low_confidence`, `ai_error`.

Escalated tickets get a fixed acknowledgement for the customer; any AI draft is stored for the agent.

## Components
| Component | Role |
|---|---|
| n8n | Channel integration, secret check, alerting, SLA reminders |
| FastAPI | Business logic, auth, rate limit, dashboard hosting |
| Ollama | Local chat + embedding models |
| PostgreSQL + pgvector | Tickets, knowledge base, vector search |

## Decisions
- **Deterministic priority and escalation rules** instead of trusting the LLM for policy decisions: auditable and testable.
- **Fail safe**: any AI error escalates to a human.
- **Two LLM calls max per ticket** to keep CPU-only deployments usable.
- **Dashboard without build step** (vanilla JS, CSP `default-src 'self'`, no `innerHTML`).
- Schema is created by `db/init.sql` on first start; for later changes add migrations (e.g. Alembic) before multi-environment use.
