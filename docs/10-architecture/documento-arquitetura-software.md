# Software Architecture Document (SAD)

Data: 2026-04-01  
Versao base: ver `VERSION`  
Repositorio: `website-tjba-ios1vcrime-2026`

## 1. Visao geral

Este documento descreve a arquitetura do sistema web para gestao de rotinas internas da Primeira Vara Crime da Comarca de Ilheus.
A arquitetura oficial do projeto e a de uma **aplicacao PHP customizada**, com frontend em **HTML, CSS e JavaScript**, banco **MySQL** e organizacao modular do codigo em `app/`, `config/`, `database/`, `public/` e `storage/`.

## 2. Linguagens de programacao

- Backend: PHP
- Frontend: HTML, CSS, JavaScript
- Banco de dados: SQL (MySQL)

## 3. Frameworks e bibliotecas

- Backend: sem framework obrigatorio
- Frontend: sem framework obrigatorio
- UI: CSS proprio do projeto
- Bibliotecas de terceiros: opcionais e pontuais, sem redefinir a arquitetura base

Observacao: o projeto **nao utiliza Next.js, Laravel ou Bootstrap como base oficial**.

## 4. Tecnologias adotadas

- Banco de dados: MySQL
- Servidor web: ambiente compativel com PHP e publicacao da pasta `public/`
- Autenticacao: controlada pelo backend PHP
- E-mail: SMTP, quando aplicavel
- Storage: local
- Logs: locais e/ou aplicacionais, conforme a infraestrutura disponivel
- Cache: nao utilizado como camada arquitetural obrigatoria
- Filas: nao utilizadas como camada arquitetural obrigatoria
- Container: nao obrigatorio para o projeto

## 5. Padrao arquitetural

O sistema segue uma arquitetura em camadas, sem acoplamento a framework:

1. Apresentacao
1. Aplicacao
1. Dominio e regras
1. Persistencia e infraestrutura

Mapeamento pratico no repositorio:

- `public/`, `public/assets/` e views em `app/views/` => apresentacao
- `app/controllers/` e `app/services/` => aplicacao
- `app/models/`, `app/rules/` e `app/access/` => dominio e regras de acesso
- `app/repositories/`, `app/helpers/`, `config/` e `database/` => persistencia e infraestrutura

## 6. Camadas do sistema

### 6.1 Apresentacao

Responsabilidades:

- entrypoints publicos e autenticados em `public/`
- carregamento do shell interno via `public/home.php`
- roteamento interno por arquivo em `public/frame.php`
- assets de interface em `public/assets/`
- views internas e de apoio em `app/views/`

Restricoes:

- arquivos publicos nao devem concentrar regra de negocio complexa
- a raiz publica deve expor apenas o que precisa ser acessado pelo navegador

### 6.2 Aplicacao

Responsabilidades:

- orquestracao dos fluxos do sistema
- coordenacao entre validacao, modelos, repositorios e respostas
- implementacao de casos de uso em services e controllers

### 6.3 Dominio e regras

Responsabilidades:

- regras de negocio
- validacoes especificas do dominio
- politicas de acesso por perfil, setor e pagina
- definicao dos comportamentos centrais das entidades do sistema

### 6.4 Persistencia e infraestrutura

Responsabilidades:

- conexao com MySQL
- consultas e persistencia em repositorios e helpers
- configuracao de ambiente e seguranca em `config/`
- scripts SQL, schema e evolucoes do banco em `database/`
- armazenamento local em `storage/`

## 7. Integracoes

### 7.1 Banco de dados MySQL

- uso: persistencia principal da aplicacao
- suporte documental e operacional em `database/`

### 7.2 SMTP (e-mail)

- uso: fluxos de notificacao e envio de mensagens quando aplicavel

### 7.3 Servicos externos de consulta

- uso: integracoes especificas do dominio, como consultas auxiliares e servicos judiciais
- observacao: integracoes devem ser tratadas como modulos do sistema, nao como definicao da stack base

## 8. Stack tecnologica (resumo)

- Backend: PHP customizado
- Frontend: HTML + CSS + JavaScript
- Banco: MySQL
- Web: publicacao da pasta `public/`
- APIs internas: `public/api/`
- Assets: `public/assets/`
- CI/CD: GitHub Actions e rotinas do repositorio

## 9. Decisoes tecnicas estruturais (principais)

- O projeto nao utiliza Next.js como shell oficial.
- O projeto nao utiliza Laravel como framework principal.
- O projeto nao utiliza Bootstrap como base visual.
- A area autenticada e centralizada em `public/home.php` e `public/frame.php`.
- O frontend usa CSS e JavaScript proprios do projeto.
- A estrutura real do repositorio e a referencia para manutencao e documentacao.

## 10. Infraestrutura

### 10.1 Hospedagem

- ambiente compativel com PHP e MySQL
- publicacao da pasta `public/` como superficie web

### 10.2 Servicos em producao

- servidor web com suporte a PHP
- banco MySQL acessado pela aplicacao
- armazenamento local para logs, uploads e backups conforme necessidade

### 10.3 Backup e retencao

- a estrategia de backup depende da infraestrutura operacional vigente
- scripts e estruturas de banco devem permanecer versionados no repositorio quando aplicavel

## 11. Ambientes

Ambientes previstos:

- Dev
- Homolog
- Prod

Diferencas entre ambientes devem ser tratadas por configuracao, sem alterar a arquitetura base do projeto.

## 12. Regras de codificacao (alto nivel)

- PHP: seguir organizacao consistente, validacao server-side e separacao de responsabilidades
- SQL: manter scripts, ajustes estruturais e schema em `database/`
- Frontend: manter HTML, CSS e JavaScript modulares e aderentes ao padrao visual do projeto
- APIs internas: concentrar endpoints em `public/api/` e padronizar respostas conforme a arquitetura vigente
- Erros e logs: nao expor detalhes sensiveis em ambiente publico

## 13. Ferramentas

- Controle de versao: Git
- Hospedagem do codigo: GitHub
- CI/CD: GitHub Actions
- IDE: VS Code
- Documentacao: Markdown
- Configuracao: arquivos do projeto e parametros de ambiente

## 14. Padroes de desenvolvimento

- Branches principais: `main` e `develop`
- Versionamento: `VERSION`, `CHANGELOG.md` e `docs/90-releases/`
- Commits: conforme convencoes do repositorio
- Evolucao estrutural: primeiro alinhar codigo e documentacao, depois publicar novas decisoes como padrao oficial
