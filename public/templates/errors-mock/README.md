# Mocks visuais — páginas de erro HTTP

Arquivos **somente HTML + CSS** para validar layout, tamanhos e textos antes de integrar PHP/partials.

## Como visualizar

1. **Servidor local (recomendado)** — a partir da pasta `public` do projeto:
   ```bash
   php -S localhost:8080
   ```
   Abra no navegador:
   - `http://localhost:8080/templates/errors-mock/` (índice com links)
   - ou diretamente: `401.html`, `403.html`, `404.html`, `500.html`, `503.html`

2. **Abrir arquivo direto** — os caminhos dos assets usam `../../assets/...` relativos a esta pasta; funciona se o arquivo estiver em `public/templates/errors-mock/`.

## Arquivos

| Arquivo | Conteúdo (mock) |
|---------|-------------------|
| `401.html` | Não autorizado |
| `403.html` | Proibido |
| `404.html` | Não encontrado |
| `500.html` | Erro interno |
| `503.html` | Serviço indisponível |
| `../../assets/css/shared/error.css` | Mesmo CSS das páginas PHP (`error-page-*` e aliases `error-mock-*`) |

## Produção

As views PHP em `app/views/internal/errors/` usam o mesmo layout (`_error_head.php`, `_error_body.php`, `_error_footer.php`) e o CSS compartilhado `public/assets/css/shared/error.css` (classes `error-page-*`).
