# Avatares por preset (usuários internos)

## Visão geral

- O campo `usuarios.avatar_slug` armazena **apenas um slug lógico** (ex.: `default`, `style_blue`), nunca caminho de arquivo livre vindo do cliente.
- As imagens ficam em `public/assets/img/avatars/presets/` e são listadas em **`public/assets/img/avatars/presets/manifest.json`** (fonte única de verdade para UI, validação e URLs).
- A whitelist e a resolução de URL pública são feitas em **`app/helpers/AvatarCatalog.php`**.

## Interno vs externo

- **Interno** (`!UserAccessHelper::isUsuarioExterno($perfis)`): pode escolher o preset em **Alterar dados pessoais**; o avatar aparece na navbar (`home.php`) conforme o slug gravado.
- **Externo**: **não** há seletor de avatar; a navbar usa **sempre** o preset padrão (`default`), mesmo que exista valor antigo no banco — a camada de apresentação força o default.

## Migração de banco

Execute o script (após backup):

- `database/migrations/20260220_usuarios_avatar_slug.sql`

Coluna: `avatar_slug VARCHAR(64) NOT NULL DEFAULT 'default'`.

Se a coluna já existir, a segunda execução do `ALTER TABLE` falhará — trate manualmente ou use um fluxo que ignore “duplicate column”.

## Inclusão de um novo preset

1. Adicione o arquivo de imagem em `public/assets/img/avatars/presets/` (preferencialmente SVG/PNG/JPG versionados no repositório).
2. Inclua uma entrada em `manifest.json`:

```json
{
  "slug": "meu_preset",
  "url": "/assets/img/avatars/presets/meu-preset.svg",
  "label": "Meu rótulo"
}
```

3. O slug deve ser único; a URL deve ser **relativa ao site**, começando com `/`, sem `..` (validado em `AvatarCatalog`).
4. Não é necessário alterar PHP além do manifest, salvo regras de negócio novas.

## Cadastro de usuário (admin)

Em `usuarios-create.php`, novos registros recebem explicitamente `avatar_slug` igual ao slug padrão do catálogo (`AvatarCatalog::getDefaultSlug()`). Outros fluxos de `INSERT` em `usuarios` podem omitir a coluna se o **DEFAULT** do banco estiver aplicado após a migração.
