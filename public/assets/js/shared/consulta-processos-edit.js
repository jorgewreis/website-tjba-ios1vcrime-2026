(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
            return;
        }
        if (typeof global.showToast === 'function') {
            global.showToast({ type: type || 'info', title: title || 'Informação', message: message || '' });
        }
    }

    function queuePostReloadToast(type, title, message, storageKey) {
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(type, title, message, storageKey);
            return;
        }
        if (common && typeof common.queuePostReloadToast === 'function') {
            common.queuePostReloadToast(type, title, message, storageKey);
        }
    }

    function flushPostReloadToast(storageKey) {
        if (App.Page && typeof App.Page.flushPostReloadToast === 'function') {
            App.Page.flushPostReloadToast(storageKey, { type: 'success', title: 'Sucesso' });
        }
    }

    function postJson(url, payload, options) {
        if (common && typeof common.postJson === 'function') {
            return common.postJson(url, payload, options);
        }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload || {})
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    }

    function normalizeAssuntos(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        const unique = {};
        list.forEach(function (item) {
            if (!item || typeof item !== 'object') {
                return;
            }
            const id = String(item.id || item.assunto_id || '').trim();
            const codigo = String(item.codigo || item.assunto_codigo || '').trim();
            const descricao = String(item.descricao || item.assunto_descricao || '').trim();
            const origemInformada = String(item.origem || '').trim();
            const origem = origemInformada === 'manual' || origemInformada === 'datajud' ? origemInformada : 'unknown';
            if (!id) {
                return;
            }
            unique[id] = { id: id, codigo: codigo, descricao: descricao, origem: origem };
        });
        return Object.keys(unique).map(function (id) { return unique[id]; })
            .sort(function (a, b) {
                const ka = (a.codigo || '') + ' ' + (a.descricao || '');
                const kb = (b.codigo || '') + ' ' + (b.descricao || '');
                return ka.localeCompare(kb, 'pt-BR');
            });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderAssuntos(container, assuntos) {
        if (!container) {
            return;
        }

        const lista = normalizeAssuntos(assuntos);
        if (lista.length === 0) {
            container.innerHTML = '<div class="col no-data"><span class="major-info">Nenhum assunto vinculado.</span></div>';
            return;
        }

        container.innerHTML = lista.map(function (item) {
            const texto = item.codigo
                ? (item.descricao && item.descricao !== item.codigo ? (item.codigo + ' - ' + item.descricao) : item.codigo)
                : item.descricao;
            const control = item.origem === 'manual'
                ? '  <button type="button" class="btn btn-secondary-outline height-sm btn-assunto-desvincular" data-assunto-id="' + item.id + '">Remover</button>'
                : '  <span class="label label-default">' + escapeHtml(item.origem === 'datajud' ? 'DataJud' : 'Origem não identificada') + '</span>';
            return [
                '<div class="display-flex row row-full justify-between align-center border-bottom padding-y-sm" data-assunto-id="' + item.id + '">',
                '  <div class="display-flex col" style="width:auto; flex:1 1 auto;">',
                '    <span class="major-info">' + escapeHtml(texto) + '</span>',
                '  </div>',
                control,
                '</div>'
            ].join('');
        }).join('');
    }

    function formatCpfDisplay(cpf) {
        const digits = String(cpf || '').replace(/\D/g, '');
        if (digits.length !== 11) {
            return String(cpf || '').trim();
        }
        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9, 11);
    }

    function formatNomeParte(value) {
        let nome = String(value || '').trim();
        if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            nome = App.ProperNameHelper.formatProper(nome, 255);
        } else if (global.TextFormatter && typeof global.TextFormatter.formatProperName === 'function') {
            nome = global.TextFormatter.formatProperName(nome, 255);
        }
        return nome;
    }

    function resolvePessoaOptionLabel(pessoa) {
        if (pessoa && String(pessoa.label || '').trim() !== '') {
            return String(pessoa.label).trim();
        }
        const nome = String((pessoa && pessoa.nome_completo) || '').trim();
        const cpf = String((pessoa && (pessoa.cpf_formatado || pessoa.cpf)) || '').trim();
        const profissao = String((pessoa && pessoa.profissao_exibicao) || '').trim();
        const lotacao = String((pessoa && pessoa.lotacao_exibicao) || '').trim();
        let label = nome;
        if (cpf !== '') {
            label = formatCpfDisplay(cpf) + ' - ' + label;
        }
        const suffix = [];
        if (profissao !== '') {
            suffix.push(profissao);
        }
        if (lotacao !== '') {
            suffix.push(lotacao);
        }
        if (suffix.length > 0) {
            label += ' (' + suffix.join(' - ') + ')';
        }
        return label;
    }

    function initialize(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'cartorio.postReloadToast';
        const processoId = String(global.PROCESSO_ASSUNTOS_PROCESSO_ID || '').trim();
        const findAssuntoEndpoint = String(global.PROCESSO_ASSUNTO_FIND_ENDPOINT || '/api/assuntos/find-by-codigo.php');
        const linkAssuntoEndpoint = String(global.PROCESSO_ASSUNTO_LINK_ENDPOINT || '/api/processos/assunto-link.php');
        const unlinkAssuntoEndpoint = String(global.PROCESSO_ASSUNTO_UNLINK_ENDPOINT || '/api/processos/assunto-unlink.php');

        const codigoInput = global.document.getElementById('processo-assunto-codigo');
        const descricaoInput = global.document.getElementById('processo-assunto-descricao');
        const btnVincularAssunto = global.document.getElementById('btn-processo-assunto-vincular');
        const assuntosList = global.document.getElementById('processo-assuntos-list');
        const parteNomeSelect = global.document.getElementById('processo-parte-nome-selecao');
        const parteNomeInput = global.document.getElementById('processo-parte-nome');
        const parteIdInput = global.document.getElementById('processo-parte-id');
        const parteTipoSelect = global.document.getElementById('processo-parte-tipo');
        const partePoloSelect = global.document.getElementById('processo-parte-polo');
        const parteAdicionarBtn = global.document.getElementById('btn-processo-parte-adicionar');
        const parteNomeStatus = global.document.getElementById('processo-parte-nome-status');
        let assuntosState = normalizeAssuntos(global.PROCESSO_ASSUNTOS_INITIAL || []);

        const BUSCA_ASSUNTO_DEBOUNCE_MS = 1000;
        let buscaAssuntoTimer = null;
        let ultimoCodigoConsultado = '';
        /** Código para o qual a consulta ao catálogo terminou com sucesso (HTTP ok). */
        let codigoVerificadoNoBd = '';
        let assuntoEncontradoNoCatalogo = false;
        let buscaPessoaTimer = null;
        let pessoaResultadoIncerto = false;
        let pessoaPendente = false;
        let pessoaNavegando = false;
        let geracaoBuscaPessoa = 0;

        function clearBuscaAssuntoTimer() {
            if (buscaAssuntoTimer !== null) {
                global.clearTimeout(buscaAssuntoTimer);
                buscaAssuntoTimer = null;
            }
        }

        function resetAssuntoLookup() {
            ultimoCodigoConsultado = '';
            codigoVerificadoNoBd = '';
            assuntoEncontradoNoCatalogo = false;
            if (descricaoInput) {
                descricaoInput.value = '';
                descricaoInput.readOnly = false;
                descricaoInput.placeholder = 'Informe a descrição se o código não estiver no catálogo';
            }
            atualizarBtnVincularAssunto();
        }

        function podeVincularAssunto() {
            const codigo = codigoInput ? String(codigoInput.value || '').trim() : '';
            if (codigo === '' || codigo !== codigoVerificadoNoBd) {
                return false;
            }
            if (assuntoEncontradoNoCatalogo) {
                return true;
            }
            const descricao = descricaoInput ? String(descricaoInput.value || '').trim() : '';
            return descricao !== '';
        }

        function atualizarBtnVincularAssunto() {
            if (!btnVincularAssunto) {
                return;
            }
            btnVincularAssunto.disabled = !podeVincularAssunto();
        }

        function aplicarAssuntoEncontrado(assunto) {
            if (!descricaoInput) {
                return;
            }
            descricaoInput.value = String(assunto.descricao || '').trim();
            descricaoInput.readOnly = true;
            descricaoInput.placeholder = 'Descrição carregada do catálogo';
        }

        function aplicarAssuntoNaoEncontrado() {
            if (!descricaoInput) {
                return;
            }
            descricaoInput.value = '';
            descricaoInput.readOnly = false;
            descricaoInput.placeholder = 'Código não cadastrado — informe a descrição para vincular';
        }

        function setParteNomeStatus(text, color) {
            if (!parteNomeStatus) {
                return;
            }
            parteNomeStatus.textContent = text || '';
            parteNomeStatus.style.color = color || '';
        }

        function parteNomeAtual() {
            return parteNomeInput ? formatNomeParte(parteNomeInput.value || '') : '';
        }

        function atualizarBtnAdicionarParte() {
            if (!parteAdicionarBtn) {
                return;
            }
            const nome = parteNomeAtual();
            const parteId = parteIdInput ? String(parteIdInput.value || '').trim() : '';
            const tipo = parteTipoSelect ? String(parteTipoSelect.value || '').trim() : '';
            const polo = partePoloSelect ? String(partePoloSelect.value || '').trim() : '';
            parteAdicionarBtn.disabled = pessoaPendente || pessoaResultadoIncerto || pessoaNavegando || (parteId === '' && nome === '') || tipo === '' || polo === '';
        }

        function aplicarPoloPadraoPorTipo() {
            if (!parteTipoSelect || !partePoloSelect || String(partePoloSelect.value || '').trim() !== '') {
                return;
            }
            const tipo = String(parteTipoSelect.value || '').trim();
            if (['reu', 'indiciado', 'sentenciado', 'requerido'].indexOf(tipo) >= 0) {
                partePoloSelect.value = 'passivo';
            } else if (['vitima', 'requerente'].indexOf(tipo) >= 0) {
                partePoloSelect.value = 'ativo';
            } else if (['testemunha_denuncia', 'testemunha_defesa', 'advogado', 'defensor_publico'].indexOf(tipo) >= 0) {
                partePoloSelect.value = 'terceiros';
            }
        }

        function mostrarInputNomeParte(preserveValue) {
            if (!parteNomeInput || !parteNomeSelect) {
                return;
            }
            parteNomeSelect.style.display = 'none';
            parteNomeSelect.disabled = true;
            parteNomeInput.style.display = '';
            parteNomeInput.readOnly = false;
            if (typeof preserveValue === 'string') {
                parteNomeInput.value = preserveValue;
            }
            if (parteIdInput) {
                parteIdInput.value = '';
            }
            setParteNomeStatus('', '');
            atualizarBtnAdicionarParte();
        }

        function mostrarSugestoesPessoas(pessoas, nomeDigitado) {
            if (!parteNomeSelect || !parteNomeInput) {
                return;
            }
            const sugestoes = Array.isArray(pessoas) ? pessoas.slice(0, 3) : [];
            if (sugestoes.length === 0) {
                mostrarInputNomeParte(nomeDigitado);
                return;
            }

            parteNomeSelect.innerHTML = '';
            const usarNome = global.document.createElement('option');
            usarNome.value = '__nova__';
            usarNome.textContent = 'Nova pessoa (usar: ' + nomeDigitado + ')';
            usarNome.dataset.nomeParte = nomeDigitado;
            usarNome.dataset.parteId = '';
            parteNomeSelect.appendChild(usarNome);

            sugestoes.forEach(function (pessoa) {
                const option = global.document.createElement('option');
                option.value = String((pessoa && pessoa.id) || '').trim();
                option.textContent = resolvePessoaOptionLabel(pessoa);
                option.dataset.nomeParte = String((pessoa && pessoa.nome_completo) || '').trim();
                option.dataset.parteId = String((pessoa && pessoa.id) || '').trim();
                parteNomeSelect.appendChild(option);
            });

            const outro = global.document.createElement('option');
            outro.value = '__outro__';
            outro.textContent = 'Outro nome (digite abaixo)';
            parteNomeSelect.appendChild(outro);

            const firstSuggestion = parteNomeSelect.querySelector('option[data-parte-id][value]:not([value="__nova__"])');
            parteNomeSelect.disabled = false;
            parteNomeSelect.style.display = '';
            parteNomeInput.style.display = 'none';
            if (firstSuggestion) {
                parteNomeSelect.value = firstSuggestion.value;
                parteNomeInput.value = formatNomeParte(firstSuggestion.dataset.nomeParte || nomeDigitado);
                if (parteIdInput) {
                    parteIdInput.value = String(firstSuggestion.dataset.parteId || '').trim();
                }
                setParteNomeStatus('Parte vinculada', 'var(--color-success-active)');
            } else {
                parteNomeSelect.value = '__nova__';
                parteNomeInput.value = formatNomeParte(nomeDigitado);
                if (parteIdInput) {
                    parteIdInput.value = '';
                }
                setParteNomeStatus('Nova pessoa', '');
            }
            atualizarBtnAdicionarParte();
        }

        function buscarPessoasPorNome(nome, geracao) {
            return fetch('/api/pessoas/search-by-name.php?nome=' + encodeURIComponent(nome) + '&limit=5', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }).then(function (result) {
                if (geracao !== geracaoBuscaPessoa || pessoaPendente || pessoaResultadoIncerto || pessoaNavegando) return;
                if (!result.ok || (result.body && result.body.error)) {
                    mostrarInputNomeParte(nome);
                    return;
                }
                mostrarSugestoesPessoas(result.body ? result.body.pessoas : [], nome);
            }).catch(function () {
                if (geracao !== geracaoBuscaPessoa || pessoaPendente || pessoaResultadoIncerto || pessoaNavegando) return;
                mostrarInputNomeParte(nome);
            });
        }

        function scheduleBuscarPessoas() {
            if (pessoaPendente || pessoaResultadoIncerto || pessoaNavegando) return;
            const geracao = ++geracaoBuscaPessoa;
            if (buscaPessoaTimer !== null) {
                global.clearTimeout(buscaPessoaTimer);
                buscaPessoaTimer = null;
            }
            const nome = parteNomeAtual();
            if (parteIdInput) {
                parteIdInput.value = '';
            }
            setParteNomeStatus('', '');
            if (nome.length < 3) {
                setParteNomeStatus('', '');
                atualizarBtnAdicionarParte();
                return;
            }
            buscaPessoaTimer = global.setTimeout(function () {
                buscaPessoaTimer = null;
                buscarPessoasPorNome(nome, geracao);
            }, 700);
            atualizarBtnAdicionarParte();
        }

        function limparFormularioParte() {
            if (parteNomeInput) {
                parteNomeInput.value = '';
            }
            if (parteTipoSelect) {
                parteTipoSelect.selectedIndex = 0;
            }
            if (partePoloSelect) {
                partePoloSelect.selectedIndex = 0;
            }
            mostrarInputNomeParte('');
        }

        function criarPessoaSeNecessario() {
            const parteId = parteIdInput ? String(parteIdInput.value || '').trim() : '';
            if (parteId !== '') {
                return Promise.resolve(parteId);
            }
            const nome = parteNomeAtual();
            if (nome === '') {
                return Promise.reject(new Error('Nome obrigatório.'));
            }
            return postJson('/api/pessoas/create.php', {
                nome_completo: nome
            }, { requireJson: true }).catch(function () {
                const error = new Error('Não foi possível confirmar o resultado. Consulte o cadastro de pessoas antes de tentar novamente.');
                error.verificacaoNecessaria = true;
                throw error;
            }).then(function (result) {
                if (result.ok && result.data && result.data.id) {
                    return String(result.data.id);
                }
                const error = new Error((result.data && result.data.error) ? result.data.error : 'Não foi possível confirmar o resultado. Consulte o cadastro de pessoas antes de tentar novamente.');
                error.verificacaoNecessaria = result.status >= 500 || !result.data || typeof result.data !== 'object' || (!result.data.error && !result.data.id) || result.data.resultado === 'verificacao_necessaria';
                if (error.verificacaoNecessaria) error.message = 'Não foi possível confirmar o resultado. Consulte o cadastro de pessoas antes de tentar novamente.';
                throw error;
            });
        }

        function buscarAssuntoPorCodigo(options) {
            const opts = options || {};
            const exibirToast = opts.exibirToast !== false;
            const codigo = codigoInput ? String(codigoInput.value || '').trim() : '';
            if (codigo === '') {
                resetAssuntoLookup();
                return Promise.resolve();
            }
            if (codigo === ultimoCodigoConsultado && descricaoInput && descricaoInput.readOnly) {
                return Promise.resolve();
            }

            return fetch(findAssuntoEndpoint + '?codigo=' + encodeURIComponent(codigo), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }).then(function (result) {
                if (!result.ok || (result.body && result.body.error)) {
                    codigoVerificadoNoBd = '';
                    assuntoEncontradoNoCatalogo = false;
                    atualizarBtnVincularAssunto();
                    if (exibirToast) {
                        notify('error', 'Erro', (result.body && result.body.error) ? result.body.error : 'Erro ao consultar assunto.');
                    }
                    return;
                }
                ultimoCodigoConsultado = codigo;
                codigoVerificadoNoBd = codigo;
                const assunto = result.body ? result.body.assunto : null;
                if (assunto) {
                    assuntoEncontradoNoCatalogo = true;
                    aplicarAssuntoEncontrado(assunto);
                    if (exibirToast) {
                        notify('info', 'Assunto encontrado', 'Descrição carregada para vinculação.');
                    }
                    atualizarBtnVincularAssunto();
                    return;
                }
                assuntoEncontradoNoCatalogo = false;
                aplicarAssuntoNaoEncontrado();
                if (exibirToast) {
                    notify('info', 'Assunto não encontrado', 'Informe a descrição para vincular manualmente.');
                }
                atualizarBtnVincularAssunto();
            }).catch(function () {
                codigoVerificadoNoBd = '';
                assuntoEncontradoNoCatalogo = false;
                atualizarBtnVincularAssunto();
                if (exibirToast) {
                    notify('error', 'Erro', 'Erro de conexão ao consultar assunto.');
                }
            });
        }

        function scheduleBuscarAssunto() {
            clearBuscaAssuntoTimer();
            const codigo = codigoInput ? String(codigoInput.value || '').trim() : '';
            if (codigo === '') {
                resetAssuntoLookup();
                return;
            }
            if (codigo !== ultimoCodigoConsultado) {
                codigoVerificadoNoBd = '';
                assuntoEncontradoNoCatalogo = false;
                if (descricaoInput) {
                    descricaoInput.readOnly = false;
                }
                atualizarBtnVincularAssunto();
            }
            buscaAssuntoTimer = global.setTimeout(function () {
                buscaAssuntoTimer = null;
                buscarAssuntoPorCodigo({ exibirToast: false });
            }, BUSCA_ASSUNTO_DEBOUNCE_MS);
        }

        flushPostReloadToast(storageKey);
        renderAssuntos(assuntosList, assuntosState);
        atualizarBtnVincularAssunto();
        atualizarBtnAdicionarParte();

        global.document.querySelectorAll('.btn-desvincular').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                const processoId = button.getAttribute('data-processo-id');
                if (!id || !processoId) {
                    return;
                }
                button.disabled = true;
                postJson('/api/processos-partes/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        queuePostReloadToast('success', 'Sucesso', 'Vínculo removido com sucesso.', storageKey);
                        global.location.reload();
                        return;
                    }
                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
                    button.disabled = false;
                }).catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    button.disabled = false;
                });
            });
        });

        if (codigoInput) {
            codigoInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    clearBuscaAssuntoTimer();
                    buscarAssuntoPorCodigo({ exibirToast: true });
                }
            });
            codigoInput.addEventListener('input', function () {
                const codigo = String(codigoInput.value || '').trim();
                if (codigo === '') {
                    resetAssuntoLookup();
                    return;
                }
                scheduleBuscarAssunto();
            });
        }

        if (descricaoInput) {
            descricaoInput.addEventListener('input', function () {
                if (!descricaoInput.readOnly) {
                    atualizarBtnVincularAssunto();
                }
            });
        }

        if (btnVincularAssunto && codigoInput && processoId) {
            btnVincularAssunto.addEventListener('click', function () {
                const codigo = String(codigoInput.value || '').trim();
                const descricao = descricaoInput ? String(descricaoInput.value || '').trim() : '';
                if (!codigo) {
                    notify('error', 'Código obrigatório', 'Informe o código do assunto para vincular.');
                    codigoInput.focus();
                    return;
                }

                btnVincularAssunto.disabled = true;
                postJson(linkAssuntoEndpoint, {
                    processo_id: processoId,
                    codigo: codigo,
                    descricao: descricao
                }).then(function (result) {
                    if (!result.data || result.data.error) {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao vincular assunto.');
                        return;
                    }

                    const assunto = result.data.assunto || null;
                    if (assunto && assunto.id) {
                        assuntosState.push({
                            id: String(assunto.id),
                            codigo: String(assunto.codigo || codigo),
                            descricao: String(assunto.descricao || descricao || codigo),
                            origem: 'manual'
                        });
                        assuntosState = normalizeAssuntos(assuntosState);
                        renderAssuntos(assuntosList, assuntosState);
                    }
                    codigoInput.value = '';
                    resetAssuntoLookup();
                    if (descricaoInput) {
                        descricaoInput.readOnly = true;
                        descricaoInput.placeholder = 'Preenchida automaticamente se o código existir no catálogo';
                    }
                    notify('success', 'Sucesso', 'Assunto vinculado ao processo.');
                }).catch(function () {
                    notify('error', 'Erro', 'Erro de conexão ao vincular assunto.');
                }).finally(function () {
                    atualizarBtnVincularAssunto();
                });
            });
        }

        if (parteNomeInput) {
            parteNomeInput.addEventListener('input', scheduleBuscarPessoas);
            parteNomeInput.addEventListener('blur', function () {
                if (pessoaPendente || pessoaResultadoIncerto || pessoaNavegando) return;
                parteNomeInput.value = parteNomeAtual();
                atualizarBtnAdicionarParte();
            });
        }

        if (parteNomeSelect && parteNomeInput) {
            parteNomeSelect.addEventListener('change', function () {
                if (pessoaPendente || pessoaResultadoIncerto || pessoaNavegando) return;
                const selected = parteNomeSelect.options[parteNomeSelect.selectedIndex] || null;
                const value = String(parteNomeSelect.value || '').trim();
                if (value === '__outro__') {
                    mostrarInputNomeParte('');
                    parteNomeInput.focus();
                    return;
                }
                const nome = selected ? String(selected.dataset.nomeParte || '').trim() : '';
                parteNomeInput.value = formatNomeParte(nome);
                if (parteIdInput) {
                    parteIdInput.value = selected ? String(selected.dataset.parteId || '').trim() : '';
                }
                const selecionadaId = selected ? String(selected.dataset.parteId || '').trim() : '';
                setParteNomeStatus(value === '__nova__' && !selecionadaId ? 'Nova pessoa' : (value === '__nova__' ? 'Pessoa cadastrada; vínculo pendente' : 'Parte vinculada'), selecionadaId ? 'var(--color-success-active)' : '');
                atualizarBtnAdicionarParte();
            });
        }

        if (parteTipoSelect) {
            parteTipoSelect.addEventListener('change', function () {
                aplicarPoloPadraoPorTipo();
                atualizarBtnAdicionarParte();
            });
        }

        if (partePoloSelect) {
            partePoloSelect.addEventListener('change', atualizarBtnAdicionarParte);
        }

        if (parteAdicionarBtn && processoId) {
            parteAdicionarBtn.addEventListener('click', function () {
                if (pessoaPendente || pessoaResultadoIncerto || pessoaNavegando) return;
                const tipo = parteTipoSelect ? String(parteTipoSelect.value || '').trim() : '';
                const polo = partePoloSelect ? String(partePoloSelect.value || '').trim() : '';
                const nome = parteNomeAtual();
                const pessoaJaExistente = parteIdInput ? String(parteIdInput.value || '').trim() !== '' : false;
                if (nome === '' || tipo === '' || polo === '') {
                    notify('error', 'Campos obrigatórios', 'Informe nome, tipo e polo da parte.');
                    return;
                }
                ++geracaoBuscaPessoa;
                if (buscaPessoaTimer !== null) {
                    global.clearTimeout(buscaPessoaTimer);
                    buscaPessoaTimer = null;
                }
                const camposParte = [parteNomeInput, parteNomeSelect, parteTipoSelect, partePoloSelect].filter(Boolean);
                const estadosCampos = camposParte.map(function (campo) { return campo.disabled; });
                camposParte.forEach(function (campo) { campo.disabled = true; });
                pessoaPendente = true;
                atualizarBtnAdicionarParte();
                criarPessoaSeNecessario().then(function (parteId) {
                    if (parteIdInput) parteIdInput.value = parteId;
                    if (!pessoaJaExistente) {
                        setParteNomeStatus('Pessoa cadastrada; vínculo pendente', 'var(--color-success-active)');
                        const selected = parteNomeSelect && parteNomeSelect.options[parteNomeSelect.selectedIndex];
                        if (selected && selected.value === '__nova__') {
                            selected.dataset.parteId = parteId;
                            selected.textContent = 'Pessoa cadastrada; vínculo pendente';
                        }
                    }
                    return postJson('/api/processos-partes/create.php', {
                        processo_id: processoId,
                        parte_id: parteId,
                        tipo_parte: tipo,
                        polo: polo
                    }, { requireJson: true }).catch(function () {
                        const error = new Error('Não foi possível confirmar o vínculo. Consulte as partes do processo antes de tentar novamente.');
                        error.verificacaoNecessaria = true;
                        throw error;
                    });
                }).then(function (result) {
                    if (result.ok && result.data && result.data.id) {
                        pessoaNavegando = true;
                        limparFormularioParte();
                        queuePostReloadToast('success', 'Sucesso', 'Parte adicionada ao processo.', storageKey);
                        global.location.reload();
                        return;
                    }
                    if (result.status >= 500 || !result.data || typeof result.data !== 'object' || (!result.data.error && !result.data.id)) {
                        const error = new Error('Não foi possível confirmar o vínculo. Consulte as partes do processo antes de tentar novamente.');
                        error.verificacaoNecessaria = true;
                        throw error;
                    }
                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro ao adicionar parte.');
                }).catch(function (error) {
                    if (error && error.verificacaoNecessaria) {
                        pessoaResultadoIncerto = true;
                        let aviso = global.document.getElementById('pessoa-resultado-incerto');
                        if (!aviso) {
                            aviso = global.document.createElement('div');
                            aviso.id = 'pessoa-resultado-incerto';
                            aviso.setAttribute('role', 'alert');
                            parteAdicionarBtn.parentNode.prepend(aviso);
                        }
                        aviso.textContent = error.message;
                        return;
                    }
                    notify('error', 'Erro', error && error.message ? error.message : 'Erro de conexão ao adicionar parte.');
                }).finally(function () {
                    pessoaPendente = false;
                    if (!pessoaResultadoIncerto && !pessoaNavegando) {
                        camposParte.forEach(function (campo, index) { campo.disabled = estadosCampos[index]; });
                    }
                    atualizarBtnAdicionarParte();
                });
            });
        }

        if (assuntosList && processoId) {
            assuntosList.addEventListener('click', function (event) {
                const target = event.target;
                if (!target || !(target instanceof HTMLElement) || !target.classList.contains('btn-assunto-desvincular')) {
                    return;
                }
                const assuntoId = String(target.getAttribute('data-assunto-id') || '').trim();
                if (!assuntoId) {
                    return;
                }
                target.disabled = true;
                postJson(unlinkAssuntoEndpoint, {
                    processo_id: processoId,
                    assunto_id: assuntoId
                }).then(function (result) {
                    if (!result.data || result.data.error) {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao desvincular assunto.');
                        target.disabled = false;
                        return;
                    }
                    if (result.data.action !== 'deleted') {
                        notify('info', 'Assunto não removido', 'O vínculo permaneceu inalterado. Atualize a lista para ver o estado atual.');
                        target.disabled = false;
                        return;
                    }
                    assuntosState = assuntosState.filter(function (item) { return item.id !== assuntoId; });
                    renderAssuntos(assuntosList, assuntosState);
                    notify('success', 'Sucesso', 'Assunto desvinculado do processo.');
                }).catch(function () {
                    notify('error', 'Erro', 'Erro de conexão ao desvincular assunto.');
                    target.disabled = false;
                });
            });
        }
    }

    App.ConsultaProcessosEdit = {
        initialize: initialize
    };
})(window);
