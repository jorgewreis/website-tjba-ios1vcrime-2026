# SGC - IOS1VCRIME

## DOCUMENTAÇÃO INICIAL

### REGRAS DE EXCLUSÃO E EDIÇÃO DE DADOS

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Introdução

Este documento estabelece as regras técnicas e de governança para operações de exclusão e edição de dados no sistema, garantindo rastreabilidade completa, conformidade com a LGPD, auditoria institucional e segurança da informação. As diretrizes aqui descritas aplicam-se a todos os módulos da Área Restrita (Interna) e devem ser observadas em toda a arquitetura do sistema, desde a camada de apresentação até a camada de infraestrutura.

A exclusão e a edição de dados são operações críticas em sistemas judiciais, pois impactam diretamente a integridade dos registros, a confiabilidade das informações e a capacidade de auditoria. Por esse motivo, o sistema adota uma abordagem híbrida de exclusão (soft delete e hard delete) e um modelo rigoroso de controle de edições, com registro detalhado de todas as alterações realizadas.

Este documento se relaciona diretamente com os documentos de **Objetivos e Público-Alvo**, **Funcionalidades das Áreas Pública e Restrita**, **Arquitetura de Software (SAD)** e **Integrações Externas e APIs**, devendo ser utilizado como referência obrigatória para implementação de funcionalidades de CRUD (Create, Read, Update, Delete) em todos os módulos do sistema.

---

## 2. Princípios gerais

As operações de exclusão e edição de dados devem respeitar os seguintes princípios fundamentais:

- **Rastreabilidade total:** toda exclusão ou edição deve ser registrada em log de auditoria, contendo identificação do usuário responsável, data e hora da operação, contexto funcional e, no caso de edições, os valores anteriores e posteriores dos campos alterados.

- **Conformidade com LGPD:** exclusões definitivas (hard delete) devem observar os direitos dos titulares de dados pessoais, incluindo o direito ao esquecimento, mas sempre respeitando as obrigações legais de retenção de dados processuais e administrativos.

- **Segurança e controle de acesso:** apenas perfis autorizados podem realizar exclusões ou edições em contextos sensíveis. Operações críticas devem exigir confirmação adicional e, quando aplicável, justificativa textual.

- **Integridade referencial:** exclusões e edições não podem comprometer a consistência do banco de dados. Relacionamentos entre entidades devem ser tratados adequadamente, seja por meio de soft delete em cascata, seja por validações que impeçam exclusões indevidas.

- **Reversibilidade quando possível:** sempre que viável, o sistema deve priorizar soft delete (exclusão lógica) em vez de hard delete (exclusão física), permitindo recuperação de dados em casos de erro ou necessidade institucional.

---

## 3. Exclusão de dados

A exclusão de dados no sistema é classificada em duas modalidades principais: **soft delete** (exclusão lógica) e **hard delete** (exclusão física). A escolha entre uma modalidade e outra depende do tipo de dado, do contexto funcional, das obrigações legais de retenção e das políticas institucionais de governança da informação.

### 3.1 Soft delete (exclusão lógica)

O soft delete consiste em marcar um registro como excluído, sem removê-lo fisicamente do banco de dados. Tecnicamente, isso é implementado por meio de um campo de controle (por exemplo, `deleted_at` ou `status`) que indica se o registro está ativo ou excluído.

#### 3.1.1 Quando utilizar soft delete

O soft delete deve ser utilizado como padrão em todas as entidades que:

- Possuam valor histórico, estatístico ou de auditoria.
- Estejam sujeitas a obrigações legais de retenção de dados.
- Façam parte de fluxos operacionais que possam exigir consulta posterior (por exemplo, audiências encerradas, tarefas concluídas, ofícios enviados).
- Sejam referenciadas por outras entidades em relacionamentos críticos.

Exemplos de entidades que devem adotar soft delete:

- Audiências
- Tarefas e sub-tarefas
- Cartas recebidas e expedidas
- Ofícios
- Editais
- Guias
- Notificações
- Logs de auditoria (nunca devem ser excluídos fisicamente)

