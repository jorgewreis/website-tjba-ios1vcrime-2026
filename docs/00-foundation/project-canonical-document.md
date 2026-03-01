---
id: project-canonical-document
title: "Documento Canônico do Projeto"
version: "0.1.0"
date: 2026-02-18
status: draft
review_cycle: 12m
authors:
  - "Jorge Reis"
decisions:
  stack: "PHP 8.2 · MySQL 8.0 (utf8mb4) · Next.js + JS ES2022"
  session_storage: "JWT tokens armazenados em sessionStorage e painel admin em iframe"
  versioning: "VERSION/CHANGELOG/docs/90-releases orientam releases Patch/Minor/Major"
---

# Documento Canônico do Projeto

## Objetivo do sistema
O objetivo deste documento é tornar explícitas as regras e referências que guiam a evolução do Sistema de Gestão do Cartório (SGC) e garantir que qualquer autor novo entenda o que já está decidido antes de acrescentar conteúdo técnico ou funcional.
Ele reúne, em um único arquivo canônico, o propósito do sistema, a stack consolidada, os padrões obrigatórios e as restrições que vêm da arquitetura e da governança institucional.
Ter essa base registrando contexto e escopo evita decisões ad hoc que contradizem a separação público/restrita ou as exigências de auditoria já documentadas.

## Stack oficial
A stack oficial combina um front-end shell em Next.js com módulos legados em PHP e SQL, conforme as premissas descritas no repositório: o front deve ser escrito em ES2022 (módulos e sintaxe moderna) e consumidor dos serviços JSON expostos pelo back-end PHP.
No back-end, adotamos PHP 8.2 para caber com o CloudPanel 2026, aproveitar atributos e tipos estritos e manter compatibilidade com bibliotecas de segurança; o código roda em scripts e classes com separação clara de responsabilidades (controllers, helpers, models).
O banco de dados é MySQL 8.0, charset `utf8mb4` (conforme `.env`), e o carregamento dos scripts front/back depende de JSON/REST; JavaScript comportamental permanece vanilla quando não há necessidade de React, respeitando o objetivo de manter respostas leves para os módulos públicos.

## Regras de arquitetura
A arquitetura deve deixar clara a divisão entre a área pública (contas, recuperações, informações não sensíveis) e a área restrita (painel administrativo, módulos cartoriais), usando camadas em `app/`, `public/` e `database/` para separar responsabilidades e mantendo logs por ação em `storage/logs`.
O painel administrativo (`admin/home.php`) carrega páginas internas e menus dentro de um `<iframe>`, enquanto o shell externo pode ser implementado com Next.js; isso garante que o frame controle sessão, navegação e layout, sem expor rotas sensíveis diretamente.
Documentos de arquitetura devem manter diagramas em formatos editáveis (diagrams.net ou similares), ADRs referenciadas em `docs/architecture/adr/` e requisitos não funcionais explícitos para disponibilidade, escalabilidade e segurança, conforme o canônico de arquitetura.

## Convenções de nomenclatura
Os arquivos e pastas devem seguir `kebab-case` em inglês sempre que possível, como recomendado no guia de documentação (`docs/00-foundation/guia-estrutura-docs.md`), mantendo nomes descritivos e curtos (ex.: `docs/architecture/adr/session-policy.md`).
Controller, Model e Helper devem usar `PascalCase` para refletir a nomenclatura de classes PHP, enquanto views e assets preferem `snake_case` ou `kebab-case` conforme o contexto de carga; o importante é que o padrão escolhido seja aplicado de forma consistente dentro de cada pasta.
Identificadores persistidos no banco também obedecem a uma convenção clara: prefixos de três letras (ex.: `USR-`, `PER-`) seguidos de hífen e sequência única, garantindo que qualquer consulta ou log identifique rapidamente o tipo de entidade envolvida.

## Padrões CSS
A paleta de cores oficial deriva das seções de interface do README: tons neutros (brancos e cinzas suaves), azuis em três camadas (claros para fundos, médios para bordas e fortes para ações), e cores semânticas para feedback (sucesso, perigo, atenção, info), embalados em regras de contraste aprovadas para leitura.
A organização dos arquivos CSS segue o padrão modular descrito em `estrutura-repositorio.md`: primeiro `reset.css`, depois `layout.css`, depois `components.css` e `pages/*.css`, mantendo o CSS de cada página separado e evitando arquivos monolíticos; os arquivos de tema e core ficam dentro de `public/assets/css/`, com importação controlada em `public/index.php` ou equivalente.
Estados de componente (hover, focus, ativo) devem usar classes utilitárias e animações discretas, e qualquer nova regra visual precisa passar pelos critérios de accesibilidade definidos no `accessibility-requirements.md` (WCAG 2.1 AA), para manter contraste e foco previsível.

