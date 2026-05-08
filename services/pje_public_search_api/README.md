# PJe Public Search Test API

Servico isolado de testes para consultar a pagina publica do PJe com FastAPI e Playwright.

Este laboratorio nao faz parte do fluxo principal do sistema PHP. Ele existe para validar a viabilidade de consulta publica do TJBA enquanto o modelo definitivo nao estiver fechado.

## Instalar

```bash
python -m venv .venv
.venv\\Scripts\\activate
pip install -r requirements.txt
playwright install chromium
```

## Rodar

```bash
uvicorn main:app --host 127.0.0.1 --port 8000 --reload
```

## Variaveis

- `PJE_PUBLIC_BASE_URL`: URL unica da consulta publica.
- `PJE_PUBLIC_BASE_URLS`: lista separada por virgula. Tem prioridade sobre `PJE_PUBLIC_BASE_URL`.
- `PJE_PUBLIC_TIMEOUT_SECONDS`: timeout por etapa, em segundos. Padrao: `20`.

Quando nenhuma URL e informada, o servico tenta primeiro:

- `https://pje.tjba.jus.br/pje/ConsultaPublica/listView.seam`
- `https://pje2g.tjba.jus.br/pje/ConsultaPublica/listView.seam`

## Endpoints

```bash
curl http://127.0.0.1:8000/health
curl http://127.0.0.1:8000/pje/0000000-00.0000.0.00.0000
curl http://127.0.0.1:8000/pje/0000000-00.0000.0.00.0000/raw
```

## Observacoes

O PJe publico pode mudar seletores, aplicar bloqueios ou exigir desafios adicionais. Quando isso ocorrer, ajuste os seletores em `scraper.py` mantendo a resposta normalizada.