#### 3.1.2 Implementação técnica do soft delete

A implementação deve seguir o padrão do Laravel, utilizando o trait `SoftDeletes` nos models correspondentes. O campo `deleted_at` (timestamp) deve ser adicionado às tabelas relevantes, armazenando a data e hora da exclusão lógica.

Ao realizar um soft delete:

- O registro permanece na tabela, mas é marcado com `deleted_at` preenchido.
- Consultas padrão (via Eloquent) automaticamente excluem registros soft-deleted, a menos que seja utilizado o método `withTrashed()` ou `onlyTrashed()`.
- A operação deve gerar um log de auditoria contendo:
  - ID do registro excluído
  - Tipo de entidade (tabela)
  - ID do usuário responsável
  - Data e hora da exclusão
  - Contexto funcional (módulo, submódulo)
  - Justificativa (quando aplicável)

#### 3.1.3 Recuperação de registros soft-deleted

Registros marcados como excluídos logicamente podem ser recuperados (restaurados) por perfis autorizados, desde que:

- A recuperação seja justificada e registrada em log.
- Não comprometa a integridade de fluxos operacionais já finalizados.
- Seja realizada dentro de um prazo razoável (a definir por política institucional, por exemplo, 90 dias).

A operação de restauração deve gerar log de auditoria equivalente ao da exclusão, contendo identificação do usuário, data/hora e justificativa.

### 3.2 Hard delete (exclusão física)

O hard delete consiste na remoção definitiva do registro do banco de dados. Essa operação é irreversível e deve ser utilizada com extrema cautela, apenas em situações específicas e devidamente justificadas.

#### 3.2.1 Quando utilizar hard delete

O hard delete deve ser utilizado exclusivamente em casos como:

- Dados de teste ou cadastros duplicados acidentais em ambiente de desenvolvimento ou homologação.
- Dados pessoais cujo titular exerceu o direito ao esquecimento (LGPD), desde que não haja obrigação legal de retenção.
- Registros temporários ou de cache que não possuem valor histórico ou de auditoria.
- Dados obsoletos que ultrapassaram o prazo de retenção definido em política institucional e que não possuem mais relevância operacional ou legal.

Exemplos de entidades que podem admitir hard delete (com restrições):

- Rascunhos de documentos não finalizados
- Tokens de autenticação expirados
- Sessões de usuário encerradas
- Dados de cache ou filas (quando implementados)

#### 3.2.2 Implementação técnica do hard delete

A exclusão física deve ser implementada com validações rigorosas:

- Verificação de permissões do usuário (apenas perfis administrativos ou chefias devem ter acesso).
- Confirmação explícita da operação (modal de confirmação com justificativa obrigatória).
- Validação de integridade referencial (garantir que a exclusão não quebre relacionamentos críticos).
- Geração de log de auditoria antes da exclusão, contendo:
  - Snapshot completo do registro (ou dos campos mais relevantes)
  - ID do usuário responsável
  - Data e hora da exclusão
  - Justificativa textual
  - Contexto funcional

Após a exclusão física, o registro não pode ser recuperado diretamente do banco de dados, mas o log de auditoria deve preservar informações suficientes para reconstituir o contexto da operação.

#### 3.2.3 Restrições e alertas

O sistema deve exibir alertas claros ao usuário antes de realizar um hard delete, informando:

- Que a operação é irreversível.
- Quais entidades relacionadas podem ser impactadas.
- Que a operação será registrada em log de auditoria.

Em casos de exclusão em massa (batch delete), deve haver validação adicional e, preferencialmente, aprovação de perfil superior (chefia ou administração).

---

## 4. Edição de dados

A edição de dados é uma operação frequente no sistema, mas deve ser controlada de forma a garantir rastreabilidade, integridade e conformidade. Toda edição relevante deve gerar registro em log de auditoria, contendo os valores anteriores e posteriores dos campos alterados.

### 4.1 Tipos de edição

As edições podem ser classificadas em:

