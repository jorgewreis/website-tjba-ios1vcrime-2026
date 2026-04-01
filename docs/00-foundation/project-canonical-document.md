---
id: project-canonical-document
title: "Documento Canônico do Projeto"
version: "0.2.0"
date: 2026-04-01
status: active
review_cycle: 12m
authors:
  - "Jorge Reis"
decisions:
  stack: "PHP · MySQL · HTML/CSS/JavaScript"
  architecture: "Aplicação web PHP customizada com área restrita em iframe e APIs internas em public/api"
  versioning: "VERSION/CHANGELOG/docs/90-releases orientam releases Patch/Minor/Major"
---

# Documento Canônico do Projeto

## Objetivo do sistema
Este documento registra as decisões estruturais que passam a valer como referência oficial para a evolução do Sistema de Gestão do Cartório (SGC).
Seu papel é eliminar ambiguidades entre proposta, documentação histórica e implementação real, deixando explícita a stack adotada, a organização do sistema e as restrições técnicas que devem orientar novas alterações.

## Stack oficial
A stack oficial do projeto é composta por **PHP** no backend, **MySQL** no banco de dados e **HTML, CSS e JavaScript** no frontend.
O sistema **não adota Next.js, Laravel ou Bootstrap como base arquitetural**.
A interface é servida por arquivos PHP em `public/`, com assets próprios em `public/assets/` e páginas internas em `app/views/`.
As interações assíncronas do frontend ocorrem por JavaScript modular e endpoints internos, principalmente em `public/api/`.

## Regras de arquitetura
A arquitetura oficial é a de uma **aplicação web PHP customizada**, com separação entre:

- entrada pública e shell autenticado em `public/`;
- regras de negócio e apoio técnico em `app/`;
- configuração em `config/`;
- SQL, estrutura e migrações em `database/`;
- runtime local em `storage/`.

O fluxo principal do sistema usa:

- `public/index.php` como entrada pública;
- `public/login.php` para autenticação;
- `public/home.php` como shell autenticado;
- `public/frame.php` para carregamento das páginas internas no iframe;
- `public/api/` como superfície principal de endpoints HTTP internos.

O backend é responsável por validação, autorização, acesso a dados e orquestração das regras do sistema.
Bibliotecas de terceiros podem existir de forma pontual, mas **não definem a arquitetura do projeto**.

## Convenções de nomenclatura
Arquivos e pastas de documentação e assets devem preferir `kebab-case`, com nomes curtos e descritivos.
Classes PHP devem usar `PascalCase`, acompanhando a convenção já adotada em helpers, models, repositories, services e middlewares.
Views, páginas públicas e scripts podem usar `snake_case` ou `kebab-case`, desde que o padrão seja consistente dentro de cada diretório.
Identificadores persistidos no banco continuam seguindo o padrão semântico com prefixo e hífen, conforme os documentos de dados e banco do projeto.

## Padrões CSS
O projeto utiliza **CSS próprio**, organizado em `public/assets/css/`, sem dependência arquitetural de Bootstrap.
Os estilos compartilhados ficam principalmente em `public/assets/css/shared/`, com arquivos como `reset.css`, `layout.css`, `form.css`, `page.css`, `table.css`, `login.css`, `toast.css` e `color.css`.
Estilos específicos de módulos podem ser mantidos em `public/assets/css/modules/`, preservando o princípio de separar base visual e regras locais.
Componentes novos devem seguir a paleta e os utilitários já consolidados no projeto, em especial os utilitários numéricos de largura definidos em `public/assets/css/shared/form.css`.

## Estrutura de pastas
A estrutura oficial do repositório é orientada pelo código atual:

- `app/` concentra `access`, `controllers`, `helpers`, `middlewares`, `models`, `repositories`, `rules`, `services` e `views`;
- `config/` mantém parâmetros de runtime e segurança;
- `database/` reúne schema, migrations, seeds e scripts SQL auxiliares;
- `public/` reúne entrypoints, assets, documentos públicos e APIs internas;
- `storage/` concentra uploads, logs e backups locais;
- `docs/` concentra a documentação versionada;
- `scripts/` reúne automações e utilitários operacionais;
- `tools/` reúne ferramentas auxiliares de desenvolvimento e IA.

Há ainda uma árvore `api/` na raiz do repositório que não compõe a arquitetura principal atual e deve ser tratada separadamente em revisão específica.

## Regras de versionamento
As versões continuam sendo controladas por `VERSION`, `CHANGELOG.md` e `docs/90-releases/`.
O repositório adota o fluxo Patch → Minor → Major já descrito na documentação de releases, preservando rastreabilidade entre código, documentos e histórico operacional.

## Restrições técnicas
As seguintes restrições passam a valer como diretriz obrigatória:

- não introduzir **Next.js**, **Laravel** ou **Bootstrap** como base oficial do projeto;
- manter a interface principal compatível com **HTML, CSS e JavaScript** sem dependência de framework frontend obrigatório;
- manter o backend em **PHP** com acesso a dados em **MySQL**;
- preservar a separação entre arquivos públicos (`public/`) e lógica interna (`app/`, `config/`, `database/`);
- manter endpoints internos alinhados à estrutura oficial do projeto, com prioridade para `public/api/`.

## Decisões já tomadas
As decisões abaixo passam a ser referência oficial:

- A base do sistema é uma aplicação **PHP customizada**.
- O frontend oficial usa **HTML, CSS e JavaScript**, sem Next.js e sem Bootstrap.
- O backend oficial usa **PHP** e o banco oficial é **MySQL**.
- A área autenticada utiliza `public/home.php` e `public/frame.php` como shell e carregamento interno.
- A estrutura real do repositório, e não modelos genéricos de framework, é a referência para documentação de arquitetura e manutenção.
