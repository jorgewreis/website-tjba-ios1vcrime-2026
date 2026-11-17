const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const handlerSource = fs.readFileSync('public/assets/js/modules/audiencias/atas-estudantes.js', 'utf8');
const viewSource = fs.readFileSync('app/views/internal/sala-audiencias-painel-estudantes.php', 'utf8');

function extractFunction(source, name) {
    const marker = 'function ' + name + '(';
    const start = source.indexOf(marker);
    assert(start >= 0, 'Função não encontrada: ' + name);
    const brace = source.indexOf('{', start);
    let depth = 0;
    let quote = '';
    let escaped = false;
    for (let i = brace; i < source.length; i++) {
        const char = source[i];
        if (quote) {
            if (escaped) escaped = false;
            else if (char === '\\') escaped = true;
            else if (char === quote) quote = '';
            continue;
        }
        if (char === '"' || char === "'" || char === '`') {
            quote = char;
        } else if (char === '{') {
            depth++;
        } else if (char === '}' && --depth === 0) {
            return source.slice(start, i + 1);
        }
    }
    throw new Error('Função sem fechamento: ' + name);
}

function makeWrapper(pauta) {
    const attributes = { 'data-inscricao-ui': pauta };
    const wrapper = {
        style: {},
        button: null,
        getAttribute(name) { return attributes[name] || null; },
        setAttribute(name, value) { attributes[name] = String(value); },
        removeAttribute(name) { delete attributes[name]; },
        querySelector(selector) { return selector === '.btn-inscrever-ata' ? this.button : null; },
        set innerHTML(html) {
            this.html = html;
            this.button = null;
            if (!html.includes('btn-inscrever-ata')) return;
            const buttonAttributes = {};
            for (const match of html.matchAll(/([\w-]+)="([^"]*)"/g)) buttonAttributes[match[1]] = match[2];
            this.button = {
                disabled: false,
                textContent: html.match(/>([^<]*)<\/button>/)?.[1] || '',
                getAttribute(name) { return buttonAttributes[name] || null; },
                setAttribute(name, value) { buttonAttributes[name] = String(value); },
                removeAttribute(name) { delete buttonAttributes[name]; },
                closest(selector) { return selector === '[data-inscricao-ui]' ? wrapper : null; }
            };
        },
        get innerHTML() { return this.html || ''; }
    };
    return wrapper;
}

const listeners = {};
const document = {
    addEventListener(name, callback) { listeners[name] = callback; }
};
const posts = [];
const notifications = [];
const context = {
    document,
    window: { App: { Page: { notify(type, title, message) { notifications.push({ type, title, message }); } } }, location: { reload() {} } },
    App: { Page: { notify(type, title, message) { notifications.push({ type, title, message }); } } },
    fetch(url, options) {
        let resolve;
        const promise = new Promise(done => { resolve = done; });
        posts.push({ url, options, resolve });
        return promise;
    },
    Object,
    String
};
vm.createContext(context);
vm.runInContext(handlerSource, context);

const renderer = extractFunction(viewSource, 'renderInscricaoUI');
vm.runInContext('function escapeHtml(value) { return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#39;"); }\nfunction statusForItem(item) { return item.status || ""; }\n' + renderer, context);

function render(wrapper, pauta) {
    context.renderInscricaoUI(wrapper, { id_pauta: pauta, status: 'designada', pode_inscrever: true });
}
function click(button) {
    listeners.click({ target: { closest(selector) { return selector === '.btn-inscrever-ata' ? button : null; } } });
}
const tick = () => new Promise(resolve => setImmediate(resolve));
const failure = { ok: false, json: () => Promise.resolve({ success: false, error: 'Falha simulada.' }) };

(async () => {
    const wrapper = makeWrapper('pauta-a');
    render(wrapper, 'pauta-a');
    click(wrapper.button);
    assert.strictEqual(posts.length, 1, 'primeiro clique deve iniciar um POST');
    assert.strictEqual(wrapper.getAttribute('data-inscricao-pendente'), '1');

    // Simula o polling substituindo o botão enquanto o POST continua pendente.
    render(wrapper, 'pauta-a');
    const buttonAfterPoll = wrapper.button;
    assert.notStrictEqual(buttonAfterPoll, null);
    assert.strictEqual(buttonAfterPoll.textContent, 'Inscrição em andamento…');
    assert.strictEqual(buttonAfterPoll.getAttribute('aria-busy'), 'true');
    assert.strictEqual(buttonAfterPoll.disabled, false, 'o estado pendente deve manter o foco de teclado');
    assert.strictEqual(buttonAfterPoll.getAttribute('aria-live'), 'polite');
    click(buttonAfterPoll);
    assert.strictEqual(posts.length, 1, 'segundo clique após re-render não deve iniciar outro POST');

    posts[0].resolve(failure);
    await tick();
    await tick();
    assert.strictEqual(wrapper.getAttribute('data-inscricao-pendente'), null, 'falha deve limpar o estado pendente');
    assert.strictEqual(wrapper.button.textContent, 'Inscrever-me', 'botão atual deve permitir retry');
    assert.strictEqual(wrapper.button.getAttribute('aria-busy'), 'false');
    assert.strictEqual(wrapper.button.getAttribute('aria-live'), null);

    click(wrapper.button);
    assert.strictEqual(posts.length, 2, 'retry após falha deve iniciar novo POST');

    const otherWrapper = makeWrapper('pauta-b');
    render(otherWrapper, 'pauta-b');
    click(otherWrapper.button);
    assert.strictEqual(posts.length, 3, 'pautas diferentes devem ter estados independentes');
    assert.strictEqual(otherWrapper.getAttribute('data-inscricao-pendente'), '1');

    console.log('REI-77 inscrição e polling: OK');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
