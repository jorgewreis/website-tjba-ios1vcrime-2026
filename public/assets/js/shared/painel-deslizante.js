(() => {
    'use strict';

    function abrir(camada, card, foco) {
        // A posição fechada existe antes de expor o diálogo. Evita que o
        // foco automático tente rolar o conteúdo fora do quadro.
        camada.classList.remove('painel-visivel');
        card.inert = true;
        camada.showModal();
        void getComputedStyle(card).transform;
        camada.scrollLeft = 0;
        camada.classList.add('painel-visivel');
        card.inert = false;
        foco?.focus({preventScroll:true});
    }

    function fechar(camada, card, concluir) {
        // Captura a posição mesmo que o painel ainda esteja entrando.
        // Reinicia a transição nesse ponto sem salto e sem encurtar a saída.
        const posicao = getComputedStyle(card).transform;
        card.style.transition = 'none';
        card.style.transform = posicao;
        camada.classList.remove('painel-visivel');
        void getComputedStyle(card).transform;
        card.style.removeProperty('transition');
        card.style.removeProperty('transform');
        void getComputedStyle(card).transform;

        if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
            concluir();
            return;
        }
        if (typeof card.getAnimations === 'function') {
            const transicoes = card.getAnimations().filter(animacao => animacao.transitionProperty === 'transform');
            if (transicoes.length === 0) { concluir(); return; }
            Promise.allSettled(transicoes.map(animacao => animacao.finished)).then(concluir);
            return;
        }
        let aguardando;
        const fim = evento => {
            if (evento && (evento.target !== card || evento.propertyName !== 'transform')) return;
            clearTimeout(aguardando);
            card.removeEventListener('transitionend', fim);
            concluir();
        };
        card.addEventListener('transitionend', fim);
        aguardando = setTimeout(fim, 1800);
    }

    window.PainelDeslizante = Object.freeze({abrir, fechar});
})();
