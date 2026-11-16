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
