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
                if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                    nome = App.ProperNameHelper.formatProper(nome, 255);
                    reuNome.value = nome;
                } else if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
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

        if (App.CPF && App.CPF.init) {
            App.CPF.init('#reu-cpf');
        }

        const labelProcessoTitulo = global.document.getElementById('label-processo-titulo');
        const labelProcessoObrigatorio = global.document.getElementById('label-processo-obrigatorio');
        const TEXTO_LABEL_PROCESSO_PADRAO = 'Processo';

        function definirVisibilidadeObrigatorioProcesso(visivel) {
            if (!labelProcessoObrigatorio) {
                return;
            }
            labelProcessoObrigatorio.style.display = visivel ? '' : 'none';
        }

        function textoClasseParaRotulo() {
            const desc = global.document.getElementById('hidden-classe-descricao');
            const hiddenClasse = global.document.getElementById('filter-classe');
            const t = (desc && desc.value ? String(desc.value).trim() : '')
                || (hiddenClasse && hiddenClasse.value ? String(hiddenClasse.value).trim() : '');
            return t;
        }

        function normalizarComparacaoClasse(s) {
            return String(s || '').trim().toLocaleLowerCase('pt-BR');
        }

        function rotuloEhClasseNaoInformada(texto) {
            const marker = (global.DataJudCycle && global.DataJudCycle.FALLBACK_CLASSE)
                ? String(global.DataJudCycle.FALLBACK_CLASSE)
                : 'Classe não informada';
            const t = normalizarComparacaoClasse(texto);
            const m = normalizarComparacaoClasse(marker);
            return t !== '' && t === m;
        }

        function aplicarRotuloProcessoAposDatajud(ev) {
            if (!ev.detail || ev.detail.processoInputId !== 'filter-processo' || !labelProcessoTitulo) {
                return;
            }
            const info = global.document.getElementById('processo-ap-info');
            if (ev.detail.success) {
                const txt = textoClasseParaRotulo();
                if (txt && !rotuloEhClasseNaoInformada(txt)) {
                    labelProcessoTitulo.textContent = txt;
                    definirVisibilidadeObrigatorioProcesso(false);
                    if (info) {
                        info.textContent = '';
                        info.style.removeProperty('color');
                    }
                } else {
                    labelProcessoTitulo.textContent = TEXTO_LABEL_PROCESSO_PADRAO;
                    definirVisibilidadeObrigatorioProcesso(true);
                }
            } else {
                labelProcessoTitulo.textContent = TEXTO_LABEL_PROCESSO_PADRAO;
                definirVisibilidadeObrigatorioProcesso(true);
            }
        }

        global.addEventListener('datajudClasseResolved', aplicarRotuloProcessoAposDatajud);

        global.addEventListener('cnjValidated', function (ev) {
            const digits = ev.detail && ev.detail.digits ? String(ev.detail.digits) : '';
            if (digits.length !== 20 && labelProcessoTitulo) {
                labelProcessoTitulo.textContent = TEXTO_LABEL_PROCESSO_PADRAO;
                definirVisibilidadeObrigatorioProcesso(true);
            }
        });
    }

    App.CartorioMedidasProtetivasCreateUI = {
        initialize: initialize
    };
})(window);
