---
id: error-pages-guidelines
title: "Diretrizes para Páginas de Erro"
version: "1.0"
date: "2026-02-21"
---

# Diretrizes para Páginas de Erro

Este documento descreve o padrão visual e comportamental das páginas de erro internas (`app/views/internal/errors/`) e como editar os componentes reutilizáveis.

Componentes

- `_error_header.php` — header reutilizável com logo e link para home.
- `_error_card.php` — card principal com código, título, mensagem, ações e sugestões.
- `_error_details.php` — bloco ocultável que apresenta metadados (timestamp, request, trace_id).

Estilos

- Todas as classes específicas estão em `public/assets/css/custom.css` (seção \"Error pages styles\"). Não adicionar novas bibliotecas CSS.

Como editar uma página de erro

1. Abra `app/views/internal/errors/<code>.php`.  
2. Ajuste as variáveis `$code`, `$title`, `$message`, `$actions`, `$suggestions` conforme o contexto.  
3. Use o DetailsToggle para expor `trace_id` ou `timestamp` quando útil para suporte — não exponha stack traces.

Testes

- Valide cada página em dev: `frame.php?page=404`, `frame.php?page=401`, etc.  
- Verifique contraste e navegação por teclado.

