# Relatorio de Padronizacao das Paginas `*-edit.php`

> **Nota (2026):** este relatorio descreve o trabalho de layout e uso de `showToast` na epoca. A politica atual de **simple vs full** e de duracao esta centralizada em `public/assets/js/shared/toast.js` e documentada em `docs/00-foundation/sistema-toast.md`. Chamadas antigas com `variant: 'full'` em todo lugar foram supersedidas por essa politica.

## Escopo

Padronizacao estrutural, visual e comportamental das paginas `*-edit.php` para alinhamento com o modelo consolidado em `*-create.php` e `*-list.php`.

## Contrato aplicado

- Estrutura base com `container-full card-page`.
- Blocos internos com `display-flex row row-full ...`.
- Eliminacao de markup legado `row table`.
- Padrao de toasts com `showToast({ type, title, message })` (variant opcional; ver `docs/00-foundation/sistema-toast.md`).
- Remocao de `alert()` e substituicao por toasts.
- Confirmacao de acoes destrutivas conforme regra vigente: duplo clique apenas para exclusao definitiva de tarefa em telas `*-edit` de Cartorio, Gabinete e Audiencias; demais acoes em clique unico.
- Feedback de sucesso/erro em operacoes principais (salvar, cancelar, excluir, adicionar/remover vinculos).
- Barra de acoes padronizada em `card-buttons` com dois grupos: esquerda (`Voltar`, `Cancelar`, `Excluir`) e direita (`Alterar regra de alocacao`, `Cadastrar`, `Salvar`), com exibicao condicional por contexto (`create/edit`), status e permissao.

## Arquivos atualizados (antes/depois)

### Cartorio

- `app/views/internal/cartorio-medidas-protetivas-edit.php`
  - **Antes:** secoes em `row table`, cancelamento com reload sem feedback explicito.
  - **Depois:** secoes em `display-flex`, barra de acoes padronizada, toast informativo em cancelamento, toasts padronizados.

- `app/views/internal/cartorio-guias-edit.php`
  - **Antes:** mistura de blocos legados e toasts com variacao de `variant`.
  - **Depois:** estrutura de secoes padronizada (`display-flex`) e toasts uniformizados (`title` + mensagem; na epoca usava-se `variant: 'full'` — hoje a politica esta em `toast.js`).

- `app/views/internal/cartorio-editais-edit.php`
  - **Antes:** estrutura hibrida `row table` e cancelamento sem feedback claro.
  - **Depois:** layout convergente com `create/list`, toast em cancelamento e padrao de feedback consistente.

  - **Antes:** secoes em `row table` e exclusao com confirmacao nativa.
  - **Depois:** secoes em `display-flex`, feedback de cancelamento/exclusao padronizado e confirmacao de exclusao alinhada ao contrato vigente.

- `app/views/internal/cartorio-cartas-expedidas-edit.php`
  - **Antes:** blocos principais legados, toasts informativos em `simple`.
  - **Depois:** blocos principais em `display-flex`, toasts em `full`, padrao de mensagens em cancelar/excluir.

- `app/views/internal/cartorio-cartas-recebidas-edit.php`
  - **Antes:** blocos legados, resquicio comentado com `row table`, toasts informativos em `simple`.
  - **Depois:** estrutura principal padronizada, remocao de resquicio legado em comentario, toasts `full` e feedback uniforme.

### Consultas e Vinculos

- `app/views/internal/consulta-partes-edit.php`
  - **Antes:** secoes e listas auxiliares em `row table`; operacoes com reload sem toast de sucesso em todos os fluxos.
  - **Depois:** secoes/listas em `display-flex`, toasts de sucesso em adicionar/remover telefone e endereco, desvinculo de instituicao com feedback padronizado.

- `app/views/internal/consulta-processos-edit.php`
  - **Antes:** estrutura hibrida, desvinculo com reload sem toast de sucesso.
  - **Depois:** estrutura principal padronizada e toast de sucesso no desvinculo antes do reload.

- `app/views/internal/processos-partes-edit.php`
  - **Antes:** bloco em `row table`, toasts sem `variant/title`.
  - **Depois:** bloco em `display-flex` e toasts no padrao completo.

- `app/views/internal/partes-instituicoes-edit.php`
  - **Antes:** estrutura legada e toasts incompletos.
  - **Depois:** estrutura em `display-flex` e toasts padronizados para validacao/salvamento.

### Admin / Cadastro interno

- `app/views/internal/usuarios-edit.php`
  - **Antes:** layout predominantemente legado `row table`.
  - **Depois:** secoes migradas para `display-flex row row-full`, mantendo regras de negocio e validacoes existentes.

- `app/views/internal/instituicoes-publicas-edit.php`
  - **Antes:** secoes de dados/enderecos/telefones/setores em `row table`; toasts mistos.
  - **Depois:** secoes padronizadas em `display-flex`, toasts uniformes e feedback de sucesso em todas as operacoes de add/remove.

- `app/views/internal/feriados-edit.php`
  - **Antes:** ja parcialmente padronizada; toasts sem `variant/title`.
  - **Depois:** toasts integralmente no contrato (`full` + `title`) e manutencao do layout alinhado.

## Seguranca e comportamento

- Padronizacao de acoes destrutivas no front:
  - substituicao de `confirm()` por contrato de confirmacao do projeto (duplo clique somente para exclusao definitiva de tarefa em `*-edit`; demais fluxos em clique unico).
- Correcao de risco de confirmacao cruzada:
  - chave de confirmacao passou a ser por item (`id`) em listas com multiplos botoes de exclusao/desvinculo.
- Endurecimento backend em endpoints mutaveis:
  - validacao de origem e metodo `POST` via helper central.

## Validacao realizada

- Varredura por `alert(` em `*-edit.php`: sem ocorrencias.
- Varredura por `row table` em `*-edit.php`: sem ocorrencias.
- Varredura por toasts fora do padrao (sem `variant/title`): corrigido.
- Linter: sem erros nos arquivos alterados.

## Resultado final

As paginas `*-edit.php` cobertas no inventario ficaram alinhadas ao modelo estrutural e visual de `*-create.php` e `*-list.php`, com padrao unificado de feedback por toast e fluxo de operacoes principais consistente.
