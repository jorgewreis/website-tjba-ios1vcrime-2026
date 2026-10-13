# Dashboard interno: regras de contagem

## Escopo atual

Os cards visíveis nesta etapa cobrem Cartas Expedidas e Cartas Recebidas. A primeira coluna reúne em um único card de até 160 px os totais ativos de cada tipo, os cadastros recentes ainda ativos e o gráfico diário dos últimos sete dias. A segunda mostra Cartas Expedidas aguardando envio e Cartas Recebidas aguardando devolução no setor autorizado. A terceira conta movimentações do usuário autenticado por página nos últimos 30 dias, com mini séries diárias. A quarta destaca a etapa ativa mais antiga, prazos previstos para os próximos sete dias e Cartas Recebidas aguardando devolução.

Os cartões de cartas ativas contam os registros principais com o mesmo recorte padrão das listagens: excluem concluídas e canceladas. A série semanal usa o mesmo filtro e agrupa somente as cartas ainda ativas por data de cadastro, nos últimos sete dias. As filas contam somente etapas executáveis. O serviço não apresenta quantidade de processos distintos.

## Filas

Uma fila conta subtarefas com `status = em_andamento`, associadas a um registro principal que não esteja concluído, cancelado ou finalizado. Subtarefas futuras em `pendente`, etapas concluídas e canceladas ficam fora da contagem. A fila é separada pelo tipo da etapa:

| Módulo | Etapas incluídas |
| --- | --- |
| Cartas Expedidas | envio; cumprimento/devolução |
| Cartas Recebidas | cumprimento; devolução |
| Guias | expedição de guia |
| Editais | publicação; decurso de prazo |
| Ofícios do Cartório | envio de ofício |

A idade da fila usa o `updated_at` da etapa que está em andamento, que é atualizado quando a etapa passa a ser executável. Esse indicador informa há quanto tempo a etapa está na fila; não declara vencimento.

## Acesso e recortes

A API exige sessão autenticada e perfil interno. Externos são rejeitados. A coluna do setor usa automaticamente as áreas dos perfis operacionais efetivos da sessão. O card de fila das cartas aparece apenas para quem tem Cartório autorizado, incluindo perfis com múltiplos setores. Usuários com somente Gabinete ou Sala de Audiências não veem esse card nem recebem filas de cartas em `setor.filas`. Não há seletor de setor ou cabeçalho de áreas autorizadas no card. Os indicadores gerais da Vara continuam na primeira coluna.

## Histórico de produtividade

`dashboard_conclusoes` recebe eventos após a conclusão bem-sucedida por uma das APIs operacionais. O usuário vem da sessão; a referência é apenas o identificador interno da etapa. Número CNJ, partes e conteúdo não são copiados. Repetições após uma etapa já concluída não criam evento. Os cards individuais desta etapa filtram Cartas Expedidas e Cartas Recebidas, contam os últimos 30 dias e exibem a série dos últimos sete. O histórico começa na implantação e não reconstrói conclusões antigas.

## Limites dos indicadores

- Cadastros da semana usam `created_at` dos registros principais.
- O card combinado compara expedidas e recebidas com barras diárias e mantém os totais ativos e os quantitativos de cadastros recentes por tipo. A área do canvas tem dimensões definidas dentro do limite de 160 px do card.
- Prazos próximos contam Cartas Expedidas na etapa de cumprimento a partir da ativação dessa etapa e Cartas Recebidas em cumprimento a partir da data de recebimento (ou criação, quando não informada), somando o prazo em dias registrado no documento. A janela mostrada é de hoje até os próximos sete dias; o card não rotula itens como vencidos.
- A agenda de audiências e os dias não úteis permanecem disponíveis na API, mas não aparecem nos cards desta etapa.
- Dados são consultados ao carregar ou recarregar a página. Não há atualização automática em segundo plano.
