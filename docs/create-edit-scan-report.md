# Scan de Conformidade Create/Edit

## Escopo varrido

- `app/views/internal/*-create.php`
- `app/views/internal/*-edit.php`

## Itens auditados

- Placeholders por tipo de campo.
- Uso de feedback inline vs `showToast`.
- Consistencia create/edit para campos equivalentes.
- Sinais de contrato em layout e componentes de formulario.

## Divergencias identificadas e tratadas

1. **CNJ/processo** com placeholder textual em alguns creates.
   - Ajustado para `0000000-00.0000.0.00.0000`.
2. **Nome completo** com placeholder heterogeneo.
   - Ajustado para `Digite o nome completo` nos campos equivalentes.
3. **CPF** com placeholder `Somente numeros` em campos equivalentes.
   - Ajustado para `000.000.000-00`.
4. **Telefone** com placeholder textual generico.
   - Ajustado para `(00) 00000-0000` em campos equivalentes.
5. **Fallback de feedback inline** em edit.
   - Removido fallback `cadastro-feedback/innerHTML`; mantido apenas `showToast`.
6. **Labels inline de feedback** em edit.
   - Removidas labels `id="cadastro-feedback"` remanescentes.

## Pendencias conhecidas

- `cnpj` permanece com placeholder `Somente numeros` por nao fazer parte do contrato minimo definido pelo usuario (CPF/CNJ/telefone/nome completo), podendo ser padronizado em lote futuro.

## Status

- Contrato base aplicado nos casos de maior impacto e com equivalencia create/edit para os tipos priorizados.
