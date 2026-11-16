'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
    constructor(value = '') {
        this.value = value;
        this.disabled = false;
        this.dataset = {};
        this.listeners = {};
        this.textContent = '';
        this.attributes = {};
        this.classes = new Set();
        this.classList = {
            add: name => this.classes.add(name),
            remove: name => this.classes.delete(name)
        };
    }

    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelector() { return null; }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    blur() {}
}

async function run() {
    const values = {
        'cadastrar-btn': new Element(),
        'filter-processo': new Element('0000001-69.2020.8.05.0001'),
        'filter-tipo': new Element('Precatória'),
        'filter-prazo': new Element('30'),
        'filter-estado': new Element('BA'),
        'filter-cidade': new Element('Salvador'),
        'filter-classe': new Element('Classe de teste')
    };
    const getValue = id => values[id] ? values[id].value : '';
    const feedbacks = [];
    const helpers = {
        initCreateButtonGate({ button }) {
            const set = (label, disabled) => {
                button.textContent = label;
                button.disabled = disabled;
            };
            return {
                setInitial: () => set('Novo cadastro', false),
                setWaiting: () => set('Aguarde...', true),
                setError: () => set('Erro', true),
                setReady: () => set('Cadastrar', false),
                setSubmitting: () => set('Aguarde...', true),
                setBlocked: () => set('Aguarde...', true)
            };
        },
        normalizeProcessValue: value => ({ ok: true, value }),
        getValue,
        formatProperName: value => value.trim(),
        buildClassePayload: () => ({}),
        notify: (...args) => feedbacks.push(args)
    };
    const finishCityLookups = [];
    let cityLookupCalls = 0;
    let createCalls = 0;
    const createPayloads = [];
    const location = { origin: 'https://example.test', href: '' };
    const storageValues = new Map();
    const sessionStorage = {
        getItem: key => storageValues.has(key) ? storageValues.get(key) : null,
        setItem: (key, value) => storageValues.set(key, String(value)),
        removeItem: key => storageValues.delete(key)
    };
    const window = {
        App: { CartorioCreateHelpers: helpers },
        document: { getElementById: id => values[id] || null },
        location,
        sessionStorage,
        __currentUserName: 'Usuário sintético',
        crypto: { getRandomValues: bytes => { bytes.fill(createPayloads.length + 7); return bytes; } },
        addEventListener() {},
        confirm: () => true
    };
    const context = {
        window,
        URL,
        fetch(url, options) {
            if (url.includes('/city')) {
                cityLookupCalls += 1;
                return new Promise(resolve => {
                    finishCityLookups.push(() => resolve({ ok: true, json: async () => ({ is_ccm: false }) }));
                });
            }
            createCalls += 1;
            createPayloads.push(JSON.parse(options.body));
            if (createCalls === 1) return Promise.reject(new Error('resposta perdida após gravação'));
            return Promise.resolve({ ok: true, json: async () => ({ id: 'CARTA-SINTETICA' }) });
        },
        setTimeout
    };
    vm.runInNewContext(
        fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/cartorio-cartas-expedidas-create.js'), 'utf8'),
        context,
        { filename: 'cartorio-cartas-expedidas-create.js' }
    );

    window.App.CartorioCartasExpedidasCreate.initialize({
        cityLookupEndpoint: '/api/city',
        endpoint: '/api/create',
        successRedirect: '/success'
    });
    const button = values['cadastrar-btn'];
    const click = () => button.listeners.click({ preventDefault() {} });

    const first = click();
    assert.equal(cityLookupCalls, 1, 'primeiro clique iniciou a busca de cidade');
    assert.equal(button.disabled, true, 'botão deve ser bloqueado antes da resposta assíncrona');

    await click();
    assert.equal(cityLookupCalls, 1, 'segundo clique não pode iniciar outra busca');
    assert.equal(createCalls, 0, 'nenhum cadastro deve ocorrer enquanto a busca está pendente');

    finishCityLookups[0]();
    await first;
    assert.equal(createCalls, 1, 'uma única submissão ocorre após a consulta');

    const windowReloaded = {
        App: window.App,
        document: window.document,
        location,
        sessionStorage,
        __currentUserName: window.__currentUserName,
        crypto: window.crypto,
        addEventListener() {},
        confirm: () => true
    };
    vm.runInNewContext(
        fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/cartorio-cartas-expedidas-create.js'), 'utf8'),
        { window: windowReloaded, URL, fetch: context.fetch, setTimeout },
        { filename: 'cartorio-cartas-expedidas-create.js (reload)' }
    );
    windowReloaded.App.CartorioCartasExpedidasCreate.initialize({
        cityLookupEndpoint: '/api/city',
        endpoint: '/api/create',
        successRedirect: '/success'
    });

    const retry = click();
    assert.equal(cityLookupCalls, 2, 'retry permitido após falha de resposta revalida cidade');
    finishCityLookups[1]();
    await retry;
    assert.equal(createCalls, 2, 'o usuário pode repetir após resultado HTTP ambíguo');
    assert.match(createPayloads[0].idempotency_key, /^[a-f0-9]{64}$/);
    assert.equal(createPayloads[1].idempotency_key, createPayloads[0].idempotency_key, 'retry idêntico reutiliza a chave idempotente');
    assert.equal(location.href, '/success', 'sucesso deve redirecionar após um único cadastro');
    assert.equal(feedbacks.some(([message]) => message === 'Enviando dados...'), true);
    console.log('PASS cartas expedidas: bloqueio de clique duplicado e chave idempotente preservada após recarga');
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
