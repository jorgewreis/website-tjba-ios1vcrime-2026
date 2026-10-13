# Dashboard interno: regras de contagem

## Escopo atual

O painel agrega as etapas executáveis de Cartas Expedidas, Cartas Recebidas, Guias, Editais e Ofícios do Cartório. A unidade é **subtarefa/documento**. O serviço não apresenta quantidade de processos porque a relação entre documentos e processos não é comum e suficientemente confiável em todos esses módulos.

## Filas

Uma fila conta subtarefas com `status = em_andamento`, associadas a um registro principal que não esteja concluído, cancelado ou finalizado. Subtarefas futuras em `pendente`, etapas concluídas e canceladas ficam fora da contagem. A fila é separada pelo tipo da etapa:

| Módulo | Etapas incluídas |
| --- | --- |
| Cartas Expedidas | envio; cumprimento/devolução |
| Cartas Recebidas | cumprimento; devolução |
| Guias | expedição de guia |
| Editais | publicação; decurso de prazo |
| Ofícios do Cartório | envio de ofício |

A idade mostrada é a data de criação da subtarefa executável mais antiga. Ela não indica vencimento.

## Acesso e recortes

A API exige sessão autenticada e perfil interno. Externos são rejeitados. O filtro de setor aceita somente áreas presentes nos perfis operacionais efetivos da sessão; a API responde 403 para qualquer outro valor. O recorte atual desses cinco módulos representa Cartório. Perfis sem Cartório autorizado veem os indicadores gerais agregados, mas não um total setorial inventado; a coluna informa que não há fluxo desses módulos ligado àquela área.

## Histórico de produtividade

`dashboard_conclusoes` recebe eventos após a conclusão bem-sucedida por uma das cinco APIs operacionais. O usuário vem da sessão; a referência é apenas o identificador interno da subtarefa. Número CNJ, partes e conteúdo não são copiados. Repetições após uma etapa já concluída não criam evento. O histórico começa na implantação e não tenta reconstruir conclusões antigas.

## Limites dos indicadores

- Cadastros mensais usam `created_at` dos registros principais, quando a tabela disponibiliza esse campo.
- Audiências contam registros ativos nos próximos sete dias, exceto cancelados.
- Dias não úteis incluem sábados, domingos e dias abrangidos por feriados/recessos ativos nos próximos 30 dias.
- Não há alerta de vencimento nesta versão: só a idade da fila é exibida, pois as regras de prazo não são uniformes para todas as filas.
- Dados são consultados ao carregar ou recarregar a página. Não há atualização automática em segundo plano.
