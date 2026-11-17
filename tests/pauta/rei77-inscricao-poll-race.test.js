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

let document;
function makeWrapper(pauta) {
    const attributes = { 'data-inscricao-ui': pauta };
    const labelAttributes = {};
    const inscriptionLabel = {
        getAttribute(name) { return labelAttributes[name] || null; },
        setAttribute(name, value) { labelAttributes[name] = String(value); },
        removeAttribute(name) { delete labelAttributes[name]; },
        focus() { document.activeElement = this; }
    };
    const wrapper = {
        previousElementSibling: inscriptionLabel,
        style: {},
        button: null,
        getAttribute(name) { return attributes[name] || null; },
        setAttribute(name, value) { attributes[name] = String(value); },
        removeAttribute(name) { delete attributes[name]; },
        focus() { document.activeElement = this; },
        querySelector(selector) { return selector === '.btn-inscrever-ata' ? this.button : null; },
        set innerHTML(html) {
            this.html = html;
            const previousButton = this.button;
            if (previousButton) previousButton.isConnected = false;
            this.button = null;
            if (!html.includes('btn-inscrever-ata')) return;
            const buttonAttributes = {};
            for (const match of html.matchAll(/([\w-]+)="([^"]*)"/g)) buttonAttributes[match[1]] = match[2];
            this.button = {
                disabled: false,
                isConnected: true,
                textContent: html.match(/>([^<]*)<\/button>/)?.[1] || '',
                getAttribute(name) { return buttonAttributes[name] || null; },
                setAttribute(name, value) { buttonAttributes[name] = String(value); },
                removeAttribute(name) { delete buttonAttributes[name]; },
                focus() { document.activeElement = this; },
                closest(selector) { return selector === '[data-inscricao-ui]' ? wrapper : null; }
            };
        },
        get innerHTML() { return this.html || ''; }
    };
    return wrapper;
}

const listeners = {};
document = {
    activeElement: null,
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

function render(wrapper, pauta, overrides) {
    context.renderInscricaoUI(wrapper, Object.assign({ id_pauta: pauta, status: 'designada', pode_inscrever: true }, overrides || {}));
}
function click(button) {
    if (button && typeof button.focus === 'function') button.focus();
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

    // Polling atualiza o botão in-place e preserva o foco enquanto o POST continua pendente.
    const focusedButton = wrapper.button;
    render(wrapper, 'pauta-a');
    const buttonAfterPoll = wrapper.button;
    assert.strictEqual(buttonAfterPoll, focusedButton, 'polling deve preservar o mesmo botão focado');
    assert.strictEqual(document.activeElement, focusedButton, 'polling não deve remover o foco de teclado');
    assert.strictEqual(focusedButton.isConnected, true);
    assert.strictEqual(buttonAfterPoll.textContent, 'Inscrição em andamento…');
    assert.strictEqual(buttonAfterPoll.getAttribute('aria-busy'), 'true');
    assert.strictEqual(buttonAfterPoll.disabled, false);
    assert.strictEqual(buttonAfterPoll.getAttribute('aria-live'), 'polite');
    click(buttonAfterPoll);
    assert.strictEqual(posts.length, 1, 'segundo clique após re-render não deve iniciar outro POST');

    posts[0].resolve(failure);
    await tick();
    await tick();
    assert.strictEqual(wrapper.getAttribute('data-inscricao-pendente'), null, 'falha deve limpar o estado pendente');
    assert.strictEqual(wrapper.button, focusedButton, 'falha elegível deve preservar o foco no botão recuperado');
    assert.strictEqual(document.activeElement, focusedButton);
    assert.strictEqual(wrapper.button.textContent, 'Inscrever-me', 'botão atual deve permitir retry');
    assert.strictEqual(wrapper.button.getAttribute('aria-busy'), null);
    assert.strictEqual(wrapper.button.getAttribute('aria-live'), null);

    click(wrapper.button);
    assert.strictEqual(posts.length, 2, 'retry após falha deve iniciar novo POST');

    // Se o polling indicar lotação antes da falha do POST, não reexibir retry inválido.
    const retryButton = wrapper.button;
    render(wrapper, 'pauta-a', { vagas_esgotadas: true });
    assert.strictEqual(wrapper.button, retryButton, 'atualização pendente também deve preservar o botão');
    posts[1].resolve(failure);
    await tick();
    await tick();
    assert.strictEqual(wrapper.getAttribute('data-inscricao-pendente'), null);
    assert.strictEqual(wrapper.button, null, 'falha deve renderizar a elegibilidade mais recente');
    assert.match(wrapper.innerHTML, /Não é permitida mais nenhuma inscrição/);

    for (const blockedState of [
        { status: 'cancelada' },
        { processo_sigiloso: true },
        { envolve_menor: true },
        { ja_inscrito: true }
    ]) {
        const blockedWrapper = makeWrapper('pauta-bloqueada-' + posts.length);
        render(blockedWrapper, blockedWrapper.getAttribute('data-inscricao-ui'));
        click(blockedWrapper.button);
        const blockedPauta = blockedWrapper.getAttribute('data-inscricao-ui');
        render(blockedWrapper, blockedPauta, blockedState);
        posts[posts.length - 1].resolve(failure);
        await tick();
        await tick();
        if (blockedState.status) {
            assert.strictEqual(blockedWrapper.style.display, 'none', 'estado terminal deve manter a apresentação original');
            assert.strictEqual(blockedWrapper.button, null, 'estado terminal deve remover a ação oculta');
            assert.strictEqual(document.activeElement, blockedWrapper.previousElementSibling, 'foco terminal deve sair da ação oculta');
            assert.strictEqual(blockedWrapper.previousElementSibling.getAttribute('role'), 'status');
            assert.strictEqual(blockedWrapper.previousElementSibling.getAttribute('aria-label'), 'Inscrições indisponíveis para esta pauta');
        } else {
            assert.strictEqual(blockedWrapper.button, null, 'estado atualizado não pode voltar a oferecer inscrição: ' + JSON.stringify(blockedState));
            click(blockedWrapper.button);
            assert.strictEqual(posts.filter(post => JSON.parse(post.options.body).pauta === blockedPauta).length, 1,
                'estado bloqueado não pode iniciar novo POST: ' + JSON.stringify(blockedState));
        }
        if (!blockedState.status) {
            assert.strictEqual(document.activeElement, blockedWrapper, 'estado indisponível deve receber o foco após remover o CTA');
            assert.strictEqual(blockedWrapper.getAttribute('role'), 'status');
        }
    }

    const otherWrapper = makeWrapper('pauta-b');
    render(otherWrapper, 'pauta-b');
    const postsBeforeOtherPauta = posts.length;
    click(otherWrapper.button);
    assert.strictEqual(posts.length, postsBeforeOtherPauta + 1, 'pautas diferentes devem ter estados independentes');
    assert.strictEqual(otherWrapper.getAttribute('data-inscricao-pendente'), '1');

    console.log('REI-77 inscrição e polling: OK');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
