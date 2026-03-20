---
id: error-pages-guidelines
title: "Diretrizes para Páginas de Erro"
version: "1.1"
date: "2026-03-20"
---

# Diretrizes para Páginas de Erro

Este documento descreve o padrão visual e comportamental das páginas de erro internas (`app/views/internal/errors/`) e como editar os componentes reutilizáveis.

## Componentes

- `_error_head.php` — `<!DOCTYPE>`, `<head>`, `_internal_head_styles.php`, `error.css`, Flaticon, `_toast_script.php`.
- `_error_body.php` — `header-page`, card com hero (ícone + código + lead), texto informacional longo, `<dl>` de detalhes técnicos, bloco de suporte.
- `_error_footer.php` — `footer-page` e script `error-page.js`.
- `error_detail_defaults.php` — função `error_page_merge_details(string $code, array $override = []): array` para montar os pares label/valor dos detalhes técnicos.
- `error_icons.php` — ícone (`path`, `alt`) e variante visual (`warning` | `danger` | `info` | `maintenance`) por código HTTP.
- `error_messages.php` — textos informacionais longos por código (401, 403, 404, 500, 503).

## Estilos

- `public/assets/css/shared/error.css` — classes `error-page-*` (alinhado a `public/templates/errors-mock/`).
- Stack global: `reset`, `color`, tema, `layout`, `page`, `form`, `table`, `toast` (via `_internal_head_styles.php`).
- **Não** usar Bootstrap nestas páginas.

## Variáveis por página (`<code>.php`)

Cada `401.php` … `503.php` define, antes dos `include`:

- `$pageTitle`, `$pageHeading`, `$description`, `$error_code`
- `$info_text` (a partir de `error_messages.php`)
- `$icon_path`, `$icon_alt`, `$variant` (a partir de `error_icons.php`)
- `$details = error_page_merge_details(...)` — opcional `$details_override` antes do merge

### 503 — previsão de retorno

Se a variável de ambiente `MAINTENANCE_UNTIL` estiver definida (string não vazia), o valor substitui o campo **Previsão** nos detalhes técnicos.

## Testes

- Em dev: `frame.php?page=401`, `frame.php?page=403`, … `frame.php?page=503`.
- Mocks estáticos: `public/templates/errors-mock/*.html` (somente HTML/CSS de referência).

## Segurança

- Não expor stack traces em produção nos detalhes técnicos.
- Textos exibidos passam por `htmlspecialchars` onde aplicável; `$support_text` customizado deve ser confiável (sem HTML de origem externa não sanitizada).
