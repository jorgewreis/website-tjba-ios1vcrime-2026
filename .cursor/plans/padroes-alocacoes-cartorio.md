# Plano: padrões de alocação (cartório)

> **Sincronização:** manter alinhado ao plano Cursor em `c:\Users\jorge\.cursor\plans\padrões_alocações_cartório_ad82215b.plan.md` — ao alterar decisões ou to-dos, atualizar **ambos**.

**Status:** rascunho aprovado para execução — decisões da rodada 1 + **10 perguntas (rodada 2)** incorporadas.

## Decisões de produto (rodada 1)

| Tema | Decisão |
|------|---------|
| **Legenda dos 3 tipos** | Apenas na **documentação interna** (`docs/...`). **Sem** legenda fixa embutida nas listas ou edições. |
| **Sala de Audiências (pauta)** | **Fora do escopo** deste plano (padronização só **cartório**). |
| **Texto do tipo `all`** | **`Responsável pelo cadastro:`** + nome (substitui “Servidor alocado:” no padrão unificado). |
| **Hierarquia de cores** | `rule` = **warning**; `all` = **neutra**; `specific` = **primária** (ajustar [table.css](public/assets/css/shared/table.css) — hoje `all` está primária e `specific` neutra). |

## Decisões complementares (10 perguntas — rodada 2)

| # | Tema | Decisão |
|---|------|---------|
| 1 | **Tipo inválido** (valor fora de `rule`/`all`/`specific`) | **Não renderizar** o chip; registrar com **`error_log`** (ou log interno equivalente) para investigação. |
| 2 | **Texto `rule`** | **`Alocação por regra do cartório:`** + nome |
| 3 | **Texto `specific`** | Manter **`Alocação direta:`** + nome |
| 4 | **Limite de 5 alocados** | **Manter** comportamento atual; **apenas documentar** no spec (sem centralizar const neste plano, salvo se a refatoração exigir). |
| 5 | **Comprimento do `title`** | **Médio:** nome + **frase curta** com o significado do tipo (detalhar frases no spec). |
| 6 | **Acessibilidade** | Incluir **`aria-label`** com o **mesmo texto completo** usado no `title` (além do `title` para hover). |
| 7 | **Release notes** | **Sim** — entrada em [docs/90-releases/notes/](docs/90-releases/notes/) (arquivo de release alinhado à versão do projeto). |
| 8 | **Editais** (só `rule` + `specific`) | **Só documentar** no spec; **sem** assert extra no código neste plano. |
| 9 | **Ordem dos chips** na linha | **Fixar:** `all` primeiro → `rule` → `specific` → demais / desconhecidos por último (implementar ordenação ao montar o array na view ou função `CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo`). |
| 10 | **Template em `public/templates/`** | **Não** criar página/catálogo extra; doc só em markdown. |

## Melhorias incorporadas (sugestões 1, 3, 4, 5)

| # | Sugestão | O que entra no plano |
|---|----------|----------------------|
| **1** | **Constantes de tipo em PHP** | Introduzir um único sítio para os literais `rule` / `all` / `specific` (ex.: classe `CartorioAlocacaoTipo` com constantes `RULE`, `ALL`, `SPECIFIC` ou `enum` em PHP 8.1+), consumida pelo `CartorioAlocacaoChipHelper` e referenciada onde fizer sentido para evitar typos e drift com o DB. |
| **3** | **Teste mínimo automatizado** | Adicionar **testes unitários** do helper (títulos por tipo, ordenação, tipo inválido → null, eventual sufixo da “frase média”), para segurar refactors. Local: pasta de testes já usada no projeto (ex. `tests/` ou equivalente). |
| **4** | **Template explícito para `title` “médio”** | No spec, definir **um modelo por tipo**: prefixo acordado + nome + separador (ex. ` — `) + **micro-frase** fixa do significado (sem parágrafos). O helper implementa esse modelo; `aria-label` replica o texto final completo. |
| **5** | **Create JS alinhado ao DOM das listas** | Em `cartorio-alocacao-direcionada-create.js`, montar o preview com a **mesma estrutura** das listas: `user-allocate-wrap` → `span.user-allocate.specific` (iniciais ou rótulo curto) + botão remover, preservando layout e estilos de chip. |

## Ajustes finos (5 perguntas — rodada final)

