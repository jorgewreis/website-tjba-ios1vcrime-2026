
# Suprimir avisos do Hugging Face/Transformers
import logging
from transformers import logging as hf_logging
hf_logging.set_verbosity_error()
logging.getLogger("transformers.modeling_utils").setLevel(logging.ERROR)

from sentence_transformers import SentenceTransformer
import faiss

from config import RAGConfig
from rag import build_docs, save_jsonl, embed_texts, build_faiss_index

def main():
    cfg = RAGConfig()
    cfg.data_dir.mkdir(parents=True, exist_ok=True)

    print("Coletando documentos...")
    docs = build_docs(cfg.repo_root, cfg)
    print(f"Total de chunks: {len(docs)}")

    save_jsonl(docs, cfg.docs_path)

    print("Gerando embeddings...")
    model = SentenceTransformer(cfg.embedding_model)
    texts = [d["text"] for d in docs]
    emb = embed_texts(model, texts)

    print("Construindo índice FAISS...")
    index = build_faiss_index(emb)
    faiss.write_index(index, str(cfg.faiss_path))

    print("OK.")
    print(f"Index: {cfg.faiss_path}")
    print(f"Docs:  {cfg.docs_path}")

if __name__ == "__main__":
    main()
