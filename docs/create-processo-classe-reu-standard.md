# Padronização Create: Processo, Classe e Réu

**Documento canônico do fluxo DataJud + BD + sync:** [`docs/00-foundation/datajud-classe-processo-bd-sync.md`](00-foundation/datajud-classe-processo-bd-sync.md) (decisões, inventário, QA, APIs).

## Objetivo

Padronizar o comportamento das telas `app/views/internal/*-create.php` para garantir consistência de:
- validação CNJ do número do processo;
- obtenção automática da classe (DataJud + leitura prévia no BD + sync em `processos`/`classes` — ver `docs/00-foundation/datajud-classe-processo-bd-sync.md`; comparação de código: `ClassesRepository::normalizeCodigo` / `codigosEquivalentes`);
- persistência de processo/classe (no submit do módulo continua valendo; o sync antecipa vínculo quando o DataJud retorna classe);
- vínculo de réu/denunciado ao processo com fallback para cadastro manual.

## Regra de referência

- Regra dedicada criada em: `.cursor/rules/processo-classe-reu-automation.mdc`.

## Contrato único do botão de cadastro

- Estado inicial obrigatório: `disabled=true` com label `Aguarde...` antes da validação do CNJ.
- CNJ inválido: manter `disabled=true` e trocar label para `Erro`.
- CNJ válido: executar consulta DataJud.
- DataJud finalizado (com sucesso ou falha): habilitar botão com label `Cadastrar`.
- Ao enviar cadastro: voltar para `Aguarde...` com `disabled=true` até a resposta da API.
- Backend deve revalidar CNJ com dígito verificador mesmo após validação frontend.

## Inventário: trio DataJud completo (`cnj.js` + `datajud-cycle-helper.js` + `datajud-api.js`)

### Create e fluxos equivalentes

- `app/views/internal/sala-audiencias-pauta-create.php`
- `app/views/internal/cartorio-editais-create.php`
- `app/views/internal/cartorio-guias-create.php`
- `app/views/internal/cartorio-cartas-recebidas-create.php`
- `app/views/internal/cartorio-cartas-expedidas-create.php`
- `app/views/internal/gabinete-monitoramento-eletronico-create.php`

### Listagens / modais com filtro processo + classe

- `app/views/internal/cartorio-oficios-list.php`
- `app/views/internal/gabinete-oficios-list.php`

### Edição com o mesmo trio (exceções de negócio podem usar `DATAJUD_SKIP_CLASSE_SYNC`)

- `app/views/internal/gabinete-reus-presos-edit.php`

### Somente CNJ (`cnj.js` sem consulta automática de classe DataJud nesta tela)

- `app/views/internal/consulta-processos-list.php`

### Telas sem input de processo (regra processo/classe não se aplica)

- `app/views/internal/consulta-partes-create.php`
- `app/views/internal/feriados-create.php`
- `app/views/internal/gabinete-reus-presos-create.php`
- `app/views/internal/instituicoes-publicas-create.php`
- `app/views/internal/partes-instituicoes-create.php`
- `app/views/internal/usuarios-create.php`

### Tela com processo por contexto (sem input livre)

- `app/views/internal/processos-partes-create.php` (usa `processo_id` por contexto da rota).

## Ajuste aplicado nesta rodada

- `app/views/internal/gabinete-monitoramento-eletronico-create.php`
  - Busca de réus vinculados ao processo segue fluxo com `select` + opção `Outro`.
  - Nome livre do réu usa `TextFormatter`.
  - Busca automática de pessoas por nome (`/api/pessoas/search-by-name.php`) para sugestão de cadastro já existente.
  - Seleção de pessoa sugerida passa a aceitar vínculo ao processo no cadastro.

- `app/services/GabineteMonitoramentoEletronicoService.php`
  - Aceita `reu_id` no payload de criação.
  - Vincula automaticamente a parte ao processo (quando necessário) em `processos_partes`.
  - Reaproveita vínculo existente quando já houver relação processo-parte.

## Observação de escopo

Esta padronização aplica regras condicionais:
- presença de processo => fluxo processo/classe obrigatório;
- presença de réu/denunciado => fluxo de vínculo/sugestão/cadastro de parte obrigatório.

Telas sem esses campos não foram alteradas por não se enquadrarem no contrato funcional.
