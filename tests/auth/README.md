# Suíte E2E de autenticação isolada

O bloqueio provisório foi substituído pelo runner abaixo, que comprova a
topologia descartável antes de executar testes que leem ou alteram dados. Os
testes individuais continuam protegidos por `sandbox-guard.php`; use apenas os
entrypoints documentados.

## Pré-requisito reproduzível

A árvore de dependências é fixada por `composer.lock`. Em um checkout limpo,
prepare o autoloader com a imagem PHP de QA já usada pela suíte:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth/prepare-dependencies.ps1
```

O comando grava apenas `vendor/` no checkout. Ele é uma preparação explícita e
não faz parte dos contêineres de teste. Não execute a suíte sem esse passo em
um checkout que ainda não tenha `vendor/autoload.php`.

## Execução

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth/run-isolated-auth-e2e.ps1
```

O runner cria uma rede Docker `--internal` descartável, não publica portas,
não usa bind mounts nos contêineres de teste e usa `tmpfs` para o banco MySQL.
O sucesso termina com `PASS auth-test-runner`; a limpeza remove todos os
recursos marcados com `fokus.auth-e2e`.

## CI de autenticação

O workflow `auth-isolated-e2e.yml` constrói `tests/auth/ci-runtime/Dockerfile` sem rede, a partir de imagens PHP e Composer fixadas por digest, compila `pdo_mysql` e `pcntl` e verifica extensões exigidas. O checkout não persiste credenciais; antes dos mounts, os manifests devem ser arquivos regulares sem links e `vendor/` deve ser criado vazio e inexistente. A instalação de `composer.lock` usa imagem Composer separada, também pinada, com scripts e plugins desativados; em seguida, valida os requisitos no runtime construído sem rede. O ID exato da imagem construída é passado por `-QaImageId` ao runner; o valor padrão local permanece disponível. O job não utiliza segredos, publica portas ou usa banco persistente, e verifica resíduos ao final.

O job exercita autenticação e a fixture de serviço de atas, sem executar o runner de atas suspenso. A fixture cobre sigilo, falhas de arquivo/banco, duas substituições concorrentes com waits observados no MySQL e upload HTTP multipart com sessões sintéticas; não cobre browser, schema real ou acesso à rede externa. O workflow está configurado para executar no GitHub Actions em pushes e pull requests; consulte o resultado do run e do commit correspondentes para verificar cada execução. A disponibilidade futura dos digests e a reprodução binária idêntica entre builders não são garantidas. Os pacotes Composer são obtidos da rede e sua disponibilidade é uma dependência externa. A existência de lint verde não substitui o resultado deste job.

A verificação final falha se o daemon não responder ou se houver recursos etiquetados, inclusive containers temporários do Composer. Ela não executa remoção genérica. Em cancelamento abrupto ou timeout do job, nem a etapa final nem o `finally` do runner são garantidos; a eliminação do executor hospedado é uma fronteira adicional, não prova de cleanup interno.


### Reforco de topologia REI-93

O runner confere estrutura e identidade dos containers, exige rede unica, interna e inicialmente vazia, e depois do start compara membros exatos, NetworkID e EndpointID entre rede e containers. Endpoints duplicados sao recusados. Antes do start, campos NetworkID/EndpointID ainda vazios sao aceitos; o contrato ativo e exigido antes de entregar o marcador, executar testes e fazer chamadas HTTP.

`docker-topology-validation.test.ps1` cobre 49 casos com objetos sinteticos, sem Docker/banco. Os mocks de resposta perdida e erro sanitizado continuam exigindo que a falha pretendida seja atingida. Snapshots repetidos reduzem alteracoes acidentais entre etapas, mas nao eliminam TOCTOU nem atestam daemon/imagens independentemente. A validacao sintetica nao autoriza execucao positiva nem encerra a investigacao historica.
