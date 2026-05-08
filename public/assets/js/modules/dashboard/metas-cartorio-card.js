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
        meta4: 'Meta 4',
        meta6: 'Meta 6',
        meta7Indigenas: 'Meta 7 - Indígenas',
        meta7Quilombolas: 'Meta 7 - Quilombolas',
        meta8Vd: 'Meta 8 - Violência Doméstica',
        meta8Feminicidio: 'Meta 8 - Feminicídio',
        meta10_1Grau: 'Meta 10 - 1º Grau',
        meta10_2Grau: 'Meta 10 - 2º Grau'
    };
    const META_RULES = {
        meta1: {
            target: '>= 100%',
            targetValue: 100,
            criterio: 'julgar mais que os distribuídos (20/12/2025 a 19/12/2026)',
            formula: '(julgados / (casos novos + dessobrestados - suspensos)) x 100'
        },
        meta2_1Grau: {
            target: '80%',
            targetValue: 80,
            criterio: 'processos distribuídos até 31/12/2022 (1º grau)',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/8)'
        },
        meta2_2Grau: {
            target: '90%',
            targetValue: 90,
            criterio: 'processos distribuídos até 31/12/2023 (2º grau)',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/9)'
        },
        meta2Jeetr: {
            target: '95%',
            targetValue: 95,
            criterio: 'JE/TR distribuídos até 31/12/2023',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/9,5)'
        },
        meta4: {
            target: '65%',
            targetValue: 65,
            criterio: 'ações penais contra a Administração Pública distribuídas até 31/12/2022',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/6,5)'
        },
        meta6: {
            target: '50%',
            targetValue: 50,
            criterio: 'ações ambientais distribuídas até 31/12/2025',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/5)'
        },
        meta7Indigenas: {
            target: '50%',
            targetValue: 50,
            criterio: 'direitos indígenas distribuídos até 31/12/2025',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/5)'
        },
        meta7Quilombolas: {
            target: '50%',
            targetValue: 50,
            criterio: 'direitos quilombolas distribuídos até 31/12/2025',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/5)'
        },
        meta8Vd: {
            target: '90%',
            targetValue: 90,
            criterio: 'violência doméstica distribuída até 31/12/2024',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/9)'
        },
        meta8Feminicidio: {
            target: '75%',
            targetValue: 75,
            criterio: 'feminicídio distribuído até 31/12/2024',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/7,5)'
        },
        meta10_1Grau: {
            target: '90%',
            targetValue: 90,
            criterio: 'infância e juventude (1º grau) até 31/12/2024',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/9)'
        },
        meta10_2Grau: {
            target: '99%',
            targetValue: 99,
            criterio: 'infância e juventude (2º grau) até 31/12/2024',
            formula: '(julgados / (distribuídos - suspensos)) x (1000/9,9)'
        }
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

    function parsePercent(value) {
        const normalized = String(value == null ? '' : value).trim().replace(/\./g, '').replace(',', '.');
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : NaN;
    }

    function cumprimentoBadgeTone(hasNumericTarget, actualValue, targetValue, isFarFromTarget) {
        if (!Number.isFinite(actualValue)) {
            return 'badge-warning';
        }
        if (!hasNumericTarget || targetValue === undefined) {
            return 'badge-warning';
        }
        if (actualValue >= targetValue) {
            return 'badge-success';
        }
        if (isFarFromTarget) {
            return 'badge-danger';
        }
        return 'badge-warning';
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
            const actualValue = parsePercent(display);
            if (Number.isFinite(actualValue) && actualValue === 0) {
                return '';
            }
            const metaRule = META_RULES[key] || null;
            const hasNumericTarget = !!(metaRule && typeof metaRule.targetValue === 'number');
            const meetsOrExceedsTarget = !!(
                hasNumericTarget
                && Number.isFinite(actualValue)
                && actualValue >= (metaRule.targetValue)
            );
            const alvoExibicao = metaRule
                ? (meetsOrExceedsTarget ? 'ok' : metaRule.target)
                : '';
            const isFarFromTarget = !!(
                metaRule
                && typeof metaRule.targetValue === 'number'
                && Number.isFinite(actualValue)
                && (metaRule.targetValue - actualValue) > 30
            );
            const fullTitle = metaRule
                ? (
                    META_LABELS[key]
                    + ' | Alvo: '
                    + alvoExibicao
                    + ' | Cumpr.: '
                    + display
                    + '% | Critério: '
                    + metaRule.criterio
                    + ' | Fórmula: '
                    + metaRule.formula
                )
                : (META_LABELS[key] + ' | Cumpr.: ' + display + '%');
            const badgeTone = cumprimentoBadgeTone(
                hasNumericTarget,
                actualValue,
                metaRule ? metaRule.targetValue : undefined,
                isFarFromTarget
            );
            let line1Html = '';
            if (metaRule) {
                line1Html = (
                    '<div class="meta-line-1-row">' +
                    '<div class="meta-line-1-left">' +
                    '<span class="meta-line-1-meta" title="' + escapeHtml(fullTitle) + '">' + escapeHtml(META_LABELS[key]) + '</span>' +
                    '<span class="meta-line-1-alvo">' + escapeHtml('Alvo: ' + alvoExibicao) + '</span>' +
                    '</div>' +
                    '<span class="badge ' + badgeTone + ' dashboard-metas-cumpr-badge">' + escapeHtml(display + '%') + '</span>' +
                    '</div>'
                );
            } else {
                line1Html = (
                    '<div class="meta-line-1-row">' +
                    '<div class="meta-line-1-left">' +
                    '<span class="meta-line-1-meta" title="' + escapeHtml(fullTitle) + '">' + escapeHtml(META_LABELS[key]) + '</span>' +
                    '</div>' +
                    '<span class="badge ' + badgeTone + ' dashboard-metas-cumpr-badge">' + escapeHtml(display + '%') + '</span>' +
                    '</div>'
                );
            }
            return (
                '<div class="dashboard-metas-row">' +
                line1Html +
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
