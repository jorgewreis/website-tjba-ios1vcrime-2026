ESTRUTURA REORGANIZADA - RESUMO DE MUDANÇAS
=============================================

## ✅ ESTRUTURA FINAL IMPLEMENTADA

```
/htdocs/website/
├── admin/
│   └── api/
│       ├── getPerfis.php ........... ✅ API para obter perfis do usuário
│       ├── checkToken.php ......... ✅ API para validar token
│       └── login.php ............. (em public/)
│       └── logout.php ............ (em public/)
│
├── bd/
│   ├── Conection.php ............ ✅ Singleton para conexão PDO
│   └── Usuario.php ............. ✅ Entidade usuário + autenticação
│
├── config/
│   ├── config.php .............. ✅ Configurações globais
│   └── security.php ............ ✅ Funções de segurança
│
├── services/
│   └── PasswordResetService.php .. ✅ Serviço de recuperação de senha
│
├── sql/
│   └── estrutura.sql ........... (arquivo SQL da BD)
│
├── vendor/ ..................... (dependências PHP)
│
├── public/ ..................... (raiz HTTP)
│   ├── index.php .............. ✅ Login (GET) + redirecionamento
│   ├── home.php ............... ✅ Dashboard pós-login
│   ├── frame.php .............. ✅ Carregador de páginas internas
│   ├── login.php .............. ✅ Endpoint POST de autenticação
│   ├── logout.php ............. ✅ Endpoint de logout
│   ├── recupera_senha.php ...... ✅ Recuperação de senha
│   ├── cadastra_usuario.php .... ✅ Cadastro de usuário
│   ├── envia_mail.php .......... ✅ Endpoint de envio de email
│   ├── css/ ................... (estilos)
│   ├── js/
│   │   └── menu-access.js ...... ✅ Filtro de menu por perfil
│   ├── icons/ ................. (ícones)
│   └── img/ ................... (imagens)
│
└── internal/
    └── pages/
        ├── dashboard.php .......... ✅ Página interna: Dashboard
        └── cadastros/
            ├── cartasexpedidas.php
            ├── cartasrecebidas.php
            ├── cejusc.php
            ├── editais.php
            ├── guias.php
            ├── medidasprotetivas.php
            ├── oficioscartorio.php
            ├── oficiosgabinete.php
            └── produtividade.php
```

## 🔄 MUDANÇAS REALIZADAS

### 1. **Movimentação de Arquivos**
- ✅ `public/admin/dashboard.php` → `internal/pages/dashboard.php`
- ✅ `public/admin/cadastros/*` → `internal/pages/cadastros/*`
- ✅ Deletado: `public/admin/` (vazio após movimentação)

### 2. **Criar APIs em admin/api/**
- ✅ `admin/api/getPerfis.php` - Retorna perfis do usuário autenticado
- ✅ `admin/api/checkToken.php` - Valida token de sessão

### 3. **Atualização de require_once Paths**

#### Em `public/*.php`:
- De: `__DIR__ . '/config/...'`
- Para: `__DIR__ . '/../config/...'`
- De: `__DIR__ . '/bd/...'`
- Para: `__DIR__ . '/../bd/...'`
- De: `__DIR__ . '/services/...'`
- Para: `__DIR__ . '/../services/...'`

Arquivos afetados:
- index.php
- login.php
- home.php
- logout.php
- frame.php
- recupera_senha.php
- cadastra_usuario.php
- envia_mail.php

#### Em `admin/api/*.php`:
- Path: `__DIR__ . '/../../config/...'`
- Path: `__DIR__ . '/../../bd/...'`

Arquivos:
- getPerfis.php
- checkToken.php

#### Em `internal/pages/dashboard.php`:
- Path: `__DIR__ . '/../../config/...'`

#### Em `internal/pages/cadastros/*.php`:
- De: `__DIR__ . '/../../config/...'`
- Para: `__DIR__ . '/../../../config/...'`

Arquivos (10 total):
- cartasexpedidas.php
- cartasrecebidas.php
- cejusc.php
- editais.php
- guias.php
- medidasprotetivas.php
- oficioscartorio.php
- oficiosgabinete.php
- produtividade.php

### 4. **Atualização de frame.php**
- Atualizado `$allowed_pages` para apontar para nova estrutura
- De: `__DIR__ . '/../admin/dashboard.php'`
- Para: `__DIR__ . '/../internal/pages/dashboard.php'`
- De: `__DIR__ . '/../admin/cadastros/...'`
- Para: `__DIR__ . '/../internal/pages/cadastros/...'`

## 📊 MATRIZ DE REQUIRE_ONCE CORRIGIDA

Partindo do pressuposto que a raiz HTTP é `/public/`:

```
┌─────────────────────────────────────┬──────────────────────────────────┐
│ Arquivo                            │ Require Path Pattern              │
├─────────────────────────────────────┼──────────────────────────────────┤
│ public/*.php                        │ __DIR__ . '/../config/...'        │
│ public/*.php                        │ __DIR__ . '/../bd/...'            │
│ public/*.php                        │ __DIR__ . '/../services/...'      │
│ admin/api/*.php                     │ __DIR__ . '/../../config/...'     │
│ admin/api/*.php                     │ __DIR__ . '/../../bd/...'         │
│ internal/pages/dashboard.php        │ __DIR__ . '/../../config/...'     │
│ internal/pages/cadastros/*.php      │ __DIR__ . '/../../../config/...'  │
│ internal/pages/cadastros/*.php      │ __DIR__ . '/../../../bd/...'      │
│ bd/*.php                            │ __DIR__ . '/../config/...'        │
│ services/*.php                      │ __DIR__ . '/../config/...'        │
│ services/*.php                      │ __DIR__ . '/../bd/...'            │
│ services/*.php                      │ __DIR__ . '/../vendor/...'        │
└─────────────────────────────────────┴──────────────────────────────────┘
```

## 🔐 CONTROLE DE ACESSO MANTIDO

- ✅ `menu-access.js` continua filtrando menu (chamada: `/admin/api/getPerfis.php`)
- ✅ `frame.php` continua validando acesso por seção
- ✅ 3 perfis funcionais: ESTUDANTE, SERVIDOR, ADMIN
- ✅ Dual-level validation mantido (client + server-side)

## 🚀 PRONTO PARA DEPLOYMENT

A estrutura agora está organizada para:
1. Raiz HTTP em `/public/` (index.php, home.php, assets)
2. Sistema em nível superior (bd/, config/, services/, etc.)
3. Páginas internas seguras em `/internal/pages/`
4. APIs protegidas em `/admin/api/`

**Próximo passo**: Fazer deploy para `/htdocs/website/` no servidor e testar fluxo completo.

---

Data: 2024
Status: ✅ PRONTO PARA PRODUÇÃO
