# Módulo de produtividade interna — estado atual

Documento vivo: consolida o que já está entregue no módulo, com foco em operação, integração e manutenção.

**Schema canônico:** [docs/schema/produtividade.sql](../../schema/produtividade.sql)

---

## Status por fase

| Fase | Status atual |
|------|--------------|
| **1 — Banco** | Concluída (`produtividade_*` + triggers de IDs). |
| **2 — Núcleo** | Concluída (regras, lançamento manual, listagem, anulação, APIs base, RBAC e menu). |
| **3 — Gestão e análise** | Concluída (exportação CSV/PDF, listagem e anulação). Ranking, resumo e projeção removidos da listagem em 2026-05. Metas removidas em 2026-05. |
| **4 — Gamificação** | Badges, popup de conquista e widget no dashboard (sem novos lançamentos automáticos desde 2026-05). |

---

## Entregas implementadas

### Banco e domínio

- Tabelas ativas: `produtividade_regras`, `produtividade_lancamentos`, `produtividade_log`, `produtividade_badges`, `produtividade_badges_concedidos`.
- `contexto_hash` com `UNIQUE` em lançamentos para idempotência de contexto.

### Serviço

- [app/services/ProdutividadeService.php](../../../app/services/ProdutividadeService.php):
  - Regras: listagem, busca, create/update e ativação.
  - Lançamento manual e anulação: perfis de gestão (**ADMIN**, **CHEFIA**, **DIRETORA**, **MAGISTRADA** — diretora e magistrada enquadradas como chefia, alinhado a `isGestaoUser`) anulam sem limite de tempo; demais usuários só anulam o próprio lançamento **ativo** dentro de **30 minutos** após `criado_em` (API e botão na listagem usam a mesma regra via `ProdutividadeService`).
  - Lançamento **manual** apenas; regras de cartório/sala-mídias/consultas reservadas (inativas no BD) — histórico `tipo_lancamento = automatico` permanece consultável.
  - Exportações CSV/PDF.
  - Badges: CRUD de gestão, verificação automática de elegibilidade, pendências e marcação de notificação.

### Repositórios

- [app/repositories/ProdutividadeRegrasRepository.php](../../../app/repositories/ProdutividadeRegrasRepository.php)
- [app/repositories/ProdutividadeLancamentosRepository.php](../../../app/repositories/ProdutividadeLancamentosRepository.php)
- [app/repositories/ProdutividadeLogRepository.php](../../../app/repositories/ProdutividadeLogRepository.php)
- [app/repositories/ProdutividadeBadgesRepository.php](../../../app/repositories/ProdutividadeBadgesRepository.php)

### APIs

- Núcleo: `public/api/produtividade/regras/*`, `lancamentos/*`, `export-csv.php`, `export-pdf.php`.
- Badges: `public/api/produtividade/badges/list.php`, `create.php`, `update.php`, `delete.php`, `set-ativo.php`, `pendentes.php`, `marcar-notificado.php`.

### Interface

- Lista principal: [app/views/internal/produtividade-list.php](../../../app/views/internal/produtividade-list.php)
  - cadastro manual + filtros + export (select CSV/PDF via `_list_export_buttons.php`, URLs `export-csv.php` / `export-pdf.php`);
  - botão **Anular** só quando `ProdutividadeService::canActorAnularLancamentoManual` for verdadeiro (alinhado à API `lancamentos/anular.php`).
- Regras: `produtividade-regras-list/create/edit.php`.
- Badges: `produtividade-badges-list/create/edit.php`.
- Compatibilidade de rota antiga: [app/views/internal/produtividade.php](../../../app/views/internal/produtividade.php).

### Front-end

- [public/assets/js/modules/produtividade/produtividade-list.js](../../../public/assets/js/modules/produtividade/produtividade-list.js)
- [public/assets/js/modules/produtividade/badge-popup.js](../../../public/assets/js/modules/produtividade/badge-popup.js)
  - toast + mini popup de conquista e confirmação de leitura das pendências.

### Portal e acesso

- Rotas em [public/frame.php](../../../public/frame.php) para regras e badges.
- RBAC em [app/access/PageAccessRegistry.php](../../../app/access/PageAccessRegistry.php):
  - `produtividade` = `all_internal`;
  - regras/badges = `gestao`.
- Menu em [public/home.php](../../../public/home.php) com entradas de Produtividade, Regras e Badges.

### Dashboard

- Widget em [app/views/internal/dashboard.php](../../../app/views/internal/dashboard.php) (pontos/resumo/ranking no widget: fora do escopo da remoção de automação na listagem; revisar em tarefa separada se necessário).

---

## Registro automático (descontinuado em 2026-05)

Removidos helpers `CartorioProdutividade` / `InternoProdutividadeAutoHelper`, `ProdutividadeService::registrarAuto`, configs `cartorio-produtividade-*` e `interno-produtividade-regras.php`, toasts `interno.produtividade.*` e blocos analíticos (ranking, resumo, projeção) na listagem.

- Regras automáticas: `ativo = 0` via `database/migrations/20260521_disable_produtividade_automatica_regras.sql`.
- Lançamentos automáticos **históricos** permanecem em `produtividade_lancamentos` e no filtro “Automático”.

---

## Observação sobre legado

- A classe antiga em `app/services/Produtividade.php` foi **removida** após auditoria do módulo: não havia referências no código; o fluxo atual usa exclusivamente [app/services/ProdutividadeService.php](../../../app/services/ProdutividadeService.php).
- Reativação de automação exigiria reimplementação de helpers, configs e integrações nos serviços (fora do escopo atual).

---

## Exportação PDF (Dompdf)

- Dependência já declarada em [composer.json](../../../composer.json): `dompdf/dompdf`.
- Para deploy, garantir:
  1. `composer install --no-dev` executado no ambiente;
  2. `vendor/autoload.php` presente e acessível;
  3. permissão de escrita temporária para renderização de PDF conforme configuração padrão do ambiente.

---

## Referências rápidas

| Área | Caminhos principais |
|------|---------------------|
| Schema | `docs/schema/produtividade.sql` |
| Serviço | `app/services/ProdutividadeService.php` |
| Migration desativação automática | `database/migrations/20260521_disable_produtividade_automatica_regras.sql` |
| Repositórios | `app/repositories/Produtividade{Regras,Lancamentos,Log,Badges}Repository.php` |
| APIs | `public/api/produtividade/**` |
| Views | `app/views/internal/produtividade-*.php` |
| JS | `public/assets/js/modules/produtividade/*.js` |
