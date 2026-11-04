-- Runs once on first database start. embedding dim 1024 = bge-m3.
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE tickets (
  id BIGSERIAL PRIMARY KEY,
  customer TEXT NOT NULL DEFAULT '',
  subject TEXT NOT NULL,
  body TEXT NOT NULL,
  category TEXT NOT NULL,
  sentiment TEXT NOT NULL,
  sentiment_score REAL NOT NULL,
  urgency SMALLINT NOT NULL,
  priority TEXT NOT NULL CHECK (priority IN ('low','medium','high','urgent')),
  status TEXT NOT NULL CHECK (status IN ('auto_resolved','escalated','resolved')),
  escalated BOOLEAN NOT NULL,
  escalation_reasons TEXT[] NOT NULL DEFAULT '{}',
  ai_reply TEXT,
  confidence REAL,
  human_reply TEXT,
  language TEXT NOT NULL DEFAULT 'fa',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  resolved_at TIMESTAMPTZ
);
CREATE INDEX tickets_status_created ON tickets (status, created_at DESC);

CREATE TABLE kb_docs (
  id BIGSERIAL PRIMARY KEY,
  title TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE kb_chunks (
  id BIGSERIAL PRIMARY KEY,
  doc_id BIGINT NOT NULL REFERENCES kb_docs(id) ON DELETE CASCADE,
  content TEXT NOT NULL,
  embedding vector(1024) NOT NULL
);
CREATE INDEX kb_chunks_embedding ON kb_chunks USING hnsw (embedding vector_cosine_ops);
