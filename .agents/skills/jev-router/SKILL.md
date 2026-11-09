---
name: jev-router
description: Encaminha tarefas deste repositório ao agente ou à skill apropriada para diagnóstico, revisão, segurança, formatação, formulários ou automação de interface.
---

# Jev — roteador do projeto

O Jev é uma orientação de roteamento do assistente para este repositório. Ele não é uma chamada ao modelo externo Jev e não executa agentes automaticamente. O assistente interpreta o pedido, consulta as instruções adequadas e executa ou delega conforme os recursos e permissões disponíveis no ambiente.

## Escolha do fluxo

1. Identifique o resultado pedido, o escopo, as autorizações e se o trabalho é apenas leitura ou inclui alterações.
2. Escolha os recursos relevantes abaixo. Quando o usuário nomear uma skill ou agente, priorize a escolha explícita e leia suas instruções. Combine especialidades somente quando necessário.
3. Confira se o recurso está realmente disponível no catálogo. As definições em `.github/agents/` e `.cursor/skills/` são referências do repositório; sua presença em disco não garante que o ambiente as registre como invocáveis. Se não estiverem disponíveis, siga suas instruções aplicáveis diretamente.
4. Delegue somente se o ambiente e o pedido autorizarem delegação. Caso contrário, execute diretamente; não afirme que outro agente atuou ou que uma skill foi carregada sem evidência.
5. Preserve o escopo, relate evidências e limitações e siga a preferência de deploy do usuário para alterações neste projeto.

| Pedido | Recurso principal | Complemento quando aplicável |
|---|---|---|
| Investigar e corrigir falha ou comportamento inesperado | `.github/agents/Debug.agent.md` | Regras e arquivos do módulo afetado |
| Revisar código sem editar | `.github/agents/CodeReviewer.agent.md` | Definição auxiliar PHP, JS, HTML ou CSS em `.github/agents/subagents/` |
| Auditar segurança sem editar | `.github/agents/SecurityAuditor.agent.md` | `docs/canon/security-canon.md` e código de acesso |
| Formatar arquivos autorizados | `.github/agents/Formatter.agent.md` | Canônico e formatador disponível para a linguagem |
| Auditar formulários create/edit | `.cursor/skills/ui-conformity-auditor/SKILL.md` | Contrato de formulário e regras da tela |
| Corrigir inconsistências em formulários create/edit | `.cursor/skills/ui-inconsistency-fixer/SKILL.md` | Agente Debug quando houver falha funcional |
| Automatizar um aplicativo de desktop | Skill `jev-desktop`, se disponível e apropriada | Ferramentas de interface e requisitos indicados na própria skill |
| Outras tarefas | Instruções do projeto e skills disponíveis pertinentes | Não forçar um agente que não corresponda ao pedido |

Para operar interfaces com `jev-desktop`, leia sua skill antes do uso e respeite seus requisitos e as informações enviadas ao serviço externo. Não use automação de desktop apenas para decidir qual agente escolher. Se a skill ou suas dependências não estiverem disponíveis, informe a limitação e use um recurso apropriado disponível.

O roteamento não altera permissões, não autoriza alterações externas e não substitui as instruções de segurança e acesso do projeto. Auditoria ou revisão isolada permanece somente leitura. Não reintroduza módulos independentes de tarefas ou expedições neste sistema: esses módulos pertencem ao FokusCloud, conforme a decisão do usuário.
