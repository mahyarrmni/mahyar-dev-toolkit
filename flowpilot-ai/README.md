# FlowPilot AI

> نسخه فارسی و راهنمای مرحله‌به‌مرحله: [README.fa.md](README.fa.md)

Lightweight, fully local AI decision automation engine. Sends text to a local LLM (Ollama), classifies **intent** and **priority**, returns a structured JSON decision, stores it in SQLite, and triggers an **n8n** workflow.

No paid APIs. Two runtime dependencies (`fastapi`, `uvicorn`). If the LLM is unavailable or returns invalid output, a keyword heuristic takes over, so the service never fails.

## Architecture

```
text → FastAPI /analyze → Ollama (JSON mode) → validate → [fallback heuristic]
                                   ↓
                       SQLite log  +  n8n webhook
```

| File | Role |
|---|---|
| `app/engine.py` | Prompt, parsing, validation, heuristic fallback |
| `app/llm.py` | Ollama client (stdlib only) |
| `app/workflows.py` | n8n webhook trigger |
| `app/db.py` | SQLite persistence |
| `app/main.py` | API |

## Quick start

```bash
ollama pull llama3.2
pip install -r requirements.txt
uvicorn app.main:app --reload
```

```bash
curl -X POST localhost:8000/analyze -H 'Content-Type: application/json' \
  -d '{"text": "Our production app is down and customers are angry!"}'
```

```json
{
  "id": 1,
  "decision": {
    "intent": "complaint",
    "priority": "critical",
    "summary": "Production outage with angry customers",
    "action": "escalate_to_manager",
    "confidence": 0.9,
    "source": "llm"
  },
  "workflow": {"status": "sent", "http": 200}
}
```

Set `"trigger": false` to skip n8n. `GET /decisions` lists history.

## n8n

1. Run n8n: `npx n8n` (or Docker).
2. Import `n8n/flowpilot_workflow.json`, activate it.
3. Set `N8N_WEBHOOK_URL` (use `/webhook-test/flowpilot` while testing in the editor).

The sample routes `high`/`critical` decisions to *Escalate*, others to *Queue*. Extend with Slack, email, or ticketing nodes.

## Config (env vars, see `.env.example`)

`OLLAMA_URL`, `OLLAMA_MODEL`, `N8N_WEBHOOK_URL`, `DB_PATH`

## Intents → actions

`support→route_to_support`, `sales→notify_sales`, `billing→route_to_finance`, `complaint→escalate_to_manager`, `bug_report→create_ticket`, `feedback→log_feedback`, `spam→discard`, `other→manual_review`

## Tests

```bash
pip install -r requirements-dev.txt
pytest
```

## License

MIT
