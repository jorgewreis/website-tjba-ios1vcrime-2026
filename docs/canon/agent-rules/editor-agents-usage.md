---
id: editor-agents-usage
title: "Uso de Agentes de Edição no Editor"
version: "1.0"
date: "2026-02-22"
---

# Uso de Agentes de Edição no Editor

## 1. Objetivo

Descrever como os agentes de IA orientados por canônicos (PHP, JS, CSS, HTML) devem ser usados no **editor** (ex.: Cursor), de forma consistente e previsível.

Este documento não define regras de código em si (isso está nos canônicos), mas sim **como conectar** o editor aos arquivos:

- `editor-agents-canon.md`
- `php-editor-agent.md`
- `js-editor-agent.md`
- `css-editor-agent.md`
- `html-editor-agent.md`

## 2. Arquivos de referência

- `[docs/canon/agent-rules/editor-agents-canon.md](./editor-agents-canon.md)`
- `[docs/canon/agent-rules/php-editor-agent.md](./php-editor-agent.md)`
- `[docs/canon/agent-rules/js-editor-agent.md](./js-editor-agent.md)`
- `[docs/canon/agent-rules/css-editor-agent.md](./css-editor-agent.md)`
- `[docs/canon/agent-rules/html-editor-agent.md](./html-editor-agent.md)`

Esses arquivos devem ser citados sempre que o desenvolvedor abrir um chat de código pedindo revisão de um arquivo.

## 3. Padrão de prompt para o editor

Quando iniciar uma conversa com o agente no editor para revisar um arquivo, usar um dos formatos abaixo (adaptando a linguagem alvo):

- **PHP**

> “Considere `global-canon`, `project-canonical-document`, `php-format-canon`, `editor-agents-canon` e `php-editor-agent` ao revisar este arquivo PHP. Atue em modo consultivo, sugerindo melhorias de estilo, legibilidade e segurança sem mudar regras de negócio.”

- **JavaScript**

> “Considere `global-canon`, `project-canonical-document`, `js-format-canon`, `editor-agents-canon` e `js-editor-agent` ao revisar este arquivo JS. Atue em modo consultivo, modernizando código e apontando riscos de segurança.”

- **CSS**

> “Considere `global-canon`, `project-canonical-document`, `css-format-canon`, `editor-agents-canon` e `css-editor-agent` ao revisar este arquivo CSS. Foque em tokens, acessibilidade e organização de estilos.”

- **HTML**

> “Considere `global-canon`, `project-canonical-document`, `html-format-canon`, `editor-agents-canon` e `html-editor-agent` ao revisar este trecho HTML. Foque em semântica e acessibilidade, sem alterar lógica de backend.”

## 4. Integração com regras/skills do editor

Ao configurar regras ou skills personalizadas no editor (por exemplo, `.cursor/rules/*.md` ou skills customizadas):

1. **Apontar para este conjunto mínimo de canônicos**:
   - `global-canon`
   - `project-canonical-document`
   - canônico de formatação da linguagem (PHP/JS/CSS/HTML)
   - `editor-agents-canon`
   - `*-editor-agent` correspondente
2. **Definir o modo de trabalho**:
   - Em revisão isolada, relatar achados sem editar; em implementação solicitada, executar o trabalho autorizado.
   - Classificar os achados por severidade quando isso ajudar a priorizar correções.
3. **Restringir o escopo**:
   - Skills devem seguir o escopo pedido e consultar arquivos relacionados quando necessário.

## 5. Fluxo recomendado de uso diário

1. Abrir o arquivo alvo no editor.
2. Selecionar o trecho ou arquivo inteiro a ser analisado.
3. Chamar o agente com o prompt padrão da linguagem correspondente.
4. Ler a lista de achados e:
   - aplicar ajustes simples diretamente;
   - discutir refatorações maiores (quando sugeridas) antes de aplicar.
5. Registrar exceções importantes (quando o código precisa quebrar uma regra de estilo por motivo justificado) em comentários no código ou documentação, se necessário.

## 6. Evolução e feedback

- Ajustes neste fluxo devem ser feitos com base em:
  - feedback dos desenvolvedores no uso diário;
  - resultados documentados em `pilot-results.md`.
- Mudanças estruturais nos prompts ou no conjunto de canônicos de referência devem ser refletidas aqui.

