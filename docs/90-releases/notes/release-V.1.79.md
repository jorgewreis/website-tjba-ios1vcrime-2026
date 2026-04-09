![Badge](https://img.shields.io/badge/MINOR-blue?style=for-the-badge)

**Data de Lançamento:** 09 de abril de 2026

# V. 1.79

> feat: padronização de alocações de usuários no cartório (tipos, chips, cores, acessibilidade e ordenação)

## Nível de Impacto

🟡 **MEDIO**

## Alterações

- Centralização de tipos de alocação em `CartorioAlocacaoTipo` (`all`, `rule`, `specific`) e uso em repositórios `*AlocadosRepository` do cartório.
- Criação de `CartorioAlocacaoChipHelper` para:
  - título/aria-label padronizados (`prefixo: Nome - micro-frase`);
  - ordenação (`all -> rule -> specific`, desempate por iniciais A-Z);
  - descarte de tipo inválido com log JSON (`tipo`, `entity_id`, `usuario_id`, `origem`).
- Refatoração das views de cartório (listas, edição parcial e ofícios edit) para usar o helper único e remover duplicação de lógica.
- Ajuste visual dos chips em `table.css`:
  - `all` neutro;
  - `rule` warning;
  - `specific` primária.
- Ajuste do preview de alocação direta no create (`cartorio-alocacao-direcionada-create.js`) para usar o mesmo DOM de chip da listagem.
- Inclusão da especificação funcional/técnica em `docs/20-modules/specs/cartorio-alocacoes-padrao.md`.

---

*Documento de release atualizado conforme padrão de alocações do cartório.*
