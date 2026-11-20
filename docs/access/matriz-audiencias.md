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
| `sala-audiencias-pauta/status-bulk.php` | POST, autenticação, origem confiável, `estudapainel` e perfil de cadastro exclusivamente externo estudante. O recorte dos metadados por pauta permanece na REI-161. |
| `sala-audiencias-pauta/tipos-list.php` | Na entrada examinada, não há verificação explícita de sessão ou perfil; não se inferiu ausência de controles externos. |
| `sala-audiencias-ata/upload.php` | POST, ID de sessão e avaliação `salaata`; a entrada examinada não chama o guard de origem confiável. |
| `sala-audiencias-ata/download-interno.php` | GET, ID de sessão e avaliação `salaata`; o serviço prepara o arquivo. |
| `sala-audiencias-ata/inscrever.php` | POST, autenticação/token de sessão, origem confiável, `estudapainel` e perfil de cadastro exclusivamente externo estudante; o serviço avalia a inscrição. |
| `sala-audiencias-ata/download.php` | GET, ID de sessão, avaliação `estudaata` e perfil de cadastro exclusivamente externo estudante; o serviço verifica a solicitação. **`estudaata` não está no registro de páginas nem na lista de páginas externas do avaliador**, de modo que a verificação observada nega esse caminho antes do serviço. A intenção e eventual correção exigem validação separada. |

O ID de sessão isolado não é equivalente a uma chamada observada a `Security::isAuthenticated()`. A existência de um guard de página também não demonstra autorização por pauta ou sigilo. As regras de inscrição e acesso a links após mudança de elegibilidade permanecem nas REI-161 e REI-162; o tratamento de pautas ausentes no polling está na REI-106. Nesta revisão não foram executadas requisições autenticadas nem consultas a dados reais para esta matriz.
