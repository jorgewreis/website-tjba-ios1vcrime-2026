(function (global) {
    'use strict';

    const card = document.getElementById('dashboard-metas-cartorio-card');
    if (!card) {
        return;
    }

    const elAno = document.getElementById('dashboard-metas-ano');
    const elComarca = document.getElementById('dashboard-metas-comarca');
    const elVara = document.getElementById('dashboard-metas-vara');
    const elTable = document.getElementById('dashboard-metas-table');

    const META_LABELS = {
        meta1: 'Meta 1',
        meta2_1Grau: 'Meta 2 - 1º Grau',
        meta2_2Grau: 'Meta 2 - 2º Grau',
        meta2Jeetr: 'Meta 2 - Jeetr',
        meta2Antigos: 'Meta 2 - Antigos',
        meta4: 'Meta 4',
        meta6: 'Meta 6',
        meta7Indigenas: 'Meta 7 - Indígenas',
        meta7Quilombolas: 'Meta 7 - Quilombolas',
        meta8Vd: 'Meta 8 - Violência Doméstica',
        meta8Feminicidio: 'Meta 8 - Feminicídio',
        meta10_1Grau: 'Meta 10 - 1º Grau',
        meta10_2Grau: 'Meta 10 - 2º Grau'
    };

    function escapeHtml(value) {
        const App = global.App || {};
        if (App.Page && typeof App.Page.escapeHtml === 'function') {
            return App.Page.escapeHtml(value);
        }
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function notifyError(message) {
        const App = global.App || {};
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify('error', 'Erro', message);
        } else if (typeof global.showToast === 'function') {
            global.showToast({ type: 'error', title: 'Erro', message: message });
        }
    }

    function renderRows(metas) {
        if (!elTable) {
            return;
        }
        const entries = Object.keys(META_LABELS).map(function (key) {
            const raw = metas && Object.prototype.hasOwnProperty.call(metas, key) ? metas[key] : null;
            if (raw == null || String(raw).trim() === '') {
                return '';
            }
            const display = String(raw);
            return (
                '<div class="dashboard-metas-row">' +
                '<span class="meta-name">' + escapeHtml(META_LABELS[key]) + '</span>' +
                '<span class="meta-value">' + escapeHtml(display) + '</span>' +
                '</div>'
            );
        }).filter(Boolean);

        if (entries.length === 0) {
            elTable.innerHTML = (
                '<div class="dashboard-metas-row">' +
                '<span class="meta-name">Sem metas com valor disponível</span>' +
                '<span class="meta-value">-</span>' +
                '</div>'
            );
            return;
        }

        elTable.innerHTML = entries.join('');
    }

    function renderHeader(data) {
        if (elAno) {
            elAno.textContent = data.ano ? ('Referência ' + data.ano) : 'Referência não informada';
        }
        if (elComarca) {
            const comarcaNome = data.comarca || '-';
            const codComarca = data.codCnjComarca || '-';
            elComarca.textContent = codComarca + ' - ' + comarcaNome;
        }
        if (elVara) {
            const varaNome = data.vara || '-';
            const codUnidade = data.codCnjUnidade || '-';
            elVara.textContent = codUnidade + ' - ' + varaNome;
        }
    }

    function renderErrorState() {
        if (!elAno || !elComarca || !elVara || !elTable) {
            return;
        }
        elAno.textContent = 'Referência indisponível';
        elComarca.textContent = '-';
        elVara.textContent = '-';
        elTable.innerHTML = (
            '<div class="dashboard-metas-row">' +
            '<span class="meta-name">Não foi possível carregar as metas</span>' +
            '<span class="meta-value">-</span>' +
            '</div>'
        );
    }

    fetch('/api/tjba/metas-cartorio.php', { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (payload) {
            if (!payload || payload.error) {
                throw new Error(payload && payload.error ? payload.error : 'Falha ao obter dados de metas.');
            }
            renderHeader(payload);
            renderRows(payload.metas || {});
        })
        .catch(function (error) {
            renderErrorState();
            notifyError('Não foi possível carregar as metas do cartório.');
            global.console.error('[dashboard-metas-cartorio]', error);
        });
})(window);
