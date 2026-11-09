---
id: editor-agents-canon
title: "Canônico de Agentes para Edição de Código"
version: "1.1"
date: "2026-02-22"
scope: "agentes de IA usados no editor (Cursor, chat de código e similares)"
inherits_from:
  - global-canon
  - project-canonical-document
description: "Regras e referências obrigatórias para agentes de IA que atuam diretamente nos arquivos de código deste repositório."
---

# Canônico de Agentes para Edição de Código

## 1. Finalidade

Este documento define as regras e referências mínimas que todo agente de IA utilizado no editor deve obedecer ao trabalhar com arquivos deste repositório.

O objetivo é garantir que:

- as sugestões de alteração de código sejam consistentes com os canônicos já existentes;
- o agente distinga revisão consultiva de implementação autorizada e preserve dados e alterações alheias;
- o foco esteja em legibilidade, segurança e aderência aos padrões de estilo do projeto.

## 2. Canônicos e referências obrigatórias

Todo agente de edição de código deve considerar, no mínimo, os seguintes documentos:

- **Canônico Global**
  - `[docs/canon/global-canon.md](../global-canon.md)`
- **Documento Canônico do Projeto**
  - `[docs/00-foundation/project-canonical-document.md](../../00-foundation/project-canonical-document.md)`
- **Formatação por linguagem**
  - PHP: `[docs/canon/php-format-canon.md](../php-format-canon.md)`
  - JavaScript: `[docs/canon/js-format-canon.md](../js-format-canon.md)`
  - CSS: `[docs/canon/css-format-canon.md](../css-format-canon.md)`
  - HTML: `[docs/canon/html-format-canon.md](../html-format-canon.md)`
- **Formatação de Markdown (quando a saída for Markdown)**
  - `[docs/canon/markdown-format-canon.md](../markdown-format-canon.md)`
- **Regras de comportamento de agentes**
  - `[docs/canon/agent-rules/agent-behavior.md](./agent-behavior.md)`
- **Canônicos de segurança (quando relevante para o trecho analisado)**
  - `[docs/canon/security-canon.md](../security-canon.md)`

Em caso de conflito entre regras, vale a hierarquia definida em `global-canon.md`.

## 3. Modo de atuação (editor)

Em revisão isolada, opere em modo consultivo. Quando o usuário pedir implementação ou correção, execute as alterações autorizadas seguindo estas diretrizes:

- **Sem mudanças destrutivas por padrão**
  - Não apagar blocos de código, não alterar assinaturas públicas nem mudar regras de negócio sem pedido explícito.
- **Priorizar clareza sobre criatividade**
  - Melhorar legibilidade, estrutura e segurança, sem refatorações profundas desnecessárias.
- **Separar tipo de feedback**
  - Distinguir claramente entre:
    - problemas críticos (segurança, bug provável, quebra evidente de canônico);
    - ajustes de estilo ou organização (indentação, nomes, ordem de imports etc.).
- **Rastreabilidade**
  - Sempre que possível, referenciar a regra canônica que motiva a sugestão (ex.: “conflita com `php-format-canon`, seção 3”).

## 4. Escopo de atuação

- O escopo é o pedido do usuário; inspecione arquivos relacionados quando necessário para entender consumidores, contratos e impactos.
- Não amplie as alterações além do trabalho solicitado. Uma varredura integral requer pedido compatível com esse escopo.

## 5. Estrutura recomendada para agentes por linguagem

Cada agente específico de linguagem (`php-editor-agent`, `js-editor-agent`, `css-editor-agent`, `html-editor-agent`) deve:

- herdar explicitamente este canônico (`editor-agents-canon`) e o canônico da linguagem correspondente;
- seguir o template descrito em `[docs/canon/templates/agent-template.md](../templates/agent-template.md)`;
- definir:
  - frontmatter com `id`, `title`, `scope`, `version`, `inherits_from`;
  - descrição curta com o papel do agente na revisão;
  - conjunto de regras operacionais (checklist de verificação);
  - fluxo de atuação (passo a passo);
  - checklist de saída (como estruturar o feedback para o desenvolvedor).

## 6. Idioma e tom

- Comunicação sempre em português técnico e objetivo.
- Evitar jargões desnecessários e explicações vagas.
- Quando propor mudanças, preferir exemplos curtos e diretamente aplicáveis.

## 7. Evolução deste canônico

- Alterações neste documento devem seguir o processo padrão de canônicos:
  - versionamento explícito no frontmatter;
  - justificativa clara no commit ou ADR associado;
  - impacto esperado sobre agentes existentes.

