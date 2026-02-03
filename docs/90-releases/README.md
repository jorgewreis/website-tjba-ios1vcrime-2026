# Releases (docs/90-releases)

Este diretório organiza o histórico de releases e notas de versão do projeto.

## Estrutura

- `KNOWN_ISSUES.md`: problemas conhecidos e limitações aceitas.
- `_draft_minor.md`: rascunho cumulativo para a próxima versão **MINOR** (alimentado por commits).
- `v*.md`: arquivos finais de release (ex.: `v1.00.md`).
- `notes/`: notas “granulares” geradas por commit para rastreabilidade.

## Regras operacionais

1. **PATCH**: incrementa `YY.MM.PPPP` e alimenta `_draft_minor.md`.
2. **MINOR**: consolida `_draft_minor.md` em um novo arquivo `vYY.MM.md` e reseta o draft.
3. **MAJOR/RELEASE**: mudanças estruturais maiores; pode exigir checklist e migrações.
