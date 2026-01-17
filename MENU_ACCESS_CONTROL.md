# Sistema de Controle de Acesso baseado em Perfis

Este documento descreve o sistema de controle de acesso (menu e páginas) implementado para a Primeira Vara Criminal de Ilhéus.

## Arquitetura

O sistema funciona em **dois níveis**:

1. **Nível Visual (Filtro de Menu)**: Oculta itens do menu baseado nos perfis do usuário
2. **Nível de Acesso (Validação de Página)**: Bloqueia acesso direto a páginas protegidas via URL

## Perfis de Usuário

Três perfis estão disponíveis:

| Perfil | Descrição | Acesso a |
|--------|-----------|----------|
| **ESTUDANTE** | Acesso restrito a informações públicas | Seção "Estudantes" |
| **SERVIDOR** | Acesso a funcionalidades administrativas do tribunal | Seções "Cartório", "Gabinete", "Sala de Audiências" |
| **ADMIN** | Acesso total ao sistema | Todas as seções |

### Múltiplos Perfis por Usuário

Um usuário pode ter múltiplos perfis. O sistema utiliza **union logic** (OR):

- Se um usuário tem perfis `SERVIDOR` e `ADMIN`, verá **todos** os itens de ambos os perfis
- Se tem apenas `ESTUDANTE`, verá apenas a seção "Estudantes"

## 1. Filtro de Menu (Client-side)

### Arquivo: `public/js/menu-access.js`

Responsável por filtrar visualmente os itens do menu baseado nos perfis do usuário.

#### Fluxo de Execução

1. Página `home.php` carrega o script `menu-access.js`
2. Ao carregar o DOM, `initMenuAccess()` é chamado
3. Função chama API `/admin/api/getPerfis.php` para obter perfis do usuário
4. Perfis são mapeados para seções de menu permitidas
5. Itens não permitidos recebem `display: none`

#### Mapa de Perfis para Seções

```javascript
const PERFIL_MENU_MAP = {
    'ESTUDANTE': ['Estudantes'],
    'SERVIDOR': ['Cartório', 'Gabinete', 'Sala de Audiências'],
    'ADMIN': ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Admin']
};
```

#### Estrutura do Menu

O menu em `home.php` segue esta estrutura HTML:

```html
<!-- Dashboard (sempre visível) -->
<div class="menu-item">Dashboard</div>

<!-- Seção Cartório -->
<div class="menu-header-text">Cartório</div>
<div class="menu-item">Cartas Expedidas</div>
<!-- ... mais itens -->

<!-- Seção Gabinete -->
<div class="menu-header-text">Gabinete</div>
<div class="menu-item">Ofícios</div>
<!-- ... mais itens -->

<!-- Seção Sala de Audiências -->
<div class="menu-header-text">Sala de Audiências</div>
<div class="menu-item">Partes</div>
<!-- ... mais itens -->

<!-- Seção Estudantes (apenas ESTUDANTE e ADMIN) -->
<div class="menu-header-text">Estudantes</div>
<div class="menu-item">Pauta de Audiências</div>
<!-- ... mais itens -->

<!-- Seção Admin (apenas ADMIN) -->
<div class="menu-header-text">Admin</div>
<div class="menu-item">Servidores</div>
```

#### Funcionamento do Filtro

1. **Identificação de Seções**: Encontra elementos com classe `menu-header-text`
2. **Busca de Itens**: Para cada seção, encontra todos os `menu-item` subsequentes até a próxima seção
3. **Aplicação de Filtro**: Oculta a seção inteira (header + itens) se não permitida

### API: `admin/api/getPerfis.php`

Endpoint que retorna os perfis do usuário autenticado.

#### Requisição

```
GET /admin/api/getPerfis.php
```

#### Resposta Sucesso (200)

```json
{
    "success": true,
    "perfis": ["SERVIDOR", "ADMIN"],
    "usuario_id": 123
}
```

#### Respostas de Erro

| Código | Motivo |
|--------|--------|
| 401 | Usuário não autenticado |
| 400 | ID de usuário inválido |
| 500 | Erro ao consultar banco de dados |

