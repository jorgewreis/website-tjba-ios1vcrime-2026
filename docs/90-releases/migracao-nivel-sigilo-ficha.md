# Migração manual do nível de sigilo

A coluna `processos.nivel_sigilo` é informativa, admite valores de 1 a 5 e inicia com `NULL`, sem preenchimento retroativo. A flag `processos.sigiloso`, os perfis e a autorização setorial continuam inalterados. O deploy normal não executa esta migração.

1. Verifique o engine da tabela, volumes, índices, espaço temporário, réplicas e transações abertas. Faça um backup privado e verifique uma restauração isolada antes de aplicar.
2. Use uma conexão dedicada e execute `database/migrations/20261127_add_processos_nivel_sigilo.sql`. A operação usa espera de até cinco segundos para o bloqueio de metadados, exige `ALGORITHM=INSTANT` e não recorre a uma cópia silenciosamente. Em caso de erro, encerre a sessão em vez de reaproveitá-la.
3. Se o motor recusar o algoritmo instantâneo ao adicionar o `CHECK`, não execute logo outra DDL em produção. Teste primeiro em uma cópia isolada. Somente em janela autorizada, na mesma conexão, defina `SET @ficha_nivel_permitir_copia = 1;` e execute a migração. Essa alternativa usa `ALGORITHM=COPY, LOCK=SHARED`; a tabela será reconstruída e as escritas ficam bloqueadas durante a cópia.
4. Confirme que a coluna é anulável e que o `CHECK` está ativo. Em ambiente isolado, verifique que `NULL`, 1 e 5 são aceitos e que 0 e 6 são rejeitados. Não altere registros reais durante a validação. Teste que apenas a administração com acesso ao setor consegue classificar um processo já sigiloso.

Para rollback de aplicação, preserve a coluna e os valores; não derrube a coluna nem modifique o sigilo cadastral.
