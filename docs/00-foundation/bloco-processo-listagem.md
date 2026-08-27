# Bloco de processo em listagens (modelo visual e funcional)

**Localização (foundation do repositório):** `docs/00-foundation/bloco-processo-listagem.md`

**Objetivo:** padronizar o bloco compacto de processo (número, prioridades, classe, assunto, abrir e copiar CNJ) em telas internas de listagem, com o **mesmo markup**, **mesmo CSS** e **mesmos comportamentos** (tooltip, cópia, navegação).

**Referência visual de origem:** `docs/mockups/reus-presos/block-processo.html`

---

## Arquivos do modelo (fonte da verdade)

| Camada | Arquivo | Função |
|--------|---------|--------|
| Markup | `app/views/internal/_processo_bloco_listagem.php` | Partial PHP: renderiza o bloco a partir de `$row` enriquecida. |
| Estilo | `public/assets/css/shared/processo-bloco-listagem.css` | Layout e estados visuais **escopados** em `.container-processo`. |
| Dados | `app/helpers/ProcessoBlocoListagemHelper.php` | Enriquece cada linha com chaves `bloco_processo_*` (classe, assuntos, prioridades, link abrir, placeholders). |
| Cópia CNJ | `public/assets/js/shared/copy-process-number.js` | **Único** listener global de cópia por clique (contrato abaixo). |
| Carga do script | `app/views/internal/_copy_process_script.php` | Inclui `copy-process-number.js` com `?v=filemtime` (evita cache obsoleto). |
| Toast (iframe) | `app/views/internal/_toast_script.php` | `toast.js` + `toast-helper.js` (`showToast`). Ver `docs/00-foundation/sistema-toast.md`. |
| Navegação UI | `public/assets/js/shared/navigate-buttons.js` | Abrir processo via `data-nav-href` em elemento com `role="button"`. |

**Exemplo de uso em listagem:** `app/views/internal/cartorio-cartas-expedidas-list.php` (include do partial + CSS + scripts no final do `body`).

---

## Contrato de dados (`$row` esperada pelo partial)

O partial assume que a linha já foi passada por `ProcessoBlocoListagemHelper::appendBlocoData($connection, $rows)` **antes** do `include`, ou que `$row` contenha manualmente as mesmas chaves.

| Chave | Tipo | Uso |
|-------|------|-----|
| `numero_processo` | string | Exibição no topo e valor copiado (CNJ). |
| `bloco_processo_classe_texto` | string | Texto da classe na primeira linha do rodapé. |
| `bloco_processo_classe_not` | bool | Se verdadeiro: estilo placeholder (`.not`), **sem** tooltip de texto completo. |
| `bloco_processo_assunto_texto` | string | Texto dos assuntos (`codigo - descricao` concatenados por `; `) ou placeholder controlado pelo helper. |
| `bloco_processo_assunto_not` | bool | Idem classe: placeholder sem tooltip dinâmico. |
| `bloco_processo_polo_passivo_texto` | string | Resumo do polo passivo no modelo `full`: primeiro nome; se houver dois, `NOME e outro`; se houver mais de dois, `NOME e outros (N)` com a quantidade restante. |
| `bloco_processo_prioridades` | lista | Cada item: `codigo`, `label`, `icon` (URL do ícone). Violência doméstica, criança/adolescente, idoso (incl. 80+), pessoa com deficiência e pessoa com doença grave são **agrupados** em um único ícone de alerta (giroflex), com `label` concatenado (`; `) no tooltip; **sigiloso** e **réu preso** permanecem ícones separados. |
| `bloco_processo_abrir_href` | string | URL para `data-nav-href` quando abrir habilitado. |
| `bloco_processo_abrir_disabled` | bool | Desabilita ação abrir e mostra estado inerte. |

**Resolução de processo no helper:** usa `processo_id` na linha e/ou número CNJ normalizado (20 dígitos) para localizar o registro e derivar classe, assuntos (`processo_assuntos` + `assuntos`), prioridades e link de edição.

---

## Estrutura visual (DOM)

Raiz obrigatória:

- `.container-processo` — escopo de **todo** o CSS do bloco; evita vazar estilo para a tabela global.