| # | Tema | Decisão |
|---|------|---------|
| 1 | **Separador nome ↔ micro-frase** no `title`/`aria-label` | Espaço + **hífen simples**: ` - ` (não usar « — »). |
| 2 | **Desempate** dentro do mesmo `tipo` | Ordenar por **iniciais A→Z** (após ordenar por tipo: all → rule → specific). |
| 3 | **Constantes `CartorioAlocacaoTipo` nos repositórios** | **Neste epic:** alinhar **todos** os `*AlocadosRepository` do cartório a usar as constantes onde aplicável. |
| 4 | **Testes do helper** | **Script PHP** em `scripts/` (asserts sem framework PHPUnit), em vez de suite PHPUnit nova neste epic. |
| 5 | **Log de tipo inválido** | **Uma linha JSON** com `tipo`, `entity_id`, `usuario_id`, `origem` (identificador curto de módulo/tela) para grep. |

## Contexto técnico (baseline)

- Tipos DB: `rule`, `all`, `specific` nos repositórios `*_alocados`. **Editais** só `rule` + `specific` (sem `all`).
- Textos duplicados em listas + [\_cartorio_alocacao_direcionada_edit.php](app/views/internal/_cartorio_alocacao_direcionada_edit.php) + [cartorio-oficios-edit.php](app/views/internal/cartorio-oficios-edit.php).
- Permissões: [AlocacaoTarefasPerfisHelper.php](app/helpers/AlocacaoTarefasPerfisHelper.php).

## Entregas

1. **Documentação** — [docs/20-modules/specs/cartorio-alocacoes-padrao.md](docs/20-modules/specs/cartorio-alocacoes-padrao.md): glossário, **templates “médios”** (prefixo + nome + separador ` - ` + micro-frase) por tipo, tabela cores ↔ tipo, exceção editais, ordem de chips (all→rule→specific; desempate **iniciais**), log JSON se inválido.
2. **Constantes + helper + repositórios** — `CartorioAlocacaoTipo` + `CartorioAlocacaoChipHelper` + **`*AlocadosRepository` cartório** com constantes: `tituloChip`, `ordenarAlocadosPorTipo`, null + **log JSON** (`tipo`, `entity_id`, `usuario_id`, `origem`).
3. **Refatorar views** — Listas cartório + partial + oficios-edit: helper; filtrar nulos; ordenar; **`title` + `aria-label` iguais**.
4. **CSS** — Trocar estilos `all` ↔ `specific`; opcional `--color-alloc-*`.
5. **Create JS** — Preview com **`user-allocate-wrap`** + **`user-allocate specific`** (paridade com listas); `title`/`aria-label` alinhados ao modelo do spec.
6. **Regra Cursor** — `.cursor/rules/cartorio-alocacoes-chips.mdc`.
7. **Release** — Nota em `docs/90-releases/notes/` sobre mudança de copy (`all` e textos unificados).
8. **Testes** — Script `scripts/` com asserts do helper (sem PHPUnit neste epic).
9. **Repositórios** — `*AlocadosRepository` cartório usando `CartorioAlocacaoTipo::*`.
10. **QA** — 7 módulos; cenário com tipo inválido (log JSON sem chip).

## Escopo explícito

- **Inclui:** Guias, CEAPA, Cejusc, cartas expedidas/recebidas, medidas protetivas, editais, ofícios.
- **Não inclui:** Sala de Audiências, legenda visual nas telas, template em `public/templates/`.

## Ordem de execução sugerida

1. Doc de glossário + **templates de title** (separador ` - `, micro-frases) + ordem + inválidos (log JSON).
2. `CartorioAlocacaoTipo` + `CartorioAlocacaoChipHelper` + refatorar **`*AlocadosRepository` cartório** + views (listas, partial, oficios-edit).
3. Script `scripts/` (asserts do helper).
4. CSS (`all`/`specific`, tokens opcionais).
5. JS create (`user-allocate-wrap` + `user-allocate specific`).
6. Regra Cursor.
7. Release note.
8. QA manual.

## To-dos

- [ ] Redigir `docs/20-modules/specs/cartorio-alocacoes-padrao.md` (incl. templates title médio por tipo)
- [ ] Criar `CartorioAlocacaoTipo` + `CartorioAlocacaoChipHelper`; refatorar `*AlocadosRepository` cartório + listas + partial + oficios-edit
- [ ] Script em `scripts/` com asserts do `CartorioAlocacaoChipHelper`
- [ ] Atualizar `table.css` (+ tokens opcionais)
- [ ] Ajustar `cartorio-alocacao-direcionada-create.js` (`user-allocate-wrap` + `user-allocate specific`)
- [ ] Adicionar `.cursor/rules/cartorio-alocacoes-chips.mdc`
- [ ] Adicionar nota em `docs/90-releases/notes/`
- [ ] Revisão nos 7 módulos + testes manuais
