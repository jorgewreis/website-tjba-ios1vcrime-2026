(() => {
    'use strict';
    const painel = document.querySelector('[data-ficha-datajud]');
    if (!painel) return;
    const status = painel.querySelector('[data-ficha-status]');
    const consultarBotao = painel.querySelector('[data-ficha-consultar]');
    const tentar = painel.querySelector('[data-ficha-tentar]');
    const recentes = painel.querySelector('[data-ficha-recentes]');
    const historico = painel.querySelector('[data-ficha-historico]');
    const todas = painel.querySelector('[data-ficha-todas]');
    const formato = new Intl.DateTimeFormat('pt-BR', {dateStyle:'short', timeZone:'America/Bahia'});
    let dadosMov = [], carregando = false;
    function texto(chave, valor) {
        if (typeof valor !== 'string' || !valor.trim()) return;
        const alvo = document.querySelector('[data-ficha-' + chave + ']');
        if (alvo) alvo.textContent = valor;
    }
    function combinarAssuntos(remotos) {
        if (!Array.isArray(remotos)) return;
        const alvo = document.querySelector('[data-ficha-assuntos]');
        const raw = painel.dataset.fichaAssuntosLocais ?? alvo?.dataset?.fichaAssuntosLocais ?? '[]';
        let locais = [];
        try {
            const dadosLocais = JSON.parse(raw);
            if (Array.isArray(dadosLocais)) locais = dadosLocais;
        } catch (_) {
            // Mantém compatibilidade com fichas anteriores sem a lista local em JSON.
        }

        const rotulos = [];
        const vistos = new Set();
        for (const valor of [...remotos, ...locais]) {
            if (typeof valor !== 'string' || !valor.trim()) continue;
            const rotulo = valor.trim();
            const chave = rotulo.normalize('NFC').replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR');
            if (vistos.has(chave)) continue;
            vistos.add(chave);
            rotulos.push(rotulo);
        }
        // Sem rótulos válidos, o texto já renderizado no servidor fica intacto.
        texto('assuntos', rotulos.join('; '));
    }
    function preencher(lista, movimentos) {
        lista.replaceChildren(...movimentos.map(mov => {
            const item = document.createElement('li');
            const data = document.createElement('span');
            data.className = 'ficha-data';
            data.textContent = Number.isFinite(mov.instante) ? formato.format(mov.instante) : 'Data não informada';
            const nome = document.createElement('strong');
            nome.textContent = mov.nome;
            item.append(data, nome);
            return item;
        }));
    }
    historico.addEventListener('toggle', () => {
        recentes.hidden = historico.open;
        historico.querySelector('summary').textContent = historico.open
            ? 'Mostrar apenas as recentes' : 'Ver todas as movimentações';
        if (historico.open && !todas.children.length) preencher(todas, dadosMov);
    });
    const falhas = Object.freeze({
        datajud_throttled: [503, 'O DataJud limitou a consulta. Aguarde antes de tentar novamente.', true],
        datajud_incomplete: [503, 'O DataJud retornou uma consulta incompleta. Aguarde antes de tentar novamente.', true],
        datajud_transport: [503, 'Não foi possível conectar ao DataJud. Aguarde antes de tentar novamente.', true],
        datajud_unavailable: [503, 'O DataJud está temporariamente indisponível. Aguarde antes de tentar novamente.', true],
        datajud_authorization: [502, 'O DataJud recusou a autenticação da integração. Informe a equipe responsável.', false],
        datajud_not_configured: [503, 'A integração do DataJud não está configurada. Informe a equipe responsável.', false],
        datajud_ambiguous: [409, 'O DataJud retornou mais de um registro. Confira a ficha na fonte institucional.', false],
        datajud_not_queryable: [422, 'Este processo não pôde ser consultado no DataJud. Confira o cadastro.', false]
    });

    async function consultar() {
        if (carregando) return;
        carregando = true;
        tentar.hidden = true;
        if (consultarBotao) consultarBotao.hidden = true;
        status.textContent = 'Consultando o DataJud…';
        try {
            const res = await fetch('/api/processos/datajud-ficha.php', {
                method:'POST', credentials:'same-origin', cache:'no-store',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({processo_id:painel.dataset.fichaDatajud})
            });
            const dados = await res.json();
            if (!res.ok) {
                const code = dados?.estado === 'erro' && typeof dados.code === 'string' ? dados.code : '';
                const falha = Object.hasOwn(falhas, code) ? falhas[code] : null;
                if (!falha || res.status !== falha[0]) throw new Error('Consulta indisponível');
                status.textContent = falha[1];
                tentar.hidden = !falha[2];
                return;
            }
            if (dados?.estado !== 'restrito' && dados?.estado !== 'disponivel' && dados?.estado !== 'indisponivel') {
                throw new Error('Resposta de consulta inválida');
            }
            if (dados.estado === 'restrito') {
                const aviso = document.querySelector('[data-ficha-sigilo-datajud]');
                if (aviso) {
                    const classificacao = typeof dados.sigilo === 'string' && dados.sigilo.trim();
                    aviso.textContent = 'Restrição indicada pelo DataJud · ' + (classificacao || 'nível não informado');
                    aviso.hidden = false;
                    const rotulo = document.getElementById('ficha-sigilo-datajud-rotulo');
                    if (rotulo) rotulo.hidden = false;
                }
                status.textContent = 'Histórico indisponível na base pública por sigilo.';
                return;
            }
            if (dados.estado !== 'disponivel') {
                status.textContent = 'Não há dados públicos verificáveis para este processo.';
                return;
            }
            texto('vara', dados.vara);
            texto('classe', dados.classe);
            combinarAssuntos(dados.assuntos);
            dadosMov = (Array.isArray(dados.movimentos) ? dados.movimentos : [])
                .filter(m => m && typeof m.nome === 'string' && m.nome.trim())
                .map(m => ({nome:m.nome, instante: typeof m.data === 'string' ? Date.parse(m.data) : NaN}))
                .sort((a,b) => (Number.isFinite(b.instante) ? b.instante : -Infinity) -
                    (Number.isFinite(a.instante) ? a.instante : -Infinity));
            preencher(recentes, dadosMov.slice(0, 3));
            historico.hidden = dadosMov.length <= 3;
            status.textContent = dadosMov.length ? '' : 'Nenhuma movimentação retornada pelo DataJud.';
        } catch (_) {
            status.textContent = 'Não foi possível consultar o DataJud agora.';
            tentar.hidden = false;
        } finally {
            carregando = false;
        }
    }
    tentar.addEventListener('click', consultar);
    consultarBotao?.addEventListener('click', consultar);
})();
