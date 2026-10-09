# Avatar de usuário

## Padrão da navbar

Usuários autenticados são exibidos com iniciais derivadas do nome e sobrenome. O elemento `.nav-new-avatar .nav-new-avatar-initials`, renderizado em `public/home.php`, usa sempre o gradiente fixo:

```css
background: #170E22;
background: linear-gradient(60deg, rgba(23, 14, 34, 1) 0%, rgba(92, 31, 145, 1) 100%);
```

As configurações de dados pessoais não permitem escolher cor ou imagem de avatar. A navbar não lê imagem ou cor salva no perfil.

## Iniciais

A regra em `app/helpers/UserInitialsHelper.php` junta nome e sobrenome, ignora partículas comuns em português (`de`, `da`, `do`, `das`, `dos`, `e`, `em`, `na`, `no`, `nas`, `nos`, `ao`, `aos`, `à`, `às`) e usa a primeira letra da primeira palavra significativa e da última (ex.: *Ana da Silva* → **AS**). Repositórios de alocação também usam esse helper.

O campo legado `usuarios.avatar_slug`, se ainda existir em uma instalação, é ignorado pela aplicação.
