from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    env: str = "development"
    database_url: str = "postgresql://supportflow:supportflow@localhost:5432/supportflow"
    ollama_url: str = "http://localhost:11434"
    chat_model: str = "qwen2.5:7b"
    embed_model: str = "bge-m3"
    num_ctx: int = 4096
    api_key: str = "dev-key"
    default_language: str = "fa"
    rag_top_k: int = 4
    rag_min_similarity: float = 0.5
    escalation_confidence: float = 0.6
    rate_limit_per_min: int = 120


settings = Settings()
