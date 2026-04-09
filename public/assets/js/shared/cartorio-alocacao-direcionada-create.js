(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    const MAX_IDS = 5;

    /**
     * @param {string} prefix ex.: "guias" → ids guias-alocar-usuario-select
     * @returns {{ getIds: function(): string[], clear: function(): void }}
     */
    function init(prefix) {
        const p = String(prefix || '').replace(/[^a-z0-9_-]/gi, '');
        if (!p) {
            return {
                getIds: function () { return []; },
                clear: function () {}
            };
        }

        const sel = global.document.getElementById(p + '-alocar-usuario-select');
        const chips = global.document.getElementById(p + '-alocar-chips');
        const btn = global.document.getElementById(p + '-alocar-adicionar');
        if (!sel || !chips || !btn) {
            return {
                getIds: function () { return []; },
                clear: function () {}
            };
        }

        const ids = [];

        function initialsFromLabel(label) {
            const words = String(label || '').trim().split(/\s+/).filter(Boolean);
            if (words.length === 0) {
                return '—';
            }
            if (words.length === 1) {
                return words[0].slice(0, 2).toUpperCase();
            }
            return (words[0][0] + words[words.length - 1][0]).toUpperCase();
        }

        function renderChip(id, label) {
            const titleText = 'Alocação direta: ' + label + ' - incluído manualmente por alocação direcionada';
            const wrap = global.document.createElement('span');
            wrap.className = 'user-allocate-wrap';
            wrap.dataset.userId = id;

            const span = global.document.createElement('span');
            span.className = 'user-allocate specific';
            span.textContent = initialsFromLabel(label);
            span.title = titleText;
            span.setAttribute('aria-label', titleText);

            const rm = global.document.createElement('button');
            rm.type = 'button';
            rm.className = 'user-allocate-remove';
            rm.setAttribute('aria-label', 'Remover alocação de ' + label);
            rm.title = 'Remover alocação';
            rm.textContent = '×';
            rm.addEventListener('click', function () {
                const ix = ids.indexOf(id);
                if (ix !== -1) {
                    ids.splice(ix, 1);
                }
                wrap.remove();
            });
            wrap.appendChild(span);
            wrap.appendChild(rm);
            chips.appendChild(wrap);
        }

        btn.addEventListener('click', function () {
            const id = (sel.value || '').trim();
            if (!id) {
                return;
            }
            if (ids.indexOf(id) !== -1) {
                return;
            }
            if (ids.length >= MAX_IDS) {
                return;
            }
            const opt = sel.options[sel.selectedIndex];
            const label = opt ? opt.textContent : id;
            ids.push(id);
            renderChip(id, label);
            sel.selectedIndex = 0;
        });

        return {
            getIds: function () {
                return ids.slice();
            },
            clear: function () {
                ids.length = 0;
                chips.innerHTML = '';
                sel.selectedIndex = 0;
            }
        };
    }

    App.CartorioAlocacaoDirecionadaCreate = {
        init: init
    };
})(window);
