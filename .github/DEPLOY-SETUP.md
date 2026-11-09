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

Se o workflow **Sync files to server** falhar com `rsync: [receiver] open "..." failed: Permission denied (13)`, o usuário de deploy não tem permissão de escrita no diretório do site no servidor.

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

## Correção (uma vez no servidor)

Conecte-se ao servidor por **SSH como root** (ou com um usuário que possa usar `chown`) e rode:

```bash
chown -R USUARIO_DE_DEPLOY: CAMINHO_DO_SITE
```

- **USUARIO_DE_DEPLOY**: o usuário que você identificou acima (ex.: `clp_abc123`).  
- **CAMINHO_DO_SITE**: o caminho completo do site (ex.: `/home/clp_abc123/htdocs/ios1vcrime`).  
- O `:` após o usuário usa o grupo padrão desse usuário (geralmente o mesmo nome).

### Exemplo (CloudPanel)

Usuário do site: `clp_abc123`  
Document root do site: `/home/clp_abc123/htdocs/ios1vcrime`

Como **root** no servidor:

```bash
chown -R clp_abc123: /home/clp_abc123/htdocs/ios1vcrime
```

Depois disso, rode o workflow de deploy correspondente de novo; o rsync deve concluir sem "Permission denied".
