# Padrão de inputs em formulários (correção automática)

## Objetivo

Garantir **correção automática** e **formatação padronizada** em campos de número de processo, nome de pessoa e nome de cidade em todas as páginas do sistema.

## Regras por tipo de campo

| Tipo de dado           | Script JS (fonte) | Comportamento | Quando aplicar |
|------------------------|-------------------|---------------|----------------|
| **Número do processo** | `cartorio/cartas-expedidas/cnj.js` | Formatação CNJ (completa 20 dígitos, máscara, validação de DV). No blur: completa sufixo se 11 dígitos; formata se 20 dígitos válidos; limpa se inválido. | Incluir script na página; id sugerido `filter-processo` ou `id*="processo"`. |
| **Nome de pessoa**     | `cartorio/cartas-expedidas/text-formatter.js` ou `cartorio/cartas-recebidas/text-formatter.js` | `TextFormatter.formatProperName(value, maxLength)` — maiúsculas/minúsculas (exceções: de, da, do, das, dos, e, em, no, na). | Registrar `blur` no input; usar no submit. |
| **Nome de cidade**     | Mesmo `text-formatter.js` | Idem nome de pessoa (`formatProperName`). | Registrar `blur` no input; usar no submit. |

## Implementação recomendada

1. **Incluir os scripts** na view (antes dos scripts inline):
   - Para número de processo: `<script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>`
   - Para nome/cidade: `<script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>`

2. **Blur nos inputs**: em cada campo que recebe correção, registrar `addEventListener('blur', ...)` e aplicar a função (ex.: `TextFormatter.formatProperName(...)` no valor).

3. **Antes do submit**: no handler do botão de enviar, disparar `input.blur()` nos campos de processo, nome e cidade **antes** de ler os valores, para que a correção seja aplicada mesmo se o usuário não sair do campo.

## Páginas que já seguem o padrão

- **cartorio-cejusc-create.php**: processo (cnj.js) + nome do réu (text-formatter); blur antes do submit.
- **cartorio-cartas-recebidas-create.php**: processo (cnj.js) + cidade (text-formatter); blur antes do submit.
- **cartorio-cartas-expedidas-create.php**: processo (cnj.js) + cidade (text-formatter); blur antes do submit.

## Novas páginas

Ao criar novas telas com campos de **número do processo**, **nome de pessoa** ou **cidade**, replicar o mesmo padrão (scripts + blur + blur antes do submit).
