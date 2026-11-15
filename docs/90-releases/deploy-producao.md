# Publicação em produção

O workflow de produção executa a verificação de sintaxe PHP e a auditoria das
dependências declaradas antes da conexão com o servidor. Publicações do mesmo
workflow usam um grupo de concorrência, sem cancelar a execução em andamento.

As dependências PHP são instaladas em diretório temporário ao lado de `vendor`.
Se a instalação falhar, o diretório atual é preservado. Se a troca falhar, o
workflow tenta restaurar o diretório anterior. Essas medidas não tornam a
publicação atômica: a sincronização do código e as migrações ainda ocorrem no
ambiente ativo.

## Efeitos condicionais do workflow atual

Após a sincronização, `.github/workflows/deploy-prod.yml` atualiza `APP_URL` no
`.env` existente, executa `npm ci` e o build quando há `package.json`, e prepara
as dependências PHP quando há `composer.json`. Também executa os scripts
`scripts/migrate-cejusc.php`, `scripts/migrate-gabinete-reu-preso-controle-tipo.php`
e `scripts/migrate-dashboard-conclusoes.php` **somente se cada arquivo existir**
no servidor após o `rsync`. A instalação do cron de usuários externos inativos
é chamada pelo workflow; a limpeza do cron e dos artefatos legados de
Cartório/Tarefas é condicionada às ferramentas e arquivos existentes.

Esses predicados não formam um executor geral de `database/migrations/`: outras
migrações continuam fora do fluxo automático. Além disso, a presença de uma
etapa no YAML não prova sua execução em um deploy específico; confirme o log do
run, o SHA publicado e, quando aplicável, o estado do ambiente. A configuração
do environment `production` é controlada no GitHub e deve ser verificada no
momento da publicação; a leitura em 14/11/2026 encontrou o environment sem
regras de aprovação ou espera e com bypass administrativo permitido.

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