Subáreas:

1. **`.main-processo`** — número do processo (`.numero`) + faixa de **prioridades** (`.prioridades`, ícones pequenos alinhados à direita).
2. **`.footer-processo`** — duas colunas:
   - **Esquerda:** `.processo-classe` e `.processo-assunto` (texto truncado com elipse no filho `.processo-bloco-text-ellipsis`).
   - **Direita:** `.buttons` com ações mini (abrir / copiar).

**Importante:** o texto truncado com `overflow: hidden` fica no **`span.processo-bloco-text-ellipsis`**, não no elemento que possui o pseudo-elemento do tooltip (`.processo-bloco-tooltip`). Caso contrário, o `::after` do tooltip é **recortado** pelo `overflow` do pai.

---

## Largura da coluna (padrão único)

- Envolva o partial sempre com **`class="col width-500 col-process"`** (tokens de `public/assets/css/shared/form.css`). Não usar `width-400` ou outras larguras para esta coluna: o bloco fica com largura visual diferente das demais listagens.
- Em `processo-bloco-listagem.css`, a regra **`.col.width-500.col-process { flex-shrink: 0; }`** evita que a coluna encolha quando a linha usa `flex-wrap` (ex.: painel em tempo real).

---

## Tooltips (contrato único)

**Mecanismo:** CSS em `processo-bloco-listagem.css` — classe `.processo-bloco-tooltip` + atributo **`data-title`**; conteúdo exibido via `::after` no hover.

**Regras obrigatórias:**

1. **Não duplicar** a mesma informação em `title` e `data-title` no mesmo elemento. Use **apenas** `data-title` para o tooltip customizado deste bloco.
2. Estados placeholder (`.not`): **não** adicionar `data-title` com o texto completo “falso”; o produto definiu sem tooltip nesses casos.
3. Prioridades: `data-title` = rótulo completo; `aria-label` igual ao rótulo quando for só ícone (`role="img"`).
4. Botões mini do rodapé: `data-title` fixo (“Abrir processo”, “Copiar número do processo”, etc.) + `aria-label` idêntico ao `data-title` quando não houver texto visível além do ícone.

---

## Cópia do número do processo (comportamento único)

**Script único:** `copy-process-number.js` — um único `document.addEventListener('click', …)` no projeto para esta interação.

**Contrato de markup para o botão “Copiar” no bloco:**

- O alvo de clique deve ser um elemento com classes **`process-info`** (container) e **`processo-bloco-tooltip--btn`** (cursor/hover do mini botão).
- Dentro dele deve existir um **`.major-info`** com o texto do CNJ a copiar. No bloco atual, esse nó usa também **`processo-bloco-copy-value`** para ficar visualmente oculto (apenas o ícone aparece), mantendo o texto disponível para cópia.
- **Não** dispare cópia ao clicar no número grande do topo (`.numero`): o número na área principal é só leitura visual; só a `div` de copiar do rodapé aciona o fluxo.

**Feedback:** o script chama `showToast` / `App.Toast` conforme ambiente (ver `sistema-toast.md`). A view deve incluir `_toast_script.php` no `head` quando houver feedback por toast.

**Cache:** incluir sempre via `_copy_process_script.php` (versionamento por `filemtime`).

---

## Abrir processo (navegação)

- Elemento com `role="button"`, **`data-nav-href`** com URL interna (ex.: `/frame.php?page=consultaprocessosedit&id=…`).
- **Não** usar `<a href>` para essa ação de UI; o script `navigate-buttons.js` trata o clique.
- Estado desabilitado: `aria-disabled="true"`, `tabindex="-1"`, classe `processo-bloco-mini-disabled`, sem `data-nav-href`.

---

## Variantes do partial (uso opcional)

O partial `_processo_bloco_listagem.php` aceita uma variável **opcional** no escopo do `include`:

| Variável | Valor | Efeito |
|----------|-------|--------|
| `$processo_bloco_variant` | `'estudantes'` | (1) Oculta o rodapé direito do bloco — sem botão "Abrir processo" e sem botão "Copiar número". (2) Quando `$row['processo_sigiloso']` for verdadeiro, mascara o número do processo via `ProcessoBlocoListagemHelper::mascararNumeroProcessoSigiloso()`, preservando ano/segmento/tribunal/unidade (`*******-**.AAAA.J.TR.OOOO`). (3) No modelo `full`, mascara o nome do polo passivo com iniciais, ignorando partículas como `de`, `da`, `do`, `dos`, `das` e `e`. |

