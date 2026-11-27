# REI-351 — planejamento e auditoria inicial

Data: 27/11/2026
Base auditada: `main` em `283ae8a248a78124b07ed271fe958ac8053a49b8`
Execução: branch `feat/rei-351-dashboard2`, worktree isolada `D:\Projects\Clients\website-tjba-ios1vcrime-2026-rei351-dashboard2`.

## Escopo e critérios

- Criar página interna paralela `dashboard2`; manter `dashboard` como padrão e preservar shell, menus e barra superior.
- Ordem desktop aprovada: faixa 1 (Visão geral, Meu trabalho, Fluxo); faixa 2 (Lotação, Criticidade, Radar, Qualidade, Prontidão); faixa 3 (Agenda, Próxima ação, Evolução de produtividade, Alertas críticos). Empilhar sem perda ou overflow em celular.
- Reutilizar apenas valores com contrato de fonte demonstrável. Indicar indisponível para fonte ausente, falha de consulta, histórico incompleto ou regra não definida. Zero só quando a fonte e a consulta forem confiáveis.
- Proteger página e endpoint com política interna e flag `DASHBOARD2_ENABLED` desligada por padrão.
- Validar dados sintéticos em isolamento; não consultar dados judiciais reais, não migrar banco de produção, não publicar a imagem, não fazer merge/deploy nem remover a página anterior.
- Evidências finais necessárias: testes, conciliação com filtros/listas sob dados sintéticos, desktop e celular, e convivência de ambas as páginas na homologação isolada.

## Plano sequencial

| Etapa | Saída | Estado nesta revisão |
| --- | --- | --- |
| Planejamento | Escopo, ordem, segurança, critérios e branch isolada | Feito; este documento e worktree dedicada |
| Auditoria de fontes e métricas | Matriz por bloco e limites de cada fonte | Auditoria inicial feita; lacunas registradas abaixo |
| Desenvolvimento | Página/endpoints isolados, sem alterar o painel atual | Implementação inicial em andamento |
| Testes | Acesso, estados, responsividade e regressão de dashboard | Pendente |
| Correções e novos testes | Corrigir resultados observados e repetir | Pendente |
| Homologação isolada | Ambas as páginas, sintético, desktop/mobile e conciliação | Pendente; ambiente ainda não identificado/configurado |
| PR revisável | Branch publicada e PR atualizado com evidências | Pendente; sem merge |

## Auditoria de fontes

Fontes examinadas: `public/frame.php`, `app/access/AccessEvaluator.php`, `app/access/PageAccessRegistry.php`, `app/views/internal/dashboard.php`, `public/api/dashboard/resumo.php`, `app/services/DashboardResumoService.php`, `app/helpers/CartasExpedidasEstadoFluxo.php` e `app/helpers/CartasExpedidasEnvioRegra.php`. A auditoria foi somente leitura antes da criação da branch. Nenhuma conexão com banco ou ambiente real foi feita.

