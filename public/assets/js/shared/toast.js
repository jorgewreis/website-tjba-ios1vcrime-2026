/**
 * Sistema de Toast – substitui alertas/erros/sucesso em todo o sistema.
 * Dois modelos: full (título + fechar + conteúdo) e simple (só conteúdo, com barra de tempo).
 * Máximo 5 toasts visíveis; excedentes entram em fila. Integração via postMessage para iframe.
 */
(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    const MAX_VISIBLE = 5;
    const ENTER_DURATION = 350;
    const EXIT_DURATION = 600;

    /** Tempo mínimo e máximo (ms) para toasts do tipo simple (informativo) */
    const SIMPLE_DURATION_MIN = 3000;
    const SIMPLE_DURATION_MAX = 15000;
    /** Duração padrão para toasts full (com botão fechar) quando não principal: 40% da calculada */
    const FULL_DURATION_DEFAULT_MS = 8000;
    /** Redução para toasts não-principais: usam 40% do tempo (redução de 60%) */
    const NON_PRINCIPAL_DURATION_FACTOR = 0.4;
    /** Tempo base + por caractere (ms) para cálculo automático de leitura */
    const SIMPLE_MS_BASE = 2000;
    const SIMPLE_MS_PER_CHAR = 45;

    const ICONS = {
        success: 'fi fi-rr-check-circle',
        error: 'fi fi-rr-circle-xmark',
        info: 'fi fi-rr-info-circle'
    };

    const TITLES = {
        success: 'Sucesso!',
        error: 'Erro!',
        info: 'Informação'
    };

    let container = null;
    let visibleList = [];
    let queue = [];

    function getContainer() {
        if (container && document.body.contains(container)) return container;
        container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Calcula duração em ms com base no tamanho do texto (leitura).
     * Para toasts não-principais (já existe outro na tela), retorna 40% desse valor.
     */
    function getCalculatedDuration(text, isPrincipal) {
        if (!text || typeof text !== 'string') return SIMPLE_DURATION_MIN;
        const len = text.trim().length;
        let ms = SIMPLE_MS_BASE + len * SIMPLE_MS_PER_CHAR;
        ms = Math.max(SIMPLE_DURATION_MIN, Math.min(SIMPLE_DURATION_MAX, ms));
        if (!isPrincipal) ms = Math.max(SIMPLE_DURATION_MIN, Math.floor(ms * NON_PRINCIPAL_DURATION_FACTOR));
        return ms;
    }

    function createToastElement(options, durationMs) {
        const {
            type = 'info',
            variant = 'full',
            title = TITLES[type] || 'Informação',
            message = '',
            subtitle
        } = options;

        const isFull = variant === 'full';
        const root = document.createElement('div');
        root.className = 'toast-item';
        root.setAttribute('role', 'alert');
        root.setAttribute('aria-live', 'polite');

        const toast = document.createElement('div');
        toast.className = isFull ? `toast-full ${type}` : `toast ${type}`;

        if (isFull) {
            const header = document.createElement('div');
            header.className = 'toast-header';
            const titleEl = document.createElement('strong');
            titleEl.className = 'toast-title';
            titleEl.textContent = title;
            const closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close';
            closeBtn.setAttribute('aria-label', 'Fechar');
            closeBtn.innerHTML = '&times;';
            header.appendChild(titleEl);
            header.appendChild(closeBtn);
            toast.appendChild(header);
        }

        const body = document.createElement('div');
        body.className = 'toast-body';
        if (isFull && ICONS[type]) {
            const iconWrap = document.createElement('div');
            iconWrap.className = 'toast-icon';
            const icon = document.createElement('i');
            icon.className = ICONS[type];
            iconWrap.appendChild(icon);
            body.appendChild(iconWrap);
        }
        const msgWrap = document.createElement('div');
        msgWrap.className = 'toast-message';
        if (subtitle) {
            const sub = document.createElement('span');
            sub.className = 'toast-subtitle';
            sub.textContent = subtitle;
            msgWrap.appendChild(sub);
        }
        const info = document.createElement('p');
        info.className = 'toast-information';
        info.textContent = message;
        msgWrap.appendChild(info);
        if (isFull) {
            const ts = document.createElement('p');
            ts.className = 'toast-timestamp';
            ts.textContent = 'Agora mesmo';
            msgWrap.appendChild(ts);
        }
        body.appendChild(msgWrap);
        toast.appendChild(body);

        if (durationMs > 0) {
            const countdownWrap = document.createElement('div');
            countdownWrap.className = 'toast-countdown-wrap';
            const countdownBar = document.createElement('div');
            countdownBar.className = 'toast-countdown-bar';
            countdownBar.style.transition = 'transform ' + durationMs + 'ms linear';
            countdownBar.style.transform = 'scaleX(1)';
            countdownWrap.appendChild(countdownBar);
            toast.appendChild(countdownWrap);
            root._toastDuration = durationMs;
            root._countdownBar = countdownBar;
        }

        root.appendChild(toast);
        root._options = options;
        root._variant = variant;

        if (isFull) {
            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    removeToast(root);
                });
            }
        }

        return root;
    }

    function removeToast(toastEl) {
        if (!toastEl || !toastEl.parentNode) return;
        if (toastEl._removeTimeout) clearTimeout(toastEl._removeTimeout);
        const onClose = toastEl._options && typeof toastEl._options.onClose === 'function';
        toastEl.classList.add('toast-exiting');
        toastEl.classList.remove('toast-visible');

        const idx = visibleList.indexOf(toastEl);
        if (idx > -1) visibleList.splice(idx, 1);

        setTimeout(function () {
            if (toastEl.parentNode) toastEl.parentNode.removeChild(toastEl);
            if (onClose) toastEl._options.onClose();
            processQueue();
        }, EXIT_DURATION);
    }

    function processQueue() {
        while (visibleList.length < MAX_VISIBLE && queue.length > 0) {
            const options = queue.shift();
            showNow(options);
        }
    }

    function showNow(options) {
        const cont = getContainer();
        const isPrincipal = visibleList.length === 0;
        const message = options.message || '';
        let durationMs = 0;
        if (options.variant === 'simple') {
            durationMs = getCalculatedDuration(message, isPrincipal);
        } else {
            durationMs = getCalculatedDuration(message, isPrincipal);
            if (durationMs < SIMPLE_DURATION_MIN) durationMs = FULL_DURATION_DEFAULT_MS;
        }
        const el = createToastElement(options, durationMs);
        el.classList.add('toast-visible');
        cont.appendChild(el);
        visibleList.push(el);

        if (el._countdownBar && durationMs > 0) {
            requestAnimationFrame(function () {
                el._countdownBar.style.transform = 'scaleX(0)';
            });
        }
        if (durationMs > 0) {
            el._removeTimeout = setTimeout(function () {
                removeToast(el);
            }, durationMs);
        }
    }

    /**
     * Exibe um toast.
     * @param {Object} options
     * @param {string} [options.type='info'] - success | error | info
     * @param {string} [options.variant='full'] - full (título + fechar + conteúdo) | simple (só conteúdo + countdown)
     * @param {string} [options.title] - Título (modelo full)
     * @param {string} [options.message] - Mensagem principal
     * @param {string} [options.subtitle] - Subtítulo opcional
     * @param {Function} [options.onClose] - Callback chamado quando o toast for fechado (botão ou auto)
     */
    function show(options) {
        const opts = Object.assign(
            { type: 'info', variant: 'full', title: '', message: '', subtitle: '' },
            typeof options === 'string' ? { message: options } : options
        );
        if (!opts.title && TITLES[opts.type]) opts.title = TITLES[opts.type];

        if (visibleList.length >= MAX_VISIBLE) {
            queue.push(opts);
            return;
        }
        showNow(opts);
    }

    function initPostMessage() {
        global.addEventListener('message', function (event) {
            if (!event.data || event.data.type !== 'APP_TOAST') return;
            const payload = event.data.payload;
            if (payload && typeof payload === 'object') show(payload);
        });
    }

    function initFlashFromParams() {
        try {
            const params = new URLSearchParams(global.location.search);
            const toastType = params.get('toast');
            const toastMsg = params.get('toast_msg') || params.get('msg');
            if (!toastType || !toastMsg) return;
            const type = ['success', 'error', 'info'].includes(toastType) ? toastType : 'info';
            show({ type, variant: 'simple', message: decodeURIComponent(toastMsg) });
            const url = new URL(global.location.href);
            url.searchParams.delete('toast');
            url.searchParams.delete('toast_msg');
            url.searchParams.delete('msg');
            global.history.replaceState({}, '', url.toString());
        } catch (e) {}
    }

    function initFlashFromData() {
        try {
            const dataEl = document.getElementById('toast-flash-data');
            if (!dataEl || !dataEl.textContent) return;
            const data = JSON.parse(dataEl.textContent);
            if (Array.isArray(data)) {
                data.forEach(function (item) { show(item); });
            } else if (data && data.message) {
                show(data);
            }
            dataEl.remove();
        } catch (e) {}
    }

    App.Toast = {
        show,
        get visibleCount() { return visibleList.length; },
        get queueCount() { return queue.length; }
    };

    if (global.document && document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initPostMessage();
            initFlashFromParams();
            initFlashFromData();
        });
    } else {
        initPostMessage();
        initFlashFromParams();
        initFlashFromData();
    }

    global.App = App;
})(window);
