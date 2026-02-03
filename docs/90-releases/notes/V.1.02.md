# Release Notes - V. 1.02  > Gerado automaticamente. # Draft Minor (acumulado para a proxima versao MINOR)

> Gerado automaticamente.
> Contem entradas detalhadas por commit (hash, data, tipo, impacto, arquivos).

---

## V. 1.00.0027

- **bump**: patch
- **impacto**: medio
- **tipo**: bugfix
- **hash**: `5e6bc84`
- **data**: 2026-02-01 21:13:35
- **mensagem**: fix: limpeza de arquivos utilizados em testes

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- M	docs/90-releases/_draft_major.md
- M	docs/90-releases/_draft_minor.md
- D	docs/90-releases/notes/V.1.03.md

### Estatisticas

-  CHANGELOG.md                     |  5 +++
-  VERSION                          |  2 +-
-  docs/90-releases/_draft_major.md | 18 ---------
-  docs/90-releases/_draft_minor.md | 27 -------------
-  docs/90-releases/notes/V.1.03.md | 86 ----------------------------------------
-  5 files changed, 6 insertions(+), 132 deletions(-)

---

## V. 1.00.0028

- **bump**: patch
- **impacto**: medio
- **tipo**: bugfix
- **hash**: `1dadf1c`
- **data**: 2026-02-01 21:20:34
- **mensagem**: fix: correção de erros de acentuação

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- M	scripts/update-draft-minor.ps1

### Estatisticas

-  CHANGELOG.md                   |  5 +++++
-  VERSION                        |  2 +-
-  scripts/update-draft-minor.ps1 | 35 +++++++++++++++++++----------------
-  3 files changed, 25 insertions(+), 17 deletions(-)

---

## V. 1.01.0001

- **bump**: patch
- **impacto**: baixo
- **tipo**: tests
- **hash**: `d955fdd6fcc8a6e21a033deefc347c0caf287e95`
- **data**: 2026-02-02 10:44:26
- **mensagem**: test: testar script

### Arquivos (name-status)

- M	CHANGELOG.md
- A	docs/10-architecture/published/Dev - Web App - Ios1VCrime - Modelo de Camadas.pdf
- A	"docs/10-architecture/published/Dev - Web App - Ios1VCrime - Politicas de autentica\303\247\303\243o.pdf"
- A	docs/40-security/authentication_password_policy.md

### Estatisticas

-  CHANGELOG.md                                       |  24 ---
-  ... - Web App - Ios1VCrime - Modelo de Camadas.pdf | Bin 0 -> 135051 bytes
-  ... - Politicas de autentica\303\247\303\243o.pdf" | Bin 0 -> 128512 bytes
-  docs/40-security/authentication_password_policy.md | 200 +++++++++++++++++++++
-  4 files changed, 200 insertions(+), 24 deletions(-)

---

## V. 1.01.0002

- **bump**: patch
- **impacto**: medio
- **tipo**: tests
- **hash**: `633d08f`
- **data**: 2026-02-02 12:31:22
- **mensagem**: test: hook

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION

### Estatisticas

-  CHANGELOG.md | 5 +++++
-  VERSION      | 2 +-
-  2 files changed, 6 insertions(+), 1 deletion(-)

---

## V. 1.01.8000

