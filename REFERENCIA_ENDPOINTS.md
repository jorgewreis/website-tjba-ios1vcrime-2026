# Referência Rápida: URLs e Endpoints

## 🌐 URLs Principais

```
Login
  GET  /public/index.php
  POST /public/login.php

Home (Dashboard)
  GET  /public/home.php

Frame (Carregador de Páginas)
  GET  /public/frame.php?page=PAGENAME
```

## 🔌 API Endpoints

### GET /admin/api/getPerfis.php
**Retorna perfis do usuário autenticado**

**Requer**: Autenticação (session + token)

**Resposta Sucesso (200)**:
```json
{
    "success": true,
    "perfis": ["SERVIDOR", "ADMIN"],
    "usuario_id": 4
}
```

**Resposta Erro (401)**:
```json
{
    "success": false,
    "message": "Não autenticado"
}
```

**Resposta Erro (400)**:
```json
{
    "success": false,
    "message": "ID de usuário inválido"
}
```

**Resposta Erro (500)**:
```json
{
    "success": false,
    "message": "Erro ao consultar banco de dados"
}
```

---

## 📄 Páginas Protegidas (em frame.php)

```
URL                              Seção Requerida    Perfis
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
?page=dashboard                  null               TODOS
?page=cartasexpedidas            Cartório           SERVIDOR, ADMIN
?page=cartasrecebidas            Cartório           SERVIDOR, ADMIN
?page=cejusc                      Cartório           SERVIDOR, ADMIN
?page=editais                     Cartório           SERVIDOR, ADMIN
?page=guias                       Cartório           SERVIDOR, ADMIN
?page=medidasprotetivas           Cartório           SERVIDOR, ADMIN
?page=officioscartorio            Cartório           SERVIDOR, ADMIN
?page=oficiosgabinete             Gabinete           SERVIDOR, ADMIN
?page=produtividade               Cartório           SERVIDOR, ADMIN
```

---

## 📡 Chamadas HTTP de Teste

### Teste 1: Buscar Perfis (Autenticado)
```bash
curl -X GET http://localhost/admin/api/getPerfis.php \
  -H "Cookie: PHPSESSID=..." \
  -H "Content-Type: application/json"
```

**Resposta esperada**:
```json
{"success": true, "perfis": ["SERVIDOR"], "usuario_id": 2}
```

### Teste 2: Buscar Perfis (Não Autenticado)
```bash
curl -X GET http://localhost/admin/api/getPerfis.php
```

**Resposta esperada**:
```
HTTP 401
{"success": false, "message": "Não autenticado"}
```

### Teste 3: Acessar Página Permitida
```bash
curl -X GET "http://localhost/public/frame.php?page=dashboard" \
  -H "Cookie: PHPSESSID=..."
```

**Resposta esperada**: HTTP 200 (conteúdo da página)

### Teste 4: Acessar Página Não Permitida
```bash
# ESTUDANTE tenta acessar página de SERVIDOR
curl -X GET "http://localhost/public/frame.php?page=editais" \
  -H "Cookie: PHPSESSID=..."
```

**Resposta esperada**:
```
HTTP 403
Acesso negado. Seu perfil não tem permissão para acessar este conteúdo.
```

---

## 🔍 Códigos HTTP Utilizados

| Código | Significado | Causa |
|--------|------------|-------|
| **200** | OK | Página acessível e carregada |
| **401** | Não Autenticado | Token inválido ou sessão expirada |
| **403** | Proibido | Perfil não tem acesso à página |
| **404** | Não Encontrado | Página não existe no whitelist |

---

## 🗺️ Mapa de Navegação

