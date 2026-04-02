(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const bootstrap = App.CartorioCreateBootstrap || null;

    function initialize(options) {
        const settings = options || {};
        const processoInput = global.document.getElementById('filter-processo');
        const reuSelect = global.document.getElementById('reu-select');
        const reuNome = global.document.getElementById('reu-nome');
        const reuComplementares = global.document.getElementById('reu-campos-complementares');
        const container = global.document.getElementById('vitimas-container');
        const template = global.document.getElementById('vitima-template');
        const addBtn = global.document.getElementById('add-vitima-btn');
        let lastReuSuggestionNome = '';

        if (bootstrap) {
            bootstrap.bindBackButton({ href: settings.backHref || '/frame.php?page=medidasprotetivas' });
            bootstrap.setupRuleButton({ targetPage: settings.ruleTargetPage || 'medidasprotetivasrules' });
        }

        function showReuInput() {
            if (reuSelect) {
                reuSelect.style.display = 'none';
                reuSelect.innerHTML = '';
                reuSelect.value = '';
            }
            if (reuNome) {
                reuNome.style.display = '';
                reuNome.required = true;
            }
            if (reuComplementares) {
                reuComplementares.style.display = 'none';
            }
        }

        function showReuSelect(reus) {
            if (!reuSelect || !reuNome) {
                return;
            }
            reuSelect.innerHTML = '';
            reus.forEach(function (reu) {
                const option = global.document.createElement('option');
                option.value = reu.id;
                option.textContent = reu.nome;
                reuSelect.appendChild(option);
            });
            const outro = global.document.createElement('option');
            outro.value = 'outro';
            outro.textContent = 'Outro';
            reuSelect.appendChild(outro);
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
            reuNome.value = '';
            if (reuComplementares) {
                reuComplementares.style.display = 'none';
            }
        }

        function formatCpfDisplay(cpf) {
            const digits = String(cpf || '').replace(/\D/g, '');
            if (digits.length !== 11) {
                return cpf || '';
            }
            return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
        }

        function showReuSelectSugestoes(pessoas, nomeDigitado) {
            if (!reuSelect || !reuNome) {
                return;
            }
            reuSelect.innerHTML = '';
            pessoas.forEach(function (pessoa) {
                const option = global.document.createElement('option');
                option.value = pessoa.id;
                let label = String(pessoa.nome_completo || '').trim();
                if (String(pessoa.cpf || '').replace(/\D/g, '').length === 11) {
                    label += ' - ' + formatCpfDisplay(pessoa.cpf);
                }
                option.textContent = label || pessoa.id;
                reuSelect.appendChild(option);
            });
            const novo = global.document.createElement('option');
            novo.value = '__novo__';
            novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
            reuSelect.appendChild(novo);
            lastReuSuggestionNome = nomeDigitado || '';
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
            if (reuComplementares) {
                reuComplementares.style.display = 'none';
            }
        }

        function carregarReusDoProcesso() {
            const digits = String(processoInput && processoInput.value || '').replace(/\D/g, '');
            if (digits.length !== 20) {
                showReuInput();
                return;
            }
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
                        showReuSelect(reus);
                    } else {
                        showReuInput();
                    }
                })
                .catch(function () {
                    showReuInput();
                });
        }

        if (processoInput) {
            processoInput.addEventListener('blur', carregarReusDoProcesso);
            processoInput.addEventListener('input', function () {
                if (String(processoInput.value || '').replace(/\D/g, '').length !== 20) {
                    showReuInput();
                }
            });
        }

        if (reuSelect) {
            reuSelect.addEventListener('change', function () {
                if (this.value === 'outro') {
                    reuSelect.style.display = 'none';
                    reuSelect.innerHTML = '';
                    reuSelect.value = '';
                    if (reuNome) {
                        reuNome.style.display = '';
                        reuNome.required = true;
                        reuNome.focus();
                    }
                    if (reuComplementares) {
                        reuComplementares.style.display = 'none';
                    }
                } else if (this.value === '__novo__') {
                    reuSelect.style.display = 'none';
                    reuSelect.innerHTML = '';
                    reuSelect.value = '';
                    if (reuNome) {
                        reuNome.value = lastReuSuggestionNome;
                        reuNome.style.display = '';
                        reuNome.required = true;
                        reuNome.focus();
                    }
                    if (reuComplementares) {
                        reuComplementares.style.display = '';
                    }
                } else if (this.value !== '' && reuComplementares) {
                    reuComplementares.style.display = 'none';
                }
            });
        }

        if (reuNome) {
            reuNome.addEventListener('blur', function () {
                let nome = String(reuNome.value || '').trim();
                if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                    nome = global.TextFormatter.formatProperName(nome, 255);
                    reuNome.value = nome;
                }
                if (nome.length < 2) {
                    return;
                }
                if (reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') {
                    return;
                }
                fetch((settings.peopleSearchEndpoint || '/api/pessoas/search-by-name.php?nome=') + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        const pessoas = data && data.pessoas ? data.pessoas : [];
                        if (pessoas.length > 0) {
                            showReuSelectSugestoes(pessoas, nome);
                        } else if (reuComplementares) {
                            reuComplementares.style.display = '';
                        }
                    })
                    .catch(function () {
                        if (reuComplementares) {
                            reuComplementares.style.display = '';
                        }
                    });
            });
        }

        function updateVitimaTitles() {
            if (!container) {
                return;
            }
            Array.from(container.querySelectorAll('.vitima-card')).forEach(function (card, index) {
                const title = card.querySelector('.vitima-card-index');
                if (title) {
                    title.textContent = 'Vítima ' + (index + 1);
                }
            });
        }

        function showVitimaComplementares(card, visible) {
            const section = card.querySelector('.vitima-campos-complementares');
            if (section) {
                section.style.display = visible ? '' : 'none';
            }
        }

        function idadeEmAnos(dataStr) {
            if (!dataStr || dataStr.length < 10) {
                return null;
            }
            const date = new Date(dataStr);
            if (isNaN(date.getTime())) {
                return null;
            }
            const today = new Date();
            let years = today.getFullYear() - date.getFullYear();
            const monthDiff = today.getMonth() - date.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < date.getDate())) {
                years -= 1;
            }
            return years;
        }

        function attachCardEvents(card) {
            const nomeInput = card.querySelector('.vitima-nome');
            const selectEl = card.querySelector('.vitima-select');
            let lastVitimaSuggestionNome = '';

            function showVitimaSelectSugestoes(pessoas, nomeDigitado) {
                if (!selectEl || !nomeInput) {
                    return;
                }
                selectEl.innerHTML = '';
                pessoas.forEach(function (pessoa) {
                    const option = global.document.createElement('option');
                    option.value = pessoa.id;
                    let label = String(pessoa.nome_completo || '').trim();
                    if (String(pessoa.cpf || '').replace(/\D/g, '').length === 11) {
                        label += ' - ' + formatCpfDisplay(pessoa.cpf);
                    }
                    option.textContent = label || pessoa.id;
                    selectEl.appendChild(option);
                });
                const novo = global.document.createElement('option');
                novo.value = '__novo__';
                novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
                selectEl.appendChild(novo);
                lastVitimaSuggestionNome = nomeDigitado || '';
                selectEl.style.display = '';
                nomeInput.style.display = 'none';
                showVitimaComplementares(card, false);
            }

            if (nomeInput) {
                nomeInput.addEventListener('blur', function () {
                    let nome = String(nomeInput.value || '').trim();
                    if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                        nome = global.TextFormatter.formatProperName(nome, 255);
                        nomeInput.value = nome;
                    }
                    if (nome.length < 2) {
                        return;
                    }
                    if (selectEl && selectEl.style.display !== 'none' && selectEl.value !== '' && selectEl.value !== '__novo__') {
                        return;
                    }
                    fetch((settings.peopleSearchEndpoint || '/api/pessoas/search-by-name.php?nome=') + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            const pessoas = data && data.pessoas ? data.pessoas : [];
                            if (pessoas.length > 0) {
                                showVitimaSelectSugestoes(pessoas, nome);
                            } else {
                                showVitimaComplementares(card, true);
                            }
                        })
                        .catch(function () {
                            showVitimaComplementares(card, true);
                        });
                });
            }

            if (selectEl) {
                selectEl.addEventListener('change', function () {
                    if (this.value === '__novo__') {
                        selectEl.style.display = 'none';
                        selectEl.innerHTML = '';
                        selectEl.value = '';
                        if (nomeInput) {
                            nomeInput.value = lastVitimaSuggestionNome;
                            nomeInput.style.display = '';
                            nomeInput.focus();
                        }
                        showVitimaComplementares(card, true);
                    } else if (this.value !== '') {
                        showVitimaComplementares(card, false);
                    }
                });
            }

            const cpfInput = card.querySelector('.vitima-cpf');
            if (cpfInput && App.CPF && App.CPF.init) {
                const uid = 'vitima-cpf-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
                cpfInput.id = uid;
                App.CPF.init('#' + uid);
            }

            card.querySelector('.btn-remover-vitima')?.addEventListener('click', function () {
                card.remove();
                updateVitimaTitles();
            });
        }

        function addVitima() {
            if (!container || !template) {
                return;
            }
            const clone = template.content.firstElementChild.cloneNode(true);
            attachCardEvents(clone);
            container.appendChild(clone);
            updateVitimaTitles();
        }

        if (App.CPF && App.CPF.init) {
            App.CPF.init('#reu-cpf');
        }

        addBtn?.addEventListener('click', function () {
            addVitima();
        });

        addVitima();

        global.document.addEventListener('change', function (event) {
            if (event.target && event.target.classList && event.target.classList.contains('vitima-data-nascimento')) {
                const card = event.target.closest('.vitima-card');
                if (card) {
                    const anos = idadeEmAnos(event.target.value);
                    const check = card.querySelector('.vitima-crianca');
                    if (check) {
                        if (anos !== null && anos < 18) {
                            check.checked = true;
                            check.disabled = true;
                        } else {
                            check.disabled = false;
                        }
                    }
                }
            }

            if (event.target && event.target.classList && event.target.classList.contains('vitima-sexo')) {
                const card = event.target.closest('.vitima-card');
                if (card) {
                    const check = card.querySelector('.vitima-mulher');
                    if (check) {
                        if (event.target.value === 'Feminino') {
                            check.checked = true;
                            check.disabled = true;
                        } else {
                            check.disabled = false;
                        }
                    }
                }
            }
        });
    }

    App.CartorioMedidasProtetivasCreateUI = {
        initialize: initialize
    };
})(window);