| Bloco/métrica | Fonte/semântica comprovada | Decisão de primeira entrega | Lacuna/limite |
| --- | --- | --- | --- |
| Visão geral — cartas | `cartas_expedidas` e `cartas_recebidas`, `created_at`; séries de 30 dias no resumo | Mostrar soma somente se ambas as métricas e séries existirem; rotular como cadastro | Não significa expedição nem evento produtivo; fuso segue o SQL existente, ainda sem equivalência Bahia/PHP demonstrada |
| Visão geral — ofícios | `oficios_cartorio` e `oficios_gabinete`, `created_at`, contagem do ano | Mostrar cadastros do ano se ambas as fontes retornarem valor | Não comprova expedição; tabela ausente/falha deve continuar indisponível |
| Visão geral — filas | Resumo agregado por subtarefas executáveis; cartas expedidas via `CartasExpedidasEstadoFluxo`; falha de módulo propaga `null`/parcial | Reutilizar total somente quando não parcial; rótulo “Filas executáveis” | Não representa produção nem necessariamente pendência por pessoa |
| Visão geral — pendências operacionais | Sem definição unificada e snapshot de referência comprovados | Indisponível | Definir estoque e denominador antes de ativar |
| Meu trabalho — oito eventos | `dashboard_conclusoes` registra conclusões parciais de cartas/ofícios, sem carga retroativa; campos de usuário do resumo não comprovam expedição. Pessoas, guias, editais, medidas e consultas não têm eventos auditados aqui | Oito métricas indisponíveis | Mapear origem, autor do evento, duplicidade, janela/fuso, completude, histórico e consultas manuais concluídas |
| Fluxo | Estado efetivo de cartas expedidas classifica envio/devolução; outras fontes são subtarefas ativas. Não há trilha íntegra para todas as transições | Renderizar estágios sem contagem/conversão; explicar indisponibilidade | Coorte, cancelamento, pulo, reabertura e idade da etapa sem histórico completo |
| Lotação | Filas e algumas regras de responsável existem no resumo, restritas por área do perfil | Exibir fila de Cartório só se a resposta indicar setor conectado; estado da fila vem da mesma resposta | Não é mapa completo de lotação; outras áreas e total pessoal atribuído carecem de filtros/contrato |
| Criticidade | Há prioridades de tarefa/processo em módulos diferentes | Indisponível | Sem mapeamento dos cinco níveis e sem denominador/deduplicação |
| Radar | Alguns prazos de cartas expedidas/recebidas; não há lista unificada | Indisponível | Cobertura, calendários, regras e limites das faixas requerem auditoria por módulo |
| Qualidade | Não existe trilha completa de reabertura/reemissão/checklist | Indisponível | Sem denominadores ou histórico confiável |
| Prontidão | Pauta pode contar audiências ativas em janela; partes dão informações de expediente/cumprimento; link pode existir na pauta | Bloco geral indisponível | AudIn, semântica de parte/expediente/audiência e disponibilidade de link exigem confirmação |
| Agenda | `sala_audiencias_pauta`, audiência ativa em janela de sete dias no resumo | Exibir apenas contagem quando não nula | Não equivale a compromissos/prazos unificados nem à lista completa da semana |
| Próxima ação | Não há filtro unificado comprovado para tarefas executáveis atribuídas ao usuário | Indisponível | Ordenação e conciliação com links necessitam contrato por módulo |
| Evolução de produtividade | Série semanal global parcial e conclusões individuais parciais | Indisponível no novo painel | Não usar comparação entre pares: sem coorte elegível, cobertura nem limiar de privacidade |
| Alertas críticos | Alertas atuais limitam-se a cartas e prazos correlatos | Reutilizar lista apenas como alertas cobertos pelo resumo, sem chamá-la de cobertura global | Não inclui todas as filas/prazos; idade de fila e vencimento devem permanecer distintos |

## Acesso e isolamento

- O painel atual usa `AccessEvaluator::canAccessPage('dashboard')` e a API atual valida perfil interno e áreas.
- A nova rota e API usam chave de página própria, mesma regra interna, negam perfis externos e exigem `DASHBOARD2_ENABLED` explícito. O valor padrão é `0`.
- A API nova é somente GET, com cache desabilitado, e reaproveita `DashboardResumoService` sem alterar contrato nem resposta do painel atual.
- A homologação precisa ativar a flag apenas no ambiente isolado, com usuários/perfis e registros sintéticos. Não executar migrações por este PR.

## Fontes e validação pendente

- Referência visual local aprovada em `C:\Users\jorge\Documents\Fokus\REI-351\image(20261127-224744).png`; não foi copiada ao repositório.
- A issue REI-351 tem descrição inicial de protótipo que diverge do escopo aprovado em seu corpo em 27/11/2026; prevalece o texto aprovado mais recente e a sequência indicada pelo usuário.
- Notion pesquisado nesta sessão; conector respondeu HTTP 401, portanto decisões do Notion não puderam ser recuperadas.
- A trilha de `dashboard_conclusoes` declara explicitamente que não há carga retroativa (`scripts/migrate-dashboard-conclusoes.php`). Não projetar séries completas a partir dela.
- A tabela de agenda atual não demonstra escopo por Vara nem deduplicação de prazos/compromissos; manter uso restrito à contagem existente.
