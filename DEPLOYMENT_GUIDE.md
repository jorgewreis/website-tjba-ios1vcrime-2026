INSTRUÇÕES DE DEPLOYMENT PÓS-REORGANIZAÇÃO
===========================================

## 🚀 PREPARAÇÃO PARA PRODUÇÃO

### Estrutura Local Atual (Workspace)
```
d:\Projects\Clients\website-tjba-ios1vcrime-2026\
├── admin/api/ ...................... APIs protegidas
├── bd/ ............................ Classes de dados
├── config/ ........................ Configurações
├── internal/pages/ ................ Páginas internas
├── public/ ........................ Raiz HTTP
├── services/ ...................... Serviços
├── sql/ ........................... Scripts SQL
├── vendor/ ........................ Dependências PHP
└── .github/, .vscode/, README.md, etc.
```

### Estrutura no Servidor (Produção)
```
/var/www/html/ ou /home/user/public_html/
└── website/ ...................... Raiz do projeto
    ├── admin/api/ ............... Mesma estrutura
    ├── bd/
    ├── config/
    ├── internal/pages/
    ├── public/ .................. Raiz HTTP (DocumentRoot)
    ├── services/
    ├── sql/
    └── vendor/
```

## 📋 PASSO A PASSO DO DEPLOYMENT

### 1. Preparação Local
- [x] Estrutura reorganizada ✓
- [x] Paths corrigidos ✓
- [x] APIs criadas ✓
- [x] Compatibilidade verificada ✓

### 2. Transferência de Arquivos

#### Via SCP/SFTP:
```bash
# Do servidor de desenvolvimento
scp -r ~/Projects/Clients/website-tjba-ios1vcrime-2026/* user@servidor:/var/www/html/website/

# Ou via rsync (mais eficiente)
rsync -avz --delete ~/Projects/Clients/website-tjba-ios1vcrime-2026/ user@servidor:/var/www/html/website/
```

#### Via FTP:
1. Conectar via cliente FTP (FileZilla, WinSCP)
2. Transferir pasta inteira para `/home/user/public_html/website/`
3. Manter estrutura idêntica

### 3. Configuração no Servidor

#### A. Permissões de Diretórios
```bash
cd /var/www/html/website
chmod 755 . admin bd config internal public services sql vendor
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

#### B. Permissões Especiais (se necessário)
```bash
# Diretório de logs (se usado)
mkdir -p /var/www/html/website/logs
chmod 755 /var/www/html/website/logs
chown www-data:www-data /var/www/html/website/logs

# Diretório de uploads (se implementado)
mkdir -p /var/www/html/website/uploads
chmod 755 /var/www/html/website/uploads
chown www-data:www-data /var/www/html/website/uploads
```

#### C. Configuração do Web Server

**Apache (.htaccess)**:
```apache
# Arquivo: /var/www/html/website/public/.htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /website/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?/$1 [L]
</IfModule>
```

**Nginx (server block)**:
```nginx
server {
    listen 80;
    server_name ios1vcrime.tjba.gov.br;
    root /var/www/html/website/public;
    
    index index.php;
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    # Redirecionar /admin/api/ para root
    location /admin/ {
        try_files $uri $uri/ /index.php?/$uri;
    }
}
```

### 4. Configuração do Banco de Dados

#### Se não existir database:
```bash
# Via SSH no servidor
mysql -u root -p

# No MySQL:
CREATE DATABASE tjba_primeira_vara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tjba_primeira_vara;
SOURCE /var/www/html/website/sql/estrutura.sql;
```

#### Verificar tabelas:
```sql
SHOW TABLES;
-- Deve ter: usuario, usuario_perfil, [outras tabelas judiciais]
```

### 5. Configuração de Variáveis de Ambiente

#### Editar `.env` no servidor:
```bash
cd /var/www/html/website
nano .env
```

Conteúdo esperado:
```
DB_HOST=localhost
DB_USER=tjba_user
DB_PASSWORD=senha_segura
DB_NAME=tjba_primeira_vara
DB_CHARSET=utf8mb4

