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
as dependências PHP quando há `composer.json`. Antes do `rsync`, o workflow aplica
primeiro a migration de setor responsável, sem preencher processos legados. A
coluna `processos.id_setor_responsavel` permanece nula até atribuição explícita;
o Cartório ativo e inequívoco é a unidade efetiva enquanto o campo estiver nulo.
O controle por lotação permanece válido e não há atualização em massa. Essa etapa usa `ALGORITHM=COPY, LOCK=SHARED`, então volume da tabela,
espaço disponível e janela operacional precisam ser avaliados antes do deploy.
Em seguida, envia o script e o SQL de idempotência de Cartas Expedidas a um
diretório temporário
privado no host e executa a migração contra a aplicação/schema atual. Se a
configuração CLI, a conexão ou a migração falhar, o código novo ainda não foi
sincronizado. O diretório temporário é removido ao final do passo. Depois do
`rsync`, também executa os scripts `scripts/migrate-cejusc.php`,
`scripts/migrate-gabinete-reu-preso-controle-tipo.php` e
`scripts/migrate-dashboard-conclusoes.php` **somente se cada arquivo existir**
no servidor. Até a migração de idempotência estar presente, a API recusa novas
criações com chave idempotente usando 503; não há fallback para criação sem
proteção. Em rollback de código, preserve as colunas e o índice junto aos dados
de idempotência para que retries antigos continuem deduplicados. A instalação do
cron de usuários externos inativos é chamada pelo workflow; a limpeza do cron e
dos artefatos legados de Cartório/Tarefas é condicionada às ferramentas e arquivos existentes.

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

## Backup e migração de prioridade de Cartas Expedidas

1. **Acesso para recuperação.** Gateway e Actions usam identidades SSH diferentes.
   Se a chave do Gateway for recusada, confirme a identidade do host e, pelo
   painel do provedor ou outro acesso administrativo já autorizado, confira a
   chave **pública** gerenciada para a conta cadastrada. Revalide no Gateway
   com uma operação somente leitura. Não substitua fingerprints, não remova
   outras chaves, não enfraqueça o SSH e não use senha como tentativa repetida.
   Um deploy verde no Actions não confirma o acesso pelo Gateway.
2. **Backup privado.** Rode o workflow manual na main com **Somente backup**.
   Esse modo não publica código nem aplica migrações. Guarde somente o ID e o
   SHA-256 de `BACKUP OK` para referência; o arquivo fica no diretório privado
   do usuário de deploy no próprio VPS. Se falhar, use a categoria/errno do log
   para direcionar diagnóstico local com acesso autorizado. Não copie dump,
   stderr bruto ou configuração de banco para o GitHub. Falha sem classificação
   suficiente não justifica alterar permissões ou repetir às cegas.
3. **Prova de recuperação.** Teste o mesmo backup em instância MySQL isolada e
   privada, nunca no schema ativo, sem copiar dados da Vara para fora do VPS.
   Valide a abertura da cópia, schema da tabela e recuperação esperada sem
   registrar conteúdo de registros. O checksum confirma integridade, não
   restaurabilidade; só confirme a recuperação depois do teste.
4. **Migração.** Na janela autorizada, execute o workflow manual na main sem
   **Somente backup**, selecione **Migrar prioridade**, informe ID e SHA-256 do
   mesmo arquivo e confirme restauração isolada e janela. Antes do DDL, o script
   recusa arquivo ausente, simbólico, sem permissões privadas, alterado ou com
   mais de 24 horas. O modo continua integrado ao deploy existente. Confirme
   o resultado da coluna, padrão e logs de publicação; não use produção para
   criar registros fictícios. Um merge sozinho não ativa a migração de prioridade.

## Próxima etapa para releases atômicas

Antes de modificar a estrutura do servidor, identificar o diretório publicado,
os caminhos persistentes, as permissões e os agendamentos. A solução deve
preparar uma release completa separada, validar sua configuração e dependências
e alternar o ponto de publicação com procedimento de retorno. Essa etapa ainda
depende de acesso administrativo e homologação no servidor.
