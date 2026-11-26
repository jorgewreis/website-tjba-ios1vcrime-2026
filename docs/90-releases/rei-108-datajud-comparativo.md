# REI-108: DataJud na Vara e no Fokus Law

Comparação em 26/11/2026: [Vara 2026 após o PR #532](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/tree/5a52b56dda771a0de863d7b45246838bd4a133a4) e [cliente do Fokus Law no monorepo](https://github.com/jorgewreis/fokus/blob/b5d9fee3480b315148686f1c2e965d74560b0a0d/apps/cloud/app/Services/LawDatajudClient.php). Ajustes da Vara no [PR #533](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/pull/533).

| Aspecto | Vara 2026 | Fokus Law | Decisão |
| --- | --- | --- | --- |
| CNJ e tribunal | Dígito verificador e tribunal resolvidos pelo número, sem fallback. | Normaliza 20 dígitos, resolve tribunal e usa `match`. | Preservar validação completa e não redirecionar para outro alias. |
| Resultado e cobertura | Solicitados no máximo dois registros e total exato; exige um único resultado verificável para escrever. | Busca paginada e valida o número de todos os documentos; ordena por última atualização e retém referências dos registros. | Não importar seleção automática de uma instância nem o tamanho de página do Law. Na Vara, mais de um documento precisa de decisão explícita sobre instância, sem aplicar dados automaticamente. |
| Dados de origem | Consulta de visualização pode ler metadados e movimentações; a sincronização passa a pedir só número, sigilo, classe e assuntos. | Projeção de metadados mais ampla, incluindo situação, órgão, grau e campos de movimentos; o cadastro e o histórico têm contrato próprio. | Minimizar o payload de sincronização; não mesclar movimentos, partes nem regras de produto. |
| Resposta incompleta e falhas | Recusa falha de shard, timeout ou resultado sem prova de completude. O PR distingue o erro do CNJ de falha HTTP/HTML no WebApp. | Distingue timeout, autenticação, TLS, rejeição temporária/429 e resposta parcial. | Tratar 429 sem assumir a causa; pausar o lote diante de indisponibilidade comum e informar sem expor a resposta bruta. |
| Sigilo e autorização | Mantém autorização por operação e setor, confirma número, completude e ausência explícita de sigilo na fonte. No PR, não consulta o CNJ se o cadastro local está sigiloso e revalida isso antes da gravação. | Fluxo próprio de empresa/caso e histórico. Ausência pública não comprova que o processo seja inexistente ou sigiloso. | Manter regras institucionais da Vara. Não consultar publicamente processo marcado sigiloso para sincronizá-lo nem usar o Law como proxy. |
| Atualização | CLI e fila próprios com metadados mínimos; a ação manual continua permitida conforme o fluxo institucional. | Cadastro enfileirado e scheduler com status e registro da última tentativa/sucesso. | Vale avaliar histórico de última tentativa e sucesso depois, com regra de retenção e acesso. Não acoplar filas. |
| Operação | O deploy da Vara não comprova a disponibilidade do DataJud. | Tem diagnóstico do cliente/tempo/fonte e configuração de timeout documentados. | Adotar sondagem somente leitura e contadores de status sem chave, CNJ ou corpo dos retornos; não atrelar publicação à disponibilidade do CNJ. |

## Diagnóstico deste incidente

Em leituras autorizadas, houve HTTP 429 e HTTP 200 com falha de shard no índice público do TJBA. Um processo de controle, informado como não sigiloso, também apresentou timeout e 429. Esses resultados não comprovam o que ocorreu no clique autenticado do WebApp, não permitem classificar o motivo do 429 e não confirmam a ausência de nenhum processo. Não houve gravação de dados reais.

## Validação e próximos passos

1. O PR #533 cobre serviço, respostas HTTP e interface sintéticos, recusa de sigilo antes da consulta e preservação da transação. Merge, deploy e confirmação de recuperação do CNJ são eventos distintos.
2. Quando uma leitura pública retornar uma resposta completa, testar o fluxo autenticado conforme permissões e autorização do responsável, sem usar processo sigiloso nem tratar HTTP 200 como único critério de sucesso.
3. A REI-139 trata separadamente da consistência de prioridades derivadas da classe 1268. A REI-113 continua a ser a referência para mudanças no produto comercial.

Fonte da informação processual: [CNJ, API Pública do DataJud](https://www.cnj.jus.br/sistemas/datajud/api-publica/), [acesso](https://datajud-wiki.cnj.jus.br/api-publica/acesso/) e [endpoints](https://datajud-wiki.cnj.jus.br/api-publica/endpoints/).
