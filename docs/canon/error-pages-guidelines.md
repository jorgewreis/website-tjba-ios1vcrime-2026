---
id: error-pages-guidelines
title: "Diretrizes para Páginas de Erro"
version: "1.0"
date: "2026-02-21"
---

# Diretrizes para Páginas de Erro

Este documento descreve o padrão visual e comportamental das páginas de erro internas (`app/views/internal/errors/`) e como editar os componentes reutilizáveis.

Componentes

- `_error_header.php` — header reutilizável com logo e título/descrição curta.
- `_error_details.php` — seção que apresenta texto informacional específico e detalhes técnicos (endpoint, método, datetime e outros metadados).
- `error_messages.php` — arquivo centralizado com mensagens informacionais por código de erro (401,403,404,500,503).

Estilos

- Todas as classes específicas estão em `/assets/css/custom.css` (seção "Error pages styles"). Não adicionar novas bibliotecas CSS.

Como editar uma página de erro

1. Abra `app/views/internal/errors/<code>.php`.  
2. Ajuste as variáveis `$code`, `$title`, `$message`. Use `$info_text` para fornecer um texto informacional detalhado específico do erro quando necessário.  
3. Os detalhes técnicos são exibidos por padrão (endpoint, método, datetime e metadados adicionais em `$details`). Não exponha stack traces em produção.

Testes

- Valide cada página em dev: `frame.php?page=404`, `frame.php?page=401`, etc.  
- Verifique contraste e navegação por teclado.