**Regras:**

- Apenas telas voltadas a estudantes (`sala-audiencias-pauta-list-estudantes.php`, `sala-audiencias-painel-estudantes.php`, `sala-audiencias-atas-estudantes.php`) definem `$processo_bloco_variant = 'estudantes';` **uma única vez antes do `foreach`** que inclui o partial.
- A variável é "sticky": após definida, vale para todos os `include` no mesmo escopo. Demais views **não** devem definir essa variável.
- A máscara é aplicada também na exportação de `estudapauta` em `InternalListExportHandlers::salaPautaEstudantes()`, mantendo coerência entre tela e download.
- O fluxo de cópia/abrir continua disponível para as demais listagens (cartório, gabinete, sala-mídias, pauta cartorial), pois elas não definem a variante.

---

## Rodapé: visibilidade dos mini botões (hover da linha)

O CSS assume que cada **linha** da lista tem um ancestral com **`.register[data-midia-id]`** (ex.: sala de mídias). Enquanto o ponteiro ou foco estiver na linha:

- `.buttons` passa a `opacity: 1`, `visibility: visible`, `pointer-events: auto`.

**Touch:** media query `(hover: none) and (pointer: coarse)` mantém os botões utilizáveis sem depender de `:hover` estável.

**Atenção:** se outra listagem reutilizar o bloco **sem** `.register[data-midia-id]` na hierarquia, é preciso **replicar o mesmo padrão de seletor** na página (ou ajustar o CSS com um segundo seletor documentado na própria listagem).

---

## Como implementar em uma nova página (checklist)

1. **Serviço / controller:** após carregar as linhas do banco, chamar  
   `ProcessoBlocoListagemHelper::appendBlocoData($pdo, $rows)`  
   e iterar `$rows` já enriquecidas na view.
2. **View (head):**
   - `include` de `_internal_head_styles.php` (já padrão nas internas);
   - link para `processo-bloco-listagem.css` com `?v=filemtime(...)`;
   - `include` de `_toast_script.php` se a página usar toast a partir de JS compartilhado.
3. **View (linha da tabela):** envolver o partial em  
   `<div class="col width-500 col-process">` … `</div>`  
   e depois `<?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>`  
   com `$row` definido no escopo do `foreach`.
4. **View (final do `body`):**  
   - `include` de `_copy_process_script.php`;  
   - demais scripts da página **depois**, se houver ordem sensível a eventos.
5. **Layout da linha:** garantir wrapper de linha compatível com o seletor de hover dos botões (ver secção anterior).
6. **QA rápido:** abrir, copiar (só pelo ícone), tooltips em classe/assunto com texto longo, prioridades, estados desabilitados, teclado (`Tab` + `Enter` nos `role="button"` conforme suporte do `navigate-buttons`).

---

## Alinhamento com regras de produto do repositório

- **Botões / UI:** contrato em `.cursor/rules/ui-buttons-contract.mdc` e `public/templates/component-buttons.html`. Neste bloco, os mini controles do mockup usam **`div.btn-mini`** com `role="button"` e `data-nav-href` onde couber — não misturar com variantes `btn btn-primary` do formulário.
- **Toast:** `.cursor/rules/toast-system.mdc` — não usar `alert()` para sucesso de cópia; o fluxo já usa toast via `copy-process-number.js`.
- **Navegação:** preferir `data-nav-href` a `<a href>` para ações de interface; ver `.cursor/rules/ui-prefer-button-over-anchor.mdc` quando aplicável.

---

## Histórico e evolução

- O modelo substitui duplicação de markup de processo em listagens pontuais, centralizando em um partial + um CSS escopado.
- Qualquer alteração visual deve ser feita **primeiro** no mockup de referência e depois refletida em `processo-bloco-listagem.css` e `_processo_bloco_listagem.php`, mantendo este documento atualizado se o contrato mudar.
