import json
import re
from pathlib import Path
from typing import Iterable, List, Dict, Tuple

import numpy as np
import requests
from sentence_transformers import SentenceTransformer

try:
    import faiss  # type: ignore
except ImportError as e:
    raise RuntimeError("faiss-cpu não instalado corretamente. Reinstale requirements.") from e


# -------------------------
# IO + chunking
# -------------------------

def iter_files(
    root: Path,
    include_paths: Iterable[str],
    allowed_exts: Tuple[str, ...],
    exclude_dirs: Tuple[str, ...],
):
    for rel in include_paths:
        p = root / rel
        if p.is_file() and p.suffix.lower() in allowed_exts:
            yield p
        elif p.is_dir():
            for fp in p.rglob("*"):
                if fp.is_dir():
                    continue
                if fp.suffix.lower() not in allowed_exts:
                    continue
                if any(part in exclude_dirs for part in fp.parts):
                    continue
                yield fp


def read_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8", errors="strict")
    except Exception as e:
        print(f"[WARN] Falha ao ler {path} em utf-8: {e}. Tentando latin-1...")
        try:
            return path.read_text(encoding="latin-1", errors="replace")
        except Exception as e2:
            print(f"[ERRO] Falha ao ler {path} em latin-1: {e2}.")
            return ""


def chunk_text(text: str, chunk_chars: int, overlap: int) -> List[str]:
    text = text.replace("\r\n", "\n")
    chunks = []
    i = 0
    n = len(text)
    min_chunk = max(120, int(chunk_chars * 0.25))
    while i < n:
        j = min(i + chunk_chars, n)
        chunk = text[i:j].strip()
        if len(chunk) < min_chunk and i > 0:
            break  # evita fragmentos muito pequenos no final
        if chunk:
            chunks.append(chunk)
        if j == n:
            break
        i = max(0, j - overlap)
    return chunks


def normalize_path(p: str) -> str:
    # remove quebras e espaços esquisitos, normaliza separador
    p = p.replace("\r", "").replace("\n", "")
    p = re.sub(r"\s+", " ", p).strip()
    p = p.replace("\\", "/")
    # remove espaços em volta de /
    p = p.replace(" /", "/").replace("/ ", "/")
    return p


def build_docs(repo_root: Path, cfg) -> List[Dict]:
    docs = []
    for fp in iter_files(repo_root, cfg.include_paths, cfg.allowed_exts, cfg.exclude_dirs):
        raw = read_text(fp)
        if not raw.strip():
            continue

        clean_path = normalize_path(fp.as_posix())

        for k, ch in enumerate(chunk_text(raw, cfg.chunk_chars, cfg.chunk_overlap)):
            docs.append({
                "id": f"{clean_path}::chunk::{k}",
                "path": clean_path,
                "chunk_index": k,
                "text": ch,
            })

    return docs


def save_jsonl(docs: List[Dict], out_path: Path):
    out_path.parent.mkdir(parents=True, exist_ok=True)
    with out_path.open("w", encoding="utf-8") as f:
        for d in docs:
            f.write(json.dumps(d, ensure_ascii=False) + "\n")


def load_jsonl(path: Path) -> List[Dict]:
    if not path.exists():
        raise FileNotFoundError(f"Arquivo não encontrado: {path}")
    docs = []
    with path.open("r", encoding="utf-8") as f:
        for line in f:
            docs.append(json.loads(line))
    return docs


# -------------------------
# Embeddings + FAISS
# -------------------------

def embed_texts(model: SentenceTransformer, texts: List[str], batch_size: int = 64) -> np.ndarray:
    emb = model.encode(
        texts,
        batch_size=batch_size,
        show_progress_bar=False,
        normalize_embeddings=True
    )
    return np.asarray(emb, dtype=np.float32)


def build_faiss_index(embeddings: np.ndarray) -> "faiss.Index":
    dim = embeddings.shape[1]
    index = faiss.IndexFlatIP(dim)
    index.add(embeddings)
    return index


# -------------------------
# Ollama (streaming)
# -------------------------

def ollama_generate(cfg, prompt: str) -> str:
    payload = {
        "model": cfg.ollama_model,
        "prompt": prompt,
        "stream": True,
        "options": {
            "temperature": 0.1,
            "num_predict": 512,
        },
    }
    try:
        r = requests.post(
            cfg.ollama_url,
            json=payload,
            stream=True,
            timeout=cfg.request_timeout_sec
        )
        r.raise_for_status()
    except Exception as e:
        print(f"[ERRO] Falha ao conectar ao Ollama: {e}")
        raise

    out = []
    for line in r.iter_lines(decode_unicode=True):
        if not line:
            continue
        data = json.loads(line)
        chunk = data.get("response", "")
        if chunk:
            out.append(chunk)
        if data.get("done"):
            break
    return "".join(out).strip()


# -------------------------
# Hybrid retrieval helpers
# -------------------------

