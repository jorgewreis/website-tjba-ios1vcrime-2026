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
    let finishCityLookup;
    let cityLookupCalls = 0;
    let createCalls = 0;
    const location = { origin: 'https://example.test', href: '' };
    const window = {
        App: { CartorioCreateHelpers: helpers },
        document: { getElementById: id => values[id] || null },
        location,
        __currentUserName: 'Usuário sintético',
        addEventListener() {},
        confirm: () => true
    };
    const context = {
        window,
        URL,
        fetch(url) {
            if (url.includes('/city')) {
                cityLookupCalls += 1;
                return new Promise(resolve => {
                    finishCityLookup = () => resolve({ ok: true, json: async () => ({ is_ccm: false }) });
                });
            }
            createCalls += 1;
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

    finishCityLookup();
    await first;
    assert.equal(createCalls, 1, 'a submissão autorizada deve ocorrer uma única vez');
    assert.equal(location.href, '/success', 'sucesso deve redirecionar após um único cadastro');
    assert.equal(feedbacks.some(([message]) => message === 'Enviando dados...'), true);
    console.log('PASS cartas expedidas: cliques repetidos durante consulta atrasada iniciam um único cadastro');
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
