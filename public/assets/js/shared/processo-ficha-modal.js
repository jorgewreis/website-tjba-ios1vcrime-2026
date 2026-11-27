(() => {
    'use strict';
    // A janela principal monta o painel acima do menu e do iframe de trabalho.
    // Páginas abertas fora dela também podem hospedar o painel localmente.
    if (!window.PainelDeslizante || typeof HTMLDialogElement === 'undefined' || typeof HTMLDialogElement.prototype.showModal !== 'function') return;
    try {
        const anfitriao = window.frameElement;
        if (anfitriao?.matches('[data-ficha-workspace], [data-ficha-modal-content]')) return;
    } catch (_) { /* Sem acesso ao pai: o painel continua local ao documento. */ }

    const conectados = new WeakSet();
    const origemSite = location.origin;
    let painel, card, quadro, botaoFechar, acionador, fechando = false;
    const workspace = document.querySelector('[data-ficha-workspace]');

    function urlDaFicha(link, base) {
        try {
            const url = new URL(link, base);
            if (url.origin !== origemSite || url.pathname !== '/frame.php'
                || url.searchParams.get('page') !== 'consultaprocessosedit'
                || url.searchParams.has('modo')
                || !/^[A-Za-z0-9_-]{1,80}$/.test(url.searchParams.get('id') || '')) return null;
            return url;
        } catch (_) {
            return null;
        }
    }

    function fechar() {
        if (!painel?.open || fechando) return;
        fechando = true;
        window.PainelDeslizante.fechar(painel, card, () => painel.close());
    }

    function aoCarregarFicha() {
        if (!painel.open) return;
        let doc;
        try { doc = quadro.contentDocument; } catch (_) { return; }
        if (!doc) return;
        doc.documentElement.classList.add('ficha-no-painel');
        doc.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || event.defaultPrevented || event.isComposing) return;
            event.preventDefault();
            fechar();
        });
        doc.addEventListener('click', event => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (!event.target.closest?.('[data-fechar-ficha]')) return;
            event.preventDefault();
            fechar();
        }, true);
    }

    function montar() {
        if (painel) return;
        painel = document.createElement('dialog');
        painel.className = 'processo-ficha-layer';
        card = document.createElement('section');
        card.className = 'processo-ficha-modal';
        card.setAttribute('data-painel-deslizante', '');
        painel.setAttribute('aria-labelledby', 'processo-ficha-modal-titulo');
        const cabecalho = document.createElement('header');
        const titulo = document.createElement('h2');
        titulo.id = 'processo-ficha-modal-titulo';
        titulo.textContent = 'Ficha do processo';
        botaoFechar = document.createElement('button');
        botaoFechar.type = 'button';
        botaoFechar.autofocus = true;
        botaoFechar.setAttribute('aria-label', 'Fechar ficha do processo');
        botaoFechar.textContent = 'Fechar';
        botaoFechar.addEventListener('click', fechar);
        cabecalho.append(titulo, botaoFechar);

        quadro = document.createElement('iframe');
        quadro.title = 'Dados do processo';
        quadro.setAttribute('data-ficha-modal-content', '');
        quadro.addEventListener('load', aoCarregarFicha);
        card.append(cabecalho, quadro);
        painel.append(card);
        document.body.append(painel);

        let apontouFora = false;
        painel.addEventListener('pointerdown', ev => { apontouFora = ev.target === painel; });
        painel.addEventListener('click', ev => {
            if (apontouFora && ev.target === painel) fechar();
            apontouFora = false;
        });
        painel.addEventListener('cancel', event => { event.preventDefault(); fechar(); });
        painel.addEventListener('close', () => {
            fechando = false;
            quadro.removeAttribute('src');
            document.documentElement.classList.remove('ficha-modal-aberto');
            if (acionador?.isConnected) acionador.focus({preventScroll:true});
            acionador = null;
        });
    }

    function abrir(url, ativador, doc) {
        montar();
        if (painel.open || fechando) return;
        acionador = ativador;
        url.searchParams.set('ficha_modal', '1');
        const origem = new URL(doc.URL);
        if (origem.origin === origemSite && origem.pathname === '/frame.php' && origem.searchParams.has('page')) {
            url.searchParams.set('voltar', origem.pathname + origem.search);
        }
        window.PainelDeslizante.abrir(painel, card, botaoFechar);
        document.documentElement.classList.add('ficha-modal-aberto');
        quadro.src = url.href;
    }

    function vincular(doc) {
        if (!doc || conectados.has(doc)) return;
        const paginaDaOrigem = new URL(doc.URL);
        // Os atalhos de retorno na própria ficha devem navegar entre os modos,
        // não abrir uma segunda ficha por cima da primeira.
        if (paginaDaOrigem.pathname === '/frame.php'
            && paginaDaOrigem.searchParams.get('page') === 'consultaprocessosedit') return;
        conectados.add(doc);
        function acionar(event, peloTeclado) {
            if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
            if (!peloTeclado && event.button !== 0) return;
            const item = event.target.closest?.('a[href], [data-nav-href]');
            if (!item || item.matches('[disabled], [aria-disabled="true"], .disabled, [download]')) return;
            if (peloTeclado && (item.tagName === 'A' || item.tagName === 'BUTTON' || item.getAttribute('role') !== 'button')) return;
            const alvo = item.getAttribute('target') || item.getAttribute('data-nav-target') || '_self';
            if (alvo !== '_self') return;
            const url = urlDaFicha(item.getAttribute('data-nav-href') || item.getAttribute('href'), doc.URL);
            if (!url) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            abrir(url, item, doc);
        }
        doc.addEventListener('click', ev => acionar(ev, false), true);
        doc.addEventListener('keydown', ev => {
            if (!ev.repeat && (ev.key === 'Enter' || ev.key === ' ')) acionar(ev, true);
        }, true);
    }
    vincular(document);
    if (workspace) {
        const montarOrigem = () => {
            try { vincular(workspace.contentDocument); } catch (_) { /* navegação fora da origem */ }
        };
        workspace.addEventListener('load', montarOrigem);
        montarOrigem();
    }
})();
