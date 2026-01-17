⚠️ ATENÇÃO: LEIA ANTES DE USAR O SISTEMA

# Sistema de Controle de Acesso baseado em Perfis

## 🎯 O que foi implementado?

Um sistema robusto de controle de acesso com **dois níveis de proteção**:

1. **Nível Visual (Client-side)**: Filtra o menu baseado nos perfis do usuário
2. **Nível de Acesso (Server-side)**: Bloqueia acesso direto a páginas não permitidas

---

## ⚡ Configuração Rápida (5 minutos)

### 1️⃣ Criar Tabela no Banco

Execute no seu MySQL:

```sql
CREATE TABLE usuario_perfil (
    id_usu_perfil INT PRIMARY KEY AUTO_INCREMENT,
    usuario INT NOT NULL,
    perfil VARCHAR(20) NOT NULL,
    FOREIGN KEY (usuario) REFERENCES usuarios(id_usuario),
    UNIQUE KEY unique_usuario_perfil (usuario, perfil)
);
```

### 2️⃣ Inserir Dados de Teste

```sql
-- Usuário 1: ESTUDANTE
INSERT INTO usuario_perfil (usuario, perfil) VALUES (1, 'ESTUDANTE');

-- Usuário 2: SERVIDOR
INSERT INTO usuario_perfil (usuario, perfil) VALUES (2, 'SERVIDOR');

-- Usuário 3: ADMIN
INSERT INTO usuario_perfil (usuario, perfil) VALUES (3, 'ADMIN');
```

### 3️⃣ Testar

- Login com um dos usuários acima
- Verificar se o menu filtra corretamente
- Tentar acessar páginas com URL direto: `frame.php?page=editais`

---

## 📋 Três Perfis Disponíveis

| Perfil | Acesso | Menu Visível |
|--------|--------|-------------|
| **ESTUDANTE** | Apenas seção "Estudantes" | Estudantes |
| **SERVIDOR** | Cartório, Gabinete, Audiências | Cartório, Gabinete, Sala de Audiências |
| **ADMIN** | Tudo | Todas as seções |

---

## 🔧 Arquivos Criados

```
admin/api/
  └─ getPerfis.php ........................ API que retorna perfis do usuário

public/js/
  └─ menu-access.js ....................... Filtra menu por perfil

Documentação:
  ├─ MENU_ACCESS_CONTROL.md .............. Documentação técnica completa
  ├─ IMPLEMENTACAO_CONTROLE_ACESSO.md ... Sumário de implementação
  ├─ CHECKLIST_IMPLEMENTACAO.md ......... Checklist de validação
  └─ STATUS_IMPLEMENTACAO.txt ........... Status visual

Dados:
  └─ sql/exemplo_dados_teste.sql ......... SQL para testes
```

---

## ⚠️ Importantes

### ✅ O que ESTÁ Implementado

- ✅ API segura `/admin/api/getPerfis.php`
- ✅ Filtro de menu em JavaScript (`menu-access.js`)
- ✅ Validação server-side em `frame.php`
- ✅ Suporte a múltiplos perfis por usuário
- ✅ Tratamento de erros (401, 403, 404)
- ✅ Documentação completa

### ❌ O que NÃO está pronto

- ❌ Páginas específicas para seção "Estudantes" (ainda não criadas)
- ❌ Admin panel para gerenciar perfis (CRUD de perfis)
- ❌ Logs de auditoria (acesso negado, etc)

---

## 🧪 Testando o Sistema

### Teste 1: Login com ESTUDANTE

```
1. Inserir dados:
   INSERT INTO usuario_perfil (usuario, perfil) VALUES (1, 'ESTUDANTE');

2. Login com usuário 1

3. Verificar:
   - Menu mostra apenas "Estudantes" ✓
   - frame.php?page=editais retorna 403 ✓
   - frame.php?page=dashboard retorna 200 ✓
```

### Teste 2: Login com SERVIDOR

```
1. Inserir dados:
   INSERT INTO usuario_perfil (usuario, perfil) VALUES (2, 'SERVIDOR');

2. Login com usuário 2

3. Verificar:
   - Menu mostra "Cartório", "Gabinete", "Sala de Audiências" ✓
   - frame.php?page=editais retorna 200 ✓
   - Menu NÃO mostra "Estudantes" ou "Admin" ✓
```

### Teste 3: Login com ADMIN

