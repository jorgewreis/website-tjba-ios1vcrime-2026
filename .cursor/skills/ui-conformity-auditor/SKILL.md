---
name: ui-conformity-auditor
description: Audita conformidade global de telas create/edit (placeholders, funcoes, layout, classes, ids, atributos, validacoes, toasts, botoes, labels e tamanhos). Use quando finalizar alteracoes em formularios `*-create.php` e `*-edit.php`.
---

# UI Conformity Auditor

## Objetivo

Validar que alteracoes em `app/views/**/*-create.php` e `app/views/**/*-edit.php` seguem o contrato do projeto.

## Passos

1. Ler `docs/create-edit-ui-contract.md` e `.cursor/rules/create-edit-ui-contract.mdc`.
2. Executar varredura de placeholders e feedback inline:
   - `rg 'placeholder="' app/views/internal --glob '*-create.php'`
   - `rg 'placeholder="' app/views/internal --glob '*-edit.php'`
   - `rg 'cadastro-feedback|alert-heading|innerHTML = `<label class="status' app/views/internal --glob '*-{create,edit}.php'`
3. Verificar campos criticos:
   - CPF -> `000.000.000-00`
   - CNJ -> `0000000-00.0000.0.00.0000`
   - Telefone -> `(00) 00000-0000`
   - Nome completo -> `Digite o nome completo`
4. Confirmar consistencia create/edit para campos equivalentes.
5. Entregar saida em duas secoes:
   - `Divergencias encontradas`
   - `Status final` (`OK` se sem divergencias)

## Saida esperada

- Lista objetiva de arquivos divergentes com sugestao de ajuste.
- Se tudo estiver conforme: `OK - contrato create/edit aderente`.
