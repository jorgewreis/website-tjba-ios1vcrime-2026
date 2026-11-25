# Matriz de acesso — Sala de Audiências

Leitura estática da revisão `bcc03bfa`, em 16/11/2026. Esta matriz descreve regras observadas, não comprova testes autenticados nem proteção equivalente em todos os endpoints. As fontes para páginas são [`public/frame.php`](../../public/frame.php), [`PageAccessRegistry`](../../app/access/PageAccessRegistry.php), [`AccessEvaluator`](../../app/access/AccessEvaluator.php) e [`UserAccessHelper`](../../app/helpers/UserAccessHelper.php). APIs são entradas independentes de `frame.php`.

## Páginas registradas

Todas passam por `Security::requireAuthentication()` e pela avaliação de página em `frame.php`.

| Chaves de `frame.php` | View | Regra observada |
| --- | --- | --- |
| `salapauta`, `salapainel` | `sala-audiencias-pauta-list.php` | Área Audiências |
| `salapautacreate` | `sala-audiencias-pauta-create.php` | Área Audiências |
| `salapautaedit` | `sala-audiencias-pauta-edit.php` | Área Audiências |
| `salapautaparteedit` | `sala-audiencias-pauta-parte-edit.php` | Área Audiências |
| `salaata` | `sala-audiencias-atas-list.php` | Área Audiências |
| `salapaineldia` | `sala-audiencias-painel-dia.php` | Área Audiências |
| `estudapauta` | `sala-audiencias-pauta-list-estudantes.php` | Estudante externo ou perfil elevado interno |
| `estudapainel` | `sala-audiencias-painel-estudantes.php` | Estudante externo ou perfil elevado interno |

Área Audiências exige `SERVIDOR-AUDIENCIAS` ou `ESTAGIARIO-AUDIENCIAS`, salvo perfil elevado. `ADMIN` e `CHEFIA` internos passam pela exceção para páginas registradas; `DIRETORA` e `MAGISTRADA` são normalizadas para `CHEFIA`. Perfil simples de servidor/estagiário, isoladamente, não satisfaz a regra de área. Usuário com perfil interno e `ESTUDANTE` é classificado como interno: o perfil estudante não amplia seu acesso; para as duas páginas de estudante, a exceção de perfil elevado ainda se aplica. `CONSULTOR` exclusivamente externo não tem acesso a essas páginas. Veja também [a matriz global](matriz-globais.md).

## APIs: controles identificados no código

Os nomes abaixo são relativos a `public/api/`. Esta é uma leitura dos controles nas entradas, não uma certificação de autorização por registro, método, CSRF, persistência ou fluxo completo. O `ApiAccessGuard` verifica autenticação e permissão da página indicada; com `true`, também exige POST de origem confiável.

| Entradas | Controle identificado na entrada |
| --- | --- |
| `sala-audiencias-pauta/create.php` | Guard `salapautacreate`, escrita. |
| `sala-audiencias-pauta/{update,cancel,delete,alocar,desalocar}.php` | Guard `salapautaedit`, escrita. `desalocar` também verifica gestão ou condições da alocação. |
| `sala-audiencias-pauta/partes-{create,update,delete}.php` | Guard `salapautaparteedit`, escrita. |
| `sala-audiencias-pauta/prioridades-{add,remove}.php` | Guard `salapautaedit`, escrita. |
| `sala-audiencias-pauta/{occupied-slots,prioridades-list}.php` | Guard `salapautaedit`, leitura. |
| `sala-audiencias-pauta/set-status-painel.php` | Guard `salapainel`, escrita, e verificação adicional `salapauta`. |
| `sala-audiencias-pauta/resumo.php` | `Security::isAuthenticated()` e `salapautaedit`; consulta também existência e status da pauta. |
| `sala-audiencias-pauta/status-bulk.php` | POST, autenticação, origem confiável, `estudapainel` e perfil de cadastro exclusivamente externo estudante. O recorte por pauta segue a política da REI-161 descrita abaixo. |
| `internal-list-export/{csv,pdf}.php?page=estudapauta` | REI-337: autenticação e permissão de página não bastam para exportar; estudante externo recebe 403 antes da consulta de linhas. Perfis internos efetivos continuam sujeitos à regra de página. A visualização da pauta permanece autorizada separadamente. |
| `sala-audiencias-pauta/tipos-list.php` | Na entrada examinada, não há verificação explícita de sessão ou perfil; não se inferiu ausência de controles externos. |
| `sala-audiencias-ata/upload.php` | POST, ID de sessão e avaliação `salaata`; a entrada examinada não chama o guard de origem confiável. |
| `sala-audiencias-ata/download-interno.php` | GET, ID de sessão e avaliação `salaata`; o serviço prepara o arquivo. |
| `sala-audiencias-ata/inscrever.php` | POST, autenticação/token de sessão, origem confiável, `estudapainel` e perfil de cadastro exclusivamente externo estudante; o serviço avalia a inscrição. |
| `sala-audiencias-ata/download.php` | GET, autenticação (`Security::isAuthenticated()`), avaliação `estudaata` e perfil de cadastro exclusivamente externo estudante; o serviço exige inscrição ativa para a pauta e valida sigilo/disponibilidade antes de entregar o arquivo. A rota autoriza apenas a chamada API: a view `sala-audiencias-atas-estudantes.php` não está ligada ao roteamento atual de `frame.php`, e não foi confirmado botão acessível no painel. |

O ID de sessão isolado não é equivalente a uma chamada observada a `Security::isAuthenticated()`. A existência de um guard de página também não demonstra autorização por pauta ou sigilo. A restrição de exportação da REI-337 é distinta das regras de inscrição, metadados e acesso a links das REI-161 e REI-162; o tratamento de pautas ausentes no polling está na REI-106. A restrição da REI-337 foi mesclada na [PR #515](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/pull/515), e o [deploy de produção do merge](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/45593624047) registrou sincronização e pós-deploy com sucesso. Isso não comprova login real ou geração real de PDF. Nesta revisão não foram executadas requisições autenticadas nem consultas a dados reais para esta matriz.

## Metadados e polling do estudante

Conforme a [decisão de 23/11/2026 no Notion](https://app.notion.com/p/4156af4c510981faa48bebd9023c3e3b) para REI-161 e REI-162, estudante já inscrito preserva metadados e acesso ou link nos status operacionais, ainda que status ou data impeçam novas inscrições; lotação impede somente novas inscrições. Para não inscritos, status ou data inelegíveis e lotação cheia produzem resposta mínima. Sigilo efetivo, presença de menor e prioridades protegidas ocultam metadados e link mesmo para inscritos. A implementação foi mesclada na [PR #512](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/pull/512). A [PR #519](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/pull/519) ampliou sua validação com MySQL descartável e componentes de sigilo reais; sessão, bootstrap e conexão da fixture são sintéticos.

No polling, a [REI-106](https://linear.app/reis-developer/issue/REI-106/webapp-2026-atualizar-painel-quando-audiencia-deixa-de-estar-ativa) determina tratar IDs ausentes apenas quando a resposta é válida e completa para o conjunto consultado, preservando cartões em falhas ou resposta parcial e transferindo o foco ao remover uma pauta. A evidência registrada para essa regra usa DOM e rede simulados; não comprova navegador autenticado ou ambiente de produção.
