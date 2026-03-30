# Módulo DataJud (front-end)

Scripts **transversais** usados em gabinete, cartório, audiências e outras áreas: formatação/validação CNJ, ciclo de UI da consulta ao DataJud (lock de botões, toasts, abort) e preenchimento da classe processual após `cnjValidated`.

## Arquivos

| Arquivo | Função |
|---------|--------|
| `cnj.js` | Máscara, validação e evento `cnjValidated` após número válido (20 dígitos). |
| `datajud-cycle-helper.js` | `window.DataJudCycle` — snapshot de botões, `aria-busy`, timer 500 ms, `AbortController` compartilhado. |
| `datajud-api.js` | Escuta `cnjValidated`, chama `POST` em `/api/datajud/search-processo.php` (ou `window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT` se definido). |

## Ordem de inclusão nas views

1. `cnj.js`
2. `datajud-cycle-helper.js`
3. `datajud-api.js`

Scripts específicos da página vêm **depois**.

## Contrato de IDs

Resolvido em cadeia em `datajud-api.js` (`getDom`): classe `audiencia-classe` → `gab-mon-classe` → `filter-classe`; processo `audiencia-processo` → `gab-mon-processo` → `filter-processo`; opcionais `hidden-classe-codigo`, `hidden-classe-descricao`, status `audiencia-classe-status` ou `processo-ap-info`.

Marcação HTML recomendada: `data-datajud-related="true"` no input de classe; `data-allow-during-loading="true"` em botões de escape (Voltar/Cancelar) quando aplicável.

## Observação

O nome legado `window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT` permanece suportado para sobrescrever a URL da API sem alterar este módulo.
