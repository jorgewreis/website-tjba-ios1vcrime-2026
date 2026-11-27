# Auditoria da consulta DataJud — 27/11/2026

Escopo: WebApp institucional, base `83133b85824e459e22b8cb1d4cbc9e07bc5fcd4d`. A cópia principal estava antiga e com alterações preexistentes; a investigação e a alteração abaixo usam worktree isolado. Este documento registra um recorte, não uma homologação completa.

## Fontes e contrato

- [Acesso à API pública do CNJ](https://datajud-wiki.cnj.jus.br/api-publica/acesso/): chave pública sujeita a alteração e cabeçalho `Authorization: APIKey …`.
- [Endpoints do CNJ](https://datajud-wiki.cnj.jus.br/api-publica/endpoints/) e [exemplo de pesquisa](https://datajud-wiki.cnj.jus.br/api-publica/exemplos/exemplo1/): POST para o índice do tribunal e pesquisa pelo número sem pontuação. O alias de Justiça Estadual da Bahia é `api_publica_tjba`.
- [Glossário do CNJ](https://datajud-wiki.cnj.jus.br/api-publica/glossario/): classe, assuntos, grau, nome do órgão julgador e código IBGE do município do órgão. O código IBGE não é, por si só, um nome de comarca judicial. O código de origem do CNJ e o órgão atual também não são intercambiáveis.
- [Informações gerais do CNJ](https://www.cnj.jus.br/sistemas/datajud/sobre/) descrevem uma chave processual que inclui grau, classe e órgão além do número; o [boletim de parametrização de setembro de 2025](https://www.cnj.jus.br/wp-content/uploads/2025/10/relatorio-mudancas-boletim-parametrizacao-09-2025.pdf) cita a existência de números iguais em graus diferentes. O boletim refere-se ao Datamart: não comprova a quantidade ou estrutura de hits destes três processos na API pública. A seleção automática de uma capa em resultados múltiplos exige critério próprio; a implementação atual recusa ambiguidade, inclusive em leitura.
- [TJBA](https://www.tjba.jus.br/estrategia/api-publica-do-datajud-cnj/) aponta para a API pública. O projeto [datajud-mcp](https://github.com/jppicanco/datajud-mcp) também usa a API pública do CNJ; não constitui fonte independente quando o índice do CNJ falha.

## Evidência de rede

Consultas diretas somente de leitura ao índice público TJBA usando os números explicitamente informados pelo usuário, em 27/11/2026:

| Processo | Resposta observada | Interpretação |
| --- | --- | --- |
| 8007726-45.2026.8.05.0103 | HTTP 429 | Consulta limitada; sem conclusão sobre dados do processo. |
| 8007957-48.2021.8.05.0103 | HTTP 200, `timed_out=false`, três shards, dois com falha, zero hits parciais | A origem reportou rejeição de execução e fila de busca cheia. Resultado incompleto não prova ausência. |
| 8010639-68.2024.8.05.0103 | HTTP 429, sem `Retry-After`, após 9,8 s | Consulta limitada; sem conclusão sobre dados do processo. |

Após intervalo de espera, uma nova consulta única ao primeiro processo retornou HTTP 504, sem `Retry-After`, após 60,3 s. Uma consulta de controle posterior, também após intervalo, retornou HTTP 429 sem `Retry-After` após 30,2 s. Não retornaram dados e não provam a causa interna das falhas.

Estas sondagens não passaram pelo WebApp e não estabelecem disponibilidade em outros momentos, causa institucional do limite ou configuração de produção. Registro operacional anterior em [REI-108](https://linear.app/reis-developer/issue/REI-108/webapp-2026-comparar-e-aprimorar-consulta-datajud-com-o-fokus-law) descreve, separadamente, timeout de transporte e falhas de shards observados nos logs sanitizados do PHP. Não gravar nem assumir dados a partir de uma resposta incompleta.

O `CnjValidator` do checkout aceitou os três números fornecidos. Isso verifica a validação local dos números, não sua presença no índice.

## Alteração local e verificação

A consulta de leitura da ficha não requisitava `grau`, embora o mapper geral reconhecesse o campo. A candidata local inclui o campo na projeção de leitura, no retorno público verificado e na ficha. Ele é exibido apenas depois de consulta bem-sucedida, sem persistência ou inferência. Os rótulos informam que o grau pertence à visualização e que o botão consulta dados e movimentações. A sincronização de classe, assuntos e órgão continua separada.

Passaram testes sintéticos de transporte e metadados de completude, mapper/ficha, interface simulada, integração diferencial com SQLite, fronteira HTTP local de sincronização com dependências simuladas e sintaxe PHP; `git diff --check` passou. Corrigida expectativa antiga do teste de ficha de `Sem sigilo` para `Público`, de acordo com a implementação anterior à auditoria.

O teste visual Playwright da ficha passou em Edge, nas larguras de 1440, 390 e 320 px, usando fixture PHP/SQLite e respostas de rede interceptadas. Uma expectativa antiga sobre o seletor de setor foi atualizada para a regra atual, que permite encaminhamento pelo operador autorizado da fixture. Na captura desktop a ficha apresentou grau, classe, assuntos, comarca de origem e vara fictícios. Isso não valida login, sessão autenticada, MySQL em produção, concorrência ou disponibilidade do CNJ.

Um caso integrado no teste de serviço usa transporte sintético e passa pelo mapper e pelo filtro real da ficha: classe, assunto, grau e vara chegam para um documento público completo; a variante restrita não os expõe. Essa fixture não simula uma resposta coletada do CNJ nem a operação HTTP autenticada.

Uma fixture HTTP local adicional executa o handler e o filtro reais com autenticação, repositório e DataJud simulados. Ela verifica os quatro campos, recusa inicial de sigilo, rechecagem após mudança de número/exclusão/sigilo e respostas de erro incompleta e ambígua. Não comprova login, banco real, concorrência ou a rede do CNJ.

O teste HTTP e o teste de interface simulada foram acrescentados ao workflow PHP existente. Ambos passaram no Windows e em contêiner Linux isolado sem rede externa, com checkout montado somente para leitura. O workflow remoto ainda não foi executado nesta candidata.

## Pendente

1. Após recuperação da origem e respeitando a limitação, consultar os três casos e conferir identidade, completude, classe, assuntos, grau e órgão retornados; registrar a origem de comarca sem inferir lotação atual.
2. Verificar a ficha em sessão autenticada, incluindo leitura, sincronização autorizada, visualização do grau e preservação dos dados diante de falhas. Confirmar schema e persistência somente no ambiente autorizado.
3. Investigar a política para múltiplos hits válidos e, se forem observados, apresentar os registros por grau sem escolher ou sincronizar automaticamente um resultado não identificado.
4. Revisar e validar a candidata versionada localmente no SHA atual e seguir os gates aplicáveis antes de publicar. Não houve push, merge ou deploy desta candidata.
