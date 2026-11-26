# Comparativo DataJud: WebApp institucional e Fokus Law

Revisão estática em 26/11/2026 para a REI-108. Nenhuma consulta real ao DataJud, credencial, processo real ou ambiente de produção foi usada.

## Código comparado

| Aspecto | WebApp institucional | Fokus Law |
| --- | --- | --- |
| Revisão | `website-tjba-ios1vcrime-2026` em `origin/main` (`e44a33bd3b406597fb8a49abacd2f7daf5400cb7`) | `jorgewreis/fokus` `main` (`b5d9fee3480b315148686f1c2e965d74560b0a0d`) |
| Cliente | `app/services/DataJudService.php` | `apps/cloud/app/Services/LawDatajudClient.php` |
| Tribunal | `DataJudTribunalResolver` escolhe um alias pelo CNJ; o alias indevido do STF foi removido | `aliasFor` também roteia pelo CNJ; teste declara 91 endpoints públicos e não roteia STF |
| Consulta | `match` e `term`, tamanho 2 e total exato; rejeita mais de um documento para evitar sincronização ambígua | `match`, lista de campos `_source`, tamanho configurável e paginação `search_after` limitada a 100 páginas |
| Resultados | O mapper retorna os campos institucionais para exibição. Sincronização exige resultado único, tribunal confirmado e ausência de sigilo comprovada | Agrega registros de graus, assuntos e movimentos e mantém metadados por registro para consulta de advocacia |
| Falhas | 400, 401, 403, 404, 429, 5xx, timeout, resposta inválida ou incompleta não são tratados como ausência; o endpoint público sanitiza erro interno | Códigos separados para limitação, indisponibilidade, timeout, fonte parcial e resposta inválida; mostra orientação de nova tentativa |
| Acesso | Chave fica no servidor; rota pública de consulta tem CORS `*`. Limites externos ou de infraestrutura não foram confirmados | Chave fica no servidor; consulta ocorre no backend da aplicação autenticada |

## Decisões aplicadas ao WebApp

1. A busca solicita apenas os campos consumidos pelo mapper institucional, reduzindo transferência e evitando requisitar metadados desnecessários.
2. Timeout de conexão e de resposta têm limites máximos, além dos mínimos já aplicados.
3. HTTP 400/429 e demais erros continuam distintos de uma resposta válida com zero resultados. Resultado ambíguo recebe resposta própria e nunca autoriza sincronização automática.
4. A sincronização automática usa uma fila sem chamada externa durante a requisição da tela. Classe e assuntos são revalidados e gravados pelo sincronizador transacional existente; uma falha na etapa de assuntos reverte a classe. Vínculos manuais permanecem protegidos pelas regras do repositório.
5. Não foi copiada a agregação de graus e a paginação do Fokus: neste WebApp o vínculo automático exige prova de um único resultado completo. Ampliar a consulta para agregar registros exigiria regra institucional para escolher entre graus/classes divergentes; não há decisão de produto ou prova da API que a autorize. O comportamento é documentado como diferença intencional.
6. Partes não são importadas nem reconciliadas pelo DataJud. No fluxo solicitado, somente vínculos internos existentes são relidos.

7. O resolver agora usa os códigos TR oficiais para TJs, TREs e Tribunais Militares; a comparação encontrou códigos estaduais regionais deslocados no WebApp. STF e CNJ continuam sem alias porque o CNJ os exclui do escopo da Base/DataJud ([FAQ oficial da Numeração Única](https://www.cnj.jus.br/programas-e-acoes/numeracao-unica/perguntas-frequentes/), [Resolução CNJ nº 331/2020 e alterações](https://atos.cnj.jus.br/atos/detalhar/3530)). O teste cobre os 91 endpoints públicos roteáveis e garante rejeição do STF, CNJ e código estadual inexistente.

## Pendências de ambiente

A rota pública continua com CORS amplo e este checkout não comprova limites aplicados por proxy, CDN ou infraestrutura. A escolha de autenticação/limite depende da intenção de acesso público e das proteções externas descritas na REI-92. O CNJ descreve a API como acesso a metadados de processos públicos e mantém uma lista própria de endpoints; aliases presentes no código não provam disponibilidade atual nem cobertura efetiva ([visão geral do CNJ](https://www.cnj.jus.br/sistemas/datajud/api-publica/), [endpoints da API](https://datajud-wiki.cnj.jus.br/api-publica/endpoints/)). Nenhuma chamada real foi feita. Testes simulados não provam disponibilidade, quota, cobertura atual da API ou configuração de produção.

## Testes adicionados/atualizados

- Cobertura HTTP simulada mantém 400/429 e respostas inválidas fora do caminho de ausência.
- Contrato do corpo de consulta verifica lista de campos e total exato.
- Teste sintético provoca falha entre gravação da classe e reconciliação dos assuntos e confere rollback integral.
- Teste de resposta ambígua confirma recusa explícita, separada de zero resultados.
