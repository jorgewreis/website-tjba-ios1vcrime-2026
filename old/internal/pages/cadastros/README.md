# Pasta: internal/pages/cadastros/

## 📝 Propósito

Esta pasta é **reservada para futuras demandas PHP**, especialmente:

- Requisições de cadastros (APIs)
- Endpoints de processamento
- Serviços auxiliares
- Scripts de validação e negócio

## ✅ Arquivos de Páginas (Movidos)

Os arquivos de **exibição de páginas** foram movidos para `internal/pages/`:

```text
internal/pages/
├── cartasexpedidas.php
├── cartasrecebidas.php
├── cejusc.php
├── dashboard.php
├── editais.php
├── guias.php
├── medidasprotetivas.php
├── oficioscartorio.php
├── oficiosgabinete.php
├── produtividade.php
└── cadastros/ ← (esta pasta, vazia, para futuras demandas)
```

## 🎯 Uso Futuro

Quando precisar adicionar funcionalidades de cadastro como:

- Requisição AJAX para salvar cartas
- API para importação de dados
- Script de validação de formulários
- Etc.

**Coloque aqui dentro de `internal/pages/cadastros/`**

## 📞 Exemplo de Uso

```php
// Futura requisição de cadastro
// internal/pages/cadastros/salva_carta.php

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../bd/Conection.php';

// Lógica de cadastro aqui
?>
```

---

**Data**: 17 de janeiro de 2026  
**Status**: 🔄 Aguardando demandas