- **bump**: patch8000
- **impacto**: medio
- **tipo**: bugfix
- **hash**: `5e50c91`
- **data**: 2026-02-02 12:34:23
- **mensagem**: fix: erros de versionamento corrigidos [hotfix] close (#58)

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- M	docs/90-releases/_draft_minor.md
- M	scripts/update-draft-minor.ps1

### Estatisticas

-  CHANGELOG.md                     |  5 +++++
-  VERSION                          |  2 +-
-  docs/90-releases/_draft_minor.md | 22 ++++++++++++++++++++++
-  scripts/update-draft-minor.ps1   | 12 ++++++------
-  4 files changed, 34 insertions(+), 7 deletions(-)

---

## V. 1.01.8001

- **bump**: patch
- **impacto**: medio
- **tipo**: change
- **hash**: `4b469ed`
- **data**: 2026-02-02 12:44:49
- **mensagem**: config: configurações internas de regramento md

### Arquivos (name-status)

- M	.markdownlint.json
- M	CHANGELOG.md
- M	VERSION
- M	docs/90-releases/_draft_minor.md

### Estatisticas

-  .markdownlint.json               | 2 ++
-  CHANGELOG.md                     | 5 +++++
-  VERSION                          | 2 +-
-  docs/90-releases/_draft_minor.md | 1 +
-  4 files changed, 9 insertions(+), 1 deletion(-)

---

## V. 1.01.8002

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `bc03528`
- **data**: 2026-02-02 12:59:05
- **mensagem**: docs: novo documento de arquitetura - close (#42)

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- R100	docs/10-architecture/charter/documento-arquitetura-woftware.md	docs/10-architecture/charter/documento-arquitetura-software.md
- A	docs/10-architecture/charter/documento-integracoes-api.md

### Estatisticas

-  CHANGELOG.md                                       |   5 +
-  VERSION                                            |   2 +-
-  ...ftware.md => documento-arquitetura-software.md} |   0
-  .../charter/documento-integracoes-api.md           | 186 +++++++++++++++++++++
-  4 files changed, 192 insertions(+), 1 deletion(-)

---

## V. 1.01.8003

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `c47d593`
- **data**: 2026-02-02 13:19:20
- **mensagem**: docs: criação de documentação de escopo - close (#34)

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- A	docs/00-foundation/glossary/escopo-produto.md
- A	docs/00-foundation/glossary/escopo-projeto.md

### Estatisticas

-  CHANGELOG.md                                  |   5 +
-  VERSION                                       |   2 +-
-  docs/00-foundation/glossary/escopo-produto.md | 240 ++++++++++++++++++++++++++
-  docs/00-foundation/glossary/escopo-projeto.md | 142 +++++++++++++++
-  4 files changed, 388 insertions(+), 1 deletion(-)

---

## V. 1.01.8004

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `be1eb23`
- **data**: 2026-02-02 13:24:07
- **mensagem**: docs: novo documento de arquitetura criado - close (#55)

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- A	docs/00-foundation/overview/documento-regras-exclusao.md

### Estatisticas

-  CHANGELOG.md                                       |   5 +
-  VERSION                                            |   2 +-
-  .../overview/documento-regras-exclusao.md          | 299 +++++++++++++++++++++
-  3 files changed, 305 insertions(+), 1 deletion(-)

---

## V. 1.01.8005

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `2e81152`
- **data**: 2026-02-02 20:35:52
- **mensagem**: docs: novo documento de segurança criado - close (#56)

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- R100	docs/40-security/authentication_password_policy.md	docs/30-security/auth/authentication_password_policy.md
- A	docs/30-security/rbac/politicas-acesso-setor.md
- M	docs/90-releases/_draft_major.md
- M	docs/90-releases/_draft_minor.md

### Estatisticas

-  CHANGELOG.md                                       |   5 +
-  VERSION                                            |   2 +-
-  .../auth}/authentication_password_policy.md        |   0
-  docs/30-security/rbac/politicas-acesso-setor.md    | 148 +++++++++++++++++++++
-  docs/90-releases/_draft_major.md                   |   2 +-
-  docs/90-releases/_draft_minor.md                   |  10 +-
-  6 files changed, 161 insertions(+), 6 deletions(-)

---

## V. 1.01.8006

- **bump**: patch
- **impacto**: medio
- **tipo**: change
- **hash**: `1a42547`
- **data**: 2026-02-02 20:54:24
- **mensagem**: config: alteração nos registros de versionamento

### Arquivos (name-status)

- M	.githooks/commit-msg
- M	.githooks/post-commit
- M	CHANGELOG.md
- M	VERSION
- M	scripts/bump-version.ps1
- M	scripts/update-draft-minor.ps1

### Estatisticas

-  .githooks/commit-msg           |  4 ++--
-  .githooks/post-commit          |  4 ++--
-  CHANGELOG.md                   |  5 +++++
-  VERSION                        |  2 +-
-  scripts/bump-version.ps1       | 18 +++---------------
-  scripts/update-draft-minor.ps1 |  5 +++--
-  6 files changed, 16 insertions(+), 22 deletions(-)

---

## V. 1.01.8007

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `eb3415d`
- **data**: 2026-02-02 20:55:33
- **mensagem**: docs: novo documento de segurança criado - close (#237)

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- A	"docs/30-security/rbac/politicas-governan\303\247a.md"

### Estatisticas

-  CHANGELOG.md                                       |  5 ++
-  VERSION                                            |  2 +-
-  .../rbac/politicas-governan\303\247a.md"           | 75 ++++++++++++++++++++++
-  3 files changed, 81 insertions(+), 1 deletion(-)

---

## V. 1.01.8008

- **bump**: patch
- **impacto**: medio
- **tipo**: bugfix
- **hash**: `b61561b`
- **data**: 2026-02-02 20:57:40
- **mensagem**: fix: correção de nomenclatura

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- R100	"docs/30-security/rbac/politicas-governan\303\247a.md"	docs/30-security/rbac/politicas-governanca.md

### Estatisticas

-  CHANGELOG.md                                                         | 5 +++++
-  VERSION                                                              | 2 +-
-  .../30-security/rbac/politicas-governanca.md                         | 0
-  3 files changed, 6 insertions(+), 1 deletion(-)

---

## V. 1.01.8009

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `474c99d`
- **data**: 2026-02-02 21:21:31
- **mensagem**: docs: novo documento de segurança criado

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- A	docs/30-security/sources/security-requirements.md

### Estatisticas

-  CHANGELOG.md                                      |   5 ++
-  VERSION                                           |   7 +-
-  docs/30-security/sources/security-requirements.md | 104 ++++++++++++++++++++++
-  3 files changed, 110 insertions(+), 6 deletions(-)

---

## V. 1.01.8010

- **bump**: patch
- **impacto**: medio
- **tipo**: docs
- **hash**: `c76105c`
- **data**: 2026-02-02 21:41:44
- **mensagem**: docs: novos documentos de security, operations e foundation criados

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION
- A	docs/00-foundation/scope/accessibility-requirements.md
- A	docs/00-foundation/scope/technical-assumptions-constraints.md
- M	docs/30-security/sources/security-requirements.md
- A	docs/40-operations/access/performance-requirements.md
- A	docs/40-operations/monitoring/audit-traceability-requirements.md

### Estatisticas

-  CHANGELOG.md                                       |   5 +
-  VERSION                                            |   2 +-
-  .../scope/accessibility-requirements.md            |  68 ++++++++++++
-  .../scope/technical-assumptions-constraints.md     |  52 +++++++++
-  docs/30-security/sources/security-requirements.md  | 122 ++++++++-------------
-  .../access/performance-requirements.md             |  47 ++++++++
-  .../monitoring/audit-traceability-requirements.md  |  59 ++++++++++
-  7 files changed, 275 insertions(+), 80 deletions(-)

---

## V. 1.02

- **bump**: minor
- **impacto**: medio
- **tipo**: refactor
- **hash**: `bd2385e`
- **data**: 2026-02-02 21:44:35
- **mensagem**: refactor: correção de versão [minor]

### Arquivos (name-status)

- M	CHANGELOG.md
- M	VERSION

### Estatisticas

-  CHANGELOG.md | 17 ++++-------------
-  VERSION      |  2 +-
-  2 files changed, 5 insertions(+), 14 deletions(-)

---
