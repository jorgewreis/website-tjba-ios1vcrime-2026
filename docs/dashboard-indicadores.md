# Dashboard interno: regras de contagem

## Escopo atual

Os cards visíveis nesta etapa cobrem Cartas Expedidas e Cartas Recebidas. A primeira coluna reúne em um único card de até 160 px os totais ativos de cada tipo, todos os cadastros recentes e o gráfico diário dos últimos sete dias. A segunda mostra Cartas Expedidas aguardando envio e Cartas Recebidas aguardando devolução no setor autorizado. A terceira conta movimentações do usuário autenticado por página nos últimos 30 dias, com mini séries diárias. A quarta apresenta cards compactos ordenados automaticamente por prioridade: prazos próximos, seguidos das filas com maior espera. Cards sem cartas correspondentes ou sem dados disponíveis são omitidos.

Os totais grandes de cartas ativas usam o mesmo recorte padrão das listagens: excluem concluídas e canceladas. O gráfico diário e os quantitativos de cadastros recentes incluem todos os registros criados nos últimos sete dias, independentemente do status atual. As filas contam somente etapas executáveis. O serviço não apresenta quantidade de processos distintos.

## Filas

Uma fila conta subtarefas com `status = em_andamento`, associadas a um registro principal que não esteja concluído, cancelado ou finalizado. Subtarefas futuras em `pendente`, etapas concluídas e canceladas ficam fora da contagem. A fila é separada pelo tipo da etapa:

| Módulo | Etapas incluídas |
| --- | --- |
| Cartas Expedidas | envio; cumprimento/devolução |
| Cartas Recebidas | cumprimento; devolução |
| Guias | expedição de guia |
| Editais | publicação; decurso de prazo |
| Ofícios do Cartório | envio de ofício |

A fila mais antiga da quarta coluna considera exclusivamente Cartas Expedidas aguardando envio e Cartas Recebidas aguardando devolução. A idade usa o `updated_at` da etapa em andamento, atualizado quando ela passa a ser executável. Cumprimento de cartas, Guias, Editais e Ofícios não concorrem para esse alerta. O indicador informa o tempo de espera e não declara vencimento.

## Acesso e recortes

A API exige sessão autenticada e perfil interno. Externos são rejeitados. A coluna do setor usa automaticamente as áreas dos perfis operacionais efetivos da sessão. O card de fila das cartas aparece apenas para quem tem Cartório autorizado, incluindo perfis com múltiplos setores. Usuários com somente Gabinete ou Sala de Audiências não veem esse card nem recebem filas de cartas em `setor.filas`. Não há seletor de setor ou cabeçalho de áreas autorizadas no card. Os indicadores gerais da Vara continuam na primeira coluna.

## Histórico de produtividade

`dashboard_conclusoes` recebe eventos após a conclusão bem-sucedida por uma das APIs operacionais. O usuário vem da sessão; a referência é apenas o identificador interno da etapa. Número CNJ, partes e conteúdo não são copiados. Repetições após uma etapa já concluída não criam evento. Os cards individuais desta etapa filtram Cartas Expedidas e Cartas Recebidas, contam os últimos 30 dias e exibem a série dos últimos sete. O histórico começa na implantação e não reconstrói conclusões antigas.

## Limites dos indicadores

- Cadastros da semana usam `created_at` dos registros principais.
- O card combinado compara expedidas e recebidas com barras diárias e mantém os totais ativos e os quantitativos de cadastros recentes por tipo. A área do canvas tem dimensões definidas dentro do limite de 160 px do card.
- Prazos próximos contam Cartas Expedidas em cumprimento a partir da conclusão registrada do envio, usando a mesma origem de data da listagem. Cartas Recebidas em cumprimento usam a data de recebimento preenchida; a criação não substitui uma data ausente. Soma-se o prazo em dias registrado. A janela vai de hoje até os próximos sete dias; o card é omitido quando a contagem é zero.
- A API fornece `alertas` já ordenados: cartas com prazo próximo precedem as filas sem vencimento definido; estas usam a data de entrada na fila, da mais antiga para a mais recente. A tela respeita essa ordem, sem seções ou textos de prioridade adicionais.
- A agenda de audiências e os dias não úteis permanecem disponíveis na API, mas não aparecem nos cards desta etapa.
- Dados são consultados ao carregar ou recarregar a página. Não há atualização automática em segundo plano.
