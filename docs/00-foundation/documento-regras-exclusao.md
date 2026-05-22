# SGC - IOS1VCRIME

## DOCUMENTAÇÃO INICIAL

### REGRAS DE EXCLUSÃO E EDIÇÃO DE DADOS

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Introdução

Este documento estabelece as regras técnicas e de governança para operações de exclusão e edição de dados no sistema, garantindo rastreabilidade compatível com a instrumentação atual, conformidade com a LGPD e segurança da informação. As diretrizes aqui descritas aplicam-se aos módulos da Área Restrita (Interna) e devem ser observadas em toda a arquitetura do sistema, desde a camada de apresentação até a camada de infraestrutura.

A exclusão e a edição de dados são operações críticas em sistemas judiciais, pois impactam diretamente a integridade dos registros, a confiabilidade das informações e a capacidade de auditoria. Por esse motivo, o sistema adota diretrizes de controle para exclusão lógica, exclusão física e edição, mas a forma exata de implementação varia conforme o módulo, o schema disponível e o nível de instrumentação já implementado.

Este documento se relaciona diretamente com os documentos de **Objetivos e Público-Alvo**, **Funcionalidades das Áreas Pública e Restrita**, **Arquitetura de Software (SAD)** e **Integrações Externas e APIs**, devendo ser utilizado como referência obrigatória para implementação de funcionalidades de CRUD (Create, Read, Update, Delete) em todos os módulos do sistema.

---

## 2. Princípios gerais

As operações de exclusão e edição de dados devem respeitar os seguintes princípios fundamentais:

- **Rastreabilidade compatível:** exclusões e edições sensíveis devem gerar registro operacional ou de auditoria de forma compatível com o módulo e com a instrumentação disponível no ambiente.

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

O soft delete deve ser utilizado preferencialmente nas entidades em que o schema e o fluxo já suportem exclusão lógica sem comprometer o comportamento do sistema:

- Possuam valor histórico, estatístico ou de auditoria.
- Estejam sujeitas a obrigações legais de retenção de dados.
- Façam parte de fluxos operacionais que possam exigir consulta posterior (por exemplo, audiências encerradas, tarefas concluídas, ofícios enviados).
- Sejam referenciadas por outras entidades em relacionamentos críticos.

Quando um módulo ainda não possui suporte nativo a soft delete, a regra é documentar explicitamente o comportamento adotado, evitando assumir que toda entidade já trabalha com `deleted_at` ou estrutura equivalente.

#### 3.1.2 Implementação técnica do soft delete

A implementação, quando adotada, deve seguir um padrão compatível com a arquitetura PHP customizada do projeto. O campo `deleted_at` (timestamp) ou um status equivalente pode ser adicionado às tabelas relevantes, armazenando a data e hora da exclusão lógica.

Ao realizar um soft delete:

- O registro permanece na tabela, mas é marcado com `deleted_at` preenchido.
- Repositórios, services e consultas padrão devem excluir registros soft-deleted por convenção, a menos que o caso de uso exija explicitamente visualização de itens excluídos.
- A operação deve gerar, sempre que o módulo já estiver instrumentado para isso, um registro operacional ou de auditoria contendo identificação do usuário, horário e contexto funcional.

#### 3.1.3 Recuperação de registros soft-deleted

Registros marcados como excluídos logicamente podem ser recuperados (restaurados) por perfis autorizados, quando o módulo oferecer essa capacidade, desde que:

- A recuperação seja justificada e registrada em log.
- Não comprometa a integridade de fluxos operacionais já finalizados.
- Seja realizada dentro de um prazo razoável (a definir por política institucional, por exemplo, 90 dias).

A operação de restauração deve gerar registro equivalente ao da exclusão quando houver instrumentação disponível no módulo.

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
- Geração de registro operacional ou de auditoria antes da exclusão, quando o módulo já possuir esse mecanismo implementado.

Após a exclusão física, o registro não pode ser recuperado diretamente do banco de dados. Quando houver trilha de auditoria disponível, ela deve preservar informações suficientes para reconstituir o contexto da operação.

#### 3.2.3 Restrições e alertas

O sistema deve exibir alertas claros ao usuário antes de realizar um hard delete, informando:

- Que a operação é irreversível.
- Quais entidades relacionadas podem ser impactadas.
- Que a operação será registrada em log de auditoria.

Em casos de exclusão em massa (batch delete), deve haver validação adicional e, preferencialmente, aprovação de perfil superior (chefia ou administração).

---

## 4. Edição de dados

A edição de dados é uma operação frequente no sistema, mas deve ser controlada de forma a garantir rastreabilidade, integridade e conformidade. Edições relevantes devem gerar registro operacional ou de auditoria sempre que o módulo ou serviço já estiver instrumentado para isso.

### 4.1 Tipos de edição

As edições podem ser classificadas em:

- **Edições simples:** alteração de campos não críticos, como descrições, observações, datas futuras ou campos de controle interno.
- **Edições críticas:** alteração de campos que impactam diretamente fluxos operacionais, cálculos, prazos, identificação de partes ou status de processos.
- **Edições administrativas:** alteração de configurações globais, parâmetros de sistema, tipos de tarefa, etc.

### 4.2 Controle de edições

Toda edição deve respeitar as seguintes diretrizes:

- **Validação de permissões:** apenas perfis autorizados podem editar determinados campos ou entidades.
- **Validação de negócio:** edições devem respeitar regras de negócio (por exemplo, não permitir alteração de data de audiência já realizada).
- **Registro operacional/auditoria:** toda edição relevante deve gerar registro contendo usuário, data/hora, contexto funcional e, quando disponível, o detalhamento dos campos alterados.

### 4.3 Implementação técnica de rastreabilidade

O projeto não possui hoje uma tabela canônica única de auditoria para todo o sistema. A rastreabilidade atual pode envolver, conforme o módulo:

- helpers de auditoria com fallback para `error_log`;
- logs do servidor e da infraestrutura local;
- tabelas auxiliares específicas já existentes no banco;
- registros operacionais pontuais feitos pelo próprio fluxo de negócio.

Quando um módulo exigir trilha mais forte, a recomendação é documentar explicitamente onde o evento fica registrado e qual consulta operacional ou técnica permite recuperá-lo.

### 4.4 Visualização de histórico de edições

O sistema deve disponibilizar, para perfis autorizados, mecanismos de consulta ao histórico de edições compatíveis com o que já existir no módulo ou na operação. Isso pode ocorrer por telas dedicadas quando implementadas, por relatórios administrativos ou por apoio operacional/técnico em casos sensíveis.

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

- **Exclusão:** hard delete deve ser tratado com máxima restrição e seguir a política de retenção vigente.
- **Edição:** registros de auditoria e segurança não devem ser alterados por rotinas operacionais comuns.
- **Exportação:** relatórios ou extrações devem ocorrer apenas em contexto administrativo/técnico autorizado e conforme a instrumentação disponível.

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

Este documento estabelece as diretrizes técnicas e de governança para operações de exclusão e edição de dados no sistema, garantindo conformidade legal, integridade da informação e rastreabilidade compatível com o estágio atual da plataforma.

As regras aqui descritas devem ser implementadas de forma consistente em todos os módulos, respeitando as especificidades funcionais de cada contexto. Qualquer exceção ou ajuste deve ser documentado e aprovado institucionalmente, com atualização deste documento para refletir as mudanças.

**Fim do documento.**
