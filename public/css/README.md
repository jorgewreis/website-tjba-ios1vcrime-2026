# CSS Files Organization

Organização dos arquivos CSS seguindo **separação de responsabilidades**, similar ao padrão usado em JavaScript.

## 📁 Estrutura de Arquivos

### Arquivos CSS Refatorados

```text
public/css/

Fundações:
├── reset.css ...................... Normalização e reset global
├── color.css ...................... Variáveis CSS (cores, sombras)

Layout e Estrutura:
├── layout.css ..................... Layout de página, containers, grids
├── menu-style.css ................. Estilos do menu sidebar, navegação

Componentes Reutilizáveis:
├── components.css ................. Forms, botões, alertas, cards

Estilos Específicos de Página:
├── login.css ...................... Página de login (layout específico)
├── pages.css ...................... Páginas internas (estrutura)

Utilitários:
├── form.css ....................... [DEPRECATED] Movido para components.css
├── style.css ...................... [DEPRECATED] Dividido em layout.css + menu-style.css
```

## 🎯 Propósito de Cada Arquivo

### **reset.css** (Fundação)

- Normalização de estilos padrão do navegador
- Remove margens e paddings padrão
- Configurações globais (box-sizing, fonts)
- **Sempre carregado primeiro**

### **color.css** (Variáveis)

- CSS custom properties (variáveis)
- Paleta de cores
- Box shadows padrão
- Espaçamentos
- **Sempre carregado depois do reset.css**

### **layout.css** (Estrutura)

- Wrappers e containers
- Flexbox/Grid layout
- Navbar
- Menu sidebar (posicionamento)
- Content areas
- Responsividade (media queries)

### **menu-style.css** (Menu e Navegação)

- Estilos de items de menu
- Links e submenu
- Animações de expansão/colapso
- Avatar e notificações
- Separado de layout.css para melhor manutenção

### **components.css** (Reutilizáveis)

- Forms e inputs
- Botões (todos os estados)
- Alertas e notificações
- Cards
- Animações gerais
- **Concentra todo UI reutilizável**

### **login.css** (Página de Login)

- Layout específico da página de login
- Estilos da seção esquerda (imagem de fundo)
- Estilos da seção direita (formulário)
- Responsive do login
- **Apenas para index.php**

### **pages.css** (Páginas Internas)

