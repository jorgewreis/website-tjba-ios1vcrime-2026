---
name: ui-conformity-auditor
description: Audita telas de cadastro e edição do sistema PHP quando o usuário pede revisão de conformidade visual, acessibilidade ou consistência de formulários.
---

# Auditoria de formulários

1. Identifique as telas e o escopo solicitados. Consulte `docs/create-edit-ui-contract.md`, `.cursor/rules/create-edit-ui-contract.mdc` e as regras aplicáveis.
2. Compare os fluxos create/edit relacionados: campos, labels, máscaras, validação, feedback, botões, acessibilidade e permissões. Considere exceções de negócio existentes.
3. Use busca estática nos arquivos afetados; quando o usuário pedir verificação visual e houver ambiente disponível, inspecione também a tela renderizada. Não use dados sensíveis como amostra.
4. Relate divergências com arquivo e contexto, e diferencie evidência estática de comportamento observado. Informe verificações não executadas.

Auditoria isolada é somente leitura. Não declare conformidade global a partir de uma amostra e não modifique outros módulos sem solicitação.
