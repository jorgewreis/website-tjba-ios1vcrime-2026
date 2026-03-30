# Sistema de Gestão do Cartório ⚖️

## Primeira Vara Crime da Comarca de Ilhéus

Sistema web interno para centralizar rotinas do **Cartório**, **Gabinete** e **Audiências**, além de um módulo dedicado a **Estudantes**, com controle de acesso por perfil e foco em organização, produtividade e rastreabilidade.

- **Desenvolvimento:** Jorge Reis (Desenvolvedor Fullstack)
- **Stack:** HTML, CSS, JavaScript, PHP e SQL (MySQL)
- **Perfis:** `ADMIN` · `SERVIDOR` · `ESTUDANTE`

---

## Sumário

- [Sistema de Gestão do Cartório ⚖️](#sistema-de-gestão-do-cartório-️)
  - [Primeira Vara Crime da Comarca de Ilhéus](#primeira-vara-crime-da-comarca-de-ilhéus)
  - [Sumário](#sumário)
  - [Visão geral](#visão-geral)
  - [Área externa (pública)](#área-externa-pública)
  - [Áreas internas (módulos)](#áreas-internas-módulos)
    - [Cartório](#cartório)
    - [Gabinete](#gabinete)
    - [Audiências](#audiências)
    - [Estudantes](#estudantes)
    - [Settings](#settings)
  - [Perfis de acesso (resumo)](#perfis-de-acesso-resumo)
  - [Fluxo de navegação](#fluxo-de-navegação)
  - [Padrões de dados e validações](#padrões-de-dados-e-validações)
    - [CPF](#cpf)
    - [Usuários](#usuários)
  - [UI: paleta de cores](#ui-paleta-de-cores)
    - [Neutras e base](#neutras-e-base)
    - [Azuis (interface)](#azuis-interface)
    - [Semânticas (feedback)](#semânticas-feedback)
  - [UI: padrão de componentes](#ui-padrão-de-componentes)
    - [Layout e superfícies](#layout-e-superfícies)
    - [Navegação e estado ativo](#navegação-e-estado-ativo)
    - [Botões](#botões)
    - [Alerts / mensagens](#alerts--mensagens)
    - [Inputs (formulários)](#inputs-formulários)
  - [Segurança (boas práticas)](#segurança-boas-práticas)
  - [Logs e auditoria](#logs-e-auditoria)
  - [Estrutura do projeto (referência)](#estrutura-do-projeto-referência)
  - [Configuração Inicial](#configuração-inicial)
  - [Contexto Ativo (estado atual)](#contexto-ativo-estado-atual)
  - [Licença e uso](#licença-e-uso)
  - [Créditos](#créditos)

---

## Visão geral

O **Sistema de Gestão do Cartório** foi desenvolvido para apoiar atividades internas da Primeira Vara Crime da Comarca de Ilhéus, oferecendo um ambiente web organizado por módulos, com funcionalidades de cadastro, consulta, acompanhamento e documentação de rotinas.

---

## Área externa (pública)

Área acessível antes do login, voltada ao acesso e manutenção de conta:

- **Login** (entrada principal: `index.php`)
- **Recuperação de senha**
- **Cadastro de novo usuário**

---

## Áreas internas (módulos)

### Cartório

Rotinas cartorárias e acompanhamento de expedientes.

**Subáreas:**

- **Cartas Precatórias**
  - **Recebidas**
  - **Enviadas**
- **Editais**
- **Ofícios**
- **Guias**
- **Medidas Protetivas**
- **Produtividade**

---

### Gabinete

Apoio ao gabinete e controles específicos.

**Subáreas:**

- **Réus presos**
- **Ofícios**
- **Monitoramento eletrônico**

---

### Audiências

Organização e gestão das audiências.

**Subáreas:**

- **Pauta**
- **Atas**
- **Painel**
- **Partes**
- **Processos**

---

### Estudantes

Área dedicada ao perfil **ESTUDANTE**, com acesso restrito e orientado ao acompanhamento do fluxo de audiências.

**Subáreas:**

- **Pauta**
  - **do dia**
  - **do mês**
- **Atas**
  - **download de atas** das audiências realizadas
- **Inscrição**
  - opção para **assistir audiências do dia**
- **Painel**
  - informações **em tempo real** sobre audiências em andamento

---

### Settings

Configurações do sistema e do usuário.

**Subáreas:**

- **Segurança**
- **Perfil**

---

## Perfis de acesso (resumo)

A autorização é baseada em perfil, garantindo que cada usuário visualize e execute apenas o que for permitido.

| Perfil | Acesso principal |
| ---: | --- |
| **ADMIN** | Acesso total aos módulos e ações administrativas (conforme regras internas). |
| **SERVIDOR** | Rotinas operacionais de Cartório, Gabinete e Audiências conforme permissões definidas. |
| **ESTUDANTE** | Acesso restrito ao módulo **Estudantes** (e demais consultas permitidas). |

> Recomendação: manter uma matriz de permissões por **módulo × ação** (`visualizar`, `criar`, `editar`, `excluir`, `download`).

---

## Fluxo de navegação

Padrão de navegação do sistema:

- **Login:** `index.php`
- **Após login:** redireciona para `admin/home.php`
- **Páginas internas:** exibidas dentro de um **iframe** em `admin/home.php` (menu carrega no mesmo iframe)
- **Exposição de URLs internas:** preferir páginas internas acessadas por **arquivos públicos** que fazem `include` dos scripts da área `admin`, evitando expor diretamente URLs sensíveis
- **Logout:** após logout bem-sucedido, redirecionar para `index.php`

---

## Padrões de dados e validações

### CPF

- **Armazenamento:** CPF salvo **com máscara** em `VARCHAR` (ex.: `000.000.000-00`)
- **Exibição:** sempre com máscara
- **Validação:** validar usando **apenas dígitos** (remover `.` e `-` antes de validar)

### Usuários

- **Chave primária:** `id_usuario` (padrão de ID: 30 caracteres = 3 letras + hífen + 26 chars; ver [docs/00-foundation/id-padrao-banco-dados.md](docs/00-foundation/id-padrao-banco-dados.md))

---

## UI: paleta de cores

Diretrizes práticas para manter consistência visual.

### Neutras e base

| Uso | Cor |
| --- | --- |
| Branco (superfícies / cards) | `#ffffff` |
| Branco translúcido (níveis) | `rgba(255,255,255,0.56)`, `rgba(255,255,255,0.38)`, `rgba(255,255,255,0.19)`, `rgba(255,255,255,0.06)` |
| Fundo da aplicação | `#f5f5f9` |
| Texto (body) | `#646e78` |
| Preto / cinza escuro (contraste) | `#000000`, `#202020` |

### Azuis (interface)

| Grupo | Cores |
| --- | --- |
| Tons claros (fundos/realces) | `#f5f5f9`, `#e2edf1`, `#cce2eb` |
| Tons médios (bordas/apoio) | `#aaccda`, `#aac6d1` |
| Tons fortes (ações/destaques) | `#2a8ab1`, `#196481`, `#0e455a`, `#05202b` |

### Semânticas (feedback)

| Tipo | Cor (texto/ícone) | Cor (fundo) |
| --- | --- | --- |
| Sucesso | `#71dd37` | `#e8fadf` |
| Perigo | `#FF3E1D` | `#ffe0db` |
| Atenção | `#ffab00` | `#fff2d6` |
| Info | `#03c3ec` | `#d7f5fc` |
| Primária (framework) | `#696cff` | `#e7e7ff` |

---

## UI: padrão de componentes

Diretrizes práticas para manter consistência visual (com base na paleta acima).

### Layout e superfícies

- **Fundo geral:** usar o **azul bem claro** (grupo "Tons claros").
- **Cards/containers:** usar **branco**.
- **Bordas/divisórias:** usar **tons médios** (azuis médios), evitando contraste pesado.
- **Texto:** padrão em **cinza do body**; títulos podem usar **azul bem escuro** (tons fortes).

### Navegação e estado ativo

- **Item ativo / destaque:** usar **azul forte** (grupo "Tons fortes").
- **Hover:** usar o próximo tom mais escuro (ainda no grupo "Tons fortes").
- **Navbar/Sidebar escura (quando houver):** usar **azul bem escuro** com texto **branco**.

### Botões

- **Primário:** azul forte (tons fortes) + texto branco.
- **Secundário:** fundo em tons claros + borda em tom médio + texto em azul escuro.
- **Perigo (ação destrutiva):** usar a cor **Perigo** + texto branco.

### Alerts / mensagens

- Para **feedback de operação** (salvar, erro de API, etc.) use o **sistema de toast** (`App.Toast` / `showToast`), não `alert()`. Política de modelo simple vs full e empilhamento: ver **`docs/00-foundation/sistema-toast.md`** e `.cursor/rules/toast-system.mdc`.
- Para **cores semânticas** em componentes estáticos (chips, badges), use o par **(cor do tipo) + (fundo do tipo)**:
  - **Sucesso**, **Perigo**, **Atenção**, **Info**.

### Inputs (formulários)

- Fundo branco, borda em tom médio.
- No foco, borda em azul forte e (se usar) sombra bem discreta.

### Listas internas (padrão de página)

As páginas internas de listagem (cartório, consultas, etc.) compartilham o mesmo esqueleto visual:

- **Container principal:** `container-full card-page`.
- **Faixa de título:** `display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom`
  - Esquerda: `<h2>` com o título da lista.
  - Direita: busca, select de export (quando existir) e/ou botão principal (ex.: `Novo cadastro`), usando larguras numéricas (`width-300`, `width-400`, etc.) e `height-md`.
- **Bloco de filtros:** `display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md`
  - Campos agrupados em colunas (`div.col`) com larguras fixas via utilitários `width-XXX`.
  - Select “Itens por página” à direita, alinhado em `align-end`.
- **Registros:** cada item como bloco:
  - Container: `display-flex col col-full register border-bottom`.
  - Linha principal: `display-flex row row-full justify-between padding-x-sm padding-y-sm align-center`.
  - Conteúdo à esquerda em `row-fit`, ações / status à direita em `row-fit`.
- **Subtarefas (quando existirem):**
  - Container: `display-flex col col-full subtask subtask-collapsed` controlado por input/radio.
  - Linhas: `display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top`.
  - Rótulo de status de subtarefa: `padding-x-lg height-sm status status-*` para chips compactos.
- **Estado vazio:** usa `register border-bottom` com mensagem em `major-info`.
- **Paginação:** `display-flex row row-full justify-between padding-x-sm padding-y-sm`, com `pagination-info` + `pagination-controls` (símbolos ‹ / ›).

### Utilitários de largura (inputs/listas)

Para campos de formulário e colunas em listas, o projeto usa utilitários numéricos definidos em `public/assets/css/shared/form.css`:

- **Classe base:** `.width-XXX`, onde `XXX` é um múltiplo de 100 (de `width-100` até `width-999`).
- **Equivalência aproximada com o grid antigo:**
  - `width-100` ≃ antigo `width-sm` (50px).
  - `width-200` ≃ antigo `width-md` (100px).
  - `width-400` ≃ antigo `width-lg` (200px).
  - `width-600` ≃ antigo `width-xl` (300px).
- **Uso típico em listas internas:**
  - Campos curtos (ex.: prazo, CPF, CEP): `width-100` ou `width-200`.
  - Campos médios (ex.: tipo, setor, CNPJ): `width-300` ou `width-400`.
  - Campos longos (ex.: nome, logradouro, observações): `width-500` a `width-700`.
  - Campos muito amplos (ex.: observações extensas): `width-800` ou `width-900`.

> As classes **`width-sm`**, **`width-md`**, **`width-lg`**, **`width-xl`** e **`width-xxl`** foram descontinuadas em favor de `width-100`…`width-999`. Novos componentes devem sempre usar o padrão numérico.

### Status de tarefas e subtarefas

- **Classes base de status:** `status-pending`, `status-current`, `status-finished`, `status-cancelled`, `status-success`, `status-warning`, etc.
- **Chips de status em tarefas principais** (linha da lista ou timeline):
  - Usam apenas `status` + a classe semântica (`status-current`, `status-finished`, etc.) e espaçamento horizontal (`padding-x-lg` quando aplicável).
  - **Não** utilizam `height-sm` por padrão.
- **Subtarefas nas listas (cartas expedidas/recebidas, guias, medidas, cejusc):**
  - Cada subtarefa é renderizada em uma linha `subtask` com um chip de status compacto.
  - Estes chips usam `padding-x-lg height-sm status status-*` para manter altura consistente nos blocos de subtarefa.

Esse padrão unifica o comportamento visual de todas as páginas internas de lista, facilitando manutenção e criação de novos módulos.

---

## Segurança (boas práticas)

- Controle de acesso por perfil (autorização por módulo/ação)
- Validação e sanitização **server-side** em formulários e endpoints
- Uso de **prepared statements** para reduzir risco de SQL Injection
- Proteções contra XSS/CSRF (conforme arquitetura adotada)
- Restringir acesso direto a scripts sensíveis e arquivos de configuração
- Recuperação de senha com fluxo seguro (token/expiração), quando aplicável

---

## Logs e auditoria

Quando aplicável, registrar eventos como:

- login/logout
- criação/edição/exclusão de registros
- downloads (ex.: atas)
- alterações em configurações/segurança

Campos sugeridos:

- `id_usuario` (autor)
- ação
- data/hora
- recurso afetado (módulo/tabela/id do registro)
- IP (opcional)

---

-## Estrutura do projeto (referência)
-
Pastas principais no repositório:
-
- `app/` — modelos, services, helpers, middlewares e views divididos entre `public` e `internal`.
- `bootstrap/`, `config/` e `database/` — inicialização, parâmetros de conexão e migrações/exportações SQL.
- `public/` — entrypoints (`index.php`, `home.php`, `frame.php`, APIs em `admin/api/`) e a pasta `assets/` com CSS/JS/template compartilhados, imagens e ícones.
- `scripts/` — automações (versionamento, drafts, bump) utilizadas pelos hooks do repositório.
- `storage/` — runtime (logs, uploads, backups) e **não versiona** o conteúdo (apenas `.gitkeep`).
- `tools/` — auxiliares de IA e modelos de prompt (usados apenas em contexto de desenvolvimento/documentação).
- `vendor/`

---

## Configuração Inicial

Após clonar o repositório, configure os hooks:

- git config core.hooksPath .githooks

## Deploy automático para dev.ios1vcrime.com.br

O branch `develop` é conectado ao workflow `.github/workflows/deploy-dev.yml`.
Cada push aprovado nesse branch dispara a sincronização automatizada com o subdomínio `dev.ios1vcrime.com.br`, que consome o conteúdo vivo da pasta `public/`. Garanta que só arquivos públicos (HTML, CSS, JS, assets) estejam sob `public/`, pois qualquer script ou arquivo sensível deve ficar em `app/`, `config/`, `storage/` ou em `tools/`.

## Contexto Ativo (estado atual)

O documento [`docs/00-foundation/contexto-ativo.md`](docs/00-foundation/contexto-ativo.md) reúne o status atual das atividades de documentação e reorganização, os problemas presentes, o que já foi validado manualmente e os arquivos que sofreram impacto direto. Consulte esse arquivo antes de iniciar qualquer alteração estrutural ou de processo para garantir alinhamento com o estado acordado.

## Licença e uso

Sistema de uso institucional da Primeira Vara Crime da Comarca de Ilhéus.
Reprodução, redistribuição ou reutilização dependem de autorização institucional.

---

## Créditos

Desenvolvido integralmente por **Jorge Reis** (Desenvolvedor Fullstack).
