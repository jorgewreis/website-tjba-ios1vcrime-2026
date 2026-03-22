# Dashboard interno vs externo

## Definição

- **Usuário externo** (`UserAccessHelper::isUsuarioExterno`): não possui perfis operacionais internos (ADMIN, CHEFIA, DIRETORA, MAGISTRADA, SERVIDOR, ESTAGIARIO, CARTORIO, GABINETE, AUDIENCIA) e possui perfil **ESTUDANTE** e/ou **CONSULTOR**.
- **Usuário interno**: qualquer outro caso com sessão válida.

## Páginas

| Rota `frame.php?page=` | Uso |
|------------------------|-----|
| `dashboard` | Painel interno (servidores, estagiários lotados, chefia, etc.). |
| `dashboardexterno` | Painel inicial para estudantes/consultores externos. |

## Regras de acesso (`public/frame.php`)

- Usuário externo que solicita `dashboard` é **redirecionado** para `dashboardexterno`.
- Usuário interno que solicita `dashboardexterno` é **redirecionado** para `dashboard`.
- **Bloco de externo** (`hasAccessToSection`): lista branca por seção — **Perfil**, **Externo**, **ConsultasCep**; se o usuário **não** tiver perfil **ESTUDANTE**, inclui também **ConsultasPartesInstituicoes**. (Assim, estudante externo não enxerga rotas dessa seção no servidor.)
- **Cadastros/edições** ligados a fluxo de cartório continuam com seção **Cartório** (`consultapartescreate`, `consultapartesedit`, cadastros de instituições, etc.), independentemente da listagem.

### Seções de consulta (modelo atual)

| Seção | Uso típico | ESTUDANTE externo | CONSULTOR externo |
|-------|------------|-------------------|-------------------|
| **ConsultasCep** | `consultacep` | Sim | Sim |
| **ConsultasPartesInstituicoes** | listagens: `consultapartes`, `instituicoespublicas`, `partesinstituicoeslist`, … | Não | Sim |
| **ConsultasProcessos** | `consultaprocessos`, `consultaprocessosedit` | Não | Não |

Perfis em cada seção estão no array `$sectionMap` em `public/frame.php` (inclui normalização DIRETORA/MAGISTRADA → CHEFIA na verificação).

### Ajuste recente (revisão)

Foi consolidada a ideia de **separar** listagens de consulta em três seções nomeadas (CEP / partes e instituições / processos), em vez de uma única seção genérica, para manter **estudante só com CEP**, **consultor com partes + instituições + CEP**, e **processos apenas para operação interna**.  
Em uma iteração intermediária havia o nome **ConsultasInternas** e `consultacep` mapeado como `All`; isso gerava inconsistência com o bloqueio de externo e com entradas ausentes no `$sectionMap`. O estado está **alinhado** com: `consultacep` → **ConsultasCep**, listagens de partes/instituições → **ConsultasPartesInstituicoes**, processos → **ConsultasProcessos**, e o `$sectionMap` preenchido para essas chaves.

### Estagiário

`getEstagiarioAllowedSections` inclui **ConsultasCep**, **ConsultasPartesInstituicoes** e **ConsultasProcessos** além das seções derivadas do setor, para o controle por setor continuar coerente com as novas seções.

## UI (`public/home.php`)

- Menu lateral e ícone da navbar usam `dashboard` ou `dashboardexterno` conforme o tipo de usuário.
- Label do primeiro item: **Dashboard** (interno) ou **Início** (externo).
- **Externo:** `getPermittedSections` retorna apenas a seção de menu **Consultas** (evita vazar outras áreas se a API de perfis errar).
- **`itemVisibleForUser`:** com **HAS_PERFIL_ESTUDANTE** → só `consultacep`; sem estudante (ex.: consultor externo) → `consultapartes`, `instituicoespublicas`, `consultacep`.

## Tela `dashboard-externo.php`

- Textos distintos para **estudante externo** (ênfase em CEP e limitações) e **consultor externo** (partes, instituições, CEP).

## Regra Cursor

Ver `.cursor/rules/dashboard-interno-externo.mdc`.