# Emails
SMTP_HOST=outlook.office365.com
SMTP_PORT=587
SMTP_USER=email@tjba.gov.br
SMTP_PASSWORD=senha_app

# Sistema
APP_URL=https://ios1vcrime.tjba.gov.br
TIMEZONE=America/Bahia
```

### 6. Teste de Conectividade

#### Verificar acesso:
```bash
# SSH no servidor
curl -I http://localhost/website/public/index.php
# Deve retornar HTTP 200

# Testar API de perfis (após login)
curl -X GET http://localhost/website/admin/api/getPerfis.php
# Deve retornar HTTP 401 (não autenticado) com JSON
```

### 7. Verificação de SSL/HTTPS

```bash
# Ativar HTTPS com Let's Encrypt
sudo certbot certonly --apache -d ios1vcrime.tjba.gov.br

# Redirecionar HTTP → HTTPS
<VirtualHost *:80>
    ServerName ios1vcrime.tjba.gov.br
    Redirect permanent / https://ios1vcrime.tjba.gov.br/
</VirtualHost>
```

## ✅ CHECKLIST DE VERIFICAÇÃO PÓS-DEPLOY

- [ ] Acessar https://ios1vcrime.tjba.gov.br/ (página login)
- [ ] Fazer login com usuário válido
- [ ] Validar redirecionamento para /home.php
- [ ] Clicar em menu item → carregar página via frame.php
- [ ] Testar 3 perfis diferentes:
  - [ ] ESTUDANTE: vê apenas "Estudantes"
  - [ ] SERVIDOR: vê "Cartório", "Gabinete", "Sala de Audiências"
  - [ ] ADMIN: vê tudo + "Admin"
- [ ] Testar APIs:
  - [ ] GET /admin/api/getPerfis.php → retorna JSON
  - [ ] GET /admin/api/checkToken.php → retorna JSON
- [ ] Testar logout → limpa sessão, redireciona para login
- [ ] Testar recuperação de senha
- [ ] Verificar logs do servidor (não há erros)
- [ ] Testar F5 em página interna (valida token)
- [ ] Testar timeout de 8 horas (sessão expira)

## 🔧 TROUBLESHOOTING

### Erro 404 no login
**Causa**: DocumentRoot não está apontando para `/website/public/`
**Solução**: Ajustar configuração do servidor (Apache/Nginx)

### Erro "Acesso negado" ao clicar menu
**Causa**: Path em frame.php apontando para local errado
**Solução**: Verificar `$allowed_pages` em `public/frame.php`

### Erro "Arquivo não encontrado"
**Causa**: Require path incorreto
**Solução**: Testar manualmente com `php -r "include '/path/to/file.php';"`

### Erro de Database
**Causa**: Credenciais `.env` erradas
**Solução**: Verificar `config/config.php`, testar conexão com `mysql -u user -p`

### API retorna 401
**Causa**: Sessão expirada ou token inválido
**Solução**: Fazer login novamente, verificar TTL em `config/security.php`

## 📚 DOCUMENTAÇÃO DE REFERÊNCIA

- [ESTRUTURA_REORGANIZADA.md](ESTRUTURA_REORGANIZADA.md) - Detalhes da estrutura
- [CHECKLIST_REORGANIZACAO.md](CHECKLIST_REORGANIZACAO.md) - Verificações completas
- [MENU_ACCESS_CONTROL.md](MENU_ACCESS_CONTROL.md) - Controle de acesso
- [REFERENCIA_ENDPOINTS.md](REFERENCIA_ENDPOINTS.md) - APIs disponíveis

## 🔐 SEGURANÇA

- [x] PDO prepared statements em uso
- [x] CSRF tokens validados
- [x] Senhas com Argon2ID
- [x] Sessions com HttpOnly + Secure (HTTPS)
- [x] Validação de acesso server-side
- [x] Rate limiting (se implementado)
- [x] Logs de auditoria (se implementado)

---

**PRÓXIMO PASSO**: Coordenar com administrador do servidor para executar deployment.

**CONTATO**: Para dúvidas técnicas, ref. jorgew.reis@outlook.com

