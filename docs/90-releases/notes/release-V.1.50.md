![Badge](https://img.shields.io/badge/MINOR-blue?style=for-the-badge)

**Data de Lançamento:** 22 de maio de 2026

# V. 1.50

> refactor: enhance priority handling in ProcessoBlocoListagemHelper

## Nível de Impacto

🟡 **MEDIO**

## Alterações

- **refactor: enhance priority handling in ProcessoBlocoListagemHelper** `[f3909556]`
- **refactor(cartas-recebidas): fluxo com 2 subtarefas (cumprimento + devolução)** — remove etapas legadas (ordens 5/7, arquivamento, tipo de envio); status principal `aguardando cumprimento` / `aguardando devolução`; migração `scripts/migrate-cartas-recebidas-2-subtarefas.php`
- **remove(medidas-protetivas):** submódulo Cartório Medidas Protetivas (telas, APIs, serviços, dashboard, exportação, produtividade); script opcional `scripts/drop-medidas-protetivas-tables.php` para homolog/prod
- **remove(enderecos-cadastro):** cadastro de endereços em instituições públicas e pessoas (UI, APIs, repositórios, serviços, toasts); script opcional `scripts/drop-enderecos-partes-instituicoes-tables.php` para homolog/prod

---

*Documento gerado automaticamente pelo sistema de versionamento.*
