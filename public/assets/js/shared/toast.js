/**
 * Sistema de Toast – substitui alertas/erros/sucesso em todo o sistema.
 * Dois modelos: full (barra de título + fechar + conteúdo) e simple (só mensagem + barra de tempo).
 * Política (resolveVariant — ignora variant explícito salvo forceVariant):
 * - success: simple (rápido), exceto mensagem longa/complexa → full
 * - error: sempre full
 * - info: simple se curto; full se mensagem longa/complexa
 * - deduplicação em 5s com contador xN; pausa no hover; erro crítico → aria-live assertive
 */
(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    const MAX_VISIBLE = 5;
    const EXIT_DURATION = 900;
    const DEDUP_WINDOW_MS = 5000;
    const DEDUP_TIMER_STEP_MS = 1500;
    const MAX_DURATION_MS = 15000;
    const SIMPLE_DURATION_MIN = 3000;
    const SUCCESS_SIMPLE_MAX_MS = 3200;
    const SUCCESS_SIMPLE_MIN_MS = 1800;
    const FULL_DURATION_DEFAULT_MS = 8000;
    const LONG_MESSAGE_THRESHOLD = 120;
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
        info: 'Informação!'
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

    function isLongOrComplexMessage(message) {
        const raw = String(message || '');
        if (raw.trim().length >= LONG_MESSAGE_THRESHOLD) return true;
        if (/\r?\n/.test(raw)) return true;
        return /(^|\n)\s*([-*•]|\d+\.)\s+/m.test(raw);
    }

    function normalizeDedupText(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/\b\d+\b/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isCriticalError(opts) {
        return opts.type === 'error' && (opts.critical === true || opts.blocking === true);
    }

    /**
     * Define simple vs full. Chamadas antigas passam variant:'full' em todo lugar;
     * a política abaixo prevalece (exceto opts.forceVariant === 'full'|'simple').
     */
    function resolveVariant(opts) {
        const forced = opts.forceVariant;
        if (forced === 'full' || forced === 'simple') {
            return forced;
        }
        const long = isLongOrComplexMessage(opts.message || '');
        if (opts.type === 'success') {
            return long ? 'full' : 'simple';
        }
        if (opts.type === 'error') {
            return 'full';
        }
        if (opts.type === 'info') {
            return long ? 'full' : 'simple';
        }
        return 'simple';
    }

    function buildDedupKey(opts) {
        return [
            opts.type || 'info',
            opts.variant || 'simple',
            normalizeDedupText(opts.message || '')
        ].join('|');
    }

    function getDuration(message, variant, type) {
        if (!message || typeof message !== 'string') return SIMPLE_DURATION_MIN;
        const len = message.trim().length;
        let ms = SIMPLE_MS_BASE + len * SIMPLE_MS_PER_CHAR;

        // Sucesso no modelo simples: tempo mais curto (não usar o mesmo multiplicador dos demais)
        if (variant === 'simple' && type === 'success') {
            ms = Math.floor((SIMPLE_MS_BASE * 0.5) + len * 22);
            ms = Math.max(SUCCESS_SIMPLE_MIN_MS, Math.min(SUCCESS_SIMPLE_MAX_MS, ms));
            return ms;
        }

        ms = Math.floor(ms * 1.5);
        if (type === 'error') ms *= 2;
        ms = Math.max(SIMPLE_DURATION_MIN, Math.min(MAX_DURATION_MS, ms));
        if (variant === 'full') {
            ms = Math.max(ms, FULL_DURATION_DEFAULT_MS);
        }
        return ms;
    }

    function isWithinDedupWindow(lastSeenAt) {
        return Date.now() - Number(lastSeenAt || 0) <= DEDUP_WINDOW_MS;
    }

    function getBarScaleX(bar) {
        if (!bar) return 1;
        const transform = global.getComputedStyle(bar).transform;
        if (!transform || transform === 'none') return 1;
        const match = transform.match(/^matrix\(([^,]+)/);
        const scaleX = match ? parseFloat(match[1]) : 1;
        return Number.isFinite(scaleX) ? Math.max(0, Math.min(1, scaleX)) : 1;
    }

    function renderDedupBadge(toastEl) {
        if (!toastEl) return;
        const count = Number(toastEl._dedupCount || 1);
        if (!toastEl._dedupBadge) return;
        if (count > 1) {
            toastEl._dedupBadge.textContent = 'x' + count;
            toastEl._dedupBadge.hidden = false;
        } else {
            toastEl._dedupBadge.hidden = true;
        }
    }

    function applyTimerState(toastEl, remainingMs, resetScale) {
        if (!toastEl || !toastEl._countdownBar) return;
        const bar = toastEl._countdownBar;
        const next = Math.max(0, Math.min(MAX_DURATION_MS, Math.floor(remainingMs)));
        toastEl._remainingMs = next;

        if (next <= 0) {
            removeToast(toastEl);
            return;
        }

        if (toastEl._removeTimeout) {
            clearTimeout(toastEl._removeTimeout);
        }

        const initialScale = resetScale ? 1 : getBarScaleX(bar);
        bar.style.transition = 'none';
        bar.style.transform = 'scaleX(' + initialScale + ')';
        requestAnimationFrame(function () {
            bar.style.transition = 'transform ' + next + 'ms linear';
            bar.style.transform = 'scaleX(0)';
        });

        toastEl._paused = false;
        toastEl._startTime = Date.now();
        toastEl._removeTimeout = setTimeout(function () {
            removeToast(toastEl);
        }, next);
    }

    function pauseToast(toastEl) {
        if (!toastEl || toastEl._paused || !toastEl._countdownBar) return;
        const elapsed = Date.now() - Number(toastEl._startTime || Date.now());
        const remaining = Math.max(0, Number(toastEl._remainingMs || 0) - elapsed);
        toastEl._remainingMs = remaining;
        if (toastEl._removeTimeout) {
            clearTimeout(toastEl._removeTimeout);
            toastEl._removeTimeout = null;
        }
        const scale = getBarScaleX(toastEl._countdownBar);
        toastEl._countdownBar.style.transition = 'none';
        toastEl._countdownBar.style.transform = 'scaleX(' + scale + ')';
        toastEl._paused = true;
    }

    function resumeToast(toastEl) {
        if (!toastEl || !toastEl._paused || !toastEl._countdownBar) return;
        applyTimerState(toastEl, toastEl._remainingMs, false);
    }

    function extendToastTimer(toastEl) {
        if (!toastEl || !toastEl._countdownBar) return;
        const now = Date.now();
        const elapsed = toastEl._paused ? 0 : now - Number(toastEl._startTime || now);
        const baseRemaining = toastEl._paused
            ? Number(toastEl._remainingMs || 0)
            : Math.max(0, Number(toastEl._remainingMs || 0) - elapsed);
        const extended = Math.min(MAX_DURATION_MS, baseRemaining + DEDUP_TIMER_STEP_MS);
        toastEl._remainingMs = extended;
        if (!toastEl._paused) {
            applyTimerState(toastEl, extended, false);
        }
    }

    function createToastElement(options, durationMs) {
        const {
            type = 'info',
            variant = 'full',
            title = TITLES[type] || 'Informação',
            message = '',
            subtitle,
            dedupCount = 1
        } = options;

        const isFull = variant === 'full';
        const root = document.createElement('div');
        root.className = 'toast-item';
        root.setAttribute('role', 'alert');
        root.setAttribute('aria-live', isCriticalError(options) ? 'assertive' : 'polite');
        const counter = document.createElement('span');
        counter.className = 'toast-counter';
        counter.hidden = true;
        counter.setAttribute('aria-hidden', 'true');

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
            header.appendChild(counter);
            header.appendChild(closeBtn);
            toast.appendChild(header);
            root._dedupBadge = counter;
        }

        const body = document.createElement('div');
        body.className = 'toast-body';
        if (!isFull) {
            counter.classList.add('toast-counter-simple');
            body.appendChild(counter);
            root._dedupBadge = counter;
        }
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
            root._countdownBar = countdownBar;
        }

        root.appendChild(toast);
        root._options = options;
        root._variant = variant;
        root._dedupKey = options.dedupKey || '';
        root._dedupLastSeenAt = Date.now();
        root._dedupCount = Math.max(1, Number(dedupCount || 1));
        root._remainingMs = durationMs;
        root._paused = false;

        root.addEventListener('mouseenter', function () {
            pauseToast(root);
        });
        root.addEventListener('mouseleave', function () {
            resumeToast(root);
        });

        if (isFull) {
            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    removeToast(root);
                });
            }
        }

        renderDedupBadge(root);
        return root;
    }

    function bumpToast(toastEl) {
        if (!toastEl) return;
        toastEl._dedupCount = Number(toastEl._dedupCount || 1) + 1;
        toastEl._dedupLastSeenAt = Date.now();
        renderDedupBadge(toastEl);
        extendToastTimer(toastEl);
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

    function findDuplicateInVisible(dedupKey) {
        for (let i = visibleList.length - 1; i >= 0; i--) {
            const item = visibleList[i];
            if (item && item._dedupKey === dedupKey && isWithinDedupWindow(item._dedupLastSeenAt)) {
                return item;
            }
        }
        return null;
    }

    function findDuplicateInQueue(dedupKey) {
        for (let i = queue.length - 1; i >= 0; i--) {
            const item = queue[i];
            if (item && item.dedupKey === dedupKey && isWithinDedupWindow(item.dedupLastSeenAt)) {
                return item;
            }
        }
        return null;
    }

    function showNow(options) {
        const cont = getContainer();
        const durationMs = getDuration(options.message || '', options.variant, options.type);
        const el = createToastElement(options, durationMs);
        el.classList.add('toast-visible');
        cont.appendChild(el);
        visibleList.push(el);

        if (el._countdownBar && durationMs > 0) {
            applyTimerState(el, durationMs, true);
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
     * @param {boolean} [options.critical=false] - Marca erro crítico (ativa aria-live assertive e prioriza full)
     * @param {Function} [options.onClose] - Callback chamado quando o toast for fechado (botão ou auto)
     */
    function show(options) {
        const input = typeof options === 'string' ? { message: options } : (options || {});
        const opts = Object.assign(
            { type: 'info', variant: 'simple', title: '', message: '', subtitle: '', critical: false },
            input
        );
        opts.variant = resolveVariant(opts);
        if (!opts.title && TITLES[opts.type]) opts.title = TITLES[opts.type];
        opts.dedupKey = buildDedupKey(opts);
        opts.dedupLastSeenAt = Date.now();
        opts.dedupCount = Math.max(1, Number(opts.dedupCount || 1));

        const duplicateVisible = findDuplicateInVisible(opts.dedupKey);
        if (duplicateVisible) {
            bumpToast(duplicateVisible);
            return duplicateVisible;
        }
        const duplicateQueue = findDuplicateInQueue(opts.dedupKey);
        if (duplicateQueue) {
            duplicateQueue.dedupCount = Number(duplicateQueue.dedupCount || 1) + 1;
            duplicateQueue.dedupLastSeenAt = Date.now();
            return null;
        }

        if (visibleList.length >= MAX_VISIBLE) {
            queue.push(opts);
            return null;
        }
        showNow(opts);
        return null;
    }

    function success(message, opts) {
        const input = Object.assign({}, opts || {}, { type: 'success', message: message || '' });
        return show(input);
    }

    function error(message, opts) {
        const input = Object.assign({}, opts || {}, { type: 'error', message: message || '' });
        return show(input);
    }

    function info(message, opts) {
        const input = Object.assign({}, opts || {}, { type: 'info', message: message || '' });
        return show(input);
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
        success,
        error,
        info,
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
