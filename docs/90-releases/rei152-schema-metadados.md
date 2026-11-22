# REI-152 — consulta preparada de metadados

Este roteiro é somente leitura e **não foi executado**. Não carrega `.env`,
não estabelece conexão, não consulta linhas de pessoas/documentos e não prova
integridade dos dados. Antes de executar, confirmar por canal independente o
ambiente, servidor, base e conta; usar conta com privilégios mínimos de leitura
de metadados e registrar evidência sem credenciais. Se a identidade não puder
ser comprovada, não conectar. Não executar migrations ou `SHOW CREATE` completo
em evidência pública; defaults, nomes de constraints e comentários podem conter
informações internas.

Após conexão somente de leitura ao alvo identificado e seleção explícita da base,
confirmar `SELECT DATABASE()` de forma segura no ambiente autorizado. As consultas
abaixo usam apenas `DATABASE()` e a tabela fixa; resultado vazio pode significar
objeto ausente ou visibilidade insuficiente, e não comprova ausência da tabela.

```sql
SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partes_documentos';

SELECT ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       CHARACTER_SET_NAME, COLLATION_NAME, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partes_documentos'
ORDER BY ORDINAL_POSITION;

SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partes_documentos'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'partes_documentos'
ORDER BY CONSTRAINT_NAME;

SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'partes_documentos'
ORDER BY TRIGGER_NAME;
```

A interpretação exige conferir tipos, nulabilidade, prefixos de índices, ordem,
colação e versão do servidor; índice sobre valores armazenados não demonstra que
valores legados estejam normalizados. Constraints parciais, expressões, triggers
e permissões podem exigir inspeção adicional específica da versão. Metadados não
comprovam ausência de duplicatas, não reconciliam CPF legado e não justificam DDL.
Não imprimir `SHOW GRANTS`, DSN, variáveis de ambiente, dados ou mensagens de
conexão que possam conter credenciais. Registrar apenas fatos necessários com
acesso restrito e distinguir consulta não executada, permissão insuficiente e
resultado efetivamente observado.
