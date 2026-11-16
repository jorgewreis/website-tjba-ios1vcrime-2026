# Configuração do servidor para deploy (DEV e PROD)

## Proteção da conexão SSH

O deploy aceita o secret `DEPLOY_HOST_KEY_SHA256` com a impressão digital
SHA256 da chave de host do servidor, obtida por um console administrativo
confiável. Quando configurado, somente a chave correspondente é aceita.
Enquanto o secret estiver ausente, o workflow mantém a conexão anterior e
emite um aviso: a identidade do servidor ainda não está fixada.

A chave privada antiga foi retirada da versão atual do repositório, mas sua
retirada não revoga acessos nem apaga o histórico. É necessário substituir a
chave autorizada no servidor, atualizar `DEPLOY_KEY_B64` e só então revogar a
chave anterior, após confirmar que o novo acesso funciona.

Se o workflow **Sync files to server** falhar com `rsync: [receiver] open "..." failed: Permission denied (13)`, investigue o acesso do usuário de deploy ao caminho indicado no erro e aos seus diretórios ancestrais.

Os **secrets** (`DEPLOY_USER`, `DEV_PATH`, `PROD_PATH`) não podem ser lidos no GitHub depois de salvos. Use as dicas abaixo para saber qual usuário e caminho usar.

---

## Como descobrir usuário e caminho (sem ler os secrets)

1. **Usuário de deploy (`DEPLOY_USER`)**  
   É o usuário com o qual você se conecta por SSH ao servidor para esse site.  
   - No **CloudPanel**: é o usuário do site (ex.: `clp_abc123`), que aparece em Sites → seu site → usuário/detalhes.  
   - Ou conecte por SSH com a chave do deploy e rode `whoami` — o resultado é o `DEPLOY_USER`.

2. **Caminho do site (`DEV_PATH` e `PROD_PATH`)**  
   São os diretórios no servidor onde o código de cada ambiente deve ficar.  
   - No **CloudPanel**: Sites → seu site → **Document Root** ou caminho do site (ex.: `/home/clp_abc123/htdocs/ios1vcrime`).  
   - Para `DEV_PATH`, use o document root do `dev.ios1vcrime.com.br`.
   - Para `PROD_PATH`, use o document root do `ios1vcrime.com.br`.

---

## Diagnóstico e correção pontual de permissões

Confirme o ambiente, o caminho efetivo do deploy e o arquivo indicado no log.
O document root informado pelo painel pode não ser o diretório usado no secret.
Identifique também o usuário do pool PHP e as permissões necessárias à aplicação.
Não altere recursivamente a propriedade do site: `.env`, `storage/` e outros
dados persistentes podem ter proprietários e necessidades diferentes do código.

Em uma sessão administrativa autorizada, use consultas somente de leitura nos
caminhos efetivamente identificados (substitua os exemplos; não imprima `.env`):

```bash
id USUARIO_DE_DEPLOY
namei -l -- /caminho/absoluto/do/arquivo-do-log
stat -c '%U:%G %a %n' -- /caminho/absoluto/do/arquivo-do-log
getfacl -p -- /caminho/absoluto/do/arquivo-do-log  # se disponível
```

Verifique também o diretório pai: o rsync precisa atravessar os diretórios e,
conforme a operação, escrever no arquivo e no diretório. Confira ACLs, montagem
somente leitura e espaço disponível antes de concluir que o proprietário deve
mudar. Para um arquivo inexistente, examine o diretório pai existente.

Defina a menor correção necessária para **esse caminho**, com proprietário,
grupo e permissões adequados à aplicação e à política do servidor. Por exemplo,
apenas se o diagnóstico comprovar que um arquivo de código específico deve ser
do usuário de deploy, um administrador pode ajustar aquele arquivo com
`chown USUARIO_DE_DEPLOY -- /caminho/absoluto/do/arquivo`; isso não é uma
correção genérica e não se aplica a `.env` ou `storage/`. Não use `-R`, não
altere o grupo sem necessidade e registre a condição anterior para recuperação.

No workflow de produção atual, `.env` é excluído do rsync e o pós-deploy ajusta
seu proprietário para o usuário do pool PHP e o modo para `600`. Preserve esse
contrato e o acesso do pool; não revele o conteúdo do arquivo. `storage/` também
é excluído do rsync e deve manter sua propriedade e permissões próprias.
Após a correção autorizada, confira novamente o caminho e o acesso necessário,
execute o workflow correspondente e acompanhe o resultado. A execução bem-sucedida
não substitui a verificação do funcionamento da aplicação.
