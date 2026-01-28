# Tipos de commit e quando usar

Este guia descreve os tipos de commit mais comuns para manter o histórico do repositório claro e consistente.
Use o prefixo apropriado no início da mensagem de commit.

## Tipos

- feat: nova funcionalidade ou incremento de comportamento que o usuário percebe.
- fix: correção de bug ou ajuste que resolve um problema existente.
- refactor: mudança interna de código sem alterar comportamento externo (ex.: reorganizar funções, remover duplicação).
- docs: alterações apenas em documentação (README, guias, comentários relevantes).
- style: ajustes de formatação ou estilo de código sem impacto lógico (ex.: espaços, lint, ordem de imports).
- perf: melhorias de desempenho sem mudar comportamento externo (ex.: otimização de consultas).
- test: adição ou ajuste de testes sem mudar código de produção.
- chore: tarefas de manutenção que não afetam o código de produção nem testes diretamente (ex.: configs, deps, scripts).
- build: alterações no build ou empacotamento (ex.: bundlers, scripts de build).
- ci: alterações em pipelines e automações de CI (ex.: GitHub Actions).
- revert: desfaz um commit anterior de forma explícita.
- deps: atualizações de dependências sem mudança de comportamento (ex.: bump de versão).
- config: ajustes de configuração de ambiente ou ferramentas (ex.: ESLint, Prettier).
- release: preparação de versão (ex.: changelog, tags, versionamento).
- i18n: ajustes de internacionalização e tradução.
- ux: melhorias de experiência de uso sem nova funcionalidade principal.

## Tags de versionamento (para bump automático)

Use estas tags no commit para controlar o bump de versão:

- `[major]` => MAJOR++
- `[minor]` => MINOR++
- `[patch]` => PATCH/BUILD na faixa 0001-1999
- `[refactor]` ou `[patch2000]` => PATCH/BUILD na faixa 2000-7999
- `[hotfix]` ou `[patch8000]` => PATCH/BUILD na faixa 8000-9999
- `[skip version]` => não atualiza a versão naquele commit

Regra adicional: se o commit alterar mais de 100 arquivos, o bump vira **MINOR** automaticamente.

## Dicas rápidas

- Seja objetivo: "feat: adicionar filtro por data na lista".
- Se houver mais de uma mudança, priorize o tipo com maior impacto.
- Evite misturar múltiplos tipos no mesmo commit; se possível, separe.

## Modelo padrão de commit

Formato:

`tipo: descrição do commit`

## Exemplos

- `docs: novo documento criado`
- `feat: permitir exportar relatório em CSV`
- `fix: corrigir erro ao salvar formulário`
- `refactor: separar validações em módulo`
- `style: ordenar imports no controller`
- `perf: reduzir consultas no carregamento`
- `test: adicionar testes para login`
- `chore: atualizar versão do Node`
- `build: ajustar script de build para produção`
- `ci: adicionar job de lint no pipeline`
- `revert: desfazer commit que quebrou deploy`
- `deps: atualizar react para 18.3`
- `config: ajustar regras do eslint`
- `release: preparar versão 1.4.0`
- `i18n: traduzir mensagens de erro`
- `ux: melhorar fluxo de cadastro em 2 passos`
