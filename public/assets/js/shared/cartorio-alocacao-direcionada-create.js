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

        function renderChip(id, label) {
            const wrap = global.document.createElement('span');
            wrap.className = 'display-flex row row-fit align-center gap-y-sm';
            wrap.style.marginRight = '8px';
            wrap.style.marginBottom = '6px';
            wrap.dataset.userId = id;
            const span = global.document.createElement('span');
            span.className = 'status status-pending padding-x-sm';
            span.textContent = label;
            span.title = 'Alocação direta: ' + label;
            const rm = global.document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-secondary-outline height-sm';
            rm.setAttribute('aria-label', 'Remover ' + label);
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
