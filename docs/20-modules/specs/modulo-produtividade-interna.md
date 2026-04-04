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
  - Lançamento manual e anulação com janela de 1h para o autor (gestão sem limite).
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
  - filtros + ranking + export + resumo + projeção + status de meta.
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

## Observação sobre legado

- [app/services/Produtividade.php](../../../app/services/Produtividade.php) permanece no repositório para compatibilidade histórica, mas o fluxo de guias já usa o serviço novo.
- Próximos incrementos recomendados: aplicar `registrarAuto` nos demais módulos prioritários (cartório, gabinete e audiências) em PRs pequenos.

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
| Integração automática inicial | `app/services/Guias.php` |
| Repositórios | `app/repositories/Produtividade{Regras,Lancamentos,Metas,Log,Badges}Repository.php` |
| APIs | `public/api/produtividade/**` |
| Views | `app/views/internal/produtividade-*.php` |
| JS | `public/assets/js/modules/produtividade/*.js` |
