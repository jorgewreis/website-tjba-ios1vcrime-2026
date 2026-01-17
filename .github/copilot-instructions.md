# Copilot Instructions: website-tjba-ios1vcrime-2026

Este é um sistema de gestão PHP para a Primeira Vara Criminal de Ilhéus, com foco em controlar rotinas judiciais (cartas precatórias, editais, guias, medidas protetivas, etc.).

## Arquitetura Essencial

### Padrão Geral
- **Framework**: PHP puro (sem framework) com arquitetura organizada em camadas
- **Banco de Dados**: MySQL 8.0+ via PDO com prepared statements (obrigatório para segurança)
- **Autenticação**: Session-based com token + CSRF protection
- **Perfis de Acesso**: 3 níveis (ESTUDANTE=1, SERVIDOR=2, ADMIN=3) com checagem via `Security::checkAccess($profile)`

### Estrutura de Diretórios

```
public/           → Acesso público: login, índice, recuperação de senha
admin/            → Área protegida: dashboard, cadastros de rotinas judiciais
  └─ cadastros/   → Páginas individuais (cartasexpedidas.php, editais.php, etc.)
  └─ api/         → Endpoints JSON (login, logout, checkToken)
bd/               → Classes de persistência (Usuario, Conection)
config/           → Configurações globais (config.php, security.php)
services/         → Serviços reutilizáveis (PasswordResetService)
```

## Padrões Críticos

### 1. Singleton Database Connection
[Conection.php](bd/Conection.php) implementa singleton para conexão PDO:
```php
$pdo = Conection::getInstance()->getConnection();
```
**Nunca** criar múltiplas instâncias de PDO. Sempre usar `getInstance()`.

### 2. Autenticação & Autorização
- [config/security.php](config/security.php) centraliza toda lógica de segurança:
  - `Security::isAuthenticated()` → verifica session + token
  - `Security::checkAccess($profile)` → valida nível de acesso por perfil
  - `Security::hashPassword()` → Argon2ID (nunca MD5/SHA1)
  - `Security::verifyCSRFToken()` → sempre validar em POST

- **Fluxo de Login** ([public/login.php](public/login.php) → [admin/api/login.php](admin/api/login.php)):
  1. Validar CSRF token
  2. Chamar `Usuario::autenticar($cpf, $senha)` 
  3. Gerar token com `Security::generateSecureToken()`
  4. Armazenar em `$_SESSION` + banco (com TTL de 8 horas)

### 3. Validação de Dados
- [Usuario.php](bd/Usuario.php) normaliza/formata CPF:
  - `normalizeCPF()` → remove caracteres especiais
  - `validarCPF()` → aplica algoritmo mod11
  - `formatCPF()` → converte para padrão XXX.XXX.XXX-XX
- **Importante**: Sempre usar `Security::sanitize()` para entrada de usuário

### 4. Endpoints JSON
APIs retornam JSON com estrutura padrão:
```php
['success' => bool, 'message' => string, ...]
```
**Sempre** definir `Content-Type: application/json` no header.

### 5. Proteção de Página
Todas páginas protegidas iniciam assim:
```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
Security::requireAuthentication(); // redireciona se não autenticado
```

## Fluxo de Dados

```
Login Form (public/index.php)
    ↓ POST CSRF-validated
JSON API (admin/api/login.php)
    ↓ valida CPF + senha
Usuario::autenticar()
    ↓ query BD + password_verify()
Session + Token gerado
    ↓ JSON response
Protected Pages (public/home.php, admin/*)
    ↓ Security::requireAuthentication()
Usuario::validarToken()
    ↓ renderiza conteúdo
```

## Convenções de Código

### PHP
- `declare(strict_types=1)` obrigatório em arquivos de classe
- Classes `final` por padrão (abrir se necessário herança)
- Type hints sempre: `function autenticar(string $cpf, string $senha): array`
- Namespace não usado; arquivo único por classe
- Prefixo de constantes privadas: `private const SESSION_TOKEN_KEY`

### Banco de Dados
- Prepared statements **obrigatório**: `$pdo->prepare("SELECT ... WHERE id = ?")` + `execute([$id])`
- Sempre `PDO::FETCH_ASSOC` para resultados
- Transações para operações multi-step (ex: reset senha + envio email)

