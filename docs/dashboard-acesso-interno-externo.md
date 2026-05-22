# Dashboard interno vs externo

## Definição

- **Usuário externo** (`UserAccessHelper::isUsuarioExterno`): não possui perfis operacionais internos (ADMIN, CHEFIA, DIRETORA, MAGISTRADA, SERVIDOR, ESTAGIARIO, CARTORIO, GABINETE, AUDIENCIA) e possui perfil **ESTUDANTE** e/ou **CONSULTOR**.
- **Usuário interno**: qualquer outro caso com sessão válida.

## Páginas

| Rota `frame.php?page=` | Uso |
|------------------------|-----|
| `dashboard` | Painel interno (servidores, estagiários lotados, chefia, etc.). |
| `dashboardexterno` | Painel inicial para estudantes/consultores externos. |

## Regras de acesso (`public/frame.php` + `AccessEvaluator`)

- Usuário externo que solicita `dashboard` é **redirecionado** para `dashboardexterno`.
- Usuário interno que solicita `dashboardexterno` é **redirecionado** para `dashboard`.
- **Externo** (`AccessEvaluator::externoCanAccessPage`): `alterardados`; **estudante** → `estudapauta`, `estudapainel`; **consultor** → `consultapartes`, `instituicoespublicas`, `partesinstituicoeslist`.
- **Cadastros/edições** ligados a fluxo de cartório continuam com regra `area_a` / Cartório (`consultapartescreate`, `consultapartesedit`, cadastros de instituições, etc.), independentemente da listagem de consulta.

### Consultas (modelo atual)

| Recurso | ESTUDANTE externo | CONSULTOR externo | Interno (composto ou elevado) |
|---------|-------------------|-------------------|-------------------------------|
| Partes / instituições (listagens) | Não | Sim | Sim |
| Processos | Não | Não | Sim |

A **consulta de CEP** (página dedicada, API e busca ViaCEP) foi **descontinuada**. O cadastro de endereços em instituições públicas e pessoas (consulta partes) também foi removido.

## UI (`public/home.php`)

- Menu lateral e ícone da navbar usam `dashboard` ou `dashboardexterno` conforme o tipo de usuário.
- Label do primeiro item: **Dashboard** (interno) ou **Início** (externo).
- **Consultor externo:** seção de menu **Consultas** com `consultapartes` e `instituicoespublicas`.
- **Estudante externo:** seção **Estudantes** (`estudapauta`, `estudapainel`); sem itens em Consultas.
- **`itemVisibleForUser`:** filtra alvos via `MENU_TARGETS_CONSULTAS_EXTERNO_CONSULTOR` e `MENU_TARGETS_CONSULTAS_EXTERNO_ESTUDANTE`.

## Tela `dashboard-externo.php`

- Textos distintos para **estudante externo** (pauta, painel, atas) e **consultor externo** (partes e instituições).

## Regra Cursor

Ver `.cursor/rules/dashboard-interno-externo.mdc`.
