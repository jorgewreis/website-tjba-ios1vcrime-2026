# Especificação: Submódulo CEAPA (Cartório)

## Visão geral

Submódulo do Cartório para acompanhar cumprimento de decisão no fluxo CEAPA.

## Entidade principal

- **Tabela:** `ceapa_tarefas`
- **Identificador:** `id` (prefixo `CPA`, 30 caracteres)
- **Campos principais:** `processo_origem_id`, `numero_processo_origem`, `parte_id`, `tipo_suspensao`, `decisao_cumprir`, `data_decisao`, `observacoes_cumprimento`, `status_cumprimento`, `created_at`, `updated_at`.

## Status de cumprimento

- `pendente`
- `em_andamento`
- `finalizada`

## Cadastro

- Campos obrigatórios:
  - Número do processo
  - Classe processual (automatizada por DataJud quando disponível)
  - Nome do réu (com vínculo ao processo quando possível)
  - Tipo de suspensão
  - Decisão a cumprir
  - Data da decisão
- Status inicial padrão: `pendente`.

## Edição

- Número do processo e classe processual:
  - bloqueados quando já houver valor cadastrado;
  - permitidos apenas se originalmente sem valor.
- Campo de observações de cumprimento no edit.
- Alteração de status no edit: `pendente`, `em_andamento`, `finalizada`.

## Referências

- Views:
  - `app/views/internal/cartorio-ceapa-list.php`
  - `app/views/internal/cartorio-ceapa-create.php`
  - `app/views/internal/cartorio-ceapa-edit.php`
- APIs:
  - `public/api/cartorio-ceapa/list.php`
  - `public/api/cartorio-ceapa/create.php`
  - `public/api/cartorio-ceapa/update.php`
