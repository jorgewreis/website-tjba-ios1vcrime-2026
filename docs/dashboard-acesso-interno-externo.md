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
- Externos só acessam seções mapeadas como **Perfil** (ex.: alterar dados), **Consultas** e **Externo**; demais rotas retornam 403.

## UI (`public/home.php`)

- Menu lateral e ícone da navbar usam `dashboard` ou `dashboardexterno` conforme o tipo de usuário.
- Label do primeiro item: **Dashboard** (interno) ou **Início** (externo).
