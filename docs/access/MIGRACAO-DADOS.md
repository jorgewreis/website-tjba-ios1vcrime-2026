# Migração de dados — perfis compostos e `codigo_area`

Siga na ordem. Faça **backup completo** do banco antes.

## 1. Banco: coluna `setores.codigo_area`

1. Abra o MySQL (phpMyAdmin, CLI ou cliente preferido).
2. Execute o ficheiro:

   `database/migrations/20260322_setores_codigo_area.sql`

3. Confira setores sem área:

   ```sql
   SELECT id_setor, codigo, nome, codigo_area
   FROM setores
   WHERE ativo = 1 AND (codigo_area IS NULL OR codigo_area = '');
   ```

4. Para cada linha, atualize manualmente, por exemplo:

   ```sql
   UPDATE setores SET codigo_area = 'CARTORIO' WHERE id_setor = 'SET-...';
   ```

   Valores permitidos: **`CARTORIO`**, **`GABINETE`**, **`AUDIENCIAS`**.

## 2. Inventário de perfis atuais

```sql
SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil, COUNT(*) AS q
FROM usuario_perfil
GROUP BY UPPER(TRIM(perfil))
ORDER BY perfil;
```

Anote quem ainda tem `ESTAGIARIO`, `SERVIDOR`, `CARTORIO`, `GABINETE` ou `AUDIENCIA` sem sufixo composto.

## 3. Regra de negócio para gerar compostos

Para cada **usuário interno** (não estudante/consultor externo):

1. Liste **setores** do usuário e o `codigo_area` de cada um:

   ```sql
   SELECT u.id_usuario, s.nome, s.codigo_area
   FROM usuarios u
   INNER JOIN usuario_setor us ON us.usuario = u.id_usuario
   INNER JOIN setores s ON s.id_setor = us.setor
   WHERE u.id_usuario = 'USR-...';
   ```

2. Defina o **cargo** por utilizador:
   - Quem era **estagiário** → prefixo `ESTAGIARIO-`
   - Quem era **servidor** (ou recebia `CARTORIO`/`GABINETE` só como “área”) → prefixo `SERVIDOR-`

3. Para **cada** `codigo_area` distinto da lotação, crie **um** perfil composto, por exemplo:
   - área `CARTORIO` + estagiário → `ESTAGIARIO-CARTORIO`
   - área `GABINETE` + servidor → `SERVIDOR-GABINETE`

4. IDs `id_usu_perfil`: use o **mesmo gerador** que o PHP já usa em `usuarios-edit.php` (inserções `INSERT INTO usuario_perfil (id_usu_perfil, ...)`). Não reutilize IDs.

## 4. Inserir compostos (exemplo manual)

```sql
INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil)
VALUES ('UPE-GERADO_PELO_SISTEMA_26CHARS', 'USR-xxxxxxxxxxxxxxxxxxxxxxxxxx', 'SERVIDOR-CARTORIO');
```

Repita por utilizador e por área (várias linhas se tiver Cartório + Gabinete).

## 5. Utilizadores ADMIN / CHEFIA

Quem já tem `ADMIN` ou `CHEFIA` (ou `DIRETORA` / `MAGISTRADA`) **não precisa** de composto para aceder ao sistema, mas pode mantê-los para coerência com a lotação (opcional).

## 6. Testar login

1. Com um utilizador **só** com `ESTAGIARIO-CARTORIO`, abra o dashboard e uma página de Cartório.
2. O mesmo utilizador **não** deve abrir páginas de Gabinete (403).
3. Com `SERVIDOR-GABINETE`, teste Ofícios / Monitoramento.

## 7. Remover perfis legados (último passo)

Só depois de **todos** os internos operacionais terem compostos corretos:

```sql
DELETE FROM usuario_perfil
WHERE UPPER(TRIM(perfil)) IN (
  'ESTAGIARIO', 'SERVIDOR', 'CARTORIO', 'GABINETE', 'AUDIENCIA'
);
```

**Não execute** se ainda existir alguém que dependa desses valores.

## 8. Novo login

Peça a todos que **fechem sessão e voltem a entrar** após a migração.

## 9. Modo B (páginas restritas)

Se uma página deve ser **só** para `SERVIDOR-CARTORIO` (por exemplo), edite `app/access/PageAccessRegistry.php`, constante `EXACT_PAGE_PROFILES`, e documente a linha em `docs/access/matriz-cartorio.md`.
