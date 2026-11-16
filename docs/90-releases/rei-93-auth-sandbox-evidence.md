# REI-93 — Evidências do sandbox E2E de autenticação

## Escopo e decisão

O executor permitido é `tests/auth/run-isolated-auth-e2e.ps1`. Ele substitui o
fluxo Compose histórico para os testes de autenticação; entrypoints individuais
continuam protegidos por `sandbox-guard.php`.

## Evidências verificadas

- A rede criada para cada execução usa `docker network create --internal` e
  recebe um identificador aleatório em `fokus.auth-e2e`.
- Os quatro contêineres (MySQL, Mailpit, app e cliente) são inspecionados antes
  dos testes: sem portas publicadas, bind mounts ou modo privilegiado.
- O MySQL usa `tmpfs` em `/var/lib/mysql`; usuário, banco e senhas são novos a
  cada execução. Mailpit recebe somente endereços `@auth-test.invalid`.
- O runtime e o token de guarda são criados com `umask 077` exclusivamente no
  contêiner app. A cópia recusada contém `.env` fora de `.env.example` ou
  reparse points.
- O `finally` remove apenas contêineres e a rede que portam o rótulo do run e
  falha quando não consegue comprovar a remoção.

## Preparação de dependências

O runner e `prepare-dependencies.ps1` usam a imagem QA por digest, expõem somente
`composer.json` e `composer.lock` como leitura e grava somente `vendor/`. Ele
desabilita plugins e scripts Composer. Essa etapa usa a rede bridge apenas para
baixar dependências; ela não é parte do sandbox de testes.

## Limites

O procedimento não comprova a confiança do daemon Docker local, do host ou do
registro que forneceu a imagem. Execute apenas em estação administrada e valide
o digest da imagem antes de substituir o valor versionado. Ele também não prova
paridade HTTPS, cookies `Secure`, iframe ou produção.