_STOP = {
    "a","o","os","as","de","da","do","das","dos","e","em","no","na","nos","nas",
    "um","uma","para","por","com","sem","que","se","ao","à","às","é","ser",
    "como","onde","qual","quais","regras","oficial","oficiais"
}


def _tokenize(q: str) -> List[str]:
    q = q.lower()
    tokens = re.findall(r"[a-z0-9_/-]+", q, flags=re.IGNORECASE)
    out = []
    for t in tokens:
        if len(t) < 3:
            continue
        if t in _STOP:
            continue
        out.append(t)
    return out[:12]


def _path_boost(path: str) -> float:
    # Ajustado ao repo 2021
    p = normalize_path(path).lower()

    if p.endswith("/docs/00-foundation/estrutura-repositorio.md"):
        return 0.30
    if "/docs/00-foundation/" in p:
        return 0.16
    if "/docs/canons/" in p:
        return 0.10
    if "/assets/css/" in p or "/public/assets/css/" in p:
        return 0.08
    if "/docs/10-architecture/" in p:
        return 0.04
    if p.endswith("/readme.md") or p.endswith("readme.md"):
        return 0.02
    return 0.0


def _lexical_score(text: str, tokens: List[str]) -> float:
    if not tokens:
        return 0.0
    t = (text or "").lower()
    hits = 0
    for tok in tokens:
        if tok in t:
            hits += 1
    return hits / max(4, len(tokens))


def _rerank_hybrid(hits: List[Dict], query: str) -> List[Dict]:
    tokens = _tokenize(query)
    reranked = []
    for h in hits:
        sem = float(h.get("score", 0.0))
        lex = _lexical_score(h.get("text", ""), tokens)
        boost = _path_boost(h.get("path", ""))
        final = sem + (0.25 * lex) + boost
        reranked.append({
            **h,
            "score": final,
            "score_sem": sem,
            "score_lex": lex,
            "score_boost": boost
        })
    reranked.sort(key=lambda x: x["score"], reverse=True)
    return reranked


def _force_include_by_filename(docs: List[Dict], filename: str, limit: int = 12) -> List[Dict]:
    filename = filename.lower().strip()
    forced = []
    for d in docs:
        p = normalize_path(d.get("path", "")).lower()
        if p.endswith(filename):
            forced.append(d)
            if len(forced) >= limit:
                break
    return forced


def _force_include_contains(docs: List[Dict], filename: str, needles: List[str], limit: int = 12) -> List[Dict]:
    fn = filename.lower().strip()
    needles_l = [n.lower() for n in needles]
    forced = []
    for d in docs:
        p = normalize_path(d.get("path", "")).lower()
        if not p.endswith(fn):
            continue
        t = (d.get("text") or "").lower()
        if any(n in t for n in needles_l):
            forced.append(d)
            if len(forced) >= limit:
                break
    return forced


def _merge_unique(base: List[Dict], extra: List[Dict]) -> List[Dict]:
    seen = set()
    out = []
    for item in base + extra:
        key = (item.get("path"), item.get("chunk_index"))
        if key in seen:
            continue
        seen.add(key)
        out.append(item)
    return out


# -------------------------
# Search + answer
# -------------------------

def search(cfg, query: str, top_k: int = None, fetch_k: int = None) -> List[Dict]:
    if top_k is None:
        top_k = getattr(cfg, "top_k_default", 10)
    if fetch_k is None:
        fetch_k = getattr(cfg, "fetch_k", 80)

    try:
        model = SentenceTransformer(cfg.embedding_model)
        docs = load_jsonl(cfg.docs_path)
        index = faiss.read_index(str(cfg.faiss_path))
    except Exception as e:
        print(f"[ERRO] Falha ao carregar embeddings ou índice: {e}")
        raise

    q_emb = embed_texts(model, [query])
    scores, idxs = index.search(q_emb, fetch_k)

    raw_hits = []
    for score, i in zip(scores[0].tolist(), idxs[0].tolist()):
        if i < 0:
            continue
        d = docs[i]
        d["path"] = normalize_path(d["path"])
        d["id"] = normalize_path(d["id"])
        raw_hits.append({**d, "score": float(score)})

    # ✅ Override direcionado quando o usuário menciona o doc
    ql = query.lower()
    forced = []
    if "estrutura-repositorio" in ql:
        forced_docs = _force_include_by_filename(docs, "estrutura-repositorio.md", limit=14)
        forced_css = _force_include_contains(
            docs,
            "estrutura-repositorio.md",
            needles=[
                "public/assets/css", "assets/css",
                "reset.css", "color.css", "pages.css",
                "components.css", "layout.css",
                "css"
            ],
            limit=18
        )
        forced_docs = _merge_unique(forced_docs, forced_css)

        forced = [{
            **d,
            "path": normalize_path(d["path"]),
            "id": normalize_path(d["id"]),
            "score": 0.90
        } for d in forced_docs]

    raw_hits = _merge_unique(raw_hits, forced)

    ranked = _rerank_hybrid(raw_hits, query)

    out = []
    seen = set()
    for h in ranked:
        key = (h["path"], h["chunk_index"])
        if key in seen:
            continue
        seen.add(key)
        out.append(h)
        if len(out) >= top_k:
            break
    return out