```
┌─────────────────────────────────────────────────┐
│ /public/index.php (Login)                       │
│  └─ POST /public/login.php → cria session      │
└────────────────────┬────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────┐
│ /public/home.php (Dashboard)                    │
│  ├─ Carrega menu.js                            │
│  ├─ Carrega menu-access.js                     │
│  └─ GET /admin/api/getPerfis.php               │
└────────────────────┬────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        ↓                         ↓
   Menu Item Visível      Menu Item Oculto
   (onclick carregarPagina) (display: none)
        │                         │
        └────────────┬────────────┘
                     ↓
    GET /public/frame.php?page=X
         │
         ├─ Valida token (401 se inválido)
         ├─ Valida whitelist (404 se não existe)
         ├─ Valida perfil (403 se não permitido)
         │
    ┌────┴────────────────┬──────────┬─────────────┐
    ↓                    ↓          ↓              ↓
  200 OK         401 Unauthorized 403 Forbidden  404 Not Found
Carrega página  Session expirada Perfil insuf.  Página inexistente
```

---

## 🔐 Headers HTTP Importantes

### Request Headers
```
Cookie: PHPSESSID=abc123def456
Content-Type: application/json
Accept: application/json
```

### Response Headers
```
Content-Type: application/json
HTTP/1.1 200 OK
```

---

## 📊 Fluxo de Dados

### Fluxo 1: Buscar Perfis (Menu Filtering)
```
home.php (DOMContentLoaded)
  ↓
initMenuAccess()
  ↓
fetchUserPerfis()
  ↓
GET /admin/api/getPerfis.php
  ↓
SELECT DISTINCT perfil FROM usuario_perfil WHERE usuario = ?
  ↓
API retorna: {success: true, perfis: ["SERVIDOR"]}
  ↓
filterMenuByAccess(["SERVIDOR"])
  ↓
Oculta seções não permitidas (display: none)
```

### Fluxo 2: Carregar Página (Access Validation)
```
Clique em item menu visível
  ↓
carregarPagina('editais')
  ↓
frame.php?page=editais
  ↓
Valida token: $_SESSION['token'] ✓
  ↓
getUserPerfils($userId) → ["SERVIDOR"]
  ↓
hasAccessToSection(["SERVIDOR"], "Cartório") → true
  ↓
require /admin/cadastros/editais.php
  ↓
Página renderizada (HTTP 200)
```

---

## 🗄️ Queries SQL Úteis

### Buscar Perfis de um Usuário
```sql
SELECT DISTINCT perfil 
FROM usuario_perfil 
WHERE usuario = 2
ORDER BY perfil ASC;
```

**Resultado esperado**:
```
perfil
─────────
SERVIDOR
```

### Buscar Usuários por Perfil
```sql
SELECT u.id_usuario, u.nome_usuario
FROM usuarios u
INNER JOIN usuario_perfil up ON u.id_usuario = up.usuario
WHERE up.perfil = 'SERVIDOR'
ORDER BY u.nome_usuario;
```

### Verificar Integridade
```sql
SELECT up.usuario, up.perfil
FROM usuario_perfil up
LEFT JOIN usuarios u ON up.usuario = u.id_usuario
WHERE u.id_usuario IS NULL;
```

Se houver resultados, há erro de referência.

---

## 🧩 Componentes Chave

### Arquivo: admin/api/getPerfis.php
```php
// Entrada: Usuário autenticado
// Saída: JSON {success, perfis[], usuario_id}
// Erros: 401, 400, 500
```

### Arquivo: public/js/menu-access.js
```javascript
// Entrada: DOM com menu-header-text
// Processo: Busca perfis → filtra DOM
// Saída: Menu oculto/visível
```

### Arquivo: public/frame.php
```php
// Entrada: ?page=X, token, sessão
// Processo: Valida token → valida página → valida perfil
// Saída: Página ou erro (401/403/404)
```

---

## ✅ Checklist de Testes Manual

- [ ] API retorna 200 com dados válidos
- [ ] API retorna 401 quando não autenticado
- [ ] Menu filtra corretamente para ESTUDANTE
- [ ] Menu filtra corretamente para SERVIDOR
- [ ] Menu mostra tudo para ADMIN
- [ ] frame.php retorna 200 para páginas permitidas
- [ ] frame.php retorna 403 para páginas não permitidas
- [ ] frame.php retorna 404 para páginas inexistentes
- [ ] Múltiplos perfis funcionam (union)
- [ ] Token expirado retorna 401

---

**Última atualização**: Janeiro 2026  
**Versão**: 1.0  
**Status**: Pronto para Produção