- **Edições simples:** alteração de campos não críticos, como descrições, observações, datas futuras ou campos de controle interno.
- **Edições críticas:** alteração de campos que impactam diretamente fluxos operacionais, cálculos, prazos, identificação de partes ou status de processos.
- **Edições administrativas:** alteração de configurações globais, parâmetros de sistema, tipos de tarefa, etc.

### 4.2 Controle de edições

Toda edição deve respeitar as seguintes diretrizes:

- **Validação de permissões:** apenas perfis autorizados podem editar determinados campos ou entidades.
- **Validação de negócio:** edições devem respeitar regras de negócio (por exemplo, não permitir alteração de data de audiência já realizada).
- **Registro em log de auditoria:** toda edição relevante deve gerar log contendo:
  - ID do registro editado
  - Tipo de entidade (tabela)
  - ID do usuário responsável
  - Data e hora da edição
  - Campos alterados (nome do campo, valor anterior, valor posterior)
  - Contexto funcional (módulo, submódulo)
  - Justificativa (quando aplicável)

### 4.3 Implementação técnica de logs de edição

A implementação de logs de edição pode ser realizada de duas formas principais:

#### 4.3.1 Tabela de auditoria genérica

Criação de uma tabela de auditoria genérica (`audit_logs` ou `activity_logs`) que registra todas as operações de edição do sistema, contendo campos como:

- `id` (chave primária)
- `user_id` (ID do usuário responsável)
- `entity_type` (tipo de entidade, ex.: "Audiencia", "Tarefa")
- `entity_id` (ID do registro editado)
- `action` (tipo de ação: "update", "delete", "restore")
- `changes` (JSON contendo campos alterados, valores anteriores e posteriores)
- `context` (módulo, submódulo, funcionalidade)
- `justification` (justificativa textual, quando aplicável)
- `ip_address` (endereço IP do usuário)
- `user_agent` (navegador/dispositivo)
- `created_at` (timestamp da operação)

#### 4.3.2 Tabelas de histórico específicas

Para entidades críticas (como audiências e tarefas do cartório), pode ser criada uma tabela de histórico específica (por exemplo, `audiencias_history`) que armazena snapshots completos do registro a cada edição, facilitando consultas e reconstituição de estados anteriores.

### 4.4 Visualização de histórico de edições

O sistema deve disponibilizar, para perfis autorizados (chefia, administração), funcionalidades de consulta ao histórico de edições, permitindo:

- Visualizar todas as edições realizadas em um registro específico.
- Filtrar edições por usuário, período, tipo de entidade ou módulo.
- Exportar logs de auditoria para análise externa ou apresentação em processos administrativos.

---

## 5. Regras específicas por módulo

Cada módulo do sistema pode possuir regras adicionais de exclusão e edição, conforme a natureza dos dados e os fluxos operacionais envolvidos. A seguir, são apresentadas diretrizes específicas para os principais módulos.

### 5.1 Módulo de Audiências (m1)

- **Exclusão:** soft delete obrigatório. Audiências nunca devem ser excluídas fisicamente, mesmo após encerramento.
- **Edição:** permitida até o momento da realização da audiência. Após a realização, apenas campos de observação ou anexos podem ser editados, com justificativa obrigatória.
- **Log:** toda edição de data, horário, tipo de audiência, partes ou status deve gerar log detalhado.

### 5.2 Módulo de Tarefas e Fluxos Internos (m3)

- **Exclusão:** soft delete como padrão. Hard delete permitido apenas para rascunhos não iniciados.
- **Edição:** permitida enquanto a tarefa estiver em andamento. Após conclusão, apenas campos de observação podem ser editados.
- **Log:** alterações em prazos, responsáveis, status ou pontuação devem gerar log obrigatório.

### 5.3 Módulo de Cartório (m4) e submódulos

- **Exclusão:** soft delete obrigatório para todos os submódulos (cartas, ofícios, editais, guias).
- **Edição:** permitida com restrições conforme o status do fluxo. Após envio ou certificação, edições devem ser justificadas.
- **Log:** todas as edições em campos críticos (partes, datas, valores, status) devem gerar log detalhado.

