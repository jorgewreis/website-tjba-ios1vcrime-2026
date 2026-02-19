from dataclasses import dataclass, field
from pathlib import Path

@dataclass(frozen=True)
class RAGConfig:
    """
    Configuração para pipeline RAG local.
    Todos os caminhos e parâmetros de busca/indexação.
    """
    repo_root: Path = Path(r"D:\Projects\Clients\website-tjba-ios1vcrime-2026")
    data_dir: Path = Path(__file__).resolve().parent / "data"
    faiss_path: Path = data_dir / "index.faiss"
    docs_path: Path = data_dir / "docs.jsonl"
    include_paths: tuple = field(default_factory=lambda: (
        "docs/00-foundation",
        "docs/10-architecture",
        "docs/20-modules",
        "docs/30-implementation",
        "docs/40-deployment",
        "docs/50-maintenance",
        "docs/60-future",
        "docs/70-appendix",
        "docs/90-releases",
        "README.md",
        "public/assets/css",
        "public/assets/css/modules",
        "public/assets/css/shared",
        "public/assets/js",
        "public/assets/js/modules",
        "public/assets/js/shared",
        "app",
        "app/controllers",
        "app/models",
        "app/views",
        "app/services",
        "app/helpers",      
        "pages",
    ))
    allowed_exts: tuple = field(default_factory=lambda: (".md", ".php", ".css", ".js", ".html", ".txt"))
    exclude_dirs: tuple = field(default_factory=lambda: (
        ".git", "node_modules", "vendor", "dist", "build", ".cache", ".venv"
    ))
    min_score: float = 0.30         # mais tolerante (seu corpus é pequeno/médio)
    fetch_k: int = 80               # pega mais candidatos no FAISS para rerank
    top_k_default: int = 10         # quantos chunks finais usar
    chunk_chars: int = 900
    chunk_overlap: int = 120
    embedding_model: str = "sentence-transformers/all-MiniLM-L6-v2"
    ollama_url: str = "http://localhost:11434/api/generate"
    ollama_model: str = "phi3:mini"
    max_context_chars: int = 4500     # limita o total enviado ao LLM
    answer_max_chars: int = 2200      # limita resposta final (controle de verborragia)
    request_timeout_sec: int = 600    # evita timeout curto demais
