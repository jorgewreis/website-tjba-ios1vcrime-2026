# Versionamento e releases

Este projeto usa versionamento com largura fixa no formato:

```text
V 1.00.0000
```

## Composição

- **MAJOR** (1) - quebra grande ou mudança incompatível
- **MINOR** (00) - evolução compatível / novas funções
- **PATCH/BUILD** (0000) - correções, ajustes pequenos, hotfixes, builds

## Regras de incremento

1. **MAJOR++** => `MINOR=00` e `PATCH/BUILD=0000`
   Ex.: `V 1.07.0123` -> `V 2.00.0000`
1. **MINOR++** => `PATCH/BUILD=0000`
   Ex.: `V 1.07.0123` -> `V 1.08.0000`
1. **PATCH/BUILD++** => incrementa somente PATCH/BUILD
   Ex.: `V 1.03.0042` -> `V 1.03.0043`

## Regra de escala (mais de 100 arquivos)

Se o commit alterar mais de 100 arquivos, o bump vira **MINOR** automaticamente.
Nessa situação, o processo gera um arquivo `docs/releases/vX.YY.md` com base no `_draft_minor.md`
e reinicia o rascunho para o próximo ciclo.

## Faixas do PATCH/BUILD

- **0001-1999** = correções/ajustes (patch)
- **2000-7999** = melhorias internas/refatoração (sem feature nova)
- **8000-9999** = hotfix crítico/emergência

## Automação no GitHub

O workflow em `.github/workflows/version-bump.yml` incrementa o `VERSION` automaticamente em push para `main`/`master`,
exceto quando o commit contém `[skip version]` / `[skip-version]`.

## Automação local (hooks)

O repo está configurado com `core.hooksPath=.githooks`.

- O hook `post-commit` em `.githooks/post-commit` faz o bump do `VERSION` e executa `git commit --amend` para incluir
  a mudança no commit do usuário (ou seja, o hash do commit muda).
- Em seguida, ele atualiza `docs/releases/_draft_minor.md` em um commit separado, referenciando o hash final do commit.

Para pular automações:

- `[skip version]` => não atualiza versão nem draft
- `[skip draft]` / `[skip-draft]` / `[skip release-notes]` => não atualiza o draft

## Processo de release (tag + changelog + nota de versão)

1. Atualize o `CHANGELOG.md` com a nova versão e os itens entregues.
1. Garanta que o `VERSION` esteja correto.
1. Crie a tag no Git e publique:

```bash
git tag -a v1.08.0000 -m "Release v1.08.0000"
git push --tags
```

1. Crie a release no GitHub e cole as notas (podem ser o mesmo texto do changelog).

## Conteúdo das notas de versão (MINOR)

O arquivo final de MINOR deve conter:

- Cabeçalho com versão e data
- Resumo (3-8 bullets do que mais importa)
- Seções completas Added/Changed/Fixed/Security/Deprecated/Removed
- Notas de compatibilidade (se houver)
- Notas de migração (se houver)
- Bugs conhecidos (se houver)
- Próximas implementações (se houver)

## Sufixos de pre-release

- `v1.08.0000-alpha.1`
- `v1.08.0000-beta.2`
- `v1.08.0000-rc.1`
- `v1.08.0000` (estável)

