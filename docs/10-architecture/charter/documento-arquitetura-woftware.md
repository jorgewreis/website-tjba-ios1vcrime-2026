# Software Architecture Document (SAD)

Data: 2026-01-28  
Versao base: ver `VERSION`  
Repositorio: `website-tjba-ios1vcrime-2026`

## 1. Visao geral

Este documento descreve a arquitetura do sistema web para gestao de rotinas internas (Cartorio, Gabinete, Audiencias e Estudantes), com rastreabilidade, governanca e evolucao controlada por versionamento.

## 2. Linguagens de programacao

- Backend: PHP
- Frontend: HTML, CSS, JavaScript
- Planejado: SCSS (Sass) para evoluir o CSS; TypeScript para evoluir o JavaScript

## 3. Frameworks e bibliotecas

- Backend: Laravel
- Frontend: Bootstrap

Observacao: dependencias de terceiros via Composer podem ser adotadas futuramente, mas hoje o projeto evita bibliotecas externas quando nao forem necessarias.

## 4. Tecnologias adotadas

- Banco de dados: MySQL
- Servidor web: Nginx
- Autenticacao: sessao + JWT (hibrido)
- E-mail: SMTP
- Storage: local (disco do servidor)
- Logs/observabilidade: AWS CloudWatch
- Cache: nao utilizado
- Filas: nao utilizado
- Container: Docker (somente desenvolvimento)

## 5. Padrao arquitetural

Arquitetura em camadas, com separacao explicita de responsabilidades:

1. Apresentacao
1. Aplicacao
1. Dominio
1. Infraestrutura

O Laravel naturalmente segue MVC. Aqui, o MVC e mapeado para camadas:

- Controllers/Views (MVC) => Apresentacao
- Services/Use Cases => Aplicacao
- Entidades/Regras => Dominio
- Integracoes/DB/IO => Infraestrutura

## 6. Camadas do sistema

### 6.1 Apresentacao

Responsabilidades:

- Rotas, controllers e validacao de entrada (request/DTO)
- Views/templates e assets (Bootstrap, CSS/SCSS, JS/TS)
- Autorizacao de acesso (guard/policies) em nivel de endpoint

Restricoes:

- Controllers devem ser finos (sem regra de negocio complexa)

### 6.2 Aplicacao

Responsabilidades:

- Casos de uso (ex.: cadastrar carta, registrar ata, listar pauta)
- Orquestracao de transacoes
- Coordenar chamadas ao dominio e infraestrutura
- Padronizar respostas e erros para a apresentacao

### 6.3 Dominio

Responsabilidades:

- Regras de negocio e invariantes (validacoes de negocio)
- Entidades/Value Objects (quando aplicavel)
- Politicas e regras de permissao em nivel de negocio

Observacao: manter o dominio o mais independente possivel de framework e IO.

### 6.4 Infraestrutura

Responsabilidades:

- Persistencia (MySQL) via Eloquent/Query Builder
- Integracao com SMTP, CloudWatch, API do CNJ e WhatsApp
- File storage local
- Implementacoes de repositorios/adapters

## 7. Integracoes

### 7.1 API do CNJ

- Tipo: HTTP API (a definir endpoints e credenciais)
- Uso: consulta/registro conforme necessidades do negocio
- Observabilidade: logar request/response (mascarando dados sensiveis)

### 7.2 SMTP (e-mail)

- Uso: notificacoes e comunicacoes operacionais
- Recomendacao: dev/homolog enviam para destinos de teste

### 7.3 WhatsApp

- Provedor: a definir (preferencia futura: API oficial)
- Uso: envio de mensagens (notificacoes e alertas)
- Recomendacao: homolog com sandbox/numero de teste

### 7.4 CloudWatch (logs)

- Uso: centralizacao de logs do backend (erros, auditoria e eventos relevantes)
- Restricao: manter dentro do plano gratuito sempre que possivel

## 8. Stack tecnologica (resumo)

- Backend: Laravel (PHP)
- Frontend: Bootstrap + HTML/CSS (SCSS planejado) + JS (TypeScript planejado)
- Banco: MySQL
- Web: Nginx (+ PHP-FPM no servidor)
- Logs: CloudWatch
- Storage: local
- Autenticacao: sessao + JWT
- CI/CD: GitHub Actions + SSH/CloudPanel

## 9. Decisoes tecnicas estruturais (principais)

- Autenticacao hibrida: sessao para navegacao web tradicional e JWT quando houver consumo por clientes/API.
- Sem cache e sem filas inicialmente para reduzir complexidade e custo.
- Logs centralizados no CloudWatch para auditoria e rastreabilidade.
- Storage local para arquivos, com politica de backup e retencao.
- Restricao de custo: adotar recursos que respeitem plano gratuito sempre que houver cobranca por uso.

## 10. Infraestrutura

### 10.1 Hospedagem

- Host: Hostinger
- Virtualizacao: KVM propria
- Painel: CloudPanel
- Recursos: 2 GB RAM, 50 GB disco, 4 TB de banda

### 10.2 Servicos em producao

- Nginx como reverse proxy/servidor web
- PHP (Laravel) executando via PHP-FPM
- MySQL acessivel pela aplicacao
- Logs enviados ao CloudWatch
- Arquivos persistidos em disco local

### 10.3 Backup e retencao

- Local: no proprio servidor
- Retencao: 90 dias
- Frequencia:
  - Dados de "produtividade": semanal
  - Demais dados: diario

## 11. Ambientes

Ambientes documentados:

- Dev
- Homolog
- Prod

Diferencas definidas:

- URLs diferentes por ambiente
- Banco unico (compartilhado entre ambientes)
- Dev: debug ligado e logs detalhados
- Homolog/Prod: debug desligado
- Prod: logar somente o necessario

Risco do banco unico:

- Maior chance de impacto acidental em dados reais. Mitigacoes recomendadas:
  - Prefixo/namespace por ambiente em tabelas ou colunas (quando aplicavel)
  - Credenciais/roles mais restritas para dev/homolog
  - Flags de ambiente para bloquear rotas perigosas fora de prod

## 12. Regras de codificacao (alto nivel)

- PHP: seguir PSR-12 e padroes do Laravel; usar tipagem estrita quando possivel
- Separacao de responsabilidades: controllers finos; regra de negocio em aplicacao/dominio
- Persistencia: preferir Eloquent/Query Builder; evitar SQL solto sem necessidade
- Validacao: sempre server-side; sanitizacao centralizada
- Logs: padrao unico de log (contexto, ids, mascaramento de dados sensiveis)
- Erros: padronizar tratamento e mensagens; nao expor detalhes em prod
- Organizacao: manter pastas por camada e limites claros entre camadas
- Frontend: Bootstrap como base; evoluir CSS para SCSS e JS para TypeScript conforme maturidade

## 13. Ferramentas

- Controle de versao: Git
- Hospedagem do codigo e fluxos: GitHub
- CI/CD: GitHub Actions
- IDE: VS Code
- Extensoes: PHP Intelephense, GitLens
- Qualidade/formatacao: ESLint, Prettier, markdownlint
- Configuracao: `.env`
- Dev container: Docker (somente dev)

## 14. Padroes de desenvolvimento

- Branches: `main` (producao) e `develop` (integracao), com branches curtos para features
- Commits: convencoes em `COMMITS.md`
- Versionamento: regras e automacao em `docs/changelog.md` e `VERSION`
- Releases: notas em `docs/releases/` com `_draft_minor.md`, arquivos por MINOR e indice em `CHANGELOG.md`
- Qualidade: rodar lint/format antes de commit e manter docs atualizados