- Estrutura de páginas internas (admin, cadastros)
- Container-main, title-page
- Layout específico de páginas protegidas
- Responsive interno
- **Para admin/dashboard.php e cadastros/**

## 📊 Mapa de Carregamento por Página

### **index.php** (Login - Pública)

```html
<!-- Fundação -->
<link rel="stylesheet" href="/css/reset.css">
<link rel="stylesheet" href="/css/color.css">

<!-- Específico para Login -->
<link rel="stylesheet" href="/css/login.css">

<!-- Componentes -->
<link rel="stylesheet" href="/css/components.css">
```

### **recupera_senha.php** (Password Reset - Pública)

```html
<!-- Fundação -->
<link rel="stylesheet" href="/css/reset.css">
<link rel="stylesheet" href="/css/color.css">

<!-- Componentes -->
<link rel="stylesheet" href="/css/components.css">

<!-- Específico para formulário -->
<link rel="stylesheet" href="/css/login.css">
```

### **home.php** (Dashboard - Interna)

```html
<!-- Fundação -->
<link rel="stylesheet" href="/css/reset.css">
<link rel="stylesheet" href="/css/color.css">

<!-- Layout e Navegação -->
<link rel="stylesheet" href="/css/layout.css">
<link rel="stylesheet" href="/css/menu-style.css">

<!-- Componentes -->
<link rel="stylesheet" href="/css/components.css">

<!-- Páginas internas -->
<link rel="stylesheet" href="/css/pages.css">
```

### **admin/dashboard.php** (Admin - Interna)

```html
<!-- Fundação -->
<link rel="stylesheet" href="/css/reset.css">
<link rel="stylesheet" href="/css/color.css">

<!-- Layout e Navegação -->
<link rel="stylesheet" href="/css/layout.css">
<link rel="stylesheet" href="/css/menu-style.css">

<!-- Componentes -->
<link rel="stylesheet" href="/css/components.css">

<!-- Páginas internas -->
<link rel="stylesheet" href="/css/pages.css">
```

### **admin/cadastros/*.php** (Cadastros - Interna)

```html
<!-- Fundação -->
<link rel="stylesheet" href="/css/reset.css">
<link rel="stylesheet" href="/css/color.css">

<!-- Layout e Navegação -->
<link rel="stylesheet" href="/css/layout.css">
<link rel="stylesheet" href="/css/menu-style.css">

<!-- Componentes -->
<link rel="stylesheet" href="/css/components.css">

<!-- Páginas internas -->
<link rel="stylesheet" href="/css/pages.css">
```

## 🔄 Histórico de Reorganização

### Arquivos Refatorados

- ✅ **style.css** → Dividido em `layout.css` + `menu-style.css`
- ✅ **form.css** → Movido para `components.css` (mantido por compatibilidade)
- ✅ **login.css** → Mantido (apenas página de login)
- ✅ **pages.css** → Mantido (apenas páginas internas)
- ✅ **reset.css** → Mantido (fundação)
- ✅ **color.css** → Mantido (variáveis)

### Novos Arquivos Criados

- ✅ **layout.css** - Estrutura e layout de página
- ✅ **menu-style.css** - Estilos de menu e navegação
- ✅ **components.css** - Componentes reutilizáveis

## 📋 Convenções

### Nomes de Classes

- `.layout-*` - Estrutura de página
- `.form-*` - Formulários
- `.button`, `.btn-*` - Botões
- `.alert-*` - Alertas e notificações
- `.menu-*` - Menu items
- `.nav-*` - Navegação superior
- `.card` - Cards reutilizáveis
- `.title-page` - Títulos de página
- `.container-main` - Container principal

### Responsividade

- **Mobile**: < 390px (hidden com `.noview`)
- **Tablet**: 390px - 767px
- **Desktop**: 768px - 1079px
- **Large**: 1080px - 1439px
- **XL**: 1440px - 1919px
- **XXL**: 1920px+

### Variáveis CSS

Definidas em `color.css`:

- `--color-white-*` - Branco com opacidade
- `--color-blue-*` - Tons de azul
- `--color-black-*` - Preto e tons
- `--bs-primary`, `--bs-danger`, `--bs-success`, etc. - Cores semânticas
- `--bs-box-shadow-*` - Sombras padrão

## ✅ Validação

- ✅ Nenhuma duplicação de estilos
- ✅ Separação clara de responsabilidades
- ✅ Ordem correta de carregamento
- ✅ Media queries em cada arquivo
- ✅ Sem dependências circulares
- ✅ Nomenclatura consistente

## 🎓 Padrões Aplicados

1. **Single Responsibility Principle** (SRP)
   - Cada arquivo tem propósito único

2. **Separation of Concerns** (SoC)
   - Layout separado de componentes
   - Menu separado de layout geral

3. **Mobile-First**
   - Estilos base aplicados a mobile
   - Media queries para dispositivos maiores

4. **DRY (Don't Repeat Yourself)**
   - Variáveis para valores reutilizáveis
   - Componentes genéricos para reutilização

## 📚 Referências

- [MDN: CSS Organization](https://developer.mozilla.org/pt-BR/docs/Web/CSS)
- [BEM Methodology](https://en.bem.info/)
- [SMACSS](https://smacss.com/)

## 🔜 Próximas Melhorias (Opcional)

1. Implementar SCSS/SASS para variáveis mais poderosas
2. Adicionar CSS preprocessor para aninhamento
3. Implementar utility-first approach (Tailwind-like)
4. Adicionar testes de acessibilidade (a11y)
5. Minificar e otimizar para produção

---

**Data**: 17/01/2026  
**Status**: ✅ Reorganização Completa
