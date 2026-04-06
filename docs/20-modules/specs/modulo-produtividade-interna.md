# Módulo de produtividade interna — estado atual

Documento vivo: consolida o que já está entregue no módulo, com foco em operação, integração e manutenção.

**Schema canônico:** [docs/schema/produtividade.sql](../../schema/produtividade.sql)

---

## Status por fase

| Fase | Status atual |
|------|--------------|
| **1 — Banco** | Concluída (`produtividade_*` + triggers de IDs). |
| **2 — Núcleo** | Concluída (regras, lançamento manual, listagem, anulação, APIs base, RBAC e menu). |
| **3 — Gestão e análise** | Concluída (metas, ranking, exportação CSV/PDF, resumo semana/mês/trimestre). |
| **4 — Gamificação e automação** | Entregue no ciclo atual (`registrarAuto`, badges, popup de conquista, widget no dashboard, integração inicial em Guias). |

---

## Entregas implementadas

### Banco e domínio

- Tabelas ativas: `produtividade_regras`, `produtividade_lancamentos`, `produtividade_metas`, `produtividade_log`, `produtividade_badges`, `produtividade_badges_concedidos`.
- `contexto_hash` com `UNIQUE` em lançamentos para idempotência de contexto.

### Serviço

- [app/services/ProdutividadeService.php](../../../app/services/ProdutividadeService.php):
  - Regras: listagem, busca, create/update e ativação.
  - Lançamento manual e anulação: perfis **ADMIN** e **CHEFIA** anulam sem limite de tempo; demais usuários só anulam o próprio lançamento **ativo** dentro de **30 minutos** após `criado_em` (API e botão na listagem usam a mesma regra via `ProdutividadeService`).
  - **`registrarAuto`** com resolução de regra por (`tipo_ato`, `tipo_documento`, `sistema_modulo`), no-op silencioso sem regra ativa e tratamento idempotente de duplicidade.
  - Metas: list/find/create/update/set-ativo.
  - Ranking por setor e exportações.
  - Resumo de cards (semana/mês/trimestre), projeção por dias úteis e resumo de meta aplicável.
  - Badges: CRUD de gestão, verificação automática de elegibilidade, pendências e marcação de notificação.

### Repositórios

- [app/repositories/ProdutividadeRegrasRepository.php](../../../app/repositories/ProdutividadeRegrasRepository.php)
- [app/repositories/ProdutividadeLancamentosRepository.php](../../../app/repositories/ProdutividadeLancamentosRepository.php)
- [app/repositories/ProdutividadeMetasRepository.php](../../../app/repositories/ProdutividadeMetasRepository.php)
- [app/repositories/ProdutividadeLogRepository.php](../../../app/repositories/ProdutividadeLogRepository.php)
- [app/repositories/ProdutividadeBadgesRepository.php](../../../app/repositories/ProdutividadeBadgesRepository.php)

### APIs

- Núcleo: `public/api/produtividade/regras/*`, `lancamentos/*`, `metas/*`, `ranking.php`, `export-csv.php`, `export-pdf.php`.
- Badges: `public/api/produtividade/badges/list.php`, `create.php`, `update.php`, `delete.php`, `set-ativo.php`, `pendentes.php`, `marcar-notificado.php`.

### Interface

- Lista principal: [app/views/internal/produtividade-list.php](../../../app/views/internal/produtividade-list.php)
  - filtros + ranking + export (select CSV/PDF via `_list_export_buttons.php`, URLs `export-csv.php` / `export-pdf.php`) + resumo + projeção + status de meta;
  - botão **Anular** só quando `ProdutividadeService::canActorAnularLancamentoManual` for verdadeiro (alinhado à API `lancamentos/anular.php`).
- Regras: `produtividade-regras-list/create/edit.php`.
- Metas: `produtividade-metas-list/create/edit.php`.
- Badges: `produtividade-badges-list/create/edit.php`.
- Compatibilidade de rota antiga: [app/views/internal/produtividade.php](../../../app/views/internal/produtividade.php).

### Front-end

- [public/assets/js/modules/produtividade/produtividade-list.js](../../../public/assets/js/modules/produtividade/produtividade-list.js)
- [public/assets/js/modules/produtividade/badge-popup.js](../../../public/assets/js/modules/produtividade/badge-popup.js)
  - toast + mini popup de conquista e confirmação de leitura das pendências.

### Portal e acesso

- Rotas em [public/frame.php](../../../public/frame.php) para regras/metas/badges.
- RBAC em [app/access/PageAccessRegistry.php](../../../app/access/PageAccessRegistry.php):
  - `produtividade` = `all_internal`;
  - regras/metas/badges = `gestao`.
- Menu em [public/home.php](../../../public/home.php) com entradas de Produtividade, Regras, Metas e Badges.

### Dashboard

- Widget em [app/views/internal/dashboard.php](../../../app/views/internal/dashboard.php):
  - pontos da semana;
  - posição do usuário no ranking do setor;
  - atalho para o módulo.

### Primeira automação integrada

- [app/services/Guias.php](../../../app/services/Guias.php) migrou para `ProdutividadeService::registrarAuto(...)` no cadastro de guia.
- O fluxo principal de guias permanece resiliente: falha de produtividade não bloqueia cadastro.

---

## Cartório — eventos automáticos (planeado)

Extensão do módulo: cobrir **todo o cartório interno** com lançamentos automáticos a partir de um **catálogo de `event_code`**, helper central, regras em `produtividade_regras`, estornos espelhados onde aplicável e documentação de catálogo em `docs/20-modules/specs/cartorio-produtividade-movimentacoes.md` (a criar na Fase 1).

- **Plano canónico (decisões + fases + inventário):** [`.cursor/plans/produtividade-cartorio-plano.md`](../../../.cursor/plans/produtividade-cartorio-plano.md)
- **Textos na listagem:** após implementação, todas as movimentações com produtividade terão entrada em `app/config/cartorio-produtividade-list-texts.php`, com **frase fixa ou template** (placeholders como finalidade da carta) por `event_code`; subtarefas com redação distinta usam **códigos distintos**; revisão **caso a caso** no plano (Fases 4–5, secção 5.2).
- **Governação:** fonte de verdade só no Git; progresso também refletido em `docs/90-releases/notes/` ao fechar cada fase.

Até à conclusão desse trabalho, o estado “entregue” deste spec continua a refletir **Fase 4** (automação inicial em Guias + badges); a secção acima descreve o **roadmap** alinhado ao plano.

---

## Observação sobre legado

- [app/services/Produtividade.php](../../../app/services/Produtividade.php) permanece no repositório para compatibilidade histórica, mas o fluxo de guias já usa o serviço novo.
- Próximos incrementos: seguir o plano em [`.cursor/plans/produtividade-cartorio-plano.md`](../../../.cursor/plans/produtividade-cartorio-plano.md) para o **cartório**; outros domínios (gabinete, audiências) em PRs pequenos quando priorizados.

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
| Plano cartório (automação completa) | [`.cursor/plans/produtividade-cartorio-plano.md`](../../../.cursor/plans/produtividade-cartorio-plano.md) |
| Schema | `docs/schema/produtividade.sql` |
| Serviço | `app/services/ProdutividadeService.php` |
| Integração automática inicial | `app/services/Guias.php` |
| Repositórios | `app/repositories/Produtividade{Regras,Lancamentos,Metas,Log,Badges}Repository.php` |
| APIs | `public/api/produtividade/**` |
| Views | `app/views/internal/produtividade-*.php` |
| JS | `public/assets/js/modules/produtividade/*.js` |
