# Contrato Global de Padronizacao (`*-create.php` e `*-edit.php`)

## Campos padrao

| Tipo | Placeholder | Regras |
|---|---|---|
| `cpf` | `000.000.000-00` | mascara + validacao front/back |
| `processo_cnj` | `0000000-00.0000.0.00.0000` | mascara + validacao CNJ |
| `telefone` | `(00) 00000-0000` | mascara + sanitizacao no submit |
| `nome_completo` | `Digite o nome completo` | normalizacao de nome proprio |
| `classe_processual_readonly` | `Preenchido automaticamente pelo DataJud` | readonly/disabled quando origem automatica |

## Layout e atributos

- Reutilizar somente classes existentes.
- Estrutura padrao: `container-full card-page`.
- Blocos internos com `display-flex row row-full ...`.
- Controles com `height-md` e largura por `width-*` conforme campo equivalente.
- Campos equivalentes entre create/edit devem manter o mesmo contrato de `id/name`, label e placeholder.

## Toasts e feedback

- Feedback de sucesso/erro/info deve ser exclusivamente via `showToast`.
- Nao usar `label/div` inline para exibir status de submit.

## Botoes

- `Voltar`: sempre habilitado.
- `Salvar/Cadastrar`: habilitar apenas com formulario valido e/ou quando houver alteracao real em edit.

## Checklist de conformidade

1. Placeholders padrao aplicados por tipo de campo.
2. Validacoes front/back alinhadas por tipo.
3. Toasters exclusivos para feedback.
4. Estrutura/layout aderente ao padrao comum.
5. Create e edit equivalentes para mesmos componentes.
