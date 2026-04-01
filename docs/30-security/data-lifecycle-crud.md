# Fluxos de Gerenciamento de Dados (CRUD) e Ciclo de Vida

## 1. Visão Geral e Integridade

O gerenciamento de dados no **WebApp | 1vCrimeIlheus** segue regras de integridade e segurança aplicadas no backend PHP e especializadas por módulo. O objetivo é garantir validação de entrada, coerência de permissões, tratamento adequado de dados sensíveis e rastreabilidade compatível com o nível de instrumentação atualmente existente no sistema.

O projeto **não adota hoje um padrão único e universal** de CRUD para todas as entidades. Dependendo do módulo, o ciclo de vida do registro pode envolver exclusão lógica, inativação por status, exclusão física controlada ou simples bloqueio funcional sem remoção do dado.

## 2. Fluxo de Cadastro (Create)

O processo de cadastro é o ponto onde as validações de negócio e segurança precisam ser mais rígidas. Antes de persistir um novo registro, o backend deve validar campos obrigatórios, formatos, unicidade quando aplicável e regras específicas do domínio.

No caso de usuários, o sistema aplica política de senha forte, valida CPF, controla perfis/permissões e exige tratamento seguro de credenciais. Em outras entidades, a validação varia conforme o módulo, mas deve sempre ocorrer no servidor.

### Diretrizes de cadastro

- CPF, matrícula, número CNJ e outros identificadores devem ser normalizados antes da validação.
- Senhas e tokens nunca devem ser persistidos em texto puro.
- Respostas de APIs devem informar sucesso ou erro de validação sem expor detalhes internos desnecessários.
- O projeto não adota, como regra global, um padrão único de IDs semânticos como `USR-...` ou `PROC-...`; a chave efetiva depende da tabela e do fluxo implementado.

## 3. Fluxo de Consulta e Visualização (Read)

A leitura de dados na Área Restrita respeita autenticação, perfil e escopo funcional do usuário. Em módulos setoriais, consultas devem considerar a lotação e o vínculo do usuário com o registro solicitado. Em módulos públicos, só podem ser expostos dados explicitamente autorizados para esse contexto.

Além do controle de acesso, a visualização pode aplicar mascaramento de dados e paginação para reduzir exposição indevida e custo operacional.

### Diretrizes de leitura

- Consultas sensíveis devem validar sessão, perfil e contexto funcional.
- O filtro setorial depende do modelo do módulo; a documentação não deve presumir que toda consulta usa exatamente `setor_id` e `deleted_at`.
- Dados públicos e dados internos devem ter contratos de visualização distintos.
- Campos sensíveis devem ser mascarados quando o perfil não exigir visualização integral.

## 4. Fluxo de Edição e Atualização (Update)

A edição de registros exige validação de permissão, regra de negócio e coerência do estado atual do dado. O sistema diferencia alterações operacionais comuns de alterações estruturais, como mudança de perfil, lotação, parâmetros globais ou redefinição de credenciais.

Nem todo módulo possui hoje a mesma profundidade de trilha de auditoria. Ainda assim, alterações sensíveis devem gerar registro operacional ou de auditoria sempre que houver mecanismo implementado para isso.

### Diretrizes de atualização

- O backend deve rejeitar alterações feitas por perfis sem permissão.
- Campos controlados pelo sistema não devem ser alterados livremente pelo cliente.
- Mudanças estruturais devem exigir perfil elevado e contexto administrativo.
- Quando o módulo suportar trilha detalhada, devem ser preservados usuário, horário, ação e dados relevantes antes/depois.

## 5. Fluxo de Exclusão e Retenção (Delete)

O sistema não possui hoje uma política única de exclusão aplicada de maneira homogênea a todas as tabelas. Há cenários em que o comportamento correto é:

- exclusão lógica com `deleted_at` ou status equivalente;
- inativação sem remoção física;
- revogação/expiração funcional do registro;
- exclusão física controlada.

Por isso, a documentação não deve presumir que toda remoção usa *soft delete* nem que exista um processo global de expurgo automático por cron para todas as entidades.

### Diretrizes de exclusão

- Exclusões sensíveis devem exigir validação de permissão e confirmação adequada.
- Quando o módulo suportar exclusão lógica, consultas padrão devem respeitar o estado do registro.
- Exclusões físicas devem ser restritas e justificadas.
- Políticas de retenção devem seguir a natureza do dado, a obrigação legal e a implementação existente no módulo.

## 6. Rastreabilidade e Operação

O estado atual do projeto utiliza uma estratégia híbrida de rastreabilidade. Dependendo do fluxo, os eventos podem ser registrados por:

- helpers de auditoria da aplicação;
- `error_log` e logs do servidor;
- tabelas auxiliares específicas;
- regras de negócio instrumentadas localmente no módulo.

Isso significa que a investigação operacional precisa considerar o contexto do módulo, e não assumir um painel único de auditoria para todo o sistema.

## 7. Conclusão

O ciclo de vida dos dados no projeto é governado por validação server-side, controle de acesso, regras de negócio e rastreabilidade compatível com o estágio atual da aplicação. Sempre que um módulo ganhar nova política de retenção, soft delete, histórico ou trilha de auditoria, essa decisão deve ser documentada explicitamente para evitar que a documentação descreva capacidades ainda não implementadas de forma global.
