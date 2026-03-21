# Matriz — Sala de Audiências

Não há rotas `frame.php` mapeadas neste repositório para Sala de Audiências (itens do menu `salapauta`, `salaata`, `salapainel` podem apontar para páginas ainda não registadas em `allowed_pages`).

Quando existirem, registe em `PageAccessRegistry` com:

```php
['kind' => 'area_a', 'area' => AccessAreas::AUDIENCIAS],
```

ou **modo B** via `EXACT_PAGE_PROFILES`.

**Bypass:** `ADMIN`, `CHEFIA`.
