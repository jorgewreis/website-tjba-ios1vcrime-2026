# Especificação: Submódulo Guias (Cartório)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **guias** de execução e recolhimento. Fluxo: **Cadastro** + **8 subtarefas** — Detalhamento de informações → Cálculo da pena de multa → Expedição da Guia → Assinatura da Juíza → Juntada automática → Geração de PDFs → Envio da Guia → Juntada de termo de envio. Implementação alinhada aos padrões de Cartas Expedidas, Cartas Recebidas e Cejusc.

## 2. Entidade principal: Guia

- **Tabela:** `guias`
- **Identificador:** `id` (prefixo GUI, 30 caracteres)
- **Campos principais:** numero_processo, processo_id (opcional), denunciado, reu_id (opcional), tipo, etapa, status, regime, data_fato, dias_multa, observacoes, created_at, updated_at, user_cadastro.

## 3. Status da tarefa principal

- `pendente`
- `em_andamento`
- `concluída`
- `cancelada`

## 4. Subtarefas (ordem fixa — 8 etapas)

| Ordem | Tipo interno | Título da tarefa | Título do botão | Complemento | Timeline (pendente) | Timeline (concluída) |
|-------|--------------|------------------|-----------------|-------------|--------------------|---------------------|
| 1 | `detalhamento_informacoes` | Detalhamento de informações | Informar data do fato e dias-multa | Input: Data do fato; Input: Qtd dias-multa | Aguardando detalhamento de informações | Data do fato e dias-multa; complemento: registrado por [usuário] em [data] |
| 2 | `calculo_pena_multa` | Cálculo da pena de multa | Informar cálculo da pena de multa | Input readonly: valor total (automático) | Aguardando cálculo da pena de multa | Pena de multa calculada no valor de [valor_multa] |
| 3 | `expedicao_guia` | Expedição da Guia | Informar expedição da Guia | Nenhum | Aguardando expedição da Guia | Guia de [tipo] expedida em [data_expedicao] |
| 4 | `aguardando_assinatura` | Assinatura da Juíza | Informar assinatura | Nenhum | Guia aguardando assinatura | Guia assinada pela juíza em [data] |
| 5 | `juntada_automatica` | Juntada automática | Confirmar juntada automática | Select: automática, manual | Aguardando confirmação de juntada automática | Texto conforme select (automática/manual) |
| 6 | `gerar_pdf` | Geração de PDFs | Informar geração em PDF | Input: Qtd docs (mín 4) | Aguardando geração de documentos em PDF | [qtd] documentos gerados em PDF |
| 7 | `envio_guia` | Envio da Guia | Informar envio da Guia | Select: tipo envio (E-mail, Malote Digital, PJe, eSAJ, ePROC); Destinatário (VEP, SEEU) | Aguardando envio da Guia | Guia enviada por [tipo_envio] para [destino] |
| 8 | `juntada_termo_envio` | Juntada de termo de envio | Informar juntada de termo de envio | Nenhum | Aguardando comprovante de envio da Guia | Juntada do termo de envio da Guia realizada |

**Regras:**

- **Ordem 1 (Detalhamento):** opcional; possui botão **Cancelar**. Ao concluir *com* data do fato e dias-multa: ordem 1 concluída e ordem 2 ativada. Ao **cancelar** ou concluir *sem* os dois dados: ordem 1 concluída ou cancelada, ordem 2 marcada com status **pulada**, ordem 3 ativada.
- **Ordem 2:** só é ativada como etapa atual se a ordem 1 tiver sido concluída com data_fato e dias_multa; caso contrário a ordem 2 fica com status **pulada** e não é exibida como pendente.
- Tipos em `guias_subtarefas.tipo`; ordem 1 a 8. Status de subtarefa: `pendente`, `em_andamento`, `concluida`, `cancelada`, `pulada`. Ao concluir a ordem 8, a guia passa a `concluída`. Todas as 8 subtarefas são criadas no cadastro da guia (ordem 1 em_andamento, 2–8 pendente).

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
- `concluir-subtarefa.php` (POST): conclui subtarefa por `id`/`subtarefa_id`; aceita `dados_especificos` (objeto com campos por tipo: data_fato, dias_multa, valor_multa, data_expedicao, tipo_juntada, qtd_docs, tipo_envio, destinatario, etc.).
- `cancelar-subtarefa.php` (POST): cancela subtarefa por `subtarefa_id`; **apenas ordem 1** (Detalhamento de informações). Marca ordem 1 como cancelada, ordem 2 como pulada e ativa ordem 3.
- `criar-primeira-subtarefa.php` (POST): cria/ativa subtarefas por `id` (guia); se a guia não tiver subtarefas, cria as 8 e ativa a ordem 1.

## 10. Banco de dados e migração de fluxo

- Script de migração de tabelas: `database/migrations/guias.sql` (tabelas `guias`, `guias_subtarefas`, `guias_alocados`).
- **Migração do fluxo para 8 subtarefas:** para guias já existentes com o fluxo antigo (7 subtarefas), executar uma única vez o script `scripts/migrate-guias-8-subtarefas.php`. Ele remove as subtarefas antigas e insere as 8 novas (ordem 1 em_andamento, 2–8 pendente), mantendo a guia em `em_andamento`.
- Regra `ALLOCATE-GUIAS`: criada pela aplicação na primeira vez que a página Regras for salva, ou inserida manualmente em `alocacoes_regras`.

## 11. Referências

- [docs/00-foundation/escopo-projeto.md](../../00-foundation/escopo-projeto.md) — Módulo Cartório, submódulo Guias.
- [docs/00-foundation/padrao-inputs-formularios.md](../../00-foundation/padrao-inputs-formularios.md) — Processo (CNJ) e nome (text-formatter).
- Implementação espelhada em Cartas Recebidas e Cejusc (views, repositórios, serviço, APIs, toasts).
