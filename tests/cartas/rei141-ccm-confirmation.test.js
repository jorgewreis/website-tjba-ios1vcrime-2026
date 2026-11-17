const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { URL } = require('node:url');

function element(value = '') {
    return {
        value,
        disabled: false,
        dataset: {},
        listeners: {},
        addEventListener(name, callback) { this.listeners[name] = callback; },
        blur() {},
    };
}

async function submitScenario({ isCcm, confirmation }) {
    const ids = [
        'cadastrar-btn',
        'filter-processo',
        'filter-tipo',
        'filter-prazo',
        'filter-estado',
        'filter-cidade',
        'filter-classe',
    ];
    const elements = Object.fromEntries(ids.map(id => [id, element()]));
    elements['filter-processo'].value = '0000001-69.2020.8.05.0001';
    elements['filter-tipo'].value = 'Acompanhamento';
    elements['filter-prazo'].value = '30';
    elements['filter-estado'].value = 'BA';
    elements['filter-cidade'].value = 'Salvador';
    elements['filter-classe'].value = 'Classe sintética';

    const posts = [];
    const confirmations = [];
    const gate = {
        setWaiting() {},
        setError() {},
        setReady() {},
        setSubmitting() {},
        setInitial() {},
    };
    const helpers = {
        initCreateButtonGate() { return gate; },
        notify() {},
        normalizeProcessValue(value) { return { ok: true, value }; },
        getValue(id) { return elements[id]?.value || ''; },
        formatProperName(value) { return value; },
        buildClassePayload() { return { classe_codigo: 'SYN', classe_descricao: 'Classe sintética' }; },
    };
    const app = { CartorioCreateHelpers: helpers };
    const window = {
        App: app,
        __currentUserName: 'Usuário sintético',
        location: { origin: 'https://law.test', href: '' },
        document: { getElementById(id) { return elements[id] || null; } },
        addEventListener() {},
        confirm(message) { confirmations.push(message); return confirmation; },
    };
    const fetch = async (url, options = {}) => {
        if (String(url).includes('/city-lookup')) {
            return { ok: true, json: async () => ({ is_ccm: isCcm, status_cidade: isCcm ? 'CCM' : 'Ativa' }) };
        }
        posts.push(JSON.parse(options.body));
        return { ok: true, json: async () => ({ id: 'CPE-SYNTHETIC' }) };
    };
    vm.runInNewContext(
        fs.readFileSync('public/assets/js/shared/cartorio-cartas-expedidas-create.js', 'utf8'),
        { window, fetch, URL, Promise, Set, String }
    );
    app.CartorioCartasExpedidasCreate.initialize({
        cityLookupEndpoint: '/city-lookup',
        endpoint: '/create',
    });
    await elements['cadastrar-btn'].listeners.click({ preventDefault() {} });
    return { posts, confirmations, window };
}

(async () => {
    const accepted = await submitScenario({ isCcm: true, confirmation: true });
    assert.equal(accepted.confirmations.length, 1);
    assert.equal(accepted.posts.length, 1);
    assert.equal(accepted.posts[0].confirmacao_ccm, true, 'confirmação aceita deve seguir explicitamente no payload');
    assert.equal(accepted.window.App.CartorioCartasExpedidasCreate.confirmCcm(' salvador ', ' ba '), true);
    assert.equal(accepted.confirmations.length, 1, 'mesma cidade/UF deve reaproveitar confirmação da sessão');
    assert.equal(accepted.window.App.CartorioCartasExpedidasCreate.confirmCcm('Salvador', 'SP'), true);
    assert.equal(accepted.confirmations.length, 2, 'cidade igual em UF distinta deve pedir confirmação própria');

    const rejected = await submitScenario({ isCcm: true, confirmation: false });
    assert.equal(rejected.confirmations.length, 1);
    assert.equal(rejected.posts.length, 0, 'recusa não pode enviar o cadastro');

    const nonCcm = await submitScenario({ isCcm: false, confirmation: false });
    assert.equal(nonCcm.confirmations.length, 0);
    assert.equal(nonCcm.posts.length, 1);
    assert.equal(nonCcm.posts[0].confirmacao_ccm, false, 'destino não CCM segue sem consentimento');

    console.log('PASS REI-141 confirmação de CCM no frontend');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