```
1. Inserir dados:
   INSERT INTO usuario_perfil (usuario, perfil) VALUES (3, 'ADMIN');

2. Login com usuário 3

3. Verificar:
   - Menu mostra TUDO ✓
   - frame.php?page=editais retorna 200 ✓
   - Qualquer página é acessível ✓
```

---

## 📚 Documentação

| Arquivo | Para Quem | O que tem |
|---------|-----------|----------|
| **MENU_ACCESS_CONTROL.md** | Developers | Documentação técnica completa (tudo!) |
| **CHECKLIST_IMPLEMENTACAO.md** | QA / Tester | Checklist, testes, validação |
| **IMPLEMENTACAO_CONTROLE_ACESSO.md** | Project Manager | Sumário, status, próximos passos |
| **STATUS_IMPLEMENTACAO.txt** | Todos | Status visual e rápido |

---

## 🆘 Problemas Comuns

### "Menu não filtra"

**Causa**: API `/admin/api/getPerfis.php` retornando erro

**Solução**:
1. Abrir DevTools → Network
2. Verificar requisição GET `/admin/api/getPerfis.php`
3. Se status ≠ 200, checar logs

### "Acesso negado mesmo com perfil correto"

**Causa**: Usuário não está na tabela `usuario_perfil`

**Solução**:
1. Verificar se usuário está em `usuario_perfil`
2. Verificar se perfil é EXATAMENTE `'ESTUDANTE'` (maiúscula, sem espaços)
3. Executar: `SELECT * FROM usuario_perfil WHERE usuario = [id_usuario]`

### "Erro 401 ao acessar API"

**Causa**: Token expirado ou sessão encerrada

**Solução**:
1. Fazer logout e login novamente
2. Token tem TTL de 8 horas

---

## 🔄 Como Adicionar Nova Página?

### Passo 1: Criar o arquivo

```php
// admin/cadastros/minhapage.php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
Security::requireAuthentication();
?>
<!-- HTML -->
```

### Passo 2: Registrar em `frame.php`

```php
$allowed_pages = [
    // ... páginas existentes
    'minhapage' => __DIR__ . '/../admin/cadastros/minhapage.php',
];

$page_to_section = [
    // ... mapeamentos existentes
    'minhapage' => 'Cartório',  // ← coloque a seção apropriada
];
```

### Passo 3: Adicionar ao menu em `home.php`

```html
<!-- Dentro da seção "Cartório" -->
<div class="menu-item" onclick="carregarPagina('minhapage')">
    Minha Página
</div>
```

---

## 🛡️ Segurança

### Proteções Implementadas

✅ **Token Validation** - Todos requests requerem token válido  
✅ **Whitelist de Páginas** - Apenas páginas permitidas são carregáveis  
✅ **Profile Checking** - Cada página requer perfil adequado  
✅ **SQL Injection Prevention** - Prepared statements em tudo  
✅ **Dupla Validação** - Visual + Server-side  

### O que NÃO está protegido

Se um usuário manipular o DOM para mostrar um item oculto, ele ainda **não consegue acessar** a página porque `frame.php` valida novamente.

---

## 📊 Resumo Técnico

```
Banco de Dados:
  └─ Tabela: usuario_perfil (usuario, perfil)

API:
  └─ GET /admin/api/getPerfis.php → {perfis: [...]}

JavaScript:
  └─ menu-access.js → filtra DOM com display: none

PHP:
  └─ frame.php → valida acesso com HTTP 403

Perfis:
  ├─ ESTUDANTE (restrito)
  ├─ SERVIDOR (administrativo)
  └─ ADMIN (total)
```

---

## 📞 Próximos Passos

1. **Criar tabela `usuario_perfil`**
2. **Inserir dados de teste**
3. **Testar cada perfil**
4. **Revisar logs**
5. **Fazer deploy em produção**

---

## 📖 Leia Também

- [MENU_ACCESS_CONTROL.md](MENU_ACCESS_CONTROL.md) - Documentação completa
- [CHECKLIST_IMPLEMENTACAO.md](CHECKLIST_IMPLEMENTACAO.md) - Testes
- [sql/exemplo_dados_teste.sql](sql/exemplo_dados_teste.sql) - SQL de teste

---

**Versão**: 1.0 (Produção)  
**Data**: Janeiro 2026  
**Status**: ✅ Pronto para usar
