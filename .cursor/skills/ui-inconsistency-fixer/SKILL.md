---
name: ui-inconsistency-fixer
description: Detecta e corrige inconsistencias globais em telas create/edit com base no contrato do projeto. Use antes de finalizar tarefas para aplicar correcoes padrao em placeholders, toasts, atributos e estados de botoes.
---

# UI Inconsistency Fixer

## Objetivo

Caçar inconsistencias de implementacao em `*-create.php` e `*-edit.php` e aplicar correcoes antes da entrega.

## Workflow

1. Ler contrato:
   - `docs/create-edit-ui-contract.md`
   - `.cursor/rules/create-edit-ui-contract.mdc`
2. Executar auditoria rapida com `rg`:
   - placeholders fora do padrao
   - feedback inline (`cadastro-feedback`, `alert-heading`, status inline)
   - divergencia create/edit para mesmo tipo de campo
3. Aplicar patches de correcao:
   - placeholders padrao por tipo
   - remocao de fallback inline de feedback
   - ajuste de estado de botoes e atributos principais quando houver divergencia clara
4. Reexecutar auditoria e somente finalizar com status `OK`.

## Regras de correcao

- Nao criar classes CSS novas.
- Nao alterar contrato funcional do modulo sem necessidade.
- Priorizar correcoes seguras e repetiveis.

## Saida esperada

- `Correcoes aplicadas` (arquivos + tipo de ajuste).
- `Pendencias` (se algo exigir decisao humana).
