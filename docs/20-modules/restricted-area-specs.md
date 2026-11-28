# Especificação Funcional — Área Restrita

## Referências e alinhamento
- `docs/00-foundation/escopo-produto.md` — visão de produto e módulos internos.
- `docs/00-foundation/funcionalidades areas publica e restrita.md` — regras de segregação e perfis.
- `docs/00-foundation/technical-assumptions-constraints.md` — premissas e restrições técnicas vigentes.
- `docs/30-security/authentication_password_policy.md` — política de autenticação, sessão e senha.

## 1. Visão geral e arquitetura
A Área Restrita é o núcleo administrativo do sistema. Ela é servida por `public/home.php`, que funciona como shell autenticado, e carrega páginas internas por meio de `public/frame.php?page=...`.

Essa abordagem preserva o menu e o cabeçalho fixos, reduz exposição direta de páginas internas e mantém a validação de acesso centralizada no backend.

## 2. Autenticação e controle de sessão
O fluxo oficial de autenticação da Área Restrita é baseado em:

- validação de credenciais no backend PHP;
- criação de sessão PHP após login bem-sucedido;
- geração de token aleatório no servidor;
- persistência do **hash** desse token na tabela de usuários com prazo de expiração;
- timeout de inatividade de **4 horas**;
- revalidação do token persistido a cada acesso à área autenticada e aos endpoints administrativos dependentes da sessão.

Na prática:

- `public/index.php` apresenta a entrada pública;
- `public/login.php` processa o login;
- `$_SESSION` mantém o estado autenticado;
- `config/security.php` valida a existência da sessão e o timeout de inatividade;
- `app/models/Usuario.php` persiste e valida `token_acesso` e `token_expira`, com `token_acesso` armazenado em hash;
- `public/home.php` e `public/admin/api/*` validam a sessão e o token persistido antes de responder.

O `sessionStorage` do navegador pode ser utilizado para preferências e estados auxiliares de interface, mas **não é o mecanismo oficial de autenticação do sistema**.

## 3. Perfis e isolamento
Os acessos da Área Restrita dependem de perfis e, quando aplicável, de lotação ou setor do usuário.

Perfis operacionais típicos:

1. **Chefias e administradores:** acesso ampliado a configurações, alocação e funcionalidades críticas.
2. **Servidores:** atuação nas rotinas do setor conforme permissão funcional.
3. **Estudantes e consultores:** acesso reduzido às áreas e consultas compatíveis com seu papel.

Regra transversal:

- o backend deve impedir acesso indevido entre setores e páginas;
- páginas e APIs internas não devem confiar apenas em ocultação visual de interface;
- decisões de permissão devem ser revalidadas no servidor.

## 4. Módulos internos principais
- **Audiências:** pauta, atas, painel e fluxos de estudantes.
- **Cartório:** Cartas Expedidas, Cartas Recebidas, Editais, Guias e Ofícios do Cartório.
- **Gabinete:** réus presos e ofícios.
- **Monitoramento eletrônico:** acesso global (`all_internal`) para usuários internos com perfil composto (cartório, gabinete ou audiências); menu apenas em **Consultas**; qualquer interno autorizado pode operar cadastro, subtarefas, cancelamento e exclusão (esta última na edição, se for o último sequencial do ano). Breadcrumb neutro (sem “Gabinete”); lista padrão em aberto.
- **Datas do monitoramento:** decisão de Jorge em 15/11/2026: início futuro e encerramento antecipado são permitidos. Se encerrado antes do início registrado, a data final operacional é igual à data de início; ela não representa a data efetiva da ação de encerramento.
- **Usuários e perfis:** cadastro, edição, perfis e dados pessoais.
- **Consultas e cadastros auxiliares:** partes, processos, instituições públicas, prioridades e feriados.
- **Configuração e governança:** páginas internas e regras administrativas existentes no roteamento atual.

## 5. Segurança operacional
A Área Restrita deve operar com:

- sessão validada no backend;
- proteção CSRF nos formulários aplicáveis;
- cookie de sessão com flags endurecidas no bootstrap central;
- rate limit de login por IP e CPF com bloqueio temporário progressivo;
- invalidação do token persistido em logout ou expiração;
- invalidação de sessão anterior após redefinição de senha;
- validação server-side de permissões;
- registro de eventos relevantes de autenticação e acesso.

## 6. Observações de manutenção
Qualquer mudança futura no modelo de autenticação deve atualizar, em conjunto:

- este documento;
- `docs/30-security/authentication_password_policy.md`;
- `docs/10-architecture/documento-integracoes-api.md`;
- `docs/00-foundation/project-canonical-document.md`, se a decisão afetar a arquitetura oficial.
