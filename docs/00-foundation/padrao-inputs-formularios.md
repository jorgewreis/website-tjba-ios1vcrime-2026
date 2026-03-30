# Padrão de inputs em formulários (correção automática)

## Objetivo

Garantir **correção automática** e **formatação padronizada** em campos de número de processo, nome de pessoa e nome de cidade em todas as páginas do sistema.

## Indicação de obrigatoriedade e campos automatizados nos labels

Em formulários internos (create e edit), os labels devem indicar de forma clara:

- **Campos obrigatórios:** incluir ao lado do texto do label um indicador em vermelho (cor danger): `<span class="label-hint-obrigatorio">*obrigatório</span>`. Classe definida em `assets/css/shared/form.css` (cor: `var(--danger-color)`).
- **Campos automatizados:** quando o valor é preenchido automaticamente (ex.: DataJud para Classe), incluir ao lado do label: `<span class="label-hint-automatizado">*automatizado</span>`. Classe em `form.css` (cor: `var(--primary-color)`).

O texto informativo (*obrigatório / *automatizado) deve ser curto e em fonte reduzida (`font-size: 0.75rem`) para não ultrapassar a largura do input ou select. Exemplo:

```html
<label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
<label for="filter-classe" id="processo-ap-info">Classe <span class="label-hint-automatizado">*automatizado</span></label>
```

Páginas que já seguem o padrão: Guias (create/edit), Cartas Expedidas (create/edit), Cartas Recebidas (create/edit), Cejusc (create/edit).

## Regras por tipo de campo

| Tipo de dado           | Script JS (fonte) | Comportamento | Quando aplicar |
|------------------------|-------------------|---------------|----------------|
| **Número do processo** | `modules/datajud/cnj.js` | Formatação CNJ (completa 20 dígitos, máscara, validação de DV). No blur: completa sufixo se 11 dígitos; formata se 20 dígitos válidos; limpa se inválido. | Incluir script na página; id sugerido `filter-processo` ou `id*="processo"`. |
| **Nome de pessoa**     | **`/assets/js/shared/text-formatter.js`** (único; espelha `app/helpers/TextFormatter.php`) | `TextFormatter.formatProperName(value, maxLength)` — artigos/preposições em minúsculas em qualquer posição (de, da, do, das, dos, e, em, no, na). | Registrar `blur` no input; usar no submit. |
| **Nome de cidade**     | Mesmo script compartilhado | Idem (`formatProperName`). | Registrar `blur` no input; usar no submit. |

## Implementação recomendada

1. **Incluir os scripts** na view (antes dos scripts inline):
   - Para número de processo: `<script src="/assets/js/modules/datajud/cnj.js"></script>`
   - Quando a tela também preenche **classe via DataJud** (após CNJ válido), na ordem: `cnj.js` → `datajud-cycle-helper.js` → `datajud-api.js` (ver `public/assets/js/modules/datajud/README.md`).
   - Para nome/cidade: `<script src="/assets/js/shared/text-formatter.js"></script>`

2. **Blur nos inputs**: em cada campo que recebe correção, registrar `addEventListener('blur', ...)` e aplicar a função (ex.: `TextFormatter.formatProperName(...)` no valor).

3. **Antes do submit**: no handler do botão de enviar, disparar `input.blur()` nos campos de processo, nome e cidade **antes** de ler os valores, para que a correção seja aplicada mesmo se o usuário não sair do campo.

## Páginas que já seguem o padrão

- **cartorio-cejusc-create.php**: processo (cnj.js) + nome do réu (text-formatter); blur antes do submit.
- **cartorio-cartas-recebidas-create.php**: processo (cnj.js) + cidade (text-formatter); blur antes do submit.
- **cartorio-cartas-expedidas-create.php**: processo (cnj.js) + cidade (text-formatter); blur antes do submit.

## Novas páginas

Ao criar novas telas com campos de **número do processo**, **nome de pessoa** ou **cidade**, replicar o mesmo padrão (scripts + blur + blur antes do submit).
