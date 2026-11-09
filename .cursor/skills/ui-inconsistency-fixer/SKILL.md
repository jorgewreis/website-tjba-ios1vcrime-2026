---
name: ui-inconsistency-fixer
description: Corrige inconsistências de formulários create/edit quando o usuário solicita ajustes em telas do sistema PHP, preservando os contratos e regras de negócio.
---

# Correção de formulários

1. Identifique as telas solicitadas, as telas relacionadas e o comportamento atual. Consulte `docs/create-edit-ui-contract.md` e as regras aplicáveis em `.cursor/rules/`.
2. Corrija as inconsistências pertinentes ao pedido em placeholders, feedback, atributos, validação e estados de botões. Reutilize componentes e estilos existentes.
3. Preserve exceções funcionais e permissões; não remova feedback acessível ou altere regras de negócio apenas para uniformizar aparência. Não amplie o diff a todos os formulários.
4. Verifique os fluxos afetados quando solicitado e possível; relate alterações, riscos e verificações não executadas. Não declare resultado validado sem evidência.
