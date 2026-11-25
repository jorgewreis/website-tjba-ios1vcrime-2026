# REI-73 — matriz parcial das APIs de pessoas

Base: `13efca1f6636d297b6ee534fb884bde11b3df98c`. Inventário de 13 arquivos rastreados sob `public/api/pessoas/`, com leitura dos handlers, trechos dos serviços, guard e consumidores literais. A inspeção é estática, salvo a sondagem de telefone indicada abaixo. Não demonstra autenticação real, MySQL, concorrência, cobertura de interface ou controles externos.

Todos chamam `ApiAccessGuard` antes da conexão. Em escrita (`true`), o guard exige sessão, POST e Origin ou Referer de mesma origem, além de acesso a ao menos uma das páginas indicadas. A leitura (`false`) verifica sessão e página, sem exigir método ou origem nesse guard. Isso não é prova de autorização por objeto, nem implica que perfis internos autorizados devam ser privados de leitura por setor.

`InstituicoesConsultaPolicy` ativa o filtro de instituições para usuário efetivamente externo com perfil CONSULTOR. Isso não demonstra filtragem de todas as pessoas ou de documentos, telefones e processos agregados. As respostas `Throwable` examinadas usam mensagem genérica no corpo; alguns erros de validação retornam a mensagem de `InvalidArgumentException`, cujo conteúdo precisa ser avaliado caso a caso.

| Arquivo relativo | Operação e páginas aceitas | Objeto e efeito observado | Referência ou limite |
|---|---|---|---|
| `create.php` | Criar; `consultapartescreate` ou `consultaprocessosedit` | `PessoasService::create`; recebe payload. | Consumidor `consulta-processos-edit.js`; validar erros e transação. |
| `get.php` | Ler; `consultapartes` | Pessoa por ID; `find` e política `onlyActive` para instituições anexadas. | Perfis externos, campos e autorização por objeto pendentes de avaliação. |
| `list.php` | Listar; `consultapartes` | Filtros nome, CPF e instituição, paginação; `onlyActive` é passado ao serviço. | O filtro de instituição ativa no repositório depende do filtro de instituição informado; avaliar política para externos na REI-70. |
| `search-by-name.php` | Buscar; `consultapartes` | Retorna ID, nome, CPF, profissão, lotação e label. | Usado nas telas de pauta, guias e edição de processo; escopo de perfis e dados pendente. |
| `update.php` | Alterar; `consultapartesedit` | `PessoasService::update` por ID; handler ignora o booleano retornado. | ID inexistente pode resultar em sucesso aparente; reprodução ponta a ponta pendente. |
| `documentos/create.php` | Criar documento; `consultapartesedit`, `partesinstituicoesedit` ou `partesinstituicoescreate` | Valida tipo/número, insere e sincroniza CPF legado. | REI-152: falta transação conjunta nos métodos examinados; concorrência e collation pendentes. |
| `documentos/delete.php` | Excluir documento; mesmas três páginas | Exclui por ID e sincroniza CPF legado. | REI-152; retorno `deleted` não substitui verificação de atomicidade. |
| `telefones/create.php` | Criar telefone; mesmas três páginas | Insere telefone de pessoa. | Bootstrap alcançou conexão simulada na sondagem de REI-320; persistência não testada. |
| `telefones/update.php` | Alterar telefone; mesmas três páginas | Handler ignora booleano do serviço. | REI-320: caminho de bootstrap incorreto reproduzido antes da conexão simulada; sucesso/ID ausente pendentes. |
| `telefones/delete.php` | Excluir telefone; mesmas três páginas | Exclui por ID; chamada identificada em `consulta-partes-edit.js`. | REI-320: caminho de bootstrap incorreto reproduzido antes da conexão simulada. |
| `vinculos-instituicoes/create.php` | Criar vínculo; mesmas três páginas | Insere e reconcilia profissão com lock de pessoa e transação. | REI-85: não equivale a comprovar todos os cenários de falha e concorrência. |
| `vinculos-instituicoes/update.php` | Alterar vínculo; mesmas três páginas | Atualiza e reconcilia profissão na operação transacional; `false` produz 404. | Consumidor `partes-instituicoes-edit.js`; REI-85 e política por objeto pendentes. |
| `vinculos-instituicoes/delete.php` | Excluir vínculo; mesmas três páginas | Exclui e reconcilia profissão na operação transacional. | Consumidor `consulta-partes-edit.js`; validar contrato e concorrência em REI-85. |

## Evidência executável anterior

Na base `1d141d3fb7b77351f84193c318828681bd0ed64f`, sem diferenças nos handlers e arquivos de serviço e guard aqui comparados, os endpoints de telefone e o guard reais foram executados em cópia temporária com perfil e origem simulados. `update` e `delete` falharam ao procurar `public/app/helpers/Conection.php`; `create` alcançou a conexão substituída por sentinela. Nenhum banco foi acessado. O ambiente implantado e a resposta de PHP-FPM não foram verificados. REI-320 registra correção e critérios; não presumir que o erro ocorra em produção.

Em laboratório SQLite anterior, falhas injetadas após a inserção ou exclusão de documento demonstraram alteração parcial do documento e CPF legado no recorte do serviço; evidência e limites estão em REI-152. SQL específico foi adaptado, portanto MySQL/InnoDB, concorrência e collation não foram demonstrados.

## Pendências

Validar matriz completa de perfis e sessões, política e dados de externos, objeto e ID cruzado, método, origem e ausência de escrita em negações, persistência e recuperação em falhas, isolamento de banco e concorrência. Consultar REI-70, REI-85, REI-149, REI-152 e REI-320 antes de abrir tarefa adicional. A consulta textual de consumidores não cobre chamadas dinâmicas ou externas. Não declarar a auditoria integral concluída com esta matriz.
