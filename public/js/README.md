# JavaScript Files Organization

Organização dos arquivos JavaScript seguindo **separação de responsabilidades**.

## Estrutura por Contexto

### Páginas Públicas (Não autenticadas)
Carregam: **cpf.js** + **auth.js**

- **[cpf.js](cpf.js)** - Utilitários de validação e formatação de CPF
  - `cpfSomenteDigitos(v)` - Remove caracteres não numéricos
  - `cpfFormatar(digitos)` - Formata 11 dígitos para padrão XXX.XXX.XXX-XX
  - `cpfAplicarMascaraParcial(valor)` - Máscara viva enquanto o usuário digita
  - `cpfValido(digitos11)` - Valida usando algoritmo mod11
  - `verificaCPF(selector)` - Valida e mascara input automaticamente
  - `initCPF(selector)` - Inicializa eventos do campo CPF
  - **Exports**: `window.initCPF`, `window.verificaCPF`, etc.
  
- **[auth.js](auth.js)** - Autenticação e recuperação de senha
  - `initLogin(formSelector, endpoint, erroId, alertId)` - Gerencia submit do formulário de login
  - `initRecuperaSenha(formSelector, cpfSelector, emailSelector, endpoint)` - Gerencia formulário de recuperação de senha
  - **Exports**: `window.initLogin`, `window.initRecuperaSenha`
  - **Dependência**: Requer `cpf.js` para validação de CPF

### Páginas Internas (Autenticadas)
Carregam: **common.js** + **menu.js**

- **[common.js](common.js)** - Funções comuns para páginas protegidas
  - `validateSessionToken(endpoint)` - Valida token de sessão via API
  - `performLogout(endpoint)` - Faz logout via API
  - `showNotification(message, type, duration)` - Exibe notificações temporárias
  - `confirmAction(message)` - Pede confirmação de ação
  - **Exports**: `window.validateSessionToken`, `window.performLogout`, etc.
  - **Uso**: Pages internas podem usar para feedback, logout, validação de sessão

- **[menu.js](menu.js)** - Navegação e menu do dashboard
  - `initMenu()` - Inicializa estrutura do menu
  - `initMenuActive()` - Marca itens ativos
  - `setupMenuLayout()` - Configura layout responsivo (3 estados: HIDDEN, VISIBLE, FIXED)
  - **Exports**: ES6 module exports (não window global)
  - **Importação**: `import { initMenu, ... } from '/js/menu.js'`

## Páginas e Scripts

| Página | Scripts | Função |
|--------|---------|--------|
| `index.php` | cpf.js + auth.js | Login |
| `recupera_senha.php` | cpf.js + auth.js | Recuperação de senha |
| `home.php` | common.js + menu.js | Dashboard principal |
| `admin/dashboard.php` | common.js + menu.js | Dashboard admin |
| `admin/cadastros/*.php` | common.js + menu.js | Páginas de cadastro |

## Padrão de Carregamento

### Públicas (common.js + auth.js)
```html
<script src="/js/cpf.js" defer></script>
<script src="/js/auth.js" defer></script>
<script defer>
  document.addEventListener("DOMContentLoaded", async () => {
    await initCPF("#cpf");
    await initLogin("#loginForm", "login.php", "erro", "alert-login");
  });
</script>
```

### Internas (common.js + menu.js)
```html
<script src="/js/common.js" defer></script>
<script type="module">
  import { initMenu, initMenuActive, setupMenuLayout } from '/js/menu.js';
  document.addEventListener("DOMContentLoaded", function() {
    initMenu();
    initMenuActive();
    setupMenuLayout();
  });
</script>
```

## Histórico

- **auth.js** - Criado em refatoração (janeiro 2026)
  - Movido de `common.js`: `initLogin()`, `initRecuperaSenha()`
  - Responsabilidade: Autenticação pública apenas

- **cpf.js** - Criado em refatoração (janeiro 2026)
  - Movido de `common.js`: Todas as funções de CPF
  - Responsabilidade: Validação e formatação de CPF

- **common.js** - Refatorado em janeiro 2026
  - Removido: Funções de CPF (→ cpf.js), login (→ auth.js)
  - Mantido: Utilitários para páginas internas
  - Responsabilidade: Funções compartilhadas por páginas autenticadas

- **menu.js** - Mantido como ES6 module
  - Responsabilidade: Navegação e layout responsivo

- **login.js** - Descontinuado
  - Status: DEPRECATED (mantido por compatibilidade)
  - Substituído: Funcionalidades movidas para `auth.js`

## Separação de Responsabilidades

```
Público (não autenticado)
├── cpf.js ..................... Utilitários de CPF
└── auth.js .................... Login e reset senha

Interno (autenticado)
├── common.js .................. Funções comuns
└── menu.js .................... Menu e navegação
```

## Notas

1. **Sem frameworks** - Vanilla JavaScript ES6 apenas
2. **Fetch API** - Para requisições XHR (sem jQuery)
3. **Window globals** - public pages usam `window.functionName`
4. **ES6 modules** - menu.js usa export/import
5. **Defer attribute** - Todos os scripts carregam com `defer`
6. **Dependências**:
   - `auth.js` depende de `cpf.js` (para validação)
   - Nenhuma circular dependency
