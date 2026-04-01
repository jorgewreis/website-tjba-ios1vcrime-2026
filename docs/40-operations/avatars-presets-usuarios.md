# Avatar: pasta 3D, iniciais e paleta (usuários internos)

## Visão geral

- O campo `usuarios.avatar_slug` guarda um **slug lógico** validado no servidor:
  - **`initials`** — não usa arquivo; na navbar mostram-se **duas iniciais** (nome + sobrenome) dentro do círculo, com **fundo e texto em cores aleatórias** geradas **a cada login** (contraste para leitura), persistidas em sessão via **`AvatarInitialsPalette`** (`public/login.php` chama `regenerateOnLogin()` após autenticação).
  - **Nome de arquivo (stem)** — ex.: `M-001` para o ficheiro `M-001.jpg` em `public/assets/img/avatars/vivid/`.
- **Não há upload**: apenas ficheiros já versionados na pasta `3d/` entram na lista.
- A whitelist e as URLs são resolvidas em **`app/helpers/AvatarCatalog.php`** (varredura da pasta no arranque do pedido, com cache estático por request).
- Valores legados **`default`** (e vazio) são normalizados para **`initials`** em leitura/atualização.

## Imagens disponíveis (3d)

1. Coloque ficheiros em **`public/assets/img/avatars/vivid/`** (extensões: `jpg`, `jpeg`, `png`, `gif`, `webp`).
2. O nome do ficheiro (sem extensão) deve usar apenas `a-z`, `A-Z`, `0-9`, `_`, `-` — esse valor é o **slug** (ex.: `avatar3d_007.jpg` → slug `avatar3d_007`).
3. Não é necessário `manifest.json` para a pasta 3d; a lista é montada automaticamente.

## Interno vs externo

| Perfil   | Navbar (`home.php`) |
| -------- | ------------------- |
| **Interno** | Se `avatar_slug` for um ficheiro da pasta 3d → `<img>`; se `initials` (ou legado) → iniciais + paleta da sessão. |
| **Externo** | **Sempre** iniciais + paleta (não usa foto 3d do BD, mesmo que exista). |

## Alinhamento de schema

- O código já trata `usuarios.avatar_slug` como coluna oficial quando ela existe no ambiente.
- O repositório **não possui hoje** uma migration versionada específica para essa coluna.
- Em ambientes legados, a criação ou ajuste de `avatar_slug` deve ser feita por migration/manual SQL alinhado ao estado atual do banco antes de liberar o fluxo de avatar para uso completo.

## Cadastro de utilizador (admin)

Em `usuarios-create.php`, o insert usa `AvatarCatalog::getDefaultSlug()` (**`initials`**). Outros `INSERT` podem omitir a coluna se o DEFAULT da base for compatível; o PHP normaliza leituras antigas.

## Iniciais (nome + sobrenome)

- A lógica única está em **`app/helpers/UserInitialsHelper.php`**: junta nome e sobrenome, ignora **partículas** comuns em português (`de`, `da`, `do`, `das`, `dos`, `e`, `em`, `na`, `no`, `nas`, `nos`, `ao`, `aos`, `à`, `às`) e usa a **primeira letra da primeira palavra significativa** e da **última** (ex.: *Ana da Silva* → **AS**).
- O avatar (`AvatarCatalog::initialsFromNames`) e os repositórios de **alocação** (`*AlocadosRepository::iniciais`) delegam nesse helper.

## Ficheiros relacionados

- `app/helpers/UserInitialsHelper.php` — regra de iniciais (avatar e alocações).
- `app/helpers/AvatarCatalog.php` — listagem 3d + slug `initials`.
- `app/helpers/AvatarInitialsPalette.php` — cores aleatórias com contraste.
- `app/models/Usuario.php` — `getAvatarNavbarDisplay()`, `atualizarAvatarSlug()`.
- `app/views/internal/alterar-dados-pessoais.php` — select + miniaturas (só internos).
- `public/home.php` — imagem ou `<span class="avatar-initials">`.
- `public/assets/css/shared/layout.css` — estilo `.avatar-initials`.
