# Controle de acesso (módulo único)

A política de páginas do `frame.php`, visibilidade do menu na `home.php` e (em evolução) as APIs devem usar **`app/access/`**.

## Arquivos

| Arquivo | Função |
|---------|--------|
| `AccessEvaluator.php` | `canAccessPage($page, $perfis)`, `permittedMenuSections($perfis)` |
| `PageAccessRegistry.php` | Mapa `page` → regra (`area_a`, `exact`, `all_internal`, `consultas_*`, `gestao`, …) |
| `ProfileNormalizer.php` | Normalização de perfis, `hasCompositeForArea`, compostos `ESTAGIARIO-*` / `SERVIDOR-*` |
| `AccessAreas.php` | Constantes `CARTORIO`, `GABINETE`, `AUDIENCIAS` |
| `AccessPolicyVersion.php` | Versão da matriz (logs 403) |

## Perfis compostos (sem legado)

Formato: **`CARGO-AREA`** em maiúsculas, com hífen:

- `ESTAGIARIO-CARTORIO`, `SERVIDOR-CARTORIO`
- `ESTAGIARIO-GABINETE`, `SERVIDOR-GABINETE`
- `ESTAGIARIO-AUDIENCIAS`, `SERVIDOR-AUDIENCIAS`

Não usar após migração: `ESTAGIARIO`, `SERVIDOR`, `CARTORIO`, `GABINETE`, `AUDIENCIA` isolados.

## Modos de rota

- **Modo A (`area_a`)**: qualquer `*-CARTORIO` (ou GABINETE / AUDIENCIAS) acessa a página.
- **Modo B (`exact`)**: apenas perfis listados (ex.: só `SERVIDOR-CARTORIO`). Sobrescrever por página em `PageAccessRegistry::EXACT_PAGE_PROFILES`.

## Bypass

`ADMIN` e `CHEFIA` (e `DIRETORA` / `MAGISTRADA` normalizadas para `CHEFIA`) passam em todas as páginas internas, exceto fluxo exclusivamente externo.

## Página omitida no registry

Comportamento: **negado** (`deny`).

## Matrizes por módulo

Ver `matriz-cartorio.md`, `matriz-gabinete.md`, `matriz-audiencias.md`, `matriz-globais.md` (sincronizar com `PageAccessRegistry`).

## Migração de dados

Passo a passo detalhado: **`MIGRACAO-DADOS.md`** nesta pasta.

SQL prontos: `database/migrations/20260322_setores_codigo_area.sql` e `20260322_usuario_perfil_composite_limpeza.sql`.