### 5.4 Módulo de Notificações (m5)

- **Exclusão:** soft delete para notificações lidas. Hard delete permitido para notificações não lidas após 90 dias.
- **Edição:** não permitida. Notificações são geradas automaticamente e não devem ser editadas manualmente.
- **Log:** exclusões devem gerar log de auditoria.

### 5.5 Módulo de Logs, Auditoria e Segurança (m11)

- **Exclusão:** hard delete proibido. Logs de auditoria são imutáveis e devem ser preservados indefinidamente (ou conforme política de retenção institucional).
- **Edição:** proibida. Logs não podem ser alterados sob nenhuma hipótese.
- **Exportação:** permitida para perfis administrativos, com registro da operação de exportação.

### 5.6 Módulo de Configurações Gerais (m12)

- **Exclusão:** soft delete para tipos, categorias e parâmetros obsoletos. Hard delete permitido apenas para dados de teste.
- **Edição:** permitida apenas para perfis administrativos (admin, chefia), com versionamento obrigatório e possibilidade de rollback.
- **Log:** todas as alterações em configurações globais devem gerar log detalhado, incluindo valores anteriores e posteriores.

---

## 6. Políticas de retenção de dados

As políticas de retenção definem por quanto tempo os dados devem ser mantidos no sistema antes de serem elegíveis para exclusão definitiva (hard delete). Essas políticas devem considerar obrigações legais, necessidades operacionais e diretrizes institucionais.

### 6.1 Dados processuais e de audiências

- **Retenção mínima:** indefinida (ou conforme determinação judicial/institucional).
- **Exclusão:** não permitida, exceto por ordem judicial ou após arquivamento definitivo do processo.

### 6.2 Dados de tarefas e produtividade

- **Retenção mínima:** 5 anos após conclusão.
- **Exclusão:** soft delete após 5 anos; hard delete após 10 anos (ou conforme política institucional).

### 6.3 Logs de auditoria

- **Retenção mínima:** 10 anos.
- **Exclusão:** hard delete permitido apenas após prazo de retenção, com aprovação institucional.

### 6.4 Dados pessoais de estudantes

- **Retenção mínima:** até o término da participação acadêmica.
- **Exclusão:** soft delete imediato após término; hard delete permitido após 2 anos, respeitando direito ao esquecimento (LGPD).

---

## 7. Conformidade com LGPD

As operações de exclusão e edição devem observar rigorosamente a Lei Geral de Proteção de Dados (LGPD), especialmente no que se refere aos direitos dos titulares de dados pessoais.

### 7.1 Direito ao esquecimento

Titulares de dados pessoais podem solicitar a exclusão de seus dados, desde que não haja obrigação legal de retenção. Nesses casos:

- O sistema deve permitir a exclusão física (hard delete) dos dados pessoais.
- Dados processuais ou de auditoria que não contenham informações pessoais identificáveis devem ser preservados.
- A operação deve ser registrada em log de auditoria, contendo justificativa e identificação do solicitante.

### 7.2 Minimização de dados

Edições devem evitar a inclusão de dados pessoais desnecessários. Campos de observação ou justificativa não devem conter informações sensíveis sem necessidade.

### 7.3 Anonimização e pseudonimização

Sempre que possível, dados pessoais em logs de auditoria devem ser pseudonimizados (substituídos por identificadores opacos), preservando a rastreabilidade sem expor informações sensíveis.

---

## 8. Conclusão

Este documento estabelece as diretrizes técnicas e de governança para operações de exclusão e edição de dados no sistema, garantindo rastreabilidade, conformidade legal e integridade da informação. A adoção de soft delete como padrão, aliada ao controle rigoroso de edições com logs detalhados, assegura que o sistema mantenha a confiabilidade necessária para um ambiente judicial.

As regras aqui descritas devem ser implementadas de forma consistente em todos os módulos, respeitando as especificidades funcionais de cada contexto. Qualquer exceção ou ajuste deve ser documentado e aprovado institucionalmente, com atualização deste documento para refletir as mudanças.

**Fim do documento.**
