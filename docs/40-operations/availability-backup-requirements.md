# Especificação de Requisitos de Disponibilidade e Backup

## 1. Introdução

Este documento estabelece os requisitos de disponibilidade do sistema (SLA), as janelas de manutenção permitidas e a política de backup e recuperação de desastres (Disaster Recovery) para o WebApp | 1vCrimeIlheus. O objetivo é garantir a continuidade do negócio e a integridade dos dados processuais.

## 2. Requisitos de Disponibilidade (SLA)

### 2.1. Meta de Uptime

- **Horário Comercial (08:00 - 19:00):** O sistema deve manter uma disponibilidade mínima de **99,9%** durante os dias úteis, dado o impacto direto na realização de audiências e atendimento ao público.
- **Fora do Horário Comercial:** A disponibilidade mínima esperada é de **99,0%**, permitindo janelas maiores para manutenção.

### 2.2. Monitoramento

- **Health Checks:** O sistema deve possuir um endpoint de monitoramento (`/api/health`) que verifique a conectividade com o banco de dados e o status do servidor PHP.
- **Alertas:** A equipe de operações deve ser notificada automaticamente (via e-mail ou SMS) em caso de indisponibilidade superior a 5 minutos.

### 2.3. Proteção de Infraestrutura

- **Cloudflare:** O tráfego deve ser roteado através do Cloudflare para mitigação de ataques DDoS e cache de ativos estáticos, reduzindo a carga no servidor de origem.

## 3. Política de Backup

### 3.1. Frequência e Tipos

- **Backup de Banco de Dados (MySQL):**
  - **Diário (Full):** Execução automática às 02:00 AM.
  - **Horário (Incremental):** Snapshots dos logs de transação (binlogs) a cada 1 hora durante o horário comercial (opcional, dependendo do volume).
- **Backup de Arquivos (Storage):**
  - **Diário:** Backup incremental de todos os arquivos enviados (PDFs, Atas) e do código-fonte implantado.

### 3.2. Retenção de Dados

- **Retenção Diária:** Manter os últimos 7 dias de backups completos.
- **Retenção Semanal:** Manter 1 backup por semana durante 4 semanas.
- **Retenção Mensal:** Manter 1 backup mensal por 12 meses (para fins de auditoria).

### 3.3. Armazenamento Off-site

- **Destino:** Os backups não devem permanecer apenas no disco local do servidor CloudPanel. É obrigatória a replicação automática para um armazenamento externo (ex: Amazon S3 ou compatível) para garantir a recuperação em caso de perda total do servidor.

## 4. Recuperação de Desastres (DR)

### 4.1. Métricas de Recuperação

- **RPO (Recovery Point Objective):** No pior cenário, a perda máxima de dados aceitável é de **24 horas** (retorno ao backup da noite anterior).
- **RTO (Recovery Time Objective):** O tempo máximo para restaurar o serviço após uma falha crítica é de **4 horas**.

### 4.2. Procedimento de Restauração

- A equipe deve possuir scripts ou documentação validada para restaurar o banco de dados e os arquivos a partir do armazenamento externo em uma nova instância limpa do CloudPanel.
- Testes de restauração devem ser realizados trimestralmente para validar a integridade dos arquivos de backup.

## 5. Janelas de Manutenção

- **Manutenções Programadas:** Devem ocorrer preferencialmente aos finais de semana ou dias úteis entre 22:00 e 06:00.
- **Aviso Prévio:** Usuários devem ser notificados na interface do sistema com pelo menos 24 horas de antecedência sobre paradas programadas que excedam 15 minutos.
