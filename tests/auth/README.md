# Contenção provisória dos testes de autenticação

Os entrypoints de `tests/auth` que poderiam escrever arquivos, consultar ou
alterar banco, enviar e-mail ou iniciar serviços estão suspensos antes desses
efeitos. A suspensão inclui os runners, o bootstrap e a limpeza automatizada.
Variáveis de ambiente, nomes de banco ou endereços locais não liberam a guarda.

Para verificar apenas a recusa em cópias temporárias, sem banco nem Docker:

```text
python -B tests/auth/sandbox-guard-rejection.test.py
```

O teste usa sentinelas para a configuração e para o comando Docker. Ele não
comprova um caminho positivo de execução em sandbox. A suspensão deve ser
substituída somente após haver evidência técnica do isolamento do destino,
dos serviços, da cópia usada e do ciclo de vida dos recursos.

Os testes de `tests/ata` têm entrypoints próprios e não são protegidos por esta
contenção. Não os execute sem comprovar isolamento e segurança de limpeza.
