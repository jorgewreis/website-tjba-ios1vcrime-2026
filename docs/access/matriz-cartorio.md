# Matriz — Cartório (modo A por omissão)

Todas as rotas abaixo usam **modo A** (`area_a` + `CARTORIO`), salvo entrada explícita em `PageAccessRegistry::EXACT_PAGE_PROFILES`.

| page (frame.php) | Modo | Perfis |
|------------------|------|--------|
| editais, editaisedit, editaiscreate, editaisrules | A | `*-CARTORIO` |
| guias, guiasedit, guiascreate, guiasrules | A | `*-CARTORIO` |
| oficioscartorio, oficioscartorioedit | A | `*-CARTORIO` |
| cartasexpedidas, cartasexpedidasedit, cartasexpedidascreate, cartasexpedidasrules | A | `*-CARTORIO` |
| cartasrecebidas, cartasrecebidasedit, cartasrecebidascreate, cartasrecebidasrules | A | `*-CARTORIO` |
| produtividade | A | `*-CARTORIO` |
| consultapartescreate, consultapartesedit | A | `*-CARTORIO` |
| instituicoespublicascreate, instituicoespublicasedit | A | `*-CARTORIO` |
| processospartescreate, processosparteslist, processospartesedit | A | `*-CARTORIO` |
| partesinstituicoescreate, partesinstituicoesedit | A | `*-CARTORIO` |

**Bypass:** `ADMIN`, `CHEFIA` (e equivalentes).