#### Implementação

```php
// Busca perfis distintos do usuário na tabela usuario_perfil
SELECT DISTINCT perfil 
FROM usuario_perfil 
WHERE usuario = ? 
ORDER BY perfil ASC
```

## 2. Validação de Página (Server-side)

### Arquivo: `public/frame.php`

Valida o acesso às páginas antes de carregá-las. Oferece proteção contra bypass direto via URL.

#### Fluxo de Autorização

1. Valida autenticação (token e sessão)
2. Obtém perfis do usuário via `getUserPerfils()`
3. Consulta mapa de página → seção: `$page_to_section`
4. Verifica se usuário tem permissão via `hasAccessToSection()`
5. Carrega página ou retorna erro 403

#### Mapa de Página para Seção

```php
$page_to_section = [
    'dashboard'         => null,          // Sempre acessível
    'cartasexpedidas'   => 'Cartório',
    'cartasrecebidas'   => 'Cartório',
    'cejusc'            => 'Cartório',
    'editais'           => 'Cartório',
    'guias'             => 'Cartório',
    'medidasprotetivas' => 'Cartório',
    'officioscartorio'  => 'Cartório',
    'oficiosgabinete'   => 'Gabinete',
    'produtividade'     => 'Cartório',
];
```

#### Validação de Acesso

```php
// Função que verifica permissão
hasAccessToSection($userPerfils, $requiredSection)

// Exemplo 1: ESTUDANTE tenta acessar editais
// $userPerfils = ['ESTUDANTE']
// $requiredSection = 'Cartório'
// Resultado: NEGADO (ESTUDANTE não está em ['SERVIDOR', 'ADMIN'])

// Exemplo 2: SERVIDOR tenta acessar editais
// $userPerfils = ['SERVIDOR']
// $requiredSection = 'Cartório'
// Resultado: PERMITIDO (SERVIDOR está em ['SERVIDOR', 'ADMIN'])
```

#### Respostas HTTP

| Código | Situação |
|--------|----------|
| 200 | Página carregada com sucesso |
| 401 | Não autenticado (sessão/token inválido) |
| 403 | Perfil não tem acesso à página |
| 404 | Página não existe no whitelist |

## 3. Banco de Dados

### Tabela: `usuario_perfil`

Armazena a relação entre usuários e seus perfis.

```sql
CREATE TABLE usuario_perfil (
    id_usu_perfil INT PRIMARY KEY AUTO_INCREMENT,
    usuario INT NOT NULL FOREIGN KEY REFERENCES usuarios(id_usuario),
    perfil VARCHAR(20) NOT NULL CHECK (perfil IN ('ESTUDANTE', 'SERVIDOR', 'ADMIN')),
    UNIQUE KEY unique_usuario_perfil (usuario, perfil)
);
```

**Exemplo de dados:**

```sql
-- Usuário 1: Estudante
INSERT INTO usuario_perfil (usuario, perfil) VALUES (1, 'ESTUDANTE');

-- Usuário 2: Servidor com acesso a múltiplas seções
INSERT INTO usuario_perfil (usuario, perfil) VALUES (2, 'SERVIDOR');

-- Usuário 3: Admin (acesso total)
INSERT INTO usuario_perfil (usuario, perfil) VALUES (3, 'ADMIN');

-- Usuário 4: Servidor + Admin (vê tudo)
INSERT INTO usuario_perfil (usuario, perfil) VALUES (4, 'SERVIDOR');
INSERT INTO usuario_perfil (usuario, perfil) VALUES (4, 'ADMIN');
```

