# Exportação em listagens internas (CSV e PDF)

**Escopo:** área restrita, telas de lista que oferecem download da visão filtrada em CSV e/ou PDF.

## Padrão de interface

As exportações **não** usam dois botões separados. O padrão é um **`<select>`** com três opções:

1. **Exportar lista** — primeira opção, `selected` e `disabled` (rótulo placeholder; não dispara ação).
2. **Exportar CSV** — abre o endpoint de CSV em nova aba.
3. **Exportar PDF** — abre o endpoint de PDF em nova aba.

Após escolher CSV ou PDF, o controle **volta** para a primeira opção (comportamento em `public/assets/js/shared/navigate-buttons.js`, classe `list-export-select`).

O markup compartilhado fica em **`app/views/internal/_list_export_buttons.php`**, com label visível **Exportação** e `aria-label="Exportar lista"` no `<select>`.

## Integração no PHP

**Modo B (maioria das listas):** `internal-list-export`

- `$listExportPage` — chave da rota em `frame.php` (ex.: `feriadoslist`).
- `$listExportQuery` — array de query string com os filtros atuais da lista (sem `page_number` / `items_per_page`, removidos no partial).

**Modo A (URLs próprias):** quando o CSV/PDF não passa por `internal-list-export` (ex.: produtividade):

- `$listExportCsvUrl` e `$listExportPdfUrl` — URLs completas (já com query).
- `$listExportControlId` — sufixo estável para o `id` do `<select>` (evita colisão se houver mais de um controle na página).

## Endpoints

- Listas genéricas: `public/api/internal-list-export/csv.php` e `pdf.php` (handler em `app/helpers/InternalListExportHandlers.php`).
- Produtividade (lançamentos): `public/api/produtividade/export-csv.php` e `export-pdf.php`.

## Referências

- Partial: `app/views/internal/_list_export_buttons.php`
- Script: `public/assets/js/shared/navigate-buttons.js`
