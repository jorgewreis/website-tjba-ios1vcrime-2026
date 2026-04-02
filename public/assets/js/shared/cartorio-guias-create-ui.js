(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const bootstrap = App.CartorioCreateBootstrap || null;

    function initialize(options) {
        const settings = options || {};
        const processoInput = global.document.getElementById('filter-processo');
        const denunciadoSelect = global.document.getElementById('filter-denunciado-select');
        const denunciadoInput = global.document.getElementById('filter-denunciado');
        const dataFato = global.document.getElementById('filter-data-fato');

        if (bootstrap) {
            bootstrap.bindBackButton({ href: settings.backHref || '/frame.php?page=guias' });
            bootstrap.setupRuleButton({ targetPage: settings.ruleTargetPage || 'guiasrules' });
            bootstrap.bindClasseResetOnShortProcess({
                onReset: function () {
                    showDenunciadoInput();
                }
            });
        }

        if (dataFato) {
            const today = new Date();
            today.setDate(today.getDate() - 1);
            dataFato.setAttribute('max', today.toISOString().split('T')[0]);
        }

        let lastSuggestionNome = '';

        function showDenunciadoInput() {
            if (denunciadoSelect) {
                denunciadoSelect.style.display = 'none';
                denunciadoSelect.innerHTML = '';
                denunciadoSelect.value = '';
            }
            if (denunciadoInput) {
                denunciadoInput.style.display = '';
                denunciadoInput.required = true;
            }
        }

        function showDenunciadoSelect(reus) {
            if (!denunciadoSelect || !denunciadoInput) {
                return;
            }
            denunciadoSelect.innerHTML = '';
            reus.forEach(function (reu) {
                const option = global.document.createElement('option');
                option.value = reu.id;
                option.textContent = reu.nome;
                denunciadoSelect.appendChild(option);
            });
            const outro = global.document.createElement('option');
            outro.value = 'outro';
            outro.textContent = 'Outro';
            denunciadoSelect.appendChild(outro);
            denunciadoSelect.style.display = '';
            denunciadoInput.style.display = 'none';
            denunciadoInput.required = false;
            denunciadoInput.value = '';
        }

        function formatCpfDisplay(cpf) {
            const digits = String(cpf || '').replace(/\D/g, '');
            if (digits.length !== 11) {
                return cpf || '';
            }
            return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
        }

        function showDenunciadoSelectSugestoes(pessoas, nomeDigitado) {
            if (!denunciadoSelect || !denunciadoInput) {
                return;
            }

            denunciadoSelect.innerHTML = '';
            pessoas.forEach(function (pessoa) {
                const option = global.document.createElement('option');
                option.value = pessoa.id;
                let label = String(pessoa.nome_completo || '').trim();
                if (String(pessoa.cpf || '').replace(/\D/g, '').length === 11) {
                    label += ' - ' + formatCpfDisplay(pessoa.cpf);
                }
                option.textContent = label || pessoa.id;
                denunciadoSelect.appendChild(option);
            });

            const novo = global.document.createElement('option');
            novo.value = '__novo__';
            novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
            denunciadoSelect.appendChild(novo);
            lastSuggestionNome = nomeDigitado || '';
            denunciadoSelect.style.display = '';
            denunciadoInput.style.display = 'none';
            denunciadoInput.required = false;
        }

        function carregarReusDoProcesso(digits) {
            fetch(settings.reusEndpoint || '/api/cartorio-guias/reus-por-processo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ numero: digits }),
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const reus = data && data.reus ? data.reus : [];
                    if (reus.length > 0) {
                        showDenunciadoSelect(reus);
                    } else {
                        showDenunciadoInput();
                    }
                })
                .catch(function () {
                    showDenunciadoInput();
                });
        }

        global.addEventListener('cnjValidated', function (event) {
            const digits = event.detail && event.detail.digits ? String(event.detail.digits).replace(/\D/g, '') : '';
            if (digits.length !== 20) {
                showDenunciadoInput();
                return;
            }
            carregarReusDoProcesso(digits);
        });

        if (denunciadoSelect) {
            denunciadoSelect.addEventListener('change', function () {
                if (this.value === 'outro') {
                    this.style.display = 'none';
                    if (denunciadoInput) {
                        denunciadoInput.style.display = '';
                        denunciadoInput.required = true;
                        denunciadoInput.focus();
                    }
                } else if (this.value === '__novo__') {
                    denunciadoSelect.style.display = 'none';
                    denunciadoSelect.innerHTML = '';
                    denunciadoSelect.value = '';
                    if (denunciadoInput) {
                        denunciadoInput.value = lastSuggestionNome;
                        denunciadoInput.style.display = '';
                        denunciadoInput.required = true;
                        denunciadoInput.focus();
                    }
                }
            });
        }

        if (denunciadoInput) {
            denunciadoInput.addEventListener('blur', function () {
                let nome = String(denunciadoInput.value || '').trim();
                if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                    nome = App.ProperNameHelper.formatProper(nome, 255);
                    denunciadoInput.value = nome;
                } else if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                    nome = global.TextFormatter.formatProperName(nome, 255);
                    denunciadoInput.value = nome;
                }
                if (nome.length < 2) {
                    return;
                }
                if (denunciadoSelect && denunciadoSelect.style.display !== 'none' && denunciadoSelect.value && denunciadoSelect.value !== 'outro' && denunciadoSelect.value !== '__novo__') {
                    return;
                }
                fetch((settings.peopleSearchEndpoint || '/api/pessoas/search-by-name.php?nome=') + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        const pessoas = data && data.pessoas ? data.pessoas : [];
                        if (pessoas.length > 0) {
                            showDenunciadoSelectSugestoes(pessoas, nome);
                        }
                    })
                    .catch(function () {
                        // noop
                    });
            });
        }

        if (processoInput) {
            processoInput.addEventListener('input', function () {
                const digits = String(processoInput.value || '').replace(/\D/g, '');
                if (digits.length !== 20) {
                    showDenunciadoInput();
                }
            });
        }
    }

    App.CartorioGuiasCreateUI = {
        initialize: initialize
    };
})(window);