## 4. Fluxo Completo de Acesso

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Usuário acessa home.php após autenticar                      │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. home.php carrega menu-access.js                              │
│    → DOMContentLoaded chama initMenuAccess()                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. menu-access.js faz fetch GET /admin/api/getPerfis.php        │
│    → Servidor retorna perfis do usuário em JSON                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. menu-access.js filtra menu                                   │
│    → Oculta seções não permitidas (display: none)               │
│    → Mostra apenas seções que o usuário pode acessar            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Usuário clica em item do menu visível                        │
│    → Chama frame.php com ?page=nomedapagina                     │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. frame.php valida autorização                                 │
│    → Se permitido: carrega a página                             │
│    → Se negado: retorna erro 403                                │
└─────────────────────────────────────────────────────────────────┘
```

## 5. Adicionando Novas Páginas

Para adicionar uma nova página ao sistema com controle de acesso:

### Passo 1: Criar a página

```php
// admin/cadastros/minhapage.php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
Security::requireAuthentication();
?>
<!-- HTML da página -->
```

### Passo 2: Adicionar ao whitelist em frame.php

```php
$allowed_pages = [
    // ... páginas existentes
    'minhapage' => __DIR__ . '/../admin/cadastros/minhapage.php',
];
```

### Passo 3: Definir a seção requerida

```php
$page_to_section = [
    // ... páginas existentes
    'minhapage' => 'Cartório',  // ou outra seção
];
```

### Passo 4: Adicionar o item ao menu em home.php

```html
<!-- Dentro da seção "Cartório" -->
<div class="menu-item" onclick="carregarPagina('minhapage')">
    Minha Página
</div>
```

## 6. Segurança

### Proteções Implementadas

1. ✅ **Token Validation**: Todos os requests requerem token válido
2. ✅ **Whitelist de Páginas**: Apenas páginas no `$allowed_pages` podem ser carregadas
3. ✅ **Profile Checking**: Cada página requer perfil adequado
4. ✅ **CORS Protection**: API retorna apenas dados do usuário autenticado
5. ✅ **SQL Injection Prevention**: Prepared statements em todas as queries
6. ✅ **Visual + Server Validation**: Dupla camada de segurança

### O que NÃO está protegido

- ⚠️ Se um usuário manipular o DOM para mostrar itens ocultos, isso **não** fornece acesso real (frame.php bloqueia)
- ⚠️ Requisições diretas a frame.php sem estar em iframe são bloqueadas pelo token check

## 7. Testes Recomendados

### Teste 1: ESTUDANTE

```
Login com usuário que tem perfil ESTUDANTE
✓ Menu deve mostrar apenas "Estudantes"
✓ Tentar acessar ?page=editais deve retornar 403
```

### Teste 2: SERVIDOR

```
Login com usuário que tem perfil SERVIDOR
✓ Menu deve mostrar "Cartório", "Gabinete", "Sala de Audiências"
✓ Menu não deve mostrar "Estudantes" ou "Admin"
✓ Pode acessar ?page=editais (200)
✓ Tentar acessar ?page=admin_* deve retornar 403
```

### Teste 3: ADMIN

```
Login com usuário que tem perfil ADMIN
✓ Menu deve mostrar tudo
✓ Pode acessar qualquer ?page=*
```

### Teste 4: Múltiplos Perfis

```
Usuário com perfis ['SERVIDOR', 'ADMIN']
✓ Menu deve mostrar todos os itens (union)
✓ Pode acessar páginas de SERVIDOR e ADMIN
```

## 8. Troubleshooting

### Menu não filtra

1. Verificar se `menu-access.js` está carregado (DevTools → Network)
2. Verificar erro em console (DevTools → Console)
3. Verificar se API `/admin/api/getPerfis.php` retorna status 200
4. Verificar se usuario_perfil está preenchido no banco

### Acesso negado mesmo com perfil correto

1. Verificar se usuário está na tabela `usuario_perfil`
2. Verificar se perfil no banco é exatamente `'ESTUDANTE'`, `'SERVIDOR'`, ou `'ADMIN'` (case-sensitive)
3. Verificar se página está no `$page_to_section` map
4. Verificar logs do servidor (`error_log()`)

### Erro 401 ou 403 ao acessar frame.php

1. Verificar se sessão está ativa (`$_SESSION['id_usuario']` e `$_SESSION['token']`)
2. Verificar se token não expirou (TTL de 8 horas)
3. Verificar se endpoint `/admin/api/checkToken.php` valida corretamente

---

**Documentação atualizada em**: 2026  
**Versão**: 1.0