def format_context(hits: List[Dict], max_chars: int) -> str:
    parts = []
    total = 0
    for h in hits:
        header = f"[SOURCE: {h['path']}::chunk::{h['chunk_index']}]"
        block = header + "\n" + (h["text"] or "").strip() + "\n"
        if total + len(block) > max_chars:
            remaining = max_chars - total
            if remaining > 200:
                parts.append(block[:remaining])
            break
        parts.append(block)
        total += len(block)
    return "\n---\n".join(parts).strip()


def _build_guarded_prompt(context: str, question: str, cfg) -> str:
    return f"""Você é um assistente de engenharia de software para um repositório local.

REGRAS CRÍTICAS:
1) Responda APENAS com base no CONTEXTO.
2) É PROIBIDO: suposições, inferências ("provavelmente", "pode", "talvez", "seria", "algo semelhante"), e exemplos inventados.
3) Para cada afirmação, inclua:
   - a citação (SOURCE: caminho::chunk::N)
   - e um EXCERPT literal copiado do CONTEXTO (máx. 2 linhas) que sustente a afirmação.
4) Se não houver EXCERPT no CONTEXTO que sustente a resposta, responda: "NÃO ENCONTRADO NO REPOSITÓRIO".
5) Se a pergunta for sobre "padrão", priorize documentos de docs/ (canons/foundation/architecture) em vez de tentar deduzir pelo nome do arquivo.
6) Seja conciso (máx. {cfg.answer_max_chars} caracteres).

CONTEXTO:
{context}

PERGUNTA:
{question}

FORMATO OBRIGATÓRIO:
- Resposta
- Evidências:
  - (SOURCE: ... ) EXCERPT: "..."
  - (SOURCE: ... ) EXCERPT: "..."
"""


def answer(cfg, question: str, top_k: int = None) -> Dict:
    print(f"[INFO] Pergunta: {question}")
    hits = search(cfg, question, top_k=top_k)

    min_score = getattr(cfg, "min_score", 0.35)
    strong = [h for h in hits if float(h.get("score", 0.0)) >= min_score]
    print(f"[INFO] {len(strong)} trechos relevantes recuperados.")

    if not strong:
        include_list = "\n".join([f"- {p}" for p in cfg.include_paths])
        return {
            "response": (
                "NÃO ENCONTRADO NO REPOSITÓRIO\n"
                "- Motivo: não há trechos recuperados com evidência suficiente para responder.\n"
                "- Próximos passos:\n"
                "  1) Busque o termo exato no repositório (ex.: Select-String) e identifique o arquivo.\n"
                "  2) Confirme que o arquivo está dentro de include_paths.\n"
                "  3) Rode novamente: python ingest.py\n"
                "- Locais atualmente indexados (include_paths):\n"
                f"{include_list}"
            ),
            "hits": hits,
        }

    context = format_context(strong, max_chars=cfg.max_context_chars)
    prompt = _build_guarded_prompt(context, question, cfg)

    response = ollama_generate(cfg, prompt).strip()

    banned = ["provavelmente", "pode", "talvez", "seria", "algo semelhante", "similar", "em geral"]
    if any(b in response.lower() for b in banned):
        response = (
            "NÃO ENCONTRADO NO REPOSITÓRIO\n"
            "- Motivo: o modelo tentou inferir/gerar conteúdo não presente nos trechos recuperados.\n"
            "- Ação: refine a pergunta para apontar o documento de padrão (ex.: 'estrutura-repositorio', 'guia-estrutura-docs')."
        )

    # Exigir EXCERPT quando o prompt obriga
    if "EXCERPT:" not in response and "NÃO ENCONTRADO NO REPOSITÓRIO" not in response:
        response = (
            "NÃO ENCONTRADO NO REPOSITÓRIO\n"
            "- Motivo: a resposta não apresentou EXCERPT literal do contexto, como exigido.\n"
            "- Ação: ajuste a consulta para termos literais do trecho (ex.: 'public/assets/css')."
        )

    # Se não citou, recusa.
    if "SOURCE:" not in response and "NÃO ENCONTRADO NO REPOSITÓRIO" not in response:
        response = (
            "NÃO ENCONTRADO NO REPOSITÓRIO\n"
            "- Motivo: a resposta não apresentou citações do contexto.\n"
            "- Ação: refine a pergunta com termos exatos (ex.: 'public/assets/css', 'CSS modular', 'components.css')."
        )

    if len(response) > cfg.answer_max_chars:
        response = response[: cfg.answer_max_chars].rstrip() + "…"

    return {"response": response, "hits": strong}
