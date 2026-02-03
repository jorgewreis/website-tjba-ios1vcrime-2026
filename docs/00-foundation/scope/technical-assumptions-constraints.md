# Especificação de Premissas e Restrições Técnicas

## 1. Introdução

Este documento formaliza as premissas básicas assumidas para o desenvolvimento do WebApp | 1vCrimeIlheus e as restrições técnicas que limitam as decisões de design e implementação. O objetivo é balizar a arquitetura do software e garantir a aderência aos requisitos de negócio e infraestrutura.

## 2. Premissas do Projeto

As premissas são fatores considerados verdadeiros para que o projeto seja viável. Caso alguma premissa não se confirme, o cronograma ou a qualidade podem ser impactados.

### 2.1. Infraestrutura e Conectividade

- **Ambiente de Hospedagem:** Assume-se a disponibilidade contínua do ambiente CloudPanel com suporte a PHP e MySQL.
- **Conectividade do Cliente:** O sistema é uma aplicação web dependente de conexão estável com a internet; não haverá suporte a modo offline (PWA Offline) nesta versão.
- **Navegadores Modernos:** Assume-se que os usuários (Servidores e Externos) utilizarão navegadores modernos (Chrome, Firefox, Edge, Safari) atualizados, compatíveis com ES6+ e recursos do Next.js.

### 2.2. Dados e Migração

- **Integridade dos Dados:** Assume-se que não haverá migração de dados legados complexos nesta fase inicial; o banco de dados começará com uma estrutura limpa ou com carga inicial controlada.

## 3. Restrições Técnicas

As restrições são limitações impostas pela arquitetura, orçamento ou diretrizes estratégicas que **devem** ser respeitadas obrigatoriamente.

### 3.1. Stack Tecnológico (Híbrido)

- **Frontend:** Deve ser desenvolvido exclusivamente em **Next.js** (React).
- **Backend/API:** A lógica de negócios e acesso a dados deve ser implementada em **PHP** (Classes e Scripts).
- **Banco de Dados:** Uso obrigatório de **MySQL** hospedado no CloudPanel.
- **Comunicação:** A troca de dados entre Frontend e Backend deve ocorrer via JSON.

### 3.2. Restrições Arquiteturais

- **Admin via Iframe:** O painel administrativo (`admin/home.php`) deve carregar as páginas internas e menus dentro de um `<iframe>`. O Next.js deve gerenciar a "casca" externa ou integrar-se a esse modelo onde aplicável.
- **Gestão de Sessão:** A persistência da autenticação (Token JWT) deve ser feita exclusivamente no **sessionStorage** do navegador. Não utilizar Cookies HTTP-Only ou Sessões de Servidor PHP nativas para controle de estado da API.
- **Identificadores (IDs):** Todas as chaves primárias devem ser strings contendo um prefixo semântico de 3 letras seguido de hífen (ex: `USR-` para usuários, `PER-` para perfis).

### 3.3. Restrições de Segurança e Dados

- **Mascaramento de CPF:** O CPF deve ser armazenado no banco de dados como `VARCHAR` contendo a máscara (pontos e traço).
- **Isolamento de Setor:** A arquitetura deve impedir fisicamente (via query SQL ou Middleware) que um usuário acesse dados de outro setor, salvo se for Admin.
- **Validação de Senha:** O sistema deve impedir o reuso da senha anterior e exigir complexidade mínima (8 caracteres, letras, números, especiais).

### 3.4. Restrições de Interface (UI/UX)

- **Estilo Visual:** A interface deve seguir uma estética minimalista, inspirada em referências artísticas (ex: Monument Valley), evitando excesso de informações (clutter) na tela.
- **Acessibilidade:** Conformidade obrigatória com WCAG 2.1 Nível AA (conforme documento `accessibility-requirements.md`).

## 4. Riscos Associados

- **Arquitetura Iframe:** O uso de iframes pode apresentar desafios para responsividade móvel e acessibilidade (leitores de tela), exigindo esforço extra de desenvolvimento para mitigação.
- **Segurança Client-Side:** O armazenamento de tokens no `sessionStorage` é suscetível a ataques XSS se não houver uma sanitização rigorosa de todos os inputs e outputs da aplicação.
