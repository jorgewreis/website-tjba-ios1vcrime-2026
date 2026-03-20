# Avatar: pasta 3D, iniciais e paleta (usuários internos)

## Visão geral

- O campo `usuarios.avatar_slug` guarda um **slug lógico** validado no servidor:
  - **`initials`** — não usa arquivo; na navbar mostram-se **duas iniciais** (nome + sobrenome) dentro do círculo, com **fundo e texto em cores aleatórias** geradas **a cada login** (contraste para leitura), persistidas em sessão via **`AvatarInitialsPalette`** (`public/login.php` chama `regenerateOnLogin()` após autenticação).
  - **Nome de arquivo (stem)** — ex.: `avatar3d_007` para o ficheiro `avatar3d_007.jpg` em `public/assets/img/avatars/3d/`.
- **Não há upload**: apenas ficheiros já versionados na pasta `3d/` entram na lista.
- A whitelist e as URLs são resolvidas em **`app/helpers/AvatarCatalog.php`** (varredura da pasta no arranque do pedido, com cache estático por request).
- Valores legados **`default`** (e vazio) são normalizados para **`initials`** em leitura/atualização.

## Imagens disponíveis (3d)

1. Coloque ficheiros em **`public/assets/img/avatars/3d/`** (extensões: `jpg`, `jpeg`, `png`, `gif`, `webp`).
2. O nome do ficheiro (sem extensão) deve usar apenas `a-z`, `A-Z`, `0-9`, `_`, `-` — esse valor é o **slug** (ex.: `avatar3d_007.jpg` → slug `avatar3d_007`).
3. Não é necessário `manifest.json` para a pasta 3d; a lista é montada automaticamente.

## Interno vs externo

| Perfil   | Navbar (`home.php`) |
| -------- | ------------------- |
| **Interno** | Se `avatar_slug` for um ficheiro da pasta 3d → `<img>`; se `initials` (ou legado) → iniciais + paleta da sessão. |
| **Externo** | **Sempre** iniciais + paleta (não usa foto 3d do BD, mesmo que exista). |

## Migrações SQL

- `database/migrations/20260220_usuarios_avatar_slug.sql` — cria `avatar_slug`.
- `database/migrations/20260221_avatar_slug_legacy_to_initials.sql` — (opcional) `UPDATE` de `default` / vazio para `initials`.

## Cadastro de utilizador (admin)

Em `usuarios-create.php`, o insert usa `AvatarCatalog::getDefaultSlug()` (**`initials`**). Outros `INSERT` podem omitir a coluna se o DEFAULT da base for compatível; o PHP normaliza leituras antigas.

## Ficheiros relacionados

- `app/helpers/AvatarCatalog.php` — listagem 3d + slug `initials`.
- `app/helpers/AvatarInitialsPalette.php` — cores aleatórias com contraste.
- `app/models/Usuario.php` — `getAvatarNavbarDisplay()`, `atualizarAvatarSlug()`.
- `app/views/internal/alterar-dados-pessoais.php` — select + miniaturas (só internos).
- `public/home.php` — imagem ou `<span class="avatar-initials">`.
- `public/assets/css/shared/layout.css` — estilo `.avatar-initials`.