### Senhas
- Hash: `Security::hashPassword($senha)` → Argon2ID
- Verificação: `Security::verifyPassword($entrada, $hash)`
- Nunca comparar strings com `==`, usar `hash_equals()` (prevenção timing attack)

### CSRF
- Gerar no formulário: `Security::generateCSRFToken()`
- Validar em POST: `Security::verifyCSRFToken($_POST['csrf_token'] ?? null)`
- Token em `$_SESSION`, não em cookie

## Dependências Críticas

- **PHP**: 7.4+ (type declarations, match expression)
- **MySQL**: 8.0+ com charset utf8mb4
- **Composer**: Gerenciador de pacotes (instalado)
  - **PHPMailer**: ^7.0 para envio de emails (em `vendor/phpmailer`)
- **Cookies**: HttpOnly obrigatório, Secure se HTTPS (detectado automaticamente)

## Desenvolvimento Local

- **Arquivo de Configuração**: `.env` na raiz com variáveis `KEY=VALUE`
- [config/config.php](config/config.php) carrega `.env` automaticamente
- **Timezone**: America/Bahia (configurado globalmente)
- **Session TTL**: 8 horas (TOKEN_EXPIRATION_HOURS = 8)

## Pontos de Atenção para AI

1. **Composer está ativo** - PHPMailer ^7.0 está instalado via `composer.json`. Atualizar dependências via `composer require` se necessário
2. **Páginas de cadastro** ([admin/cadastros/](admin/cadastros/)) são estruturas HTML simples - adicionar funcionalidade via API endpoints em `admin/api/`
3. **Logout** remove token do BD + destroi sessão (ver [Usuario::logoutLocal()](bd/Usuario.php))
4. **Password reset**: Usar [PasswordResetService](services/PasswordResetService.php) como padrão (gera senha temp, envia email, atualiza BD)
5. **Email**: Função `sendEmail()` em PasswordResetService - suporta SMTP (Microsoft 365) ou LOG fallback
6. **Recuperação de senha** ([public/recupera_senha.php](public/recupera_senha.php)): resposta neutra por segurança (não diz se usuário existe)

## Controle de Acesso baseado em Perfis (Menu + Páginas)

**Novo sistema implementado (veja [MENU_ACCESS_CONTROL.md](MENU_ACCESS_CONTROL.md) para documentação completa)**

### Perfis
- `ESTUDANTE`: Acesso apenas a "Estudantes" no menu
- `SERVIDOR`: Acesso a "Cartório", "Gabinete", "Sala de Audiências"
- `ADMIN`: Acesso a tudo (inclui "Estudantes" e "Admin")

### Dois Níveis de Proteção
1. **Client-side (Visual)**: [public/js/menu-access.js](public/js/menu-access.js) filtra menu por perfil
   - Chama API [admin/api/getPerfis.php](admin/api/getPerfis.php) para obter perfis do usuário
   - Aplica mapa `PERFIL_MENU_MAP` para mostrar/ocultar seções
   
2. **Server-side (Acesso)**: [public/frame.php](public/frame.php) valida autorização
   - Função `getUserPerfils($userId)` busca perfis no BD
   - Função `hasAccessToSection($userPerfils, $requiredSection)` valida acesso
   - Retorna 403 se perfil não tem permissão
   - Sempre usar mapa `$page_to_section` ao adicionar páginas

### Banco de Dados
- Tabela `usuario_perfil`: relaciona usuários a perfis
- Campos: `id_usu_perfil`, `usuario` (FK), `perfil` (VARCHAR)
- Um usuário pode ter múltiplos perfis (union logic para permissões)

### Adicionando Nova Página com Acesso Controlado
1. Criar arquivo em `admin/cadastros/novapage.php`
2. Adicionar ao whitelist em `frame.php`: `'novapage' => __DIR__ . '/../admin/cadastros/novapage.php'`
3. Mapear à seção em `frame.php`: `'novapage' => 'Cartório'` (ou seção apropriada)
4. Adicionar item ao menu em `public/home.php`: `<div class="menu-item" onclick="carregarPagina('novapage')">Nova Página</div>`

## Licença & Restrições

Projeto restritivo - código proprietário. Qualquer fork/distribuição requer autorização explícita de `jorgew.reis@outlook.com`.