## Estrutura de pastas
A raiz do repositório concentra governança (`README.md`, `LICENSE.md`, `VERSION`, `CHANGELOG.md`, `COMMITS.md`), configuração (`.env`, `.gitattributes`, `.gitignore`), automações (`scripts/`, `.github/workflows`) e código (`app/`, `config/`, `database/`, `public/`, `storage/`).
Documentação fica em `docs/`, com subpastas numeradas (`00-foundation`, `10-architecture`, `20-modules`, `30-security`, `40-operations`, `90-releases`) organizando público, arquitetura, módulos, segurança, operações e histórico de releases.
Diretórios de runtime como `storage/` não versionam artefatos; apenas marcadores (`.gitkeep`) ficam no repositório para garantir existência, enquanto uploads, logs e backups são mantidos lá e ignorados pelo Git conforme regra da raiz.

## Regras de versionamento
As versões vivem em três lugares: o arquivo `VERSION` é a fonte única para scripts e automações, `CHANGELOG.md` registra o histórico cumulativo e `docs/90-releases/` abriga `_draft_minor.md`, notas por versão (`vYY.MM.md`) e checklists; isso garante rastreabilidade entre o código, a documentação e os releases oficiais.
Os ciclos seguem o modelo Patch/Minor/Major descrito em `docs/90-releases/README.md`: patches incrementam `YY.MM.PPPP` e alimentam o draft, minors consolidam o draft em um novo `vYY.MM.md`, e majors são reservados para mudanças estruturais com checklists e migrações adicionais.
Branches e commits acompanham a governança do escopo (como detalhado em `docs/00-foundation/escopo-projeto.md`): há uma estratégia fixa para proteger `main`/`release`, usar branches temporárias para feature fixes e documentar convenções (descrição clara do escopo, ticket e área impactada) antes do merge.

## Restrições técnicas
O front-end deve ser desenvolvido com Next.js e servir como shell para o painel administrativo, enquanto o backend continua em PHP (8.2) acessando MySQL e entregando JSON; a comunicação entre camadas precisa seguir esse modelo híbrido, sem introduzir APIs REST externas que não sigam a estrutura existente.
Segurança exige que o token JWT seja armazenado exclusivamente no `sessionStorage`, que não se use cookies HTTP-only nem sessões nativas do PHP para controle de estado, e que CPF permaneça com máscara no banco (`VARCHAR`), conforme as restrições de dados e autenticação definidas no documento de premissas.
A interface deve entregar a estética minimalista e acessível definida no `accessibility-requirements.md`, mantendo o isolamento de setores (nada de dados de outro setor para perfis não-admin) e obedecendo o padrão de IDs com prefixo semântico para facilitar auditoria e logs.

## Decisões já tomadas
Esta seção registra decisões críticas que já foram documentadas e que devem ser mantidas como ponto de referência obrigatório antes de qualquer nova mudança.
A seguir estão as decisões confirmadas até agora, com a relação direta aos documentos de premissas e governança:
- O painel administrativo roda dentro de um `<iframe>` hospedado em `admin/home.php` e a “casca” externa pode ser Next.js, mantendo a separação entre shell e conteúdos sensíveis.
- A persistência da autenticação usa tokens JWT salvos somente no `sessionStorage`, evitando cookies HTTP-only ou sessão de PHP para permitir auditoria em JavaScript.
- Todos os identificadores principais (`USR-`, `PER-`, `CRT-`, etc.) mantêm prefixos semânticos, facilitando logs e rastreamento de eventos no banco de dados.
- O versionamento segue o trio `VERSION`/`CHANGELOG`/`docs/90-releases` com ciclos Patch → Minor → Major descritos no canônico de releases.
- A paleta de CSS e os splits de componentes seguem o padrão minimalista e modular descrito no README e nos documentos de UI.
A continuidade dessas decisões garante que qualquer nova adição respeite o modelo de governança compartilhado e não introduza variações que compliquem auditoria, segurança ou experiência do usuário.
