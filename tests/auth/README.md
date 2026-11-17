# Suíte E2E de autenticação

## Estado atual

Os testes diretos continuam bloqueados pelo `sandbox-guard.php`. O runner `run-isolated-auth-e2e.ps1` existe, mas sua execução positiva permanece suspensa até revisão independente comprovar a identidade do daemon Docker, a procedência das imagens, o isolamento efetivo de MySQL/SMTP/rede e a propriedade/limpeza limitada dos recursos. A configuração sintética, o nome das imagens, a topologia declarada ou um teste negativo em cópias não comprovam esses requisitos.

Não execute a suíte E2E, o runner nem scripts de preparação/limpeza para contornar o guard. O teste unitário sintético `docker-cleanup-verification.test.ps1` pode ser executado sem Docker e não libera a suíte positiva. O script de limpeza manual está bloqueado porque a etiqueta histórica pode corresponder a recursos de outra execução. A ocorrência histórica que motivou a contenção também segue sem destino e efeitos comprovados.

## Artefatos

- `sandbox-guard.php` interrompe execução direta sem os marcadores criados dentro do container pelo runner.
- `run-isolated-auth-e2e.ps1` descreve uma proposta de caminho isolado; a implementação está sujeita à revisão de segurança e ainda não está liberada para uso.
- `sandbox-guard-rejection.test.py` testa recusas em cópias temporárias; não executa banco, Docker, runner nem configuração do checkout.
- `docker-cleanup-verification.test.ps1` testa, com respostas sintéticas, a classificação de ausência após falha de `inspect`; não executa Docker nem comprova a procedência do daemon.
- `cleanup.ps1` permanece bloqueado; não remova recursos usando etiquetas compartilhadas.

## Próximos critérios para liberar o caminho positivo

1. Verificar daemon/contexto Docker e procedência/pin das imagens por evidência independente do próprio runner.
2. Validar que o teste não possui rota de saída de rede ou relay SMTP e que DB/SMTP são instâncias descartáveis exclusivas da execução.
3. Demonstrar que a limpeza só alcança IDs criados por aquela execução e não falha silenciosamente.
4. Obter decisão sobre a investigação histórica antes de executar o caminho positivo.

Preparação de dependências pode usar Docker com bridge/rede externa e gravar `vendor/` no checkout. Não é pré-condição para os testes de recusa e deve ser revisada separadamente antes de qualquer uso.
