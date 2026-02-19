import sys
from config import RAGConfig
from rag import answer

def main():
    try:
        cfg = RAGConfig()
        question = " ".join(sys.argv[1:]).strip()
        if not question:
            print('Uso: python query.py "sua pergunta aqui"')
            sys.exit(1)

        out = answer(cfg, question)  # usa cfg.top_k_default

        print("\n=== RESPOSTA ===\n")
        print(out["response"])

        print("\n=== FONTES RECUPERADAS ===\n")
        for h in out["hits"]:
            print(f"- {h['path']} (chunk {h['chunk_index']}) score={h['score']:.3f}")
    except Exception as e:
        print(f"[ERRO] Falha ao executar query: {e}")
        sys.exit(2)

if __name__ == "__main__":
    main()
