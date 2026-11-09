# Publicação em produção

O workflow de produção executa a verificação de sintaxe PHP e a auditoria das
dependências declaradas antes da conexão com o servidor. Publicações do mesmo
workflow usam um grupo de concorrência, sem cancelar a execução em andamento.

As dependências PHP são instaladas em diretório temporário ao lado de `vendor`.
Se a instalação falhar, o diretório atual é preservado. Se a troca falhar, o
workflow tenta restaurar o diretório anterior. Essas medidas não tornam a
publicação atômica: a sincronização do código e as migrações ainda ocorrem no
ambiente ativo.

## Antes de alterar migrações

Documente as tabelas afetadas, a compatibilidade com as versões anterior e
nova, e o procedimento de recuperação. Confirme que existe backup restaurável
e homologue a restauração em ambiente separado antes de mudanças irreversíveis.
Reverter o código não desfaz alterações no banco.

## Próxima etapa para releases atômicas

Antes de modificar a estrutura do servidor, identificar o diretório publicado,
os caminhos persistentes, as permissões e os agendamentos. A solução deve
preparar uma release completa separada, validar sua configuração e dependências
e alternar o ponto de publicação com procedimento de retorno. Essa etapa ainda
depende de acesso administrativo e homologação no servidor.
