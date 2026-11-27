(() => {
    'use strict';
    const painel = document.querySelector('[data-ficha-datajud]');
    if (!painel) return;
    const status = painel.querySelector('[data-ficha-status]');
    const tentar = painel.querySelector('[data-ficha-tentar]');
    const recentes = painel.querySelector('[data-ficha-recentes]');
    const historico = painel.querySelector('[data-ficha-historico]');
    const todas = painel.querySelector('[data-ficha-todas]');
    const formato = new Intl.DateTimeFormat('pt-BR', {dateStyle:'short', timeZone:'America/Bahia'});
    let dadosMov = [], carregando = false;
    function texto(chave, valor) {
        if (typeof valor !== 'string' || !valor.trim()) return;
        const alvo = document.querySelector('[data-ficha-' + chave + ']');
        if (alvo && !alvo.hasAttribute('data-ficha-sigilo-local')) alvo.textContent = valor;
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
    async function consultar() {
        if (carregando) return;
        carregando = true;
        tentar.hidden = true;
        status.textContent = 'Consultando o DataJud…';
        try {
            const res = await fetch('/api/processos/datajud-ficha.php', {
                method:'POST', credentials:'same-origin', cache:'no-store',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({processo_id:painel.dataset.fichaDatajud})
            });
            if (!res.ok) throw new Error('Consulta indisponível');
            const dados = await res.json();
            if (dados.estado === 'restrito') {
                if (typeof dados.sigilo === 'string') texto('sigilo', dados.sigilo);
                status.textContent = 'Histórico indisponível na base pública por sigilo.';
                return;
            }
            if (dados.estado !== 'disponivel') {
                status.textContent = 'Dados não disponíveis na base pública para este processo.';
                return;
            }
            texto('vara', dados.vara);
            texto('classe', dados.classe);
            texto('sigilo', dados.sigilo);
            if (Array.isArray(dados.assuntos)) texto('assuntos', dados.assuntos.filter(s => typeof s === 'string').join('; '));
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
    consultar();
})();
