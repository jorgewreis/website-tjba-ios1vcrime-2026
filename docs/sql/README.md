# Scripts SQL e padrão de IDs

## Padrão de identificadores (ID)

Toda criação de tabela e geração de ID no sistema deve seguir a **regra de 30 caracteres**:

- **Formato:** 3 letras (prefixo) + hífen + 26 caracteres (código).
- **Exemplo:** `EST-1A2B3C4D5E6F7G8H9I0J1K2L3M4`.

Documentação completa: [docs/00-foundation/id-padrao-banco-dados.md](../00-foundation/id-padrao-banco-dados.md).

## Arquivos nesta pasta

- **plano-migracao-cartorio-oficios.md** – Plano de migração do legado `CartorioOficios` para `oficios_cartorio` (fonte: [`CartorioOficios.sql`](../../CartorioOficios.sql) na raiz do repositório).
- **estudantes.sql** – Tabela de cadastro de estudantes (consulta a audiências/atas/pautas). ID: `EST-` + 26 chars.

Ao criar novos scripts SQL para tabelas, usar sempre `CHAR(30)` (ou `VARCHAR(30)`) para a chave primária e o formato prefixo + hífen + código.
