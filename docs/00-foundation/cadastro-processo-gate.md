# Governança do Gate de Cadastro por Processo

## Objetivo

Uniformizar o comportamento do botão de cadastro em telas que possuem `numero_processo` com automação CNJ + DataJud.

## Contrato obrigatório

1. Estado inicial: botão `disabled` com label `Aguarde...`.
2. CNJ inválido: label `Erro` e botão permanece `disabled`.
3. CNJ válido: disparar DataJud.
4. DataJud finalizado (sucesso ou falha): label `Cadastrar` e botão habilitado.
5. Submit da API: botão volta para `Aguarde...` até resposta.
6. Falha de API: botão volta para `Cadastrar` habilitado.

## Eventos de integração

- `cnjValidated`: disparado somente após **20 dígitos com DV válido** (no `input` ao completar o número ou no `blur`); inicia busca DataJud.
- CNJ inválido (DV incorreto): campo **limpo**, evento `cnjInvalid`, **sem** chamada DataJud.
- `cnjInvalid`: usado para forçar estado `Erro`.
- `datajudClasseResolved`: usado para liberar estado `Cadastrar`.

## Segurança de backend

Todo endpoint de criação com `numero_processo` deve validar:

- formato CNJ;
- dígito verificador CNJ.

Regex sem validação de dígito não atende o contrato.

## QA mínimo

- Processo inválido nunca libera cadastro.
- DataJud em andamento mantém botão em `Aguarde...`.
- Ao final de DataJud (mesmo com falha), botão fica `Cadastrar` habilitado.
- Backend recusa CNJ com DV inválido mesmo com bypass de frontend.
