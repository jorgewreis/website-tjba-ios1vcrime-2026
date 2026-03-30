# Sistema de Toast (notificações)

**Objetivo:** documentar o comportamento único do feedback não bloqueante no produto (substitui `alert()` para retorno de operações).

**Implementação:** `public/assets/js/shared/toast.js` (`App.Toast`), `public/assets/js/shared/toast-helper.js` (`showToast` em iframes), estilos em `public/assets/css/shared/toast.css`.

**Regra de produto (Cursor):** `.cursor/rules/toast-system.mdc` (obrigatória no repositório).

---

## Dois modelos visuais

| Modelo | Classe CSS | Uso |
|--------|------------|-----|
| **Simple** | `.toast` | Mensagem + barra de tempo regressiva (sem barra de título). |
| **Full** | `.toast-full` | Barra de título + botão fechar + corpo (+ barra de tempo no rodapé). |

A escolha **não depende** de passar `variant: 'full'` em todo lugar: o motor aplica política automática (ver abaixo). Use `forceVariant` só em caso excepcional.

---

## Política automática (`resolveVariant` em `toast.js`)

1. **`type: 'success'`** → em geral **simple** (duração mais curta). Se a mensagem for **longa ou complexa** (muitos caracteres, quebras de linha, listas) → **full**.
2. **`type: 'error'`** → sempre **full** (barra de título para leitura de falhas).
3. **`type: 'info'`** → **simple** se mensagem curta; **full** se longa/complexa.

Parâmetros opcionais:

- `forceVariant: 'full' | 'simple'` — força o modelo (evitar salvo necessidade).
- `critical: true` ou `blocking: true` (com `type: 'error'`) — erro crítico (`aria-live="assertive"`).

`title` é usado principalmente no modelo **full**; pode ser omitido em sucesso simples.

---

## Duração

- **Sucesso + simple:** tempo reduzido (faixa típica ~1,8–3,2 s), proporcional ao tamanho da mensagem com teto baixo.
- **Full / erro:** duração maior; erros têm multiplicador adicional.

Pausa no **hover**; deduplicação de mensagens iguais em janela de poucos segundos (contador `xN`).

---

## Layout, empilhamento e animação

- **Container:** canto inferior direito (`#toast-container`), `flex-direction: column`, `justify-content: flex-end`, `gap` entre itens (sem sobreposição).
- **Ordem:** itens mais antigos acima; o mais recente fica **mais próximo da base** da tela (empilhamento para cima).
- **Entrada:** animação **de baixo** (`translateY`).
- **Saída:** desliza para a **direita** (`translateX`).
- Máximo de toasts visíveis simultâneos definido em `toast.js` (fila para o excedente).

---

## Onde chamar

| Contexto | API |
|----------|-----|
| Página no shell (ex.: `home.php`) | `App.Toast.show({ ... })` |
| View interna em iframe (`frame.php`) | Incluir `_toast_script.php` e usar `showToast({ ... })` (postMessage para o parent). |

Exemplos mínimos:

```javascript
App.Toast.show({ type: 'success', message: 'Salvo com sucesso.' });
App.Toast.show({ type: 'error', title: 'Erro', message: 'Não foi possível concluir a operação.' });
showToast({ type: 'info', message: 'Processando…' });
```

Não é necessário (nem recomendado) repetir `variant: 'full'` em todo `showToast` para erro: a política já define **full** para erro.

---

## Histórico de documentação

Relatórios antigos de padronização de telas (ex.: `docs/edit-pages-layout-toast-standardization-report.md`) podem citar `variant: 'full'` como padrão de escrita na época; o comportamento atual é o descrito neste documento e em `toast.js`.

---

## Referências

- `.cursor/rules/toast-system.mdc`
- `.cursor/rules/destructive-actions-confirmation.mdc` (confirmação + toast `info`)
- `docs/create-edit-ui-contract.md` (contrato create/edit)
