# Configuração do servidor para deploy (DEV e PROD)

## Proteção da conexão SSH

O deploy aceita o secret `DEPLOY_HOST_KEY_SHA256` com a impressão digital
SHA256 da chave de host do servidor, obtida por um console administrativo
confiável. Quando configurado, somente a chave correspondente é aceita.
Enquanto o secret estiver ausente, o workflow mantém a conexão anterior e
emite um aviso: a identidade do servidor ainda não está fixada.

## Túnel WireGuard do deploy de produção

O workflow de produção estabelece um peer WireGuard temporário no runner e
conecta o SSH a `10.222.231.1`. O peer do CI usa somente `10.222.231.3/32` e
roteia apenas `10.222.231.1/32`; não habilita encaminhamento para outras redes.
O endpoint usa UDP `51820`, já reservado ao serviço WireGuard do servidor.

Guarde a chave privada do peer exclusivamente no secret
`WG_CLIENT_PRIVATE_KEY` do environment GitHub `production`. Não a grave no
repositório, em arquivo de configuração versionado ou no servidor. O servidor
recebe apenas a chave pública do peer e `AllowedIPs = 10.222.231.3/32` em
`/etc/wireguard/wg0.conf`. O environment `production` deve permitir deploy
somente da branch `main`.

Para rotacionar o peer, gere um novo par de chaves em um ambiente confiável,
atualize o secret do environment e substitua a chave pública do peer no servidor.
Confirme o handshake e o SSH pelo endereço WireGuard antes de remover a chave
anterior. Preserve `DEPLOY_HOST_KEY_SHA256`; o túnel não substitui a validação
da identidade SSH do servidor.

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

2. **Diretório da aplicação (`DEV_PATH` e `PROD_PATH`)**
   Os dois workflows sincronizam a raiz do repositório nesses destinos, cada
   ambiente no seu diretório. Esses valores são a raiz da aplicação, **não**
   necessariamente o Document Root. Por exemplo, se `PROD_PATH` for
   `/srv/ios1vcrime`, a raiz pública planejada é `/srv/ios1vcrime/public`.
   Aplique a mesma relação ao ambiente DEV, com caminhos separados.

   No painel e na configuração efetiva do servidor web, verifique que o
   Document Root aponta para o subdiretório `public/` da aplicação correspondente.
   Arquivos como `.env`, `config/`, `app/`, `database/`, `scripts/` e `vendor/`
   devem ficar fora da raiz pública. Confirme regras de roteamento, aliases,
   links simbólicos, acesso a arquivos ocultos e permissões do processo PHP;
   mudar apenas o painel ou o secret não comprova essa separação.

   **Não altere caminhos ou configuração de produção apenas com base neste
   exemplo.** Antes de qualquer mudança, identifique destino e Document Root
   efetivos por ambiente, revise efeitos em sessão, assets, uploads e rollback,
   teste com arquivos sintéticos em ambiente isolado e use o procedimento de
   implantação autorizado. Se estiverem apontando ao mesmo diretório, trate como
   risco a investigar, não como prova de exposição ou de correção automática.

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
