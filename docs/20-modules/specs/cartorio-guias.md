# Especificação: Submódulo Guias (Cartório)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **guias** de execução e recolhimento. Fluxo de subtarefas: Cálculo da pena de multa → Expedir Guia → Assinar Guia → Gerar PDF → Enviar documentos → Juntar termo de envio → Expedir certidão. Implementação alinhada aos padrões de Cartas Expedidas, Cartas Recebidas e Cejusc.

## 2. Entidade principal: Guia

- **Tabela:** `guias`
- **Identificador:** `id` (prefixo GUI, 30 caracteres)
- **Campos principais:** numero_processo, processo_id (opcional), denunciado, reu_id (opcional), tipo, etapa, status, regime, data_fato, dias_multa, observacoes, created_at, updated_at, user_cadastro.

## 3. Status da tarefa principal

- `pendente`
- `em_andamento`
- `concluída`
- `cancelada`

## 4. Subtarefas (ordem fixa)

1. Cálculo da pena de multa (`calcular_pena_multa`)
2. Expedir Guia (`expedir_guia`)
3. Assinar Guia (`assinar_guia`)
4. Gerar PDF da documentação (`gerar_pdf`)
5. Enviar documentos (`enviar_documentos`)
6. Juntar termo de envio (`juntar_termo_envio`)
7. Expedir certidão (`expedir_certidao`)

Tipos internos em `guias_subtarefas.tipo`; ordem 1 a 7. Ao concluir uma subtarefa, a próxima é ativada/criada; ao concluir a última, a guia passa a `concluída`.

## 5. Alocação

- Regra em `alocacoes_regras`: `ALLOCATE-GUIAS` (um usuario_id).
- Tipos de alocado: `rule` (regra automática), `specific` (movimentação/edição), `all` (quem cadastrou).
- Tabela `guias_alocados`: guia_id, usuario_id, tipo. Limite de 5 alocados por guia; evita duplicata (guia_id + usuario_id).

## 6. Permissões

- **Cancelar tarefa:** SERVIDOR, ADMIN, DIRETORA, MAGISTRADA. Botão oculto se status cancelada ou concluída.
- **Excluir tarefa:** ADMIN, DIRETORA, MAGISTRADA.
- **Alterar regra de alocação:** ADMIN, DIRETORA, MAGISTRADA (página Regras de Guias).

## 7. Cadastro

- **Campos obrigatórios:** Número do processo, Tipo de guia (default: "Guia - Tipo não informado").
- **Opcionais:** Denunciado, Etapa, Regime, Data do fato, Dias-multa, Observações.
- Formatação: número do processo (CNJ) e nome do denunciado conforme padrão de inputs (cnj.js, text-formatter.js); blur antes do submit.

## 8. Rotas e views

- **Listagem:** `frame.php?page=guias` → `cartorio-guias-list.php`
- **Cadastro:** `frame.php?page=guiascreate` → `cartorio-guias-create.php`
- **Edição:** `frame.php?page=guiasedit&id={id}` → `cartorio-guias-edit.php`
- **Regras:** `frame.php?page=guiasrules` → `cartorio-guias-rules.php`

## 9. API (public/api/cartorio-guias/)

- `list.php` (GET): lista guias com filtros (status, tipo, processo) e paginação.
- `create.php` (POST): cria guia; retorna `{ id }`.
- `update.php` (POST): atualiza guia por `id`.
- `delete.php` (POST): exclui guia por `id`.
- `cancel.php` (POST): cancela guia por `id`.
- `concluir-subtarefa.php` (POST): conclui subtarefa por `id` (subtarefa).
- `criar-primeira-subtarefa.php` (POST): cria/ativa primeira subtarefa por `id` (guia).

## 10. Banco de dados

- Script de migração: `database/migrations/guias.sql` (tabelas `guias`, `guias_subtarefas`, `guias_alocados`).
- Regra `ALLOCATE-GUIAS`: criada pela aplicação na primeira vez que a página Regras for salva, ou inserida manualmente em `alocacoes_regras`.

## 11. Referências

- [docs/00-foundation/escopo-projeto.md](../../00-foundation/escopo-projeto.md) — Módulo Cartório, submódulo Guias.
- [docs/00-foundation/padrao-inputs-formularios.md](../../00-foundation/padrao-inputs-formularios.md) — Processo (CNJ) e nome (text-formatter).
- Implementação espelhada em Cartas Recebidas e Cejusc (views, repositórios, serviço, APIs, toasts).
