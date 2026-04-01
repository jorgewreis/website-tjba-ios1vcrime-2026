# Especificação de Premissas e Restrições Técnicas

## 1. Introdução

Este documento formaliza as premissas básicas e as restrições técnicas do projeto, considerando a arquitetura que de fato foi adotada para o sistema.
Seu objetivo é orientar decisões de design, manutenção e evolução sem recorrer a frameworks ou convenções que não fazem parte da base oficial do repositório.

## 2. Premissas do Projeto

As premissas são fatores considerados verdadeiros para a continuidade do projeto. Se alguma delas deixar de valer, pode haver impacto em prazo, operação ou qualidade.

### 2.1. Infraestrutura e Conectividade

- **Ambiente de hospedagem:** Assume-se a disponibilidade contínua de um ambiente com suporte a **PHP** e **MySQL**, compatível com a estrutura atual do projeto.
- **Conectividade do cliente:** O sistema é uma aplicação web dependente de conexão estável com a internet; não há suporte a modo offline.
- **Navegadores modernos:** Assume-se uso de navegadores modernos (Chrome, Firefox, Edge, Safari) atualizados, compatíveis com HTML5, CSS3 e JavaScript ES6+.

### 2.2. Dados e Migração

- **Integridade dos dados:** O banco deve permanecer sob controle do projeto, com scripts SQL versionados em `database/`.
- **Migrações controladas:** Evoluções estruturais do banco devem ocorrer por scripts explícitos e revisáveis, evitando mudanças implícitas ou dependentes de framework ORM.

## 3. Restrições Técnicas

As restrições abaixo devem ser respeitadas obrigatoriamente.

### 3.1. Stack Tecnológica Oficial

- **Backend:** Deve ser desenvolvido em **PHP**, usando a organização existente do repositório.
- **Frontend:** Deve ser desenvolvido em **HTML, CSS e JavaScript**, sem dependência arquitetural de Next.js, React ou Bootstrap.
- **Banco de Dados:** Uso de **MySQL** como banco principal.
- **Comunicação:** A aplicação pode combinar renderização server-side em PHP com requisições JSON para endpoints internos quando necessário.

### 3.2. Restrições Arquiteturais

- **Shell autenticado:** A área restrita do sistema deve permanecer centrada em `public/home.php`, com carregamento interno por `public/frame.php`.
- **Separação de responsabilidades:** Arquivos públicos devem permanecer em `public/`, enquanto regras de negócio, acesso a dados, configuração e apoio técnico devem permanecer fora da raiz pública.
- **Identificadores (IDs):** As chaves primárias e identificadores semânticos continuam seguindo o padrão institucional já documentado.
- **Sem framework obrigatório:** O projeto não deve depender de Laravel, Next.js ou Bootstrap para funcionar como sistema principal.

### 3.3. Restrições de Segurança e Dados

- **Mascaramento de CPF:** O CPF deve permanecer armazenado em `VARCHAR`, com máscara, conforme o padrão atual do projeto.
- **Isolamento de setor:** A aplicação deve impedir acesso indevido entre setores e perfis por meio das regras implementadas no backend.
- **Políticas de autenticação e senha:** Devem seguir os documentos específicos de segurança e a decisão arquitetural vigente para autenticação da área restrita.

### 3.4. Restrições de Interface (UI/UX)

- **Estilo visual:** A interface deve seguir a linguagem visual própria do projeto, com CSS customizado e sem adoção de Bootstrap como base.
- **Acessibilidade:** Deve haver aderência às diretrizes de acessibilidade já documentadas para o sistema.

## 4. Riscos Associados

- **Arquitetura com iframe:** O uso de iframe na área restrita exige atenção extra em responsividade, acessibilidade e navegação.
- **Código sem framework dominante:** A manutenção depende de disciplina na organização dos arquivos, padronização de naming e separação clara de responsabilidades.
- **Acoplamento entre páginas e endpoints internos:** Mudanças estruturais em `public/`, `app/` e `public/api/` exigem revisão documental para evitar novas divergências entre código e documentação.
